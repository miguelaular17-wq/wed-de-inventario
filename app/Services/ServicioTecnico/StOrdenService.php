<?php

namespace App\Services\ServicioTecnico;

use App\Models\StMovimientoRepuesto;
use App\Models\StOrden;
use App\Models\StOrdenEvento;
use App\Models\StOrdenRepuesto;
use App\Models\StRepuesto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StOrdenService
{
    /**
     * @param  list<array{repuesto_id:int,cantidad:int}>  $lineasRepuesto
     */
    public function actualizarOrden(StOrden $orden, array $datos, User $user, array $lineasRepuesto = []): StOrden
    {
        return DB::transaction(function () use ($orden, $datos, $user, $lineasRepuesto) {
            $estadoAnterior = $orden->estado;

            if (isset($datos['sede']) && strtoupper((string) $datos['sede']) !== strtoupper((string) $orden->sede)) {
                $this->transferir($orden, (string) $datos['sede'], $user);
                unset($datos['sede']);
            }

            if ($lineasRepuesto !== [] && ! $orden->repuestos_descontados_at) {
                $this->syncRepuestos($orden, $lineasRepuesto, $user);
            }

            $nuevoEstado = $datos['estado'] ?? $orden->estado;

            if ($nuevoEstado !== $estadoAnterior) {
                $this->validarTransicion($orden, $estadoAnterior, $nuevoEstado);
            }

            $datos['updated_by'] = $user->id;
            $orden->fill($datos);

            if ($nuevoEstado === StOrden::ESTADO_LISTO && ! $orden->repuestos_descontados_at) {
                $this->descontarRepuestos($orden, $user);
            }

            $orden->costo_refacciones = $this->calcularCostoRefacciones($orden);
            $orden->save();

            if ($nuevoEstado !== $estadoAnterior) {
                $this->registrarEvento($orden, $user, StOrdenEvento::TIPO_ESTADO, sprintf(
                    'Estado: %s → %s',
                    StOrden::ESTADOS[$estadoAnterior] ?? $estadoAnterior,
                    StOrden::ESTADOS[$nuevoEstado] ?? $nuevoEstado
                ), ['de' => $estadoAnterior, 'a' => $nuevoEstado]);
            }

            return $orden->fresh(['repuestosLineas.repuesto', 'eventos.usuario', 'tecnico']);
        });
    }

    public function transferir(StOrden $orden, string $sedeDestino, User $user): void
    {
        if ($user->scopesServicioToOwnSede()) {
            throw ValidationException::withMessages([
                'sede' => 'No tienes permiso para transferir órdenes entre sedes.',
            ]);
        }

        $sedeDestino = strtoupper($sedeDestino);
        $sedes = config('inventario.sedes_locales', []);

        if (! in_array($sedeDestino, $sedes, true)) {
            throw ValidationException::withMessages(['sede' => 'Sede de destino inválida.']);
        }

        if ($sedeDestino === strtoupper((string) $orden->sede)) {
            return;
        }

        if ($orden->repuestos_descontados_at) {
            throw ValidationException::withMessages([
                'sede' => 'No se puede transferir una orden con repuestos ya descontados del inventario.',
            ]);
        }

        $sedeOrigen = strtoupper((string) $orden->sede);
        $orden->sede_origen_transfer = $sedeOrigen;
        $orden->transfer_estado = StOrden::TRANSFER_PENDIENTE;
        $orden->sede = $sedeDestino;

        $this->registrarEvento($orden, $user, StOrdenEvento::TIPO_TRANSFERENCIA, sprintf(
            'Transferencia pendiente: %s → %s',
            $sedeOrigen,
            $sedeDestino
        ), ['origen' => $sedeOrigen, 'destino' => $sedeDestino]);
    }

    public function confirmarRecepcion(StOrden $orden, User $user): StOrden
    {
        if ($orden->transfer_estado !== StOrden::TRANSFER_PENDIENTE) {
            throw ValidationException::withMessages([
                'transfer' => 'Esta orden no tiene una transferencia pendiente.',
            ]);
        }

        if (strtoupper((string) $orden->sede) !== strtoupper((string) $user->sede)) {
            throw ValidationException::withMessages([
                'transfer' => 'Solo el técnico de la sede destino puede confirmar la recepción.',
            ]);
        }

        $orden->transfer_estado = StOrden::TRANSFER_ACEPTADA;
        $orden->updated_by = $user->id;
        $orden->save();

        $this->registrarEvento($orden, $user, StOrdenEvento::TIPO_TRANSFERENCIA, sprintf(
            'Recepción confirmada en %s (desde %s)',
            $orden->sede,
            $orden->sede_origen_transfer
        ));

        return $orden->fresh();
    }

    /**
     * @param  list<array{repuesto_id:int,cantidad:int}>  $lineas
     */
    public function syncRepuestos(StOrden $orden, array $lineas, User $user): void
    {
        if ($orden->repuestos_descontados_at) {
            throw ValidationException::withMessages([
                'repuestos' => 'Los repuestos ya fueron descontados; no se pueden modificar.',
            ]);
        }

        $ids = [];
        foreach ($lineas as $linea) {
            $repuestoId = (int) ($linea['repuesto_id'] ?? 0);
            $cantidad = max(1, (int) ($linea['cantidad'] ?? 1));

            if ($repuestoId <= 0) {
                continue;
            }

            $repuesto = StRepuesto::query()
                ->where('id', $repuestoId)
                ->where('sede', strtoupper((string) $orden->sede))
                ->activos()
                ->first();

            if (! $repuesto) {
                throw ValidationException::withMessages([
                    'repuestos' => 'Repuesto no disponible en la sede de la orden.',
                ]);
            }

            if ($repuesto->stock < $cantidad) {
                throw ValidationException::withMessages([
                    'repuestos' => "Stock insuficiente para «{$repuesto->nombre}» (disponible: {$repuesto->stock}).",
                ]);
            }

            StOrdenRepuesto::query()->updateOrCreate(
                ['orden_id' => $orden->id, 'repuesto_id' => $repuesto->id],
                [
                    'cantidad' => $cantidad,
                    'precio_unitario' => $repuesto->precio_venta,
                    'costo_unitario' => $repuesto->costo,
                    'descontado' => false,
                ]
            );

            $ids[] = $repuesto->id;
        }

        StOrdenRepuesto::query()
            ->where('orden_id', $orden->id)
            ->where('descontado', false)
            ->when($ids !== [], fn ($q) => $q->whereNotIn('repuesto_id', $ids))
            ->delete();
    }

    public function descontarRepuestos(StOrden $orden, User $user): void
    {
        if ($orden->repuestos_descontados_at) {
            return;
        }

        $lineas = StOrdenRepuesto::query()
            ->where('orden_id', $orden->id)
            ->where('descontado', false)
            ->with('repuesto')
            ->get();

        foreach ($lineas as $linea) {
            $repuesto = StRepuesto::query()->lockForUpdate()->find($linea->repuesto_id);
            if (! $repuesto) {
                continue;
            }

            if ($repuesto->stock < $linea->cantidad) {
                throw ValidationException::withMessages([
                    'estado' => "Stock insuficiente para «{$repuesto->nombre}» al marcar como listo.",
                ]);
            }

            $antes = $repuesto->stock;
            $repuesto->stock = $antes - $linea->cantidad;
            $repuesto->save();

            StMovimientoRepuesto::create([
                'repuesto_id' => $repuesto->id,
                'orden_id' => $orden->id,
                'tipo' => StMovimientoRepuesto::TIPO_SALIDA,
                'cantidad' => -$linea->cantidad,
                'stock_antes' => $antes,
                'stock_despues' => $repuesto->stock,
                'motivo' => 'Orden '.$orden->codigo(),
                'user_id' => $user->id,
                'created_at' => now(),
            ]);

            $linea->descontado = true;
            $linea->save();

            $this->registrarEvento($orden, $user, StOrdenEvento::TIPO_REPUESTO, sprintf(
                'Descontado: %s × %d',
                $repuesto->nombre,
                $linea->cantidad
            ), ['repuesto_id' => $repuesto->id, 'cantidad' => $linea->cantidad]);
        }

        $orden->repuestos_descontados_at = now();
        $orden->costo_refacciones = $this->calcularCostoRefacciones($orden);
    }

    public function calcularCostoRefacciones(StOrden $orden): float
    {
        return (float) StOrdenRepuesto::query()
            ->where('orden_id', $orden->id)
            ->get()
            ->sum(fn (StOrdenRepuesto $l) => (float) $l->costo_unitario * (int) $l->cantidad);
    }

    public function registrarEvento(StOrden $orden, ?User $user, string $tipo, string $descripcion, ?array $meta = null): void
    {
        StOrdenEvento::create([
            'orden_id' => $orden->id,
            'user_id' => $user?->id,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    private function validarTransicion(StOrden $orden, string $de, string $a): void
    {
        $permitidas = [
            StOrden::ESTADO_PENDIENTE => [StOrden::ESTADO_EN_PROCESO, StOrden::ESTADO_CANCELADO],
            StOrden::ESTADO_EN_PROCESO => [StOrden::ESTADO_PENDIENTE, StOrden::ESTADO_LISTO, StOrden::ESTADO_CANCELADO],
            StOrden::ESTADO_LISTO => [StOrden::ESTADO_EN_PROCESO, StOrden::ESTADO_ENTREGADO],
            StOrden::ESTADO_ENTREGADO => [],
            StOrden::ESTADO_CANCELADO => [],
        ];

        if (! in_array($a, $permitidas[$de] ?? [], true)) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cambiar de «'.(StOrden::ESTADOS[$de] ?? $de).'» a «'.(StOrden::ESTADOS[$a] ?? $a).'».',
            ]);
        }

        if ($a === StOrden::ESTADO_ENTREGADO && $orden->excedePresupuesto()) {
            // La confirmación se maneja en el controlador con confirmar_exceso
        }

        if ($de === StOrden::ESTADO_LISTO && $a === StOrden::ESTADO_EN_PROCESO && $orden->repuestos_descontados_at) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede revertir a «En proceso» después de descontar repuestos.',
            ]);
        }
    }
}
