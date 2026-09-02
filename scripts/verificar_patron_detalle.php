<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$casos = [
    ['DORAL', 'FAC', '143601'],
    ['DORAL', 'FAC', '141477'],
    ['NUNES', 'FAC', '2079'],
    ['ZAMORA', 'FAC', '55576'],
    ['DORAL', 'FAC', '143302'],
];

foreach ($casos as [$sede, $tipo, $num]) {
    echo "=== {$sede} {$tipo} {$num} ===\n";
    $doc = DB::table('ventas_documentos')
        ->where('sede', $sede)
        ->where('tipo_documento', $tipo)
        ->where('numero_documento', $num)
        ->first();
    if ($doc) {
        echo "Doc total_neto_usd: {$doc->total_neto_usd} | vendedor: {$doc->vendedor} | fecha: {$doc->fecha}\n";
    }
    $lineas = DB::table('ventas_detalle')
        ->where('sede', $sede)
        ->where('tipo_documento', $tipo)
        ->where('numero_documento', $num)
        ->where('anulado', false)
        ->select('id', 'cantidad', 'precio_venta', 'precio_neto', 'nombre_producto', 'codigo_producto')
        ->get();
    $sumBruto = 0;
    $sumNeto = 0;
    foreach ($lineas as $l) {
        $bruto = (float) $l->cantidad * (float) $l->precio_venta;
        $neto = (float) $l->cantidad * (float) ($l->precio_neto ?? $l->precio_venta);
        $sumBruto += $bruto;
        $sumNeto += $neto;
        $ratio = $l->precio_venta > 0 ? round((float) $l->precio_neto / (float) $l->precio_venta, 4) : '-';
        echo "  L{$l->id}: qty={$l->cantidad} pv={$l->precio_venta} pn={$l->precio_neto} ratio={$ratio} | ".mb_substr($l->nombre_producto ?? '', 0, 40)."\n";
    }
    echo "  Suma bruto líneas: ".number_format($sumBruto, 2)."\n";
    echo "  Suma neto líneas:  ".number_format($sumNeto, 2)."\n";
    if ($doc && $sumNeto > 0) {
        echo "  Doc/neto ratio: ".round((float) $doc->total_neto_usd / $sumNeto, 4)."\n";
    }
    echo "\n";
}

// Patrón ratio 2x
$ratios = DB::select("
    SELECT
        CASE
            WHEN lineas_neto > 0 AND ABS(doc_neto / lineas_neto - 2) < 0.02 THEN '~2x (doc ≈ 2× líneas)'
            WHEN lineas_neto > 0 AND ABS(doc_neto / lineas_neto - 1) < 0.02 THEN '~1x (ok)'
            WHEN lineas_neto = 0 AND doc_neto > 0 THEN 'sin líneas'
            ELSE 'otro'
        END as patron,
        COUNT(*) as cantidad,
        SUM(doc_neto - lineas_neto) as suma_diff
    FROM (
        SELECT ABS(d.total_neto_usd) as doc_neto,
               COALESCE((
                   SELECT SUM(ABS(vd.cantidad * COALESCE(vd.precio_neto, vd.precio_venta)))
                   FROM ventas_detalle vd
                   WHERE vd.sede = d.sede AND vd.tipo_documento = d.tipo_documento
                     AND vd.numero_documento = d.numero_documento AND vd.anulado = false
               ), 0) as lineas_neto
        FROM ventas_documentos d
        WHERE d.fecha BETWEEN '2026-08-16' AND '2026-08-31'
          AND LOWER(TRIM(COALESCE(d.estado, ''))) = 'registrado'
          AND UPPER(d.tipo_documento) <> 'DEV'
    ) x
    WHERE ABS(doc_neto - lineas_neto) >= 0.01
    GROUP BY 1
    ORDER BY cantidad DESC
");

echo "=== Clasificación del patrón en 300 docs con diff ===\n";
foreach ($ratios as $r) {
    echo "  {$r->patron}: {$r->cantidad} docs, suma diff $".number_format((float) $r->suma_diff, 2)."\n";
}
