<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Services\Nomina\CommissionCalculationService;
use Illuminate\Support\Facades\DB;

$periodo = NominaPeriodo::query()
    ->whereDate('fecha_inicio', '2026-08-16')
    ->whereDate('fecha_fin', '2026-08-31')
    ->first();

if (! $periodo) {
    echo "Período no encontrado\n";
    exit(1);
}

$liqs = NominaLiquidacionComision::query()
    ->with(['empleado.cliente'])
    ->where('periodo_id', $periodo->id)
    ->get()
    ->filter(fn ($l) => str_contains(mb_strtoupper($l->empleado?->nombre() ?? ''), 'JORGE')
        && str_contains(mb_strtoupper($l->empleado?->nombre() ?? ''), 'DIAZ'));

echo "Período: {$periodo->etiqueta} (id {$periodo->id})\n\n";

if ($liqs->isEmpty()) {
    echo "No hay liquidación para Jorge Díaz en este período\n";
    exit(1);
}

foreach ($liqs as $liq) {
    $emp = $liq->empleado;

    echo "Empleado: {$emp->nombre()} (id {$emp->id})\n";
    echo "Modo: {$emp->modo_comision} · Código vendedor: {$emp->codigo_vendedor}\n\n";

    echo "=== LIQUIDACIÓN GUARDADA ===\n";
    echo "Facturas ST (snapshot): $" . number_format($liq->ventasSt(), 2) . "\n";
    echo "Egresos 058: $" . number_format($liq->egresos058(), 2) . "\n";
    echo "Base neta ST: $" . number_format($liq->baseStNeta(), 2) . "\n";
    echo "Tel otros: $" . number_format($liq->base_telefonia, 2) . "\n";
    echo "Otros prod: $" . number_format($liq->base_otros, 2) . "\n";
    echo "Comisión ST: $" . number_format($liq->snapshot['comision_st'] ?? 0, 2) . "\n";
    echo "Comisión tel: $" . number_format($liq->comision_telefonia, 2) . "\n";
    echo "Comisión otros: $" . number_format($liq->comision_otros, 2) . "\n";
    echo "Comisión total: $" . number_format($liq->comision_total, 2) . "\n";
    echo "Retención: $" . number_format($liq->retencion, 2) . "\n";
    echo "A pagar: $" . number_format($liq->total_pagar, 2) . "\n\n";

    $calc = app(CommissionCalculationService::class)->calcular($periodo, $emp);
    echo "=== RECÁLCULO EN VIVO ===\n";
    echo "ventas_st: $" . number_format($calc['ventas_st'] ?? 0, 2) . "\n";
    echo "gastos (058): $" . number_format($calc['gastos'] ?? 0, 2) . "\n";
    echo "base_st: $" . number_format($calc['base_st'] ?? 0, 2) . "\n";
    echo "base_telefonia: $" . number_format($calc['base_telefonia'] ?? 0, 2) . "\n";
    echo "base_otros: $" . number_format($calc['base_otros'] ?? 0, 2) . "\n";
    echo "comision_st: $" . number_format($calc['comision_st'] ?? 0, 2) . "\n";
    echo "comision_telefonia: $" . number_format($calc['comision_telefonia'] ?? 0, 2) . "\n";
    echo "comision_otros: $" . number_format($calc['comision_otros'] ?? 0, 2) . "\n";
    echo "total: $" . number_format($calc['total'] ?? 0, 2) . "\n\n";

    $pctSt = 50;
    $manualSt = round(max(0, ($calc['ventas_st'] ?? 0) - ($calc['gastos'] ?? 0)) * $pctSt / 100, 2);
    $manualTel = round(($calc['base_telefonia'] ?? 0) * 0.20 / 100, 2);
    $manualOtros = round(($calc['base_otros'] ?? 0) * 1 / 100, 2);
    $manualTotal = round($manualSt + $manualTel + $manualOtros, 2);
    echo "=== VERIFICACIÓN MANUAL ===\n";
    echo "ST: max(0, {$calc['ventas_st']} - {$calc['gastos']}) × 50% = \${$manualSt}\n";
    echo "Tel: {$calc['base_telefonia']} × 0.20% = \${$manualTel}\n";
    echo "Otros: {$calc['base_otros']} × 1% = \${$manualOtros}\n";
    echo "Total manual: \${$manualTotal}\n";
    echo "Coincide con guardado: " . ($manualTotal == (float) $liq->comision_total ? 'SÍ' : 'NO (guardado $'.$liq->comision_total.')') . "\n\n";

    // Egresos 058 detalle
    if (Schema::hasTable('flujo_cajas')) {
        $egresos = DB::table('flujo_cajas')
            ->where('nomina_empleado_id', $emp->id)
            ->where('tipo_gasto', '058 - SERVICIO TECNICO (GARANTIAS)')
            ->whereBetween('fecha', [$periodo->fecha_inicio->toDateString(), $periodo->fecha_fin->toDateString()])
            ->get(['fecha', 'monto_usd', 'monto_bs', 'tasa_cambio', 'descripcion']);
        echo "=== EGRESOS 058 ({$egresos->count()} filas) ===\n";
        $sum = 0;
        foreach ($egresos as $e) {
            $usd = (float) ($e->monto_usd ?: 0);
            if ($usd == 0 && $e->tasa_cambio > 0) {
                $usd = abs((float) $e->monto_bs) / (float) $e->tasa_cambio;
            }
            $sum += abs($usd);
            echo "{$e->fecha} | \${$usd} | {$e->descripcion}\n";
        }
        echo "Suma egresos: $" . number_format($sum, 2) . "\n";
    }
}
