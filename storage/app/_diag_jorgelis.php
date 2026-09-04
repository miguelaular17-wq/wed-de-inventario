<?php

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Services\Nomina\EmployeeSalesService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$e = NominaEmpleado::with(['cliente', 'vendedores'])
    ->whereHas('cliente', fn ($q) => $q->where('nombre', 'like', '%JORGELIS%'))
    ->first();

if (! $e) {
    echo "no emp\n";
    exit(1);
}

echo "id={$e->id}\n";
echo 'nombre='.$e->nombre()."\n";
echo "modo={$e->modo_comision}\n";
echo "sede={$e->sede}\n";
echo "codigo_vendedor=[{$e->codigo_vendedor}]\n";
echo 'aliases='.json_encode($e->vendedores->toArray())."\n";

$claves = app(EmployeeSalesService::class)->claves($e);
echo 'claves='.json_encode($claves)."\n";

$ini = '2026-08-16';
$fin = '2026-08-31';

$rows = DB::table('ventas_documentos')
    ->selectRaw("UPPER(TRIM(vendedor)) as v, COUNT(*) as n, ROUND(SUM(total_neto_usd)::numeric, 2) as neto")
    ->whereBetween('fecha', [$ini, $fin])
    ->whereRaw("UPPER(TRIM(vendedor)) LIKE '%JORGEL%'")
    ->groupBy(DB::raw('UPPER(TRIM(vendedor))'))
    ->get();

echo 'docs_por_vendedor='.json_encode($rows)."\n";

$bySede = DB::table('ventas_documentos')
    ->selectRaw("UPPER(TRIM(vendedor)) as v, sede, COUNT(*) as n, ROUND(SUM(total_neto_usd)::numeric, 2) as neto")
    ->whereBetween('fecha', [$ini, $fin])
    ->whereRaw("UPPER(TRIM(vendedor)) LIKE '%JORGEL%'")
    ->groupBy(DB::raw('UPPER(TRIM(vendedor))'), 'sede')
    ->get();

echo 'por_sede='.json_encode($bySede)."\n";

$liq = NominaLiquidacionComision::query()
    ->where('empleado_id', $e->id)
    ->whereHas('periodo', fn ($q) => $q->where('fecha_inicio', '2026-08-16')->where('fecha_fin', '2026-08-31'))
    ->first();

if ($liq) {
    echo "liq base_total={$liq->base_total} tel={$liq->base_telefonia} otros={$liq->base_otros} com={$liq->comision_total}\n";
}
