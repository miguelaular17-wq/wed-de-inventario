<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPeriodo;
use App\Services\Nomina\CommissionCalculationService;

$empleado = NominaEmpleado::query()
    ->whereHas('cliente', fn ($q) => $q->where('nombre', 'like', '%DANIB%'))
    ->first();
$periodo = NominaPeriodo::query()
    ->whereDate('fecha_inicio', '2026-08-16')
    ->first();

if (! $empleado || ! $periodo) {
    echo "Empleado o período no encontrado.\n";
    exit(0);
}

$resultado = app(CommissionCalculationService::class)->calcular($periodo, $empleado);

echo "Danibei recalculado (venta neta):\n";
echo "  Base total: $".number_format($resultado['base'], 2)."\n";
echo "  Base tel:   $".number_format($resultado['base_telefonia'], 2)."\n";
echo "  Base otros: $".number_format($resultado['base_otros'], 2)."\n";
echo "  Comisión:   $".number_format($resultado['total'], 2)."\n";
echo "  Profit ref: $2,763.99\n";
