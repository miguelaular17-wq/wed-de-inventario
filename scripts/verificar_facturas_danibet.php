<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$desde = '2026-08-16';
$hasta = '2026-08-31';
$claves = ['DANIBET DE ALMEIDA'];
$ph = implode(',', array_fill(0, count($claves), '?'));

$base = DB::table('ventas_detalle')
    ->whereBetween('fecha', [$desde, $hasta])
    ->where('anulado', false)
    ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves);

$lineas = (clone $base)->count();

$docs = (clone $base)
    ->selectRaw("tipo_documento, numero_documento, sede")
    ->groupBy('tipo_documento', 'numero_documento', 'sede')
    ->get();

$docsFac = $docs->filter(fn ($d) => strtoupper((string) $d->tipo_documento) !== 'DEV');
$docsDev = $docs->filter(fn ($d) => strtoupper((string) $d->tipo_documento) === 'DEV');

$unidades = (float) (clone $base)
    ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(cantidad) ELSE ABS(cantidad) END) as u")
    ->value('u');

$unidadesFac = (float) (clone $base)
    ->whereRaw("UPPER(tipo_documento) <> 'DEV'")
    ->selectRaw('SUM(ABS(cantidad)) as u')
    ->value('u');

$unidadesDev = (float) (clone $base)
    ->whereRaw("UPPER(tipo_documento) = 'DEV'")
    ->selectRaw('SUM(ABS(cantidad)) as u')
    ->value('u');

echo "=== DANIBET DE ALMEIDA | {$desde} a {$hasta} ===\n\n";
echo "Líneas detalle (ventas_detalle): {$lineas}\n";
echo "Documentos únicos (tipo+numero+sede): ".$docs->count()."\n";
echo "  - Facturas (FAC u otro no DEV): ".$docsFac->count()."\n";
echo "  - Devoluciones (DEV): ".$docsDev->count()."\n\n";

echo "Unidades netas (FAC - DEV): ".number_format($unidades, 2)."\n";
echo "Unidades en facturas: ".number_format($unidadesFac, 2)."\n";
echo "Unidades en devoluciones: ".number_format($unidadesDev, 2)."\n\n";

if (DB::getSchemaBuilder()->hasTable('ventas_documentos')) {
    $vd = DB::table('ventas_documentos')
        ->whereBetween('fecha', [$desde, $hasta])
        ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves)
        ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'")
        ->selectRaw("COUNT(*) as docs")
        ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -1 ELSE 1 END) as docs_neto")
        ->first();

    echo "ventas_documentos (registrado): {$vd->docs} docs | neto doc count: {$vd->docs_neto}\n";

    $vdMonto = DB::table('ventas_documentos')
        ->whereBetween('fecha', [$desde, $hasta])
        ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves)
        ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'")
        ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(total_neto_usd) ELSE ABS(total_neto_usd) END) as neto")
        ->value('neto');
    echo "ventas_documentos venta neta: $".number_format((float) $vdMonto, 2)."\n";
}

echo "\nProfit reporte (referencia usuario): 103 unidades | $2,763.99\n";
