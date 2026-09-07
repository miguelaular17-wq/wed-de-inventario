<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaPrestamo;
use App\Models\Nomina\NominaPrestamoAbono;
use App\Models\Nomina\NominaPrestamoCuota;
use App\Models\Nomina\NominaPrestamoPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LoanDiscountPlanService
{
    public function __construct(private SalaryAdvanceService $quincenas)
    {
    }

    public function disponible(): bool
    {
        return Schema::hasTable('nomina_prestamo_planes');
    }

    /**
     * @return \Illuminate\Support\Collection<int, NominaPrestamoCuota>
     */
    public function cuotasPendientes(?string $termino = null)
    {
        $query = NominaPrestamoCuota::query()
            ->with(['prestamo.empleado.cliente'])
            ->whereIn('estado', ['PENDIENTE', 'VENCIDA', 'PARCIAL'])
            ->whereHas('prestamo', function ($prestamo) use ($termino) {
                $prestamo->whereIn('estado', ['PENDIENTE', 'ACTIVO'])
                    ->whereHas('empleado', function ($empleado) use ($termino) {
                        $empleado->activos();
                        if (trim((string) $termino) !== '') {
                            $empleado->buscar($termino);
                        }
                    });
            })
            ->orderBy('prestamo_id')
            ->orderBy('numero');

        return $query->get()
            ->filter(fn (NominaPrestamoCuota $cuota) => $cuota->puedeDescontarseEnNomina() && $cuota->saldo() > 0)
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{empleado: NominaEmpleado, cuotas: \Illuminate\Support\Collection<int, NominaPrestamoCuota>, saldo: float}>
     */
    public function deudores(?string $termino = null)
    {
        return $this->cuotasPendientes($termino)
            ->filter(fn (NominaPrestamoCuota $cuota) => $cuota->prestamo?->empleado)
            ->groupBy(fn (NominaPrestamoCuota $cuota) => (int) $cuota->prestamo->empleado_id)
            ->map(function ($grupo) {
                return [
                    'empleado' => $grupo->first()->prestamo->empleado,
                    'cuotas' => $grupo->values(),
                    'saldo' => round($grupo->sum(fn (NominaPrestamoCuota $cuota) => $cuota->saldo()), 2),
                ];
            })
            ->sortBy(fn (array $fila) => mb_strtolower($fila['empleado']->nombre()))
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, NominaPrestamoPlan>
     */
    public function planesDeQuincena(array $quincena)
    {
        if (! $this->disponible()) {
            return collect();
        }

        return NominaPrestamoPlan::query()
            ->with(['empleado.cliente', 'cuota.prestamo'])
            ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
            ->whereDate('quincena_fin', $quincena['fin']->toDateString())
            ->whereIn('estado', [NominaPrestamoPlan::PENDIENTE, NominaPrestamoPlan::APLICADO])
            ->get()
            ->keyBy('id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, NominaPrestamoPlan>
     */
    public function planesDeEmpleado(NominaEmpleado $empleado, array $quincena)
    {
        if (! $this->disponible()) {
            return collect();
        }

        return NominaPrestamoPlan::query()
            ->where('empleado_id', $empleado->id)
            ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
            ->whereDate('quincena_fin', $quincena['fin']->toDateString())
            ->whereIn('estado', [NominaPrestamoPlan::PENDIENTE, NominaPrestamoPlan::APLICADO])
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<array{cuota_id?:int, aplicar?:mixed, monto?:float|string|null, destino?:string}>  $filas
     */
    public function guardarParaQuincena(array $filas, array $quincena, ?int $usuarioId = null): int
    {
        if (! $this->disponible()) {
            throw ValidationException::withMessages([
                'planes' => 'Falta migrar la tabla de planes de préstamo.',
            ]);
        }

        $guardados = 0;

        DB::transaction(function () use ($filas, $quincena, $usuarioId, &$guardados) {
            foreach ($filas as $fila) {
                $cuotaId = (int) ($fila['cuota_id'] ?? 0);
                if ($cuotaId <= 0) {
                    continue;
                }

                $cuota = NominaPrestamoCuota::query()->with('prestamo.empleado')->find($cuotaId);
                if (! $cuota || ! $cuota->prestamo?->empleado) {
                    continue;
                }

                $existente = NominaPrestamoPlan::query()
                    ->where('cuota_id', $cuota->id)
                    ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
                    ->first();

                if (empty($fila['aplicar'])) {
                    if ($existente && $existente->estado === NominaPrestamoPlan::PENDIENTE) {
                        $existente->delete();
                    }
                    continue;
                }

                if ($existente && $existente->estado === NominaPrestamoPlan::APLICADO) {
                    continue;
                }

                $saldo = $cuota->saldo();
                $monto = array_key_exists('monto', $fila) && $fila['monto'] !== null && $fila['monto'] !== ''
                    ? round((float) $fila['monto'], 2)
                    : $saldo;
                $monto = min(max($monto, 0), $saldo);
                if ($monto <= 0) {
                    continue;
                }

                $destino = ($fila['destino'] ?? '') === NominaPrestamoPlan::DESTINO_COMISION
                    ? NominaPrestamoPlan::DESTINO_COMISION
                    : NominaPrestamoPlan::DESTINO_NOMINA;

                if ($destino === NominaPrestamoPlan::DESTINO_COMISION && ! $cuota->prestamo->empleado->generaComision()) {
                    $destino = NominaPrestamoPlan::DESTINO_NOMINA;
                }

                $datos = [
                    'empleado_id' => $cuota->prestamo->empleado_id,
                    'prestamo_id' => $cuota->prestamo_id,
                    'cuota_id' => $cuota->id,
                    'quincena_inicio' => $quincena['inicio']->toDateString(),
                    'quincena_fin' => $quincena['fin']->toDateString(),
                    'etiqueta' => $quincena['etiqueta'],
                    'monto' => $monto,
                    'destino' => $destino,
                    'estado' => NominaPrestamoPlan::PENDIENTE,
                    'created_by' => $usuarioId,
                ];

                if ($existente) {
                    $existente->update($datos);
                    $plan = $existente;
                } else {
                    $plan = NominaPrestamoPlan::create($datos);
                }

                NominaAuditLog::registrar('PRESTAMO_PLAN', 'prestamo', $cuota->prestamo_id, null, [
                    'plan_id' => $plan->id,
                    'cuota_id' => $cuota->id,
                    'empleado_id' => $cuota->prestamo->empleado_id,
                    'monto' => $monto,
                    'destino' => $destino,
                    'quincena' => $quincena['etiqueta'],
                ]);
                $guardados++;
            }
        });

        return $guardados;
    }

    /**
     * @return list<array{cuota_id:int, monto:float, destino:string}>
     */
    public function paraCalcular(NominaPeriodo $periodo): array
    {
        if (! $this->disponible()) {
            return [];
        }

        return NominaPrestamoPlan::query()
            ->whereDate('quincena_inicio', $periodo->fecha_inicio->toDateString())
            ->whereDate('quincena_fin', $periodo->fecha_fin->toDateString())
            ->where('estado', NominaPrestamoPlan::PENDIENTE)
            ->get()
            ->map(fn (NominaPrestamoPlan $plan) => [
                'cuota_id' => (int) $plan->cuota_id,
                'monto' => (float) $plan->monto,
                'destino' => $plan->destino,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{cuota_id:int, monto?:float|null, destino?:string|null}>  $descuentos
     * @param  list<array{cuota_id:int, monto:float, destino:string}>  $planes
     * @return list<array{cuota_id:int, monto?:float|null, destino?:string|null}>
     */
    public function completarDestinos(array $descuentos, array $planes): array
    {
        $porCuota = collect($planes)->keyBy('cuota_id');

        return array_map(function (array $fila) use ($porCuota) {
            $id = (int) ($fila['cuota_id'] ?? 0);
            if ($id > 0 && empty($fila['destino']) && $porCuota->has($id)) {
                $fila['destino'] = $porCuota[$id]['destino'];
                if (! array_key_exists('monto', $fila) || $fila['monto'] === null || $fila['monto'] === '') {
                    $fila['monto'] = $porCuota[$id]['monto'];
                }
            }

            return $fila;
        }, $descuentos);
    }

    /**
     * @param  list<int>  $cuotaIds
     */
    public function marcarAplicados(NominaPeriodo $periodo, array $cuotaIds): void
    {
        if (! $this->disponible() || $cuotaIds === []) {
            return;
        }

        NominaPrestamoPlan::query()
            ->whereDate('quincena_inicio', $periodo->fecha_inicio->toDateString())
            ->whereDate('quincena_fin', $periodo->fecha_fin->toDateString())
            ->whereIn('cuota_id', $cuotaIds)
            ->where('estado', NominaPrestamoPlan::PENDIENTE)
            ->update([
                'estado' => NominaPrestamoPlan::APLICADO,
                'nomina_periodo_id' => $periodo->id,
            ]);
    }

    public function deshacerPeriodo(int $periodoId): void
    {
        if (! $this->disponible()) {
            return;
        }

        NominaPrestamoPlan::query()
            ->where('nomina_periodo_id', $periodoId)
            ->update([
                'estado' => NominaPrestamoPlan::PENDIENTE,
                'nomina_periodo_id' => null,
            ]);
    }

    public function deshacerComisionPeriodo(int $periodoId): void
    {
        if (! $this->disponible()) {
            return;
        }

        NominaPrestamoPlan::query()
            ->where('nomina_periodo_id', $periodoId)
            ->where('destino', NominaPrestamoPlan::DESTINO_COMISION)
            ->update([
                'estado' => NominaPrestamoPlan::PENDIENTE,
                'nomina_periodo_id' => null,
            ]);
    }

    /**
     * @return list<array{cuota_id:int, monto:float, destino:string}>
     */
    public function planesComisionPendientes(NominaPeriodo $periodo): array
    {
        if (! $this->disponible()) {
            return [];
        }

        return NominaPrestamoPlan::query()
            ->whereDate('quincena_inicio', $periodo->fecha_inicio->toDateString())
            ->whereDate('quincena_fin', $periodo->fecha_fin->toDateString())
            ->where('destino', NominaPrestamoPlan::DESTINO_COMISION)
            ->where('estado', NominaPrestamoPlan::PENDIENTE)
            ->get()
            ->map(fn (NominaPrestamoPlan $plan) => [
                'cuota_id' => (int) $plan->cuota_id,
                'monto' => (float) $plan->monto,
                'destino' => $plan->destino,
            ])
            ->values()
            ->all();
    }

/**
     * @param  list<array{cuota_id:int, monto:float, destino:string}>|\Illuminate\Support\Collection  $deudores
     * @return array{deudores:int, saldo:float, programado:float, nomina:float, comision:float, total_prestamo:float, total_pagado:float}
     */
    public function kpis(array $quincena, $deudores): array
    {
        $planes = $this->planesDeQuincena($quincena)->where('estado', NominaPrestamoPlan::PENDIENTE);

        return [
            'deudores' => $deudores->count(),
            'saldo' => round((float) $deudores->sum('saldo'), 2),
            'total_prestamo' => round((float) $deudores->sum('total_prestamo'), 2),
            'total_pagado' => round((float) $deudores->sum('total_pagado'), 2),
            'programado' => round((float) $planes->sum('monto'), 2),
            'nomina' => round((float) $planes->where('destino', NominaPrestamoPlan::DESTINO_NOMINA)->sum('monto'), 2),
            'comision' => round((float) $planes->where('destino', NominaPrestamoPlan::DESTINO_COMISION)->sum('monto'), 2),
        ];
    }

    /**
     * Programa descuento libre (sin cuota) para la quincena actual, FIFO sobre préstamos activos.
     */
    public function programarLibreEmpleado(
        NominaEmpleado $empleado,
        float $monto,
        string $destino,
        array $quincena,
        ?int $usuarioId = null,
        ?int $prestamoId = null,
    ): int {
        if (! $this->disponible()) {
            throw ValidationException::withMessages([
                'planes' => 'Falta migrar la tabla de planes de préstamo.',
            ]);
        }

        $monto = round($monto, 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor a cero.']);
        }

        $destino = $destino === NominaPrestamoPlan::DESTINO_COMISION
            ? NominaPrestamoPlan::DESTINO_COMISION
            : NominaPrestamoPlan::DESTINO_NOMINA;
        if ($destino === NominaPrestamoPlan::DESTINO_COMISION && ! $empleado->generaComision()) {
            $destino = NominaPrestamoPlan::DESTINO_NOMINA;
        }

        $query = NominaPrestamo::query()
            ->where('empleado_id', $empleado->id)
            ->whereIn('estado', ['PENDIENTE', 'ACTIVO'])
            ->where('saldo_pendiente', '>', 0)
            ->orderBy('fecha')
            ->orderBy('id');
        if ($prestamoId) {
            $query->where('id', $prestamoId);
        }
        $prestamos = $query->get();
        $saldoTotal = round((float) $prestamos->sum('saldo_pendiente'), 2);
        if ($prestamos->isEmpty()) {
            throw ValidationException::withMessages(['monto' => 'Este empleado no tiene saldo pendiente.']);
        }
        if ($monto - $saldoTotal > 0.009) {
            throw ValidationException::withMessages([
                'monto' => 'El monto no puede superar el saldo pendiente ($'.number_format($saldoTotal, 2).').',
            ]);
        }

        $guardados = 0;
        $restante = $monto;

        DB::transaction(function () use ($prestamos, $quincena, $destino, $usuarioId, $empleado, &$restante, &$guardados) {
            foreach ($prestamos as $prestamo) {
                if ($restante <= 0) {
                    break;
                }
                $aplica = min($restante, (float) $prestamo->saldo_pendiente);
                if ($aplica <= 0) {
                    continue;
                }

                $existente = NominaPrestamoPlan::query()
                    ->where('prestamo_id', $prestamo->id)
                    ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
                    ->whereNull('cuota_id')
                    ->first();

                if ($existente && $existente->estado === NominaPrestamoPlan::APLICADO) {
                    continue;
                }

                $datos = [
                    'empleado_id' => $empleado->id,
                    'prestamo_id' => $prestamo->id,
                    'cuota_id' => null,
                    'quincena_inicio' => $quincena['inicio']->toDateString(),
                    'quincena_fin' => $quincena['fin']->toDateString(),
                    'etiqueta' => $quincena['etiqueta'],
                    'monto' => $aplica,
                    'destino' => $destino,
                    'estado' => NominaPrestamoPlan::PENDIENTE,
                    'created_by' => $usuarioId,
                ];

                if ($existente) {
                    $existente->update($datos);
                    $plan = $existente;
                } else {
                    $plan = NominaPrestamoPlan::create($datos);
                }

                NominaAuditLog::registrar('PRESTAMO_PLAN', 'prestamo', $prestamo->id, null, [
                    'plan_id' => $plan->id,
                    'empleado_id' => $empleado->id,
                    'monto' => $aplica,
                    'destino' => $destino,
                    'quincena' => $quincena['etiqueta'],
                    'libre' => true,
                ]);

                $guardados++;
                $restante = round($restante - $aplica, 2);
            }
        });

        return $guardados;
    }

    /**
     * Aplica planes libres (sin cuota) pendientes del empleado en el período.
     *
     * @return array{0:float,1:float} [nómina, comisión]
     */
    public function aplicarLibresEmpleado(
        NominaEmpleado $empleado,
        NominaPeriodo $periodo,
        ?int $usuarioId = null,
    ): array {
        if (! $this->disponible()) {
            return [0.0, 0.0];
        }

        $planes = NominaPrestamoPlan::query()
            ->with('prestamo')
            ->where('empleado_id', $empleado->id)
            ->whereDate('quincena_inicio', $periodo->fecha_inicio->toDateString())
            ->whereDate('quincena_fin', $periodo->fecha_fin->toDateString())
            ->where('estado', NominaPrestamoPlan::PENDIENTE)
            ->whereNull('cuota_id')
            ->orderBy('id')
            ->get();

        if ($planes->isEmpty()) {
            return [0.0, 0.0];
        }

        $payments = app(LoanPaymentService::class);
        $nomina = 0.0;
        $comision = 0.0;

        DB::transaction(function () use ($planes, $periodo, $usuarioId, $payments, &$nomina, &$comision) {
            foreach ($planes as $plan) {
                $prestamo = NominaPrestamo::query()->lockForUpdate()->find($plan->prestamo_id);
                if (! $prestamo || in_array($prestamo->estado, ['PAGADO', 'CANCELADO'], true)) {
                    $plan->delete();
                    continue;
                }

                $monto = min(round((float) $plan->monto, 2), (float) $prestamo->saldo_pendiente);
                if ($monto <= 0) {
                    $plan->delete();
                    continue;
                }

                $esComision = $plan->destino === NominaPrestamoPlan::DESTINO_COMISION;
                $payments->registrarAbono($prestamo, [
                    'fecha' => $periodo->fecha_fin->toDateString(),
                    'monto' => $monto,
                    'tipo' => NominaPrestamoAbono::TIPO_NOMINA,
                    'observacion' => ($esComision ? 'Descuento de comisión' : 'Descuento de nómina').' período #'.$periodo->id,
                ], $usuarioId);

                $plan->estado = NominaPrestamoPlan::APLICADO;
                $plan->nomina_periodo_id = $periodo->id;
                $plan->monto = $monto;
                $plan->save();

                if ($esComision) {
                    $comision = round($comision + $monto, 2);
                } else {
                    $nomina = round($nomina + $monto, 2);
                }
            }
        });

        return [$nomina, $comision];
    }
}
