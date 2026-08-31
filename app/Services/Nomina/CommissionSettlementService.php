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

        if (Schema::hasTable('nomina_comision_abonos')) {
            NominaComisionAbono::query()
                ->where('periodo_id', $periodo->id)
                ->update(['periodo_id' => null, 'estado' => 'PENDIENTE']);
        }

        if (Schema::hasTable('nomina_comision_descuentos')) {
            NominaComisionDescuento::query()
                ->where('periodo_id', $periodo->id)
                ->where('tipo', 'PRESTAMO')
                ->delete();

            NominaComisionDescuento::query()
                ->where('periodo_id', $periodo->id)
                ->where('tipo', '!=', 'PRESTAMO')
                ->update(['periodo_id' => null, 'estado' => 'PENDIENTE']);
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
        $retencionPct = NominaConfig::getDecimal('retencion_comision_pct', 10);
        $retencion = round($bruto * $retencionPct / 100, 2);
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
            ->where('estado', 'PENDIENTE')
            ->whereBetween('fecha', [$periodo->fecha_inicio->toDateString(), $periodo->fecha_fin->toDateString()])
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
            ->where('estado', 'PENDIENTE')
            ->where('tipo', '!=', 'PRESTAMO')
            ->whereBetween('fecha', [$periodo->fecha_inicio->toDateString(), $periodo->fecha_fin->toDateString()])
            ->get();

        foreach ($items as $item) {
            $item->update([
                'estado' => 'APLICADO',
                'periodo_id' => $periodo->id,
            ]);
        }

        return round((float) $items->sum('monto'), 2);
    }
}
