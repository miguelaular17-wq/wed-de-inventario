<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpleadoAjuste;
use App\Models\Nomina\NominaComisionAbono;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaConfig;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use Illuminate\Support\Facades\Schema;

class CommissionSettlementService
{
    public function __construct(private AjusteService $ajustes)
    {
    }

    public function limpiarPeriodo(NominaPeriodo $periodo): void
    {
        if (Schema::hasTable('nomina_liquidaciones_comision')) {
            NominaLiquidacionComision::query()->where('periodo_id', $periodo->id)->delete();
        }

        if (Schema::hasTable('nomina_comision_descuentos')) {
            NominaComisionDescuento::query()
                ->where('periodo_id', $periodo->id)
                ->where('tipo', 'PRESTAMO')
                ->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $calculo
     */
    public function liquidar(
        NominaPeriodo $periodo,
        NominaEmpleado $empleado,
        array $calculo,
        float $prestamos = 0
    ): NominaLiquidacionComision {
        $abonos = $this->aplicarAbonos($periodo, $empleado)
            + $this->ajustes->aplicarComision($periodo, $empleado, NominaEmpleadoAjuste::TIPO_BONIFICACION);
        $descuentos = $this->aplicarDescuentos($periodo, $empleado)
            + $this->ajustes->aplicarComision($periodo, $empleado, NominaEmpleadoAjuste::TIPO_DEDUCCION);
        $comisionTotal = round((float) ($calculo['total'] ?? 0), 2);
        $bruto = round($comisionTotal + $abonos, 2);
        $modo = (string) ($calculo['modo'] ?? $empleado->modo_comision);
        $retencionPct = NominaConfig::getDecimal('retencion_comision_pct', 10);
        if ($modo === NominaEmpleado::COMISION_SERVICIO_TECNICO) {
            // Retención solo sobre la parte de Otros productos (+ abonos), no sobre ST.
            $retenible = round(
                (float) ($calculo['comision_telefonia'] ?? 0)
                + (float) ($calculo['comision_otros'] ?? 0)
                + $abonos,
                2
            );
        } else {
            $retenible = $bruto;
        }
        $retencion = round($retenible * $retencionPct / 100, 2);
        $prestamos = round($prestamos, 2);
        $totalPagar = round($bruto - $retencion - $descuentos - $prestamos, 2);
        $fechaPago = $periodo->fecha_pago_comision
            ?? $periodo->fecha_fin?->copy()->addDays(3);

        return NominaLiquidacionComision::create([
            'periodo_id' => $periodo->id,
            'empleado_id' => $empleado->id,
            'modo' => $calculo['modo'] ?? $empleado->modo_comision,
            'base_total' => $calculo['base'] ?? 0,
            'base_telefonia' => $calculo['base_telefonia'] ?? 0,
            'base_otros' => $calculo['base_otros'] ?? 0,
            'pct_telefonia' => $calculo['pct_telefonia'] ?? 0,
            'pct_otros' => $calculo['pct_otros'] ?? 0,
            'comision_telefonia' => $calculo['comision_telefonia'] ?? 0,
            'comision_otros' => $calculo['comision_otros'] ?? 0,
            'comision_total' => $comisionTotal,
            'abonos' => $abonos,
            'retencion_pct' => $retencionPct,
            'retencion' => $retencion,
            'descuentos' => $descuentos,
            'prestamos' => $prestamos,
            'total_pagar' => $totalPagar,
            'fecha_pago' => $fechaPago?->toDateString(),
            'snapshot' => $calculo,
        ]);
    }

    private function aplicarAbonos(NominaPeriodo $periodo, NominaEmpleado $empleado): float
    {
        if (! Schema::hasTable('nomina_comision_abonos')) {
            return 0.0;
        }

        $items = NominaComisionAbono::query()
            ->where('empleado_id', $empleado->id)
            ->where(function ($q) use ($periodo) {
                $q->where('periodo_id', $periodo->id)
                    ->orWhere(function ($q2) use ($periodo) {
                        [$desde, $hasta] = $this->rangoFechasComision($periodo);
                        $q2->where('estado', 'PENDIENTE')
                            ->whereDate('fecha', '>=', $desde)
                            ->whereDate('fecha', '<=', $hasta);
                    });
            })
            ->get();

        foreach ($items as $item) {
            $item->update([
                'estado' => 'APLICADO',
                'periodo_id' => $periodo->id,
            ]);
        }

        return round((float) $items->sum('monto'), 2);
    }

    private function aplicarDescuentos(NominaPeriodo $periodo, NominaEmpleado $empleado): float
    {
        if (! Schema::hasTable('nomina_comision_descuentos')) {
            return 0.0;
        }

        $items = NominaComisionDescuento::query()
            ->where('empleado_id', $empleado->id)
            ->where('tipo', '!=', 'PRESTAMO')
            ->where(function ($q) use ($periodo) {
                $q->where('periodo_id', $periodo->id)
                    ->orWhere(function ($q2) use ($periodo) {
                        [$desde, $hasta] = $this->rangoFechasComision($periodo);
                        $q2->where('estado', 'PENDIENTE')
                            ->whereDate('fecha', '>=', $desde)
                            ->whereDate('fecha', '<=', $hasta);
                    });
            })
            ->get();

        foreach ($items as $item) {
            $item->update([
                'estado' => 'APLICADO',
                'periodo_id' => $periodo->id,
            ]);
        }

        return round((float) $items->sum('monto'), 2);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function rangoFechasComision(NominaPeriodo $periodo): array
    {
        $inicio = $periodo->fecha_inicio->toDateString();
        $fin = $periodo->fecha_fin->toDateString();
        $pago = $periodo->fecha_pago_comision?->toDateString()
            ?? $periodo->fecha_fin?->copy()->addDays(3)->toDateString();

        if ($pago && $pago > $fin) {
            $fin = $pago;
        }

        return [$inicio, $fin];
    }
}
