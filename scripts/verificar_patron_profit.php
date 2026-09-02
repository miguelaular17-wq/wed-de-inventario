<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$desde = '2026-08-16';
$hasta = '2026-08-31';

echo "=== PATRÓN: ventas_documentos vs suma líneas ({$desde} a {$hasta}) ===\n\n";

$vendedoresDocs = DB::table('ventas_documentos')
    ->whereBetween('fecha', [$desde, $hasta])
    ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'")
    ->whereNotNull('vendedor')
    ->whereRaw("TRIM(vendedor) <> ''")
    ->groupBy(DB::raw('UPPER(TRIM(vendedor))'))
    ->selectRaw('UPPER(TRIM(vendedor)) as vendedor')
    ->selectRaw('COUNT(*) as docs')
    ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(total_neto_usd) ELSE ABS(total_neto_usd) END) as neto_docs")
    ->orderByDesc('neto_docs')
    ->get();

$filas = [];
foreach ($vendedoresDocs as $v) {
    $claves = [$v->vendedor];
    $ph = '?';

    $netoLineas = (float) DB::table('ventas_detalle')
        ->whereBetween('fecha', [$desde, $hasta])
        ->where('anulado', false)
        ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves)
        ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -1 ELSE 1 END * ABS(cantidad * COALESCE(precio_neto, precio_venta))) as t")
        ->value('t');

    $docsLineas = (int) DB::table('ventas_detalle')
        ->whereBetween('fecha', [$desde, $hasta])
        ->where('anulado', false)
        ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves)
        ->selectRaw("COUNT(DISTINCT CONCAT(sede,'|',tipo_documento,'|',numero_documento)) as c")
        ->value('c');

    $diff = round((float) $v->neto_docs - $netoLineas, 2);
    $pct = $netoLineas > 0 ? round($diff / $netoLineas * 100, 2) : ($diff != 0 ? 100 : 0);

    $filas[] = [
        'vendedor' => $v->vendedor,
        'docs_profit' => (int) $v->docs,
        'docs_lineas' => $docsLineas,
        'neto_profit' => round((float) $v->neto_docs, 2),
        'neto_lineas' => round($netoLineas, 2),
        'diff' => $diff,
        'pct' => $pct,
    ];
}

// Vendedores solo en líneas (sin cabecera o sin vendedor en docs)
$soloLineas = DB::table('ventas_detalle as vd')
    ->whereBetween('vd.fecha', [$desde, $hasta])
    ->where('vd.anulado', false)
    ->whereNotNull('vd.vendedor')
    ->whereRaw("TRIM(vd.vendedor) <> ''")
    ->whereNotExists(function ($q) use ($desde, $hasta) {
        $q->selectRaw('1')
            ->from('ventas_documentos as d')
            ->whereBetween('d.fecha', [$desde, $hasta])
            ->whereRaw("LOWER(TRIM(COALESCE(d.estado, ''))) = 'registrado'")
            ->whereRaw('UPPER(TRIM(d.vendedor)) = UPPER(TRIM(vd.vendedor))');
    })
    ->groupBy(DB::raw('UPPER(TRIM(vd.vendedor))'))
    ->selectRaw('UPPER(TRIM(vd.vendedor)) as vendedor')
    ->selectRaw("COUNT(DISTINCT CONCAT(vd.sede,'|',vd.tipo_documento,'|',vd.numero_documento)) as docs")
    ->selectRaw("SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN -1 ELSE 1 END * ABS(vd.cantidad * COALESCE(vd.precio_neto, vd.precio_venta))) as neto")
    ->get();

echo "--- Vendedores con diferencia Profit vs líneas ---\n";
$conDiff = array_filter($filas, fn ($f) => abs($f['diff']) >= 0.01);
usort($conDiff, fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));
printf("%-35s %5s %5s %12s %12s %10s %6s\n", 'VENDEDOR', 'DOC-P', 'DOC-L', 'NETO-PROFIT', 'NETO-LINEAS', 'DIFERENCIA', '%');
foreach ($conDiff as $f) {
    printf(
        "%-35s %5d %5d %12s %12s %10s %5.2f%%\n",
        mb_substr($f['vendedor'], 0, 35),
        $f['docs_profit'],
        $f['docs_lineas'],
        number_format($f['neto_profit'], 2),
        number_format($f['neto_lineas'], 2),
        number_format($f['diff'], 2),
        $f['pct']
    );
}
echo "\nTotal vendedores en Profit: ".count($filas)."\n";
echo "Con diferencia >= \$0.01: ".count($conDiff)."\n";
echo "Suma diferencias: $".number_format(array_sum(array_column($conDiff, 'diff')), 2)."\n\n";

if ($soloLineas->isNotEmpty()) {
    echo "--- Vendedores solo en líneas (sin cabecera Profit) ---\n";
    foreach ($soloLineas as $s) {
        echo "  {$s->vendedor}: {$s->docs} docs, $".number_format((float) $s->neto, 2)."\n";
    }
    echo "\n";
}

