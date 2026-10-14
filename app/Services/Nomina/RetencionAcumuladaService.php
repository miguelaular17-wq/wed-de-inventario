<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use Carbon\Carbon;

class RetencionAcumuladaService
{
    public function __construct(private PayrollSedeAreaTotals $sedes) {}

    /**
     * @return array{
     *     anio:?int,
     *     anios:list<int>,
     *     hasta:string,
     *     total:float,
     *     sedes:list<array{nombre:string, trabajadores:int, quincenas:int, retencion:float}>,
     *     trabajadores:list<array{id:int, nombre:string, sede:string, quincenas:int, retencion:float}>
     * }
     */
    public function resumen(?int $anio): array
    {
        $hasta = Carbon::today();
        $estados = [
            NominaPeriodo::CALCULADO,
            NominaPeriodo::APROBADO,
            NominaPeriodo::PAGADO,
            NominaPeriodo::CERRADO,
        ];

        $anios = NominaPeriodo::query()
            ->whereIn('estado', $estados)
            ->whereDate('fecha_fin', '<=', $hasta->toDateString())
            ->orderByDesc('fecha_fin')
            ->pluck('fecha_fin')
            ->map(fn ($fecha) => (int) Carbon::parse($fecha)->year)
            ->unique()
            ->values()
            ->all();

        $filas = NominaLiquidacionComision::query()
            ->where('modo', '!=', NominaEmpleado::COMISION_NINGUNA)
            ->whereHas('empleado')
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'periodo'])
            ->whereHas('periodo', function ($query) use ($anio, $hasta, $estados) {
                $query->whereIn('estado', $estados)
                    ->whereDate('fecha_fin', '<=', $hasta->toDateString());
                if ($anio !== null) {
                    $query->whereYear('fecha_fin', $anio);
                }
            })
            ->get();

        $porTrabajador = [];
        foreach ($filas as $liq) {
            $empleado = $liq->empleado;
            if (! $empleado) {
                continue;
            }
            $id = (int) $empleado->id;
            $monto = $liq->esServicioTecnico()
                ? $liq->retencionOtrosProductos()
                : (float) $liq->retencion;
            if (! isset($porTrabajador[$id])) {
                $grupo = $this->sedes->grupoDeEmpleado($empleado);
                $porTrabajador[$id] = [
                    'id' => $id,
                    'nombre' => $empleado->nombre(),
                    'sede' => $grupo['nombre'],
                    'quincenas' => 0,
                    'retencion' => 0.0,
                ];
            }
            $porTrabajador[$id]['quincenas']++;
            $porTrabajador[$id]['retencion'] = round($porTrabajador[$id]['retencion'] + $monto, 2);
        }

        $trabajadores = array_values($porTrabajador);
        usort($trabajadores, function (array $a, array $b) {
            $cmp = $b['retencion'] <=> $a['retencion'];

            return $cmp !== 0 ? $cmp : strcasecmp($a['nombre'], $b['nombre']);
        });

        $porSede = [];
        foreach ($trabajadores as $trabajador) {
            $sede = $trabajador['sede'];
            if (! isset($porSede[$sede])) {
                $porSede[$sede] = [
                    'nombre' => $sede,
                    'trabajadores' => 0,
                    'quincenas' => 0,
                    'retencion' => 0.0,
                ];
            }
            $porSede[$sede]['trabajadores']++;
            $porSede[$sede]['quincenas'] += $trabajador['quincenas'];
            $porSede[$sede]['retencion'] = round($porSede[$sede]['retencion'] + $trabajador['retencion'], 2);
        }

        $sedes = array_values($porSede);
        usort($sedes, fn (array $a, array $b) => $b['retencion'] <=> $a['retencion']);

        return [
            'anio' => $anio,
            'anios' => $anios,
            'hasta' => $hasta->format('d/m/Y'),
            'total' => round(array_sum(array_column($trabajadores, 'retencion')), 2),
            'sedes' => $sedes,
            'trabajadores' => $trabajadores,
        ];
    }
}
