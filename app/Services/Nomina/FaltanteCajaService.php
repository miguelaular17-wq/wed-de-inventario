<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPeriodo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FaltanteCajaService
{
    public function disponible(): bool
    {
        return Schema::hasTable('nomina_comision_descuentos');
    }

    public function tieneDecision(): bool
    {
        return $this->disponible()
            && Schema::hasColumn('nomina_comision_descuentos', 'decision');
    }

    public function tieneDestino(): bool
    {
        return $this->disponible()
            && Schema::hasColumn('nomina_comision_descuentos', 'destino');
    }

    /**
     * Cajeras/cajeros activos (cargo "Cajero"/"Cajera" o texto legacy con "cajer").
     */
    public function cajeras(?string $q = null): Collection
    {
        $query = NominaEmpleado::query()
            ->activos()
            ->with(['cliente', 'sedeCatalogo', 'empresa', 'cargoCatalogo'])
            ->where(function ($builder) {
                $builder
                    ->whereHas('cargoCatalogo', function ($cargo) {
                        $cargo->whereRaw('LOWER(nombre) LIKE ?', ['%cajer%']);
                    })
                    ->orWhereRaw('LOWER(COALESCE(cargo, \'\')) LIKE ?', ['%cajer%']);
            })
            ->join('clientes', 'clientes.id', '=', 'nomina_empleados.cliente_id')
            ->select('nomina_empleados.*')
            ->orderBy('clientes.nombre');

        if ($q !== null && trim($q) !== '') {
            $query->buscar(trim($q));
        }

        return $query->get();
    }

    public function create(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaComisionDescuento
    {
        if (! $this->disponible()) {
            throw ValidationException::withMessages([
                'faltante' => 'Falta migrar la tabla de descuentos de comisión.',
            ]);
        }

        if (! $this->esCajera($empleado)) {
            throw ValidationException::withMessages([
                'empleado_id' => 'Solo se puede cargar faltante de caja a cajeros/cajeras.',
            ]);
        }

        $monto = round((float) ($data['monto'] ?? 0), 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto debe ser mayor a cero.',
            ]);
        }

        $fecha = Carbon::parse($data['fecha'] ?? now())->startOfDay();
        $motivo = trim((string) ($data['motivo'] ?? ''));
        if ($motivo === '') {
            $motivo = 'Faltante de caja';
        } elseif (! str_contains(mb_strtolower($motivo), 'faltante')) {
            $motivo = 'Faltante de caja: '.$motivo;
        }

        $payload = [
            'empleado_id' => $empleado->id,
            'fecha' => $fecha->toDateString(),
            'tipo' => 'FALTANTE',
            'monto' => $monto,
            'motivo' => $motivo,
            'estado' => 'PENDIENTE',
            'created_by' => $usuarioId,
        ];
        if ($this->tieneDecision()) {
            $payload['decision'] = NominaComisionDescuento::DECISION_PENDIENTE;
        }

        $row = NominaComisionDescuento::create($payload);

        NominaAuditLog::registrar('FALTANTE_CAJA_CREAR', 'faltante_caja', $row->id, null, [
            'empleado_id' => $empleado->id,
            'monto' => $monto,
            'motivo' => $motivo,
            'fecha' => $fecha->toDateString(),
            'decision' => $payload['decision'] ?? null,
        ]);

        return $row;
    }

    public function decidir(
        NominaComisionDescuento $descuento,
        string $decision,
        ?int $usuarioId = null
    ): NominaComisionDescuento {
        if ($descuento->tipo !== 'FALTANTE') {
            throw ValidationException::withMessages([
                'tipo' => 'Solo aplica a faltantes de caja.',
            ]);
        }

        if ($descuento->estado === 'APLICADO') {
            throw ValidationException::withMessages([
                'estado' => 'Este faltante ya fue aplicado; no se puede cambiar la decisión.',
            ]);
        }

        if ($descuento->estado === 'CANCELADO') {
            throw ValidationException::withMessages([
                'estado' => 'Este faltante está cancelado.',
            ]);
        }

        $decision = mb_strtoupper(trim($decision), 'UTF-8');
        if (! in_array($decision, [
            NominaComisionDescuento::DECISION_DESCONTAR,
            NominaComisionDescuento::DECISION_NO_DESCONTAR,
            NominaComisionDescuento::DECISION_PENDIENTE,
        ], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Decisión no válida.',
            ]);
        }

        if (! $this->tieneDecision()) {
            throw ValidationException::withMessages([
                'decision' => 'Falta migrar la columna de decisión.',
            ]);
        }

        $anterior = $descuento->decision;
        $descuento->decision = $decision;
        $descuento->save();

        NominaAuditLog::registrar('FALTANTE_CAJA_DECISION', 'faltante_caja', $descuento->id, [
            'decision' => $anterior,
        ], [
            'decision' => $decision,
            'usuario_id' => $usuarioId,
        ]);

        return $descuento;
    }

    /**
     * Saldo en cuenta = registros aún sin decidir (suman a la misma persona).
     */
    public function saldoCuenta(NominaEmpleado $empleado): float
    {
        if (! $this->disponible()) {
            return 0.0;
        }

        $query = NominaComisionDescuento::query()
            ->where('empleado_id', $empleado->id)
            ->where('tipo', 'FALTANTE')
            ->where('estado', 'PENDIENTE');

        if ($this->tieneDecision()) {
            $query->where(function ($q) {
                $q->where('decision', NominaComisionDescuento::DECISION_PENDIENTE)
                    ->orWhereNull('decision');
            });
        }

        return round((float) $query->sum('monto'), 2);
    }

    /**
     * @return array{cuenta:float,a_descontar:float}
     */
    public function resumenCuenta(NominaEmpleado $empleado): array
    {
        return [
            'cuenta' => $this->saldoCuenta($empleado),
            'a_descontar' => $this->pendienteDe($empleado),
        ];
    }

    /**
     * Toma monto de la cuenta (FIFO) y lo marca para descontar en nómina o comisión.
     * Permite cantidad parcial: p. ej. cuenta $70 → descontar $50.
     */
    public function descontarDeCuenta(
        NominaEmpleado $empleado,
        float $monto,
        ?int $usuarioId = null,
        ?string $motivoExtra = null,
        Carbon|string|null $fechaDescuento = null,
        string $destino = NominaComisionDescuento::DESTINO_COMISION
    ): float {
        $destino = $destino === NominaComisionDescuento::DESTINO_NOMINA
            ? NominaComisionDescuento::DESTINO_NOMINA
            : NominaComisionDescuento::DESTINO_COMISION;

        if ($destino === NominaComisionDescuento::DESTINO_COMISION && ! $empleado->generaComision()) {
            $destino = NominaComisionDescuento::DESTINO_NOMINA;
        }

        return $this->aplicarMontoSobreCuenta(
            $empleado,
            $monto,
            NominaComisionDescuento::DECISION_DESCONTAR,
            $usuarioId,
            $motivoExtra,
            $fechaDescuento,
            $destino
        );
    }

    /**
     * Saca monto de la cuenta sin descontar (queda en historial como no descontar).
     */
    public function noDescontarDeCuenta(
        NominaEmpleado $empleado,
        float $monto,
        ?int $usuarioId = null,
        ?string $motivoExtra = null,
        Carbon|string|null $fechaDescuento = null
    ): float {
        return $this->aplicarMontoSobreCuenta(
            $empleado,
            $monto,
            NominaComisionDescuento::DECISION_NO_DESCONTAR,
            $usuarioId,
            $motivoExtra,
            $fechaDescuento,
            null
        );
    }

    private function aplicarMontoSobreCuenta(
        NominaEmpleado $empleado,
        float $monto,
        string $decision,
        ?int $usuarioId,
        ?string $motivoExtra,
        Carbon|string|null $fechaDescuento = null,
        ?string $destino = null
    ): float {
        if (! $this->tieneDecision()) {
            throw ValidationException::withMessages([
                'decision' => 'Falta migrar la columna de decisión.',
            ]);
        }

        $monto = round($monto, 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto debe ser mayor a cero.',
            ]);
        }

        $disponible = $this->saldoCuenta($empleado);
        if ($monto > $disponible + 0.001) {
            throw ValidationException::withMessages([
                'monto' => 'La cuenta solo tiene $'.number_format($disponible, 2).' disponible.',
            ]);
        }

        $fechaAplicacion = null;
        if ($fechaDescuento) {
            try {
                $fechaAplicacion = Carbon::parse($fechaDescuento)->toDateString();
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'fecha_descuento' => 'Fecha de descuento no válida.',
                ]);
            }
        }

        $guardarDestino = $this->tieneDestino()
            && $decision === NominaComisionDescuento::DECISION_DESCONTAR
            && in_array($destino, [
                NominaComisionDescuento::DESTINO_NOMINA,
                NominaComisionDescuento::DESTINO_COMISION,
            ], true);

        return (float) DB::transaction(function () use (
            $empleado,
            $monto,
            $decision,
            $usuarioId,
            $motivoExtra,
            $fechaAplicacion,
            $destino,
            $guardarDestino
        ) {
            $lineas = NominaComisionDescuento::query()
                ->where('empleado_id', $empleado->id)
                ->where('tipo', 'FALTANTE')
                ->where('estado', 'PENDIENTE')
                ->where(function ($q) {
                    $q->where('decision', NominaComisionDescuento::DECISION_PENDIENTE)
                        ->orWhereNull('decision');
                })
                ->orderBy('fecha')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $restante = $monto;
            foreach ($lineas as $linea) {
                if ($restante <= 0.001) {
                    break;
                }

                $lineaMonto = round((float) $linea->monto, 2);
                if ($lineaMonto <= $restante + 0.001) {
                    $anterior = $linea->decision;
                    $linea->decision = $decision;
                    if ($fechaAplicacion && $decision === NominaComisionDescuento::DECISION_DESCONTAR) {
                        $linea->fecha = $fechaAplicacion;
                    }
                    if ($guardarDestino) {
                        $linea->destino = $destino;
                    } elseif ($this->tieneDestino() && $decision === NominaComisionDescuento::DECISION_NO_DESCONTAR) {
                        $linea->destino = null;
                    }
                    $linea->save();
                    NominaAuditLog::registrar('FALTANTE_CAJA_DECISION', 'faltante_caja', $linea->id, [
                        'decision' => $anterior,
                    ], [
                        'decision' => $decision,
                        'destino' => $guardarDestino ? $destino : null,
                        'fecha_descuento' => $fechaAplicacion,
                        'usuario_id' => $usuarioId,
                        'via' => 'cuenta',
                    ]);
                    $restante = round($restante - $lineaMonto, 2);
                    continue;
                }

                $parcial = $restante;
                $remanente = round($lineaMonto - $parcial, 2);
                $linea->monto = $remanente;
                $linea->save();

                $motivo = $linea->motivo ?: 'Faltante de caja';
                if ($motivoExtra) {
                    $motivo .= ' | '.$motivoExtra;
                }
                $motivo .= ' (parcial $'.number_format($parcial, 2).')';

                $fechaLinea = $fechaAplicacion
                    && $decision === NominaComisionDescuento::DECISION_DESCONTAR
                    ? $fechaAplicacion
                    : ($linea->fecha?->toDateString() ?? now()->toDateString());

                $payload = [
                    'empleado_id' => $empleado->id,
                    'fecha' => $fechaLinea,
                    'tipo' => 'FALTANTE',
                    'monto' => $parcial,
                    'motivo' => $motivo,
                    'estado' => 'PENDIENTE',
                    'decision' => $decision,
                    'created_by' => $usuarioId,
                ];
                if ($guardarDestino) {
                    $payload['destino'] = $destino;
                }

                $nuevo = NominaComisionDescuento::create($payload);

                NominaAuditLog::registrar('FALTANTE_CAJA_DECISION', 'faltante_caja', $nuevo->id, null, [
                    'decision' => $decision,
                    'destino' => $guardarDestino ? $destino : null,
                    'monto' => $parcial,
                    'fecha_descuento' => $fechaAplicacion,
                    'desde_id' => $linea->id,
                    'usuario_id' => $usuarioId,
                    'via' => 'cuenta_parcial',
                ]);

                $restante = 0.0;
            }

            if ($restante > 0.01) {
                throw ValidationException::withMessages([
                    'monto' => 'No se pudo cubrir el monto completo desde la cuenta.',
                ]);
            }

            return $monto;
        });
    }

    public function cancelar(NominaComisionDescuento $descuento, ?string $motivo = null): NominaComisionDescuento
    {
        if ($descuento->tipo !== 'FALTANTE') {
            throw ValidationException::withMessages([
                'tipo' => 'Solo se pueden cancelar faltantes de caja.',
            ]);
        }

        if ($descuento->estado === 'APLICADO') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar un faltante ya aplicado en comisiones.',
            ]);
        }

        if ($descuento->estado === 'CANCELADO') {
            return $descuento;
        }

        $anterior = $descuento->estado;
        $descuento->estado = 'CANCELADO';
        if ($motivo) {
            $descuento->motivo = trim(($descuento->motivo ?: '').' | Cancelado: '.$motivo);
        }
        $descuento->save();

        NominaAuditLog::registrar('FALTANTE_CAJA_CANCELAR', 'faltante_caja', $descuento->id, [
            'estado' => $anterior,
        ], [
            'estado' => 'CANCELADO',
        ]);

        return $descuento;
    }

    public function delDia(Carbon|string $fecha): Collection
    {
        if (! $this->disponible()) {
            return collect();
        }

        $fecha = Carbon::parse($fecha)->toDateString();

        return NominaComisionDescuento::query()
            ->with(['empleado.cliente', 'empleado.cargoCatalogo', 'empleado.sedeCatalogo', 'creador'])
            ->where('tipo', 'FALTANTE')
            ->whereDate('fecha', $fecha)
            ->where('estado', '!=', 'CANCELADO')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Historial del día agrupado por persona (totales + detalle).
     *
     * @return \Illuminate\Support\Collection<int, array{
     *   empleado_id:int,
     *   empleado:?NominaEmpleado,
     *   total:float,
     *   en_cuenta:float,
     *   a_descontar:float,
     *   no_descontar:float,
     *   lineas:\Illuminate\Support\Collection
     * }>
     */
    public function historialDelDiaAgrupado(Carbon|string $fecha): Collection
    {
        return $this->delDia($fecha)
            ->groupBy('empleado_id')
            ->map(function (Collection $lineas, $empleadoId) {
                $lineas = $lineas->sortBy('id')->values();
                $enCuenta = $lineas->filter(fn ($l) => in_array($l->decision, [
                    NominaComisionDescuento::DECISION_PENDIENTE, null,
                ], true))->sum('monto');
                $aDescontar = $lineas->where('decision', NominaComisionDescuento::DECISION_DESCONTAR)->sum('monto');
                $noDescontar = $lineas->where('decision', NominaComisionDescuento::DECISION_NO_DESCONTAR)->sum('monto');

                return [
                    'empleado_id' => (int) $empleadoId,
                    'empleado' => $lineas->first()?->empleado,
                    'total' => round((float) $lineas->sum('monto'), 2),
                    'en_cuenta' => round((float) $enCuenta, 2),
                    'a_descontar' => round((float) $aDescontar, 2),
                    'no_descontar' => round((float) $noDescontar, 2),
                    'lineas' => $lineas,
                ];
            })
            ->sortBy(fn (array $g) => mb_strtolower($g['empleado']?->nombre() ?? ''))
            ->values();
    }

    /**
     * Historial reciente (incluye cancelados) para la quincena o últimos registros.
     */
    public function historial(?Carbon $inicio = null, ?Carbon $fin = null, int $limite = 80): Collection
    {
        if (! $this->disponible()) {
            return collect();
        }

        $query = NominaComisionDescuento::query()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'creador'])
            ->where('tipo', 'FALTANTE')
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($inicio && $fin) {
            $query->whereDate('fecha', '>=', $inicio->toDateString())
                ->whereDate('fecha', '<=', $fin->toDateString());
        }

        return $query->limit($limite)->get();
    }

    /**
     * @return array{pendiente:float,por_decidir:float,del_dia:float,cajeras:int,personas_hoy:int}
     */
    public function kpis(Carbon|string $fecha): array
    {
        $fecha = Carbon::parse($fecha);
        $delDia = $this->delDia($fecha);

        $pendiente = 0.0;
        $porDecidir = 0.0;
        if ($this->disponible()) {
            $base = NominaComisionDescuento::query()
                ->where('tipo', 'FALTANTE')
                ->where('estado', 'PENDIENTE');

            if ($this->tieneDecision()) {
                $pendiente = (float) (clone $base)
                    ->where('decision', NominaComisionDescuento::DECISION_DESCONTAR)
                    ->sum('monto');
                $porDecidir = (float) (clone $base)
                    ->where(function ($q) {
                        $q->where('decision', NominaComisionDescuento::DECISION_PENDIENTE)
                            ->orWhereNull('decision');
                    })
                    ->sum('monto');
            } else {
                $pendiente = (float) $base->sum('monto');
            }
        }

        return [
            'pendiente' => round($pendiente, 2),
            'por_decidir' => round($porDecidir, 2),
            'del_dia' => round((float) $delDia->sum('monto'), 2),
            'cajeras' => $this->cajeras()->count(),
            'personas_hoy' => $delDia->pluck('empleado_id')->unique()->count(),
        ];
    }

    public function pendienteDe(NominaEmpleado $empleado): float
    {
        if (! $this->disponible()) {
            return 0.0;
        }

        $query = NominaComisionDescuento::query()
            ->where('empleado_id', $empleado->id)
            ->where('tipo', 'FALTANTE')
            ->where('estado', 'PENDIENTE');

        if ($this->tieneDecision()) {
            $query->where('decision', NominaComisionDescuento::DECISION_DESCONTAR);
        }

        return round((float) $query->sum('monto'), 2);
    }

    /**
     * Descuenta faltantes con destino nómina (o legacy sin comisión) al calcular el período.
     */
    public function aplicarANominaSinComision(NominaPeriodo $periodo): void
    {
        if (! $this->disponible()) {
            return;
        }

        $desde = $periodo->fecha_inicio->toDateString();
        $hasta = $periodo->fecha_fin->toDateString();

        $query = NominaComisionDescuento::query()
            ->with('empleado')
            ->where('tipo', 'FALTANTE')
            ->where('estado', 'PENDIENTE')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta);

        if ($this->tieneDecision()) {
            $query->where('decision', NominaComisionDescuento::DECISION_DESCONTAR);
        }

        if ($this->tieneDestino()) {
            $query->where(function ($q) {
                $q->where('destino', NominaComisionDescuento::DESTINO_NOMINA)
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('destino')
                            ->whereHas('empleado', fn ($e) => $e->where('modo_comision', NominaEmpleado::COMISION_NINGUNA));
                    });
            });
        } else {
            $query->whereHas('empleado', fn ($q) => $q->where('modo_comision', NominaEmpleado::COMISION_NINGUNA));
        }

        foreach ($query->get() as $item) {
            $item->update([
                'estado' => 'APLICADO',
                'periodo_id' => $periodo->id,
            ]);
        }
    }

    public function deshacerPeriodoNomina(int $periodoId): void
    {
        if (! $this->disponible()) {
            return;
        }

        $query = NominaComisionDescuento::query()
            ->where('tipo', 'FALTANTE')
            ->where('periodo_id', $periodoId)
            ->where('estado', 'APLICADO');

        if ($this->tieneDestino()) {
            $query->where(function ($q) {
                $q->where('destino', NominaComisionDescuento::DESTINO_NOMINA)
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('destino')
                            ->whereHas('empleado', fn ($e) => $e->where('modo_comision', NominaEmpleado::COMISION_NINGUNA));
                    });
            });
        } else {
            $query->whereHas('empleado', fn ($q) => $q->where('modo_comision', NominaEmpleado::COMISION_NINGUNA));
        }

        $query->update([
            'estado' => 'PENDIENTE',
            'periodo_id' => null,
        ]);
    }

    public function totalAplicadoNomina(NominaPeriodo $periodo, NominaEmpleado $empleado): float
    {
        if (! $this->disponible()) {
            return 0.0;
        }

        $query = NominaComisionDescuento::query()
            ->where('empleado_id', $empleado->id)
            ->where('tipo', 'FALTANTE')
            ->where('periodo_id', $periodo->id)
            ->where('estado', 'APLICADO');

        if ($this->tieneDestino()) {
            $query->where(function ($q) use ($empleado) {
                $q->where('destino', NominaComisionDescuento::DESTINO_NOMINA);
                if (! $empleado->generaComision()) {
                    $q->orWhereNull('destino');
                }
            });
        } elseif ($empleado->generaComision()) {
            return 0.0;
        }

        return round((float) $query->sum('monto'), 2);
    }

    public function esCajera(NominaEmpleado $empleado): bool
    {
        $empleado->loadMissing('cargoCatalogo');
        $nombre = mb_strtolower((string) ($empleado->cargoCatalogo?->nombre ?: $empleado->cargo ?: ''));

        return str_contains($nombre, 'cajer');
    }
}
