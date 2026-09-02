<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$desde = '2026-08-16';
$hasta = '2026-08-31';

$stats = DB::select("
    SELECT
        COUNT(*) FILTER (WHERE ABS(doc_neto - bruto) < 0.02) as doc_eq_bruto,
        COUNT(*) FILTER (WHERE ABS(doc_neto - neto) < 0.02) as doc_eq_neto,
        COUNT(*) FILTER (WHERE ABS(doc_neto - bruto) >= 0.02 AND ABS(doc_neto - neto) >= 0.02) as doc_otro,
        COUNT(*) as total
    FROM (
        SELECT ABS(d.total_neto_usd) as doc_neto,
               COALESCE((
                   SELECT SUM(ABS(vd.cantidad * vd.precio_venta))
                   FROM ventas_detalle vd
                   WHERE vd.sede = d.sede AND vd.tipo_documento = d.tipo_documento
                     AND vd.numero_documento = d.numero_documento AND vd.anulado = false
               ), 0) as bruto,
               COALESCE((
                   SELECT SUM(ABS(vd.cantidad * COALESCE(vd.precio_neto, vd.precio_venta)))
                   FROM ventas_detalle vd
                   WHERE vd.sede = d.sede AND vd.tipo_documento = d.tipo_documento
                     AND vd.numero_documento = d.numero_documento AND vd.anulado = false
               ), 0) as neto
        FROM ventas_documentos d
        WHERE d.fecha BETWEEN ? AND ?
          AND LOWER(TRIM(COALESCE(d.estado, ''))) = 'registrado'
          AND UPPER(d.tipo_documento) <> 'DEV'
    ) x
", [$desde, $hasta]);

$s = $stats[0];
echo "=== ¿Qué es total_neto_usd en cabecera? ({$desde} a {$hasta}) ===\n\n";
echo "Documentos FAC: {$s->total}\n";
echo "  doc ≈ suma bruto (precio_venta): {$s->doc_eq_bruto}\n";
echo "  doc ≈ suma neto (precio_neto):   {$s->doc_eq_neto}\n";
echo "  doc distinto de ambos:           {$s->doc_otro}\n\n";

// Danibet specifically
$danibet = DB::selectOne("
    SELECT
        SUM(ABS(d.total_neto_usd)) as doc_total,
        SUM(COALESCE(b.bruto,0)) as bruto,
        SUM(COALESCE(b.neto,0)) as neto
    FROM ventas_documentos d
    LEFT JOIN LATERAL (
        SELECT SUM(ABS(vd.cantidad * vd.precio_venta)) as bruto,
               SUM(ABS(vd.cantidad * COALESCE(vd.precio_neto, vd.precio_venta))) as neto
        FROM ventas_detalle vd
        WHERE vd.sede = d.sede AND vd.tipo_documento = d.tipo_documento
          AND vd.numero_documento = d.numero_documento AND vd.anulado = false
    ) b ON true
    WHERE d.fecha BETWEEN ? AND ?
      AND UPPER(TRIM(d.vendedor)) = 'DANIBET DE ALMEIDA'
      AND LOWER(TRIM(COALESCE(d.estado, ''))) = 'registrado'
", [$desde, $hasta]);

echo "DANIBET DE ALMEIDA:\n";
echo "  Profit (ventas_documentos): $".number_format($danibet->doc_total, 2)."\n";
echo "  Suma bruto líneas:          $".number_format($danibet->bruto, 2)."\n";
echo "  Suma neto líneas:           $".number_format($danibet->neto, 2)."\n\n";

echo "PATRÓN ENCONTRADO:\n";
echo "  1. Profit = SUM(ventas_documentos.total_neto_usd) por vendedor\n";
echo "  2. En muchas facturas, total_neto_usd ≈ suma BRUTA (precio_venta), no precio_neto\n";
echo "  3. precio_neto en líneas sí tiene descuento/IVA (~50-60% del bruto en varios casos)\n";
echo "  4. 300 de 9,612 facturas (3.1%) tienen diff cabecera vs líneas; 55 de 87 vendedores afectados\n";
echo "  5. Casos extremos: doc = 2× neto líneas (precio_neto = 50% precio_venta)\n";
