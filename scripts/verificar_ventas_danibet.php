<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$desde = '2026-08-16';
$hasta = '2026-08-31';

$emps = DB::table('nomina_empleados as e')
    ->join('clientes as c', 'c.id', '=', 'e.cliente_id')
    ->where('c.nombre', 'like', '%DANIB%')
    ->select('e.id', 'c.nombre', 'e.codigo_vendedor', 'e.sede')
    ->get();

foreach ($emps as $emp) {
    echo "=== {$emp->nombre} (sede empleado: {$emp->sede}) ===\n";
    $claves = [];
    if ($emp->codigo_vendedor) {
        $claves[] = mb_strtoupper(trim($emp->codigo_vendedor), 'UTF-8');
    }
    if (DB::getSchemaBuilder()->hasTable('nomina_empleado_vendedores')) {
        $aliases = DB::table('nomina_empleado_vendedores')->where('empleado_id', $emp->id)->get();
        foreach ($aliases as $a) {
            foreach ([$a->codigo_profit, $a->nombre_normalizado] as $x) {
                if ($x) {
                    $claves[] = mb_strtoupper(trim($x), 'UTF-8');
                }
            }
        }
    }
    $claves = array_values(array_unique(array_filter($claves)));
    echo 'Claves vendedor: '.implode(', ', $claves)."\n";
    if ($claves === []) {
        continue;
    }

    $ph = implode(',', array_fill(0, count($claves), '?'));
    $bruto = (float) DB::table('ventas_detalle')
        ->whereBetween('fecha', [$desde, $hasta])
        ->where('anulado', false)
        ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves)
        ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -1 ELSE 1 END * ABS(cantidad * precio_venta)) as t")
        ->value('t');
    $neto = (float) DB::table('ventas_detalle')
        ->whereBetween('fecha', [$desde, $hasta])
        ->where('anulado', false)
        ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves)
        ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -1 ELSE 1 END * ABS(cantidad * COALESCE(precio_neto, precio_venta))) as t")
        ->value('t');

    echo 'Bruto (comisiones): $'.number_format($bruto, 2)."\n";
    echo 'Neto (Profit/gerencial): $'.number_format($neto, 2)."\n\n";

    $porSede = DB::table('ventas_detalle')
        ->whereBetween('fecha', [$desde, $hasta])
        ->where('anulado', false)
        ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves)
        ->groupBy(DB::raw('UPPER(TRIM(sede))'))
        ->selectRaw('UPPER(TRIM(sede)) as sede')
        ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -1 ELSE 1 END * ABS(cantidad * precio_venta)) as bruto")
        ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -1 ELSE 1 END * ABS(cantidad * COALESCE(precio_neto, precio_venta))) as neto")
        ->orderByDesc('neto')
        ->get();

    echo "Por sede:\n";
    foreach ($porSede as $r) {
        echo "  {$r->sede}: bruto $".number_format($r->bruto, 2).' | neto $'.number_format($r->neto, 2)."\n";
    }
    echo "\n";
}
