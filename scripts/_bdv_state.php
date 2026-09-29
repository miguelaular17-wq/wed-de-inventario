<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== conciliacion_lineas VENEZUELA LIQ / PAGOMOVIL ===\n";
$rows = DB::table('conciliacion_lineas')
    ->where(function ($q) {
        $q->whereRaw("UPPER(banco) LIKE '%VENEZUELA%'");
    })
    ->where(function ($q) {
        $q->whereRaw("UPPER(descripcion) LIKE '%LIQ.TARJETA%'")
            ->orWhereRaw("UPPER(descripcion) LIKE '%LIQUIDACION T/%'")
            ->orWhereRaw("UPPER(descripcion) LIKE '%PAGOMOVIL%'");
    })
    ->whereBetween('fecha', ['2026-08-01', '2026-08-31'])
    ->selectRaw('estado, COUNT(*) as n, ROUND(SUM(ABS(monto))::numeric,2) as total')
    ->groupBy('estado')
    ->get();
foreach ($rows as $r) {
    echo "{$r->estado}\tn={$r->n}\ttotal={$r->total}\n";
}

echo "\n=== sample LIQ pendiente ===\n";
$liq = DB::table('conciliacion_lineas')
    ->whereRaw("UPPER(banco) LIKE '%VENEZUELA%'")
    ->whereRaw("(UPPER(descripcion) LIKE '%LIQ.TARJETA%' OR UPPER(descripcion) LIKE '%LIQUIDACION T/%')")
    ->whereBetween('fecha', ['2026-08-01', '2026-08-31'])
    ->orderBy('fecha')
    ->limit(8)
    ->get(['id','fecha','referencia','descripcion','monto','estado','tesoreria_ingreso_id','flujo_caja_id']);
foreach ($liq as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
}

echo "\n=== tesoreria_ingresos punto_venta agosto ===\n";
if (Schema::hasTable('tesoreria_ingresos')) {
    $ing = DB::table('tesoreria_ingresos')
        ->where('tipo', 'punto_venta')
        ->whereRaw("UPPER(COALESCE(banco,'')) LIKE '%VENEZUELA%'")
        ->whereBetween('fecha', ['2026-08-01', '2026-08-31'])
        ->selectRaw('es_conciliado, COUNT(*) n, ROUND(SUM(monto)::numeric,2) total')
        ->groupBy('es_conciliado')
        ->get();
    foreach ($ing as $r) {
        echo json_encode($r)."\n";
    }
    echo "sample:\n";
    foreach (DB::table('tesoreria_ingresos')->where('tipo','punto_venta')->whereBetween('fecha',['2026-08-01','2026-08-31'])->orderByDesc('monto')->limit(5)->get(['id','fecha','monto','lote_referencia','banco','titular','es_conciliado']) as $r) {
        echo json_encode($r)."\n";
    }
}

echo "\n=== LIQ conciliado linked wrong? ===\n";
$wrong = DB::table('conciliacion_lineas as c')
    ->leftJoin('tesoreria_ingresos as t', 't.id', '=', 'c.tesoreria_ingreso_id')
    ->whereRaw("UPPER(c.banco) LIKE '%VENEZUELA%'")
    ->whereRaw("(UPPER(c.descripcion) LIKE '%LIQ.TARJETA%' OR UPPER(c.descripcion) LIKE '%LIQUIDACION T/%')")
    ->whereBetween('c.fecha', ['2026-08-01', '2026-08-31'])
    ->where('c.estado', 'conciliado')
    ->limit(10)
    ->get(['c.id','c.fecha','c.monto','c.descripcion','c.tesoreria_ingreso_id','t.lote_referencia','t.monto as lote_monto','t.tipo']);
foreach ($wrong as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
}
