<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Services\GerencialDashboardService;
use App\Services\Nomina\CommissionCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$desde = '2026-08-16';
$hasta = '2026-08-31';
$sede = 'CENTRO';

$periodo = NominaPeriodo::query()
    ->whereDate('fecha_inicio', $desde)
    ->whereDate('fecha_fin', $hasta)
    ->first();

echo "=== PERÍODO {$periodo?->etiqueta} (id {$periodo?->id}) ===\n\n";

$liq = NominaLiquidacionComision::query()
    ->with('empleado')
    ->where('periodo_id', $periodo->id)
    ->whereHas('empleado', fn ($q) => $q->where('modo_comision', 'SUPERVISOR_SEDE'))
    ->get()
    ->first(fn ($l) => str_contains(mb_strtoupper($l->empleado->nombre()), 'CARLOS'));

if ($liq) {
    echo "Liquidación actual (snapshot):\n";
    echo "  Base: $" . number_format($liq->base_total, 2) . "\n";
    echo "  Comisión: $" . number_format($liq->comision_total, 2) . "\n\n";

    $nuevo = app(CommissionCalculationService::class)->calcular($periodo, $liq->empleado);
    echo "Recálculo con venta neta:\n";
    echo "  Base: $" . number_format($nuevo['base'], 2) . "\n";
    echo "  Comisión 0.05%: $" . number_format($nuevo['total'], 2) . "\n\n";
}

if (Schema::hasTable('ventas_documentos')) {
    $docs = DB::table('ventas_documentos')
        ->whereBetween('fecha', [$desde, $hasta])
        ->whereRaw('UPPER(TRIM(sede)) = ?', [$sede])
        ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'")
        ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(total_neto_usd) ELSE ABS(total_neto_usd) END) as netas")
        ->first();
    echo "Gerencial / comisión (ventas_documentos): $" . number_format($docs->netas ?? 0, 2) . "\n";
    echo "Comisión esperada: $" . number_format(($docs->netas ?? 0) * 0.0005, 2) . "\n";
}
