<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPeriodo;
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
            ->keyBy('cuota_id');
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
            ->keyBy('cuota_id');
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

    /**
     * @return array{deudores:int, saldo:float, programado:float, nomina:float, comision:float}
     */
    public function kpis(array $quincena, $deudores): array
    {
        $planes = $this->planesDeQuincena($quincena)->where('estado', NominaPrestamoPlan::PENDIENTE);

        return [
            'deudores' => $deudores->count(),
            'saldo' => round((float) $deudores->sum('saldo'), 2),
            'programado' => round((float) $planes->sum('monto'), 2),
            'nomina' => round((float) $planes->where('destino', NominaPrestamoPlan::DESTINO_NOMINA)->sum('monto'), 2),
            'comision' => round((float) $planes->where('destino', NominaPrestamoPlan::DESTINO_COMISION)->sum('monto'), 2),
        ];
    }
}
