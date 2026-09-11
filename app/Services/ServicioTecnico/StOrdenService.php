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
    public function actualizarOrden(StOrden $orden, array $datos, User $user, array $lineasRepuesto = [], ?string $comentarioEstado = null): StOrden
    {
        return DB::transaction(function () use ($orden, $datos, $user, $lineasRepuesto, $comentarioEstado) {
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
                    'Estado: %s → %s. Motivo: %s',
                    StOrden::ESTADOS[$estadoAnterior] ?? $estadoAnterior,
                    StOrden::ESTADOS[$nuevoEstado] ?? $nuevoEstado,
                    trim((string) $comentarioEstado)
                ), ['de' => $estadoAnterior, 'a' => $nuevoEstado, 'comentario' => trim((string) $comentarioEstado)]);
            }

            return $orden->fresh(['repuestosLineas.repuesto', 'eventos.usuario', 'tecnico']);
        });
    }

    public function transferir(StOrden $orden, string $sedeDestino, User $user, ?User $tecnicoDestino = null): void
    {
        if (! $tecnicoDestino && ! $user->puedeTransferirServicio()) {
            throw ValidationException::withMessages([
                'sede' => 'No tienes permiso para transferir órdenes entre sedes.',
            ]);
        }

        $sedeDestino = strtoupper($sedeDestino);
        $sedes = config('inventario.sedes_locales', []);

        if (! in_array($sedeDestino, $sedes, true)) {
            throw ValidationException::withMessages(['sede' => 'Sede de destino inválida.']);
        }

        if ($sedeDestino === strtoupper((string) $orden->sede) && ! $tecnicoDestino) {
            return;
        }

        if ($orden->repuestos_descontados_at) {
            throw ValidationException::withMessages([
                'sede' => 'No se puede transferir una orden con repuestos ya descontados del inventario.',
            ]);
        }

        $sedeOrigen = strtoupper((string) $orden->sede);
        $codigoAnterior = $orden->codigo();

        $orden->sede_origen_transfer = $sedeOrigen;
        $orden->sede_destino_transfer = $sedeDestino;
        $orden->transfer_estado = StOrden::TRANSFER_PENDIENTE;
        if ($tecnicoDestino) {
            $orden->tecnico_id = $tecnicoDestino->id;
        }
        if ($sedeDestino !== $sedeOrigen) {
            // (sede, numero) es único: al cambiar de sede hay que tomar el siguiente número allí.
            $orden->sede = $sedeDestino;
            $orden->numero = StOrden::siguienteNumero($sedeDestino);
        }

        $this->registrarEvento($orden, $user, StOrdenEvento::TIPO_TRANSFERENCIA, sprintf(
            'Transferencia pendiente: %s → %s%s (%s → %s)',
            $sedeOrigen,
            $sedeDestino,
            $tecnicoDestino ? ' · Para '.$tecnicoDestino->name : '',
            $codigoAnterior,
            $orden->codigo()
        ), [
            'origen' => $sedeOrigen,
            'destino' => $sedeDestino,
            'tecnico_destino_id' => $tecnicoDestino?->id,
            'codigo_anterior' => $codigoAnterior,
            'codigo_nuevo' => $orden->codigo(),
        ]);

        if ($orden->equipo_id && ($equipo = $orden->equipoCelular ?: \App\Models\StEquipo::query()->find($orden->equipo_id))) {
            $equipo->estado_actual = \App\Models\StEquipo::ESTADO_EN_TRANSITO;
            $equipo->save();

            app(StEquipoService::class)->registrarEvento(
                $equipo,
                $user,
                \App\Models\StEquipoEvento::TIPO_ENVIO,
                'Enviado a '.$sedeDestino,
                sprintf('Orden %s (antes %s) · Desde %s', $orden->codigo(), $codigoAnterior, $sedeOrigen),
                $orden,
                $sedeOrigen,
                [
                    'origen' => $sedeOrigen,
                    'destino' => $sedeDestino,
                    'codigo_anterior' => $codigoAnterior,
                    'codigo_nuevo' => $orden->codigo(),
                ]
            );
        }

        $orden->save();
    }

    public function confirmarRecepcion(StOrden $orden, User $user): StOrden
    {
        if ($orden->transfer_estado !== StOrden::TRANSFER_PENDIENTE) {
            throw ValidationException::withMessages([
                'transfer' => 'Esta orden no tiene una transferencia pendiente.',
            ]);
        }

        if ($orden->tecnico_id
            ? (int) $orden->tecnico_id !== (int) $user->id
            : strtoupper((string) $orden->sede) !== strtoupper((string) $user->sede)) {
            throw ValidationException::withMessages([
                'transfer' => 'Solo la persona destinataria puede confirmar la recepción.',
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

        if ($orden->equipo_id && ($equipo = $orden->equipoCelular ?: \App\Models\StEquipo::query()->find($orden->equipo_id))) {
            $equipo->sede_actual = strtoupper((string) $orden->sede);
            $equipo->estado_actual = \App\Models\StEquipo::ESTADO_EN_TALLER;
            $equipo->save();

            app(StEquipoService::class)->registrarEvento(
                $equipo,
                $user,
                \App\Models\StEquipoEvento::TIPO_RECEPCION,
                'Recibido en ST '.$orden->sede,
                sprintf('Orden %s · Enviado desde %s', $orden->codigo(), $orden->sede_origen_transfer),
                $orden,
                (string) $orden->sede,
                [
                    'origen' => $orden->sede_origen_transfer,
                    'destino' => $orden->sede,
                ]
            );
        }

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

    /**
     * @param  array{empresa:string,motivo:string,observacion:string}  $data
     */
    public function enviarGarantiaExterna(StOrden $orden, array $data, User $user): StOrden
    {
        $this->validarGarantiaExterna($orden, [StOrden::GARANTIA_PENDIENTE_ENVIO]);

        return DB::transaction(function () use ($orden, $data, $user) {
            $orden->update([
                'empresa_envio_garantia' => $data['empresa'],
                'motivo_envio_garantia' => $data['motivo'],
                'observacion_envio_garantia' => $data['observacion'],
                'estado_garantia_externa' => StOrden::GARANTIA_ENVIADO,
                'garantia_enviado_at' => now(),
                'garantia_enviado_por' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->registrarEvento(
                $orden,
                $user,
                StOrdenEvento::TIPO_GARANTIA_EXTERNA,
                'Equipo enviado a '.$data['empresa'].'. Motivo: '.$data['motivo'].'. Observación: '.$data['observacion'],
                ['estado' => StOrden::GARANTIA_ENVIADO, 'empresa' => $data['empresa']]
            );
            if ($orden->equipo_id && ($equipo = $orden->equipoCelular ?: \App\Models\StEquipo::query()->find($orden->equipo_id))) {
                $equipo->update(['estado_actual' => \App\Models\StEquipo::ESTADO_EN_TRANSITO]);
                app(StEquipoService::class)->registrarEvento(
                    $equipo,
                    $user,
                    \App\Models\StEquipoEvento::TIPO_ENVIO,
                    'Enviado a garantía: '.$data['empresa'],
                    'Motivo: '.$data['motivo'].'. Observación: '.$data['observacion'],
                    $orden,
                    (string) $orden->sede,
                    ['empresa' => $data['empresa'], 'estado_garantia' => StOrden::GARANTIA_ENVIADO]
                );
            }

            return $orden->fresh();
        });
    }

    public function actualizarGarantiaExterna(StOrden $orden, string $comentario, string $tipo, User $user): void
    {
        $this->validarGarantiaExterna($orden, [StOrden::GARANTIA_ENVIADO, StOrden::GARANTIA_EN_PROCESO]);
        $prefijo = $tipo === 'avance' ? 'Avance' : 'Comentario';

        $this->registrarEvento(
            $orden,
            $user,
            StOrdenEvento::TIPO_GARANTIA_EXTERNA,
            $prefijo.' de garantía externa: '.$comentario,
            ['estado' => $orden->estadoGarantiaExternaActual(), 'tipo' => $tipo]
        );
        if ($orden->equipo_id && ($equipo = $orden->equipoCelular ?: \App\Models\StEquipo::query()->find($orden->equipo_id))) {
            app(StEquipoService::class)->registrarEvento(
                $equipo,
                $user,
                \App\Models\StEquipoEvento::TIPO_NOTA,
                $prefijo.' de garantía externa',
                $comentario,
                $orden,
                (string) $orden->sede,
                ['estado_garantia' => $orden->estadoGarantiaExternaActual()]
            );
        }
    }

    public function iniciarProcesoGarantiaExterna(StOrden $orden, string $comentario, User $user): StOrden
    {
        $this->validarGarantiaExterna($orden, [StOrden::GARANTIA_ENVIADO]);
        $orden->update([
            'estado_garantia_externa' => StOrden::GARANTIA_EN_PROCESO,
            'updated_by' => $user->id,
        ]);
        $this->registrarEvento(
            $orden,
            $user,
            StOrdenEvento::TIPO_GARANTIA_EXTERNA,
            'Garantía externa en proceso. '.$comentario,
            ['estado' => StOrden::GARANTIA_EN_PROCESO]
        );
        if ($orden->equipo_id && ($equipo = $orden->equipoCelular ?: \App\Models\StEquipo::query()->find($orden->equipo_id))) {
            app(StEquipoService::class)->registrarEvento(
                $equipo,
                $user,
                \App\Models\StEquipoEvento::TIPO_REPARACION,
                'Garantía externa en proceso',
                $comentario,
                $orden,
                (string) $orden->sede,
                ['estado_garantia' => StOrden::GARANTIA_EN_PROCESO]
            );
        }

        return $orden->fresh();
    }

    public function recibirGarantiaExterna(StOrden $orden, ?string $comentario, User $user): StOrden
    {
        $this->validarGarantiaExterna($orden, [StOrden::GARANTIA_ENVIADO, StOrden::GARANTIA_EN_PROCESO]);
        $orden->update([
            'estado_garantia_externa' => StOrden::GARANTIA_RECIBIDO,
            'garantia_recibido_at' => now(),
            'garantia_recibido_por' => $user->id,
            'updated_by' => $user->id,
        ]);
        $descripcion = 'Equipo recibido nuevamente en sede '.strtoupper((string) $orden->sede).'.';
        if ($comentario) {
            $descripcion .= ' Observación: '.$comentario;
        }
        $this->registrarEvento(
            $orden,
            $user,
            StOrdenEvento::TIPO_GARANTIA_EXTERNA,
            $descripcion,
            ['estado' => StOrden::GARANTIA_RECIBIDO, 'sede' => $orden->sede]
        );
        if ($orden->equipo_id && ($equipo = $orden->equipoCelular ?: \App\Models\StEquipo::query()->find($orden->equipo_id))) {
            $equipo->update([
                'estado_actual' => \App\Models\StEquipo::ESTADO_EN_TALLER,
                'sede_actual' => strtoupper((string) $orden->sede),
            ]);
            app(StEquipoService::class)->registrarEvento(
                $equipo,
                $user,
                \App\Models\StEquipoEvento::TIPO_RECEPCION,
                'Recibido de garantía externa',
                $descripcion,
                $orden,
                (string) $orden->sede,
                ['estado_garantia' => StOrden::GARANTIA_RECIBIDO]
            );
        }

        return $orden->fresh();
    }

    /**
     * @param  list<string>  $estadosPermitidos
     */
    private function validarGarantiaExterna(StOrden $orden, array $estadosPermitidos): void
    {
        if (! $orden->esGarantia()) {
            throw ValidationException::withMessages(['garantia' => 'Este flujo solo está disponible para garantías.']);
        }
        if (! in_array($orden->estadoGarantiaExternaActual(), $estadosPermitidos, true)) {
            throw ValidationException::withMessages(['garantia' => 'La acción no corresponde al estado actual de la garantía.']);
        }
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
        if (! array_key_exists($a, $orden->estadosPermitidos())) {
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
