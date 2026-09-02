<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$desde = '2026-08-16';
$hasta = '2026-08-31';
$claves = ['DANIBET DE ALMEIDA'];
$ph = implode(',', array_fill(0, count($claves), '?'));

$netoLineas = (float) DB::table('ventas_detalle')
    ->whereBetween('fecha', [$desde, $hasta])
    ->where('anulado', false)
    ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves)
    ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -1 ELSE 1 END * ABS(cantidad * COALESCE(precio_neto, precio_venta))) as t")
    ->value('t');

$netoDocs = (float) DB::table('ventas_documentos')
    ->whereBetween('fecha', [$desde, $hasta])
    ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves)
    ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'")
    ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(total_neto_usd) ELSE ABS(total_neto_usd) END) as t")
    ->value('t');

echo "Neto líneas (comisiones): $".number_format($netoLineas, 2)."\n";
echo "Neto documentos (Profit):  $".number_format($netoDocs, 2)."\n";
echo "Diferencia:                $".number_format($netoDocs - $netoLineas, 2)."\n\n";

// Por documento: comparar suma líneas vs total_neto_usd
$docs = DB::table('ventas_documentos as d')
    ->whereBetween('d.fecha', [$desde, $hasta])
    ->whereRaw('UPPER(TRIM(d.vendedor)) IN ('.$ph.')', $claves)
    ->whereRaw("LOWER(TRIM(COALESCE(d.estado, ''))) = 'registrado'")
    ->select('d.sede', 'd.tipo_documento', 'd.numero_documento', 'd.total_neto_usd')
    ->get();

$diffs = [];
foreach ($docs as $doc) {
    $netoLineaDoc = (float) DB::table('ventas_detalle')
        ->whereBetween('fecha', [$desde, $hasta])
        ->where('anulado', false)
        ->where('sede', $doc->sede)
        ->where('tipo_documento', $doc->tipo_documento)
        ->where('numero_documento', $doc->numero_documento)
        ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -1 ELSE 1 END * ABS(cantidad * COALESCE(precio_neto, precio_venta))) as t")
        ->value('t');
    $docNeto = (float) $doc->total_neto_usd;
    $diff = round($docNeto - $netoLineaDoc, 2);
    if (abs($diff) >= 0.01) {
        $diffs[] = [
            'doc' => "{$doc->tipo_documento} {$doc->numero_documento} ({$doc->sede})",
            'doc_neto' => $docNeto,
            'lineas_neto' => $netoLineaDoc,
            'diff' => $diff,
        ];
    }
}

usort($diffs, fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));
echo "Documentos con diferencia líneas vs cabecera: ".count($diffs)." de ".$docs->count()."\n";
echo "Top 10 diferencias:\n";
foreach (array_slice($diffs, 0, 10) as $d) {
    echo "  {$d['doc']}: doc $".number_format($d['doc_neto'], 2)." | líneas $".number_format($d['lineas_neto'], 2)." | Δ $".number_format($d['diff'], 2)."\n";
}
$sumDiff = array_sum(array_column($diffs, 'diff'));
echo "\nSuma de diferencias: $".number_format($sumDiff, 2)."\n";

// Líneas sin precio_neto
$sinNeto = DB::table('ventas_detalle')
    ->whereBetween('fecha', [$desde, $hasta])
    ->where('anulado', false)
    ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves)
    ->whereNull('precio_neto')
    ->count();
echo "\nLíneas sin precio_neto: {$sinNeto}\n";