// Patrón por documento: cuántos tienen diff
$docsDiff = DB::select("
    SELECT COUNT(*) FILTER (WHERE ABS(diff) >= 0.01) as con_diff,
           COUNT(*) as total,
           SUM(diff) FILTER (WHERE ABS(diff) >= 0.01) as suma_diff,
           AVG(ABS(diff)) FILTER (WHERE ABS(diff) >= 0.01) as avg_abs_diff
    FROM (
        SELECT d.sede, d.tipo_documento, d.numero_documento,
               ABS(d.total_neto_usd) - COALESCE((
                   SELECT SUM(ABS(vd.cantidad * COALESCE(vd.precio_neto, vd.precio_venta)))
                   FROM ventas_detalle vd
                   WHERE vd.fecha BETWEEN ? AND ?
                     AND vd.anulado = false
                     AND vd.sede = d.sede
                     AND vd.tipo_documento = d.tipo_documento
                     AND vd.numero_documento = d.numero_documento
               ), 0) as diff
        FROM ventas_documentos d
        WHERE d.fecha BETWEEN ? AND ?
          AND LOWER(TRIM(COALESCE(d.estado, ''))) = 'registrado'
          AND UPPER(d.tipo_documento) <> 'DEV'
    ) x
", [$desde, $hasta, $desde, $hasta]);

$pat = $docsDiff[0];
echo "--- Patrón a nivel documento (FAC) ---\n";
echo "Documentos con diff cabecera vs líneas: {$pat->con_diff} de {$pat->total}\n";
echo "Suma de diferencias: $".number_format((float) $pat->suma_diff, 2)."\n";
echo "Promedio |diff| en los que difieren: $".number_format((float) $pat->avg_abs_diff, 2)."\n\n";

// Top documentos con mayor diff
$topDocs = DB::select("
    SELECT sede, tipo_documento, numero_documento, vendedor,
           doc_neto, lineas_neto, diff
    FROM (
        SELECT d.sede, d.tipo_documento, d.numero_documento, d.vendedor,
               ABS(d.total_neto_usd) as doc_neto,
               COALESCE((
                   SELECT SUM(ABS(vd.cantidad * COALESCE(vd.precio_neto, vd.precio_venta)))
                   FROM ventas_detalle vd
                   WHERE vd.fecha BETWEEN ? AND ?
                     AND vd.anulado = false
                     AND vd.sede = d.sede
                     AND vd.tipo_documento = d.tipo_documento
                     AND vd.numero_documento = d.numero_documento
               ), 0) as lineas_neto,
               ABS(d.total_neto_usd) - COALESCE((
                   SELECT SUM(ABS(vd.cantidad * COALESCE(vd.precio_neto, vd.precio_venta)))
                   FROM ventas_detalle vd
                   WHERE vd.fecha BETWEEN ? AND ?
                     AND vd.anulado = false
                     AND vd.sede = d.sede
                     AND vd.tipo_documento = d.tipo_documento
                     AND vd.numero_documento = d.numero_documento
               ), 0) as diff
        FROM ventas_documentos d
        WHERE d.fecha BETWEEN ? AND ?
          AND LOWER(TRIM(COALESCE(d.estado, ''))) = 'registrado'
          AND UPPER(d.tipo_documento) <> 'DEV'
    ) x
    WHERE ABS(diff) >= 0.01
    ORDER BY ABS(diff) DESC
    LIMIT 15
", [$desde, $hasta, $desde, $hasta, $desde, $hasta]);

echo "--- Top 15 facturas con mayor diferencia ---\n";
foreach ($topDocs as $d) {
    echo "  {$d->sede} {$d->tipo_documento} {$d->numero_documento} | {$d->vendedor} | doc $".number_format($d->doc_neto, 2)." vs líneas $".number_format($d->lineas_neto, 2)." | Δ $".number_format($d->diff, 2)."\n";
}

// Caso especial VENDEDOR genérico
echo "\n--- Caso 'VENDEDOR' genérico (código 00) ---\n";
$gen = DB::table('ventas_documentos')
    ->whereBetween('fecha', [$desde, $hasta])
    ->whereRaw("UPPER(TRIM(vendedor)) = 'VENDEDOR'")
    ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'")
    ->selectRaw('COUNT(*) as docs')
    ->selectRaw("SUM(ABS(total_neto_usd)) as neto")
    ->first();
echo "Profit: {$gen->docs} docs, $".number_format((float) $gen->neto, 2)."\n";
$genLineas = DB::table('ventas_detalle')
    ->whereBetween('fecha', [$desde, $hasta])
    ->where('anulado', false)
    ->whereRaw("UPPER(TRIM(vendedor)) = 'VENDEDOR'")
    ->selectRaw("COUNT(DISTINCT CONCAT(sede,'|',tipo_documento,'|',numero_documento)) as docs")
    ->selectRaw("SUM(ABS(cantidad * COALESCE(precio_neto, precio_venta))) as neto")
    ->first();
echo "Líneas: {$genLineas->docs} docs, $".number_format((float) $genLineas->neto, 2)."\n";
