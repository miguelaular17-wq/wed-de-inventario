<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$desde = '2026-08-16';
$hasta = '2026-08-31';
$claves = ['DANIBET DE ALMEIDA'];
$ph = implode(',', array_fill(0, count($claves), '?'));

$brutoLineas = (float) DB::table('ventas_detalle')
    ->whereBetween('fecha', [$desde, $hasta])
    ->where('anulado', false)
    ->whereRaw('UPPER(TRIM(vendedor)) IN ('.$ph.')', $claves)
    ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -1 ELSE 1 END * ABS(cantidad * precio_venta)) as t")
    ->value('t');

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

$liq = DB::table('nomina_liquidaciones_comision as l')
    ->join('nomina_empleados as e', 'e.id', '=', 'l.empleado_id')
    ->join('clientes as c', 'c.id', '=', 'e.cliente_id')
    ->join('nomina_periodos as p', 'p.id', '=', 'l.periodo_id')
    ->where('c.nombre', 'like', '%DANIB%')
    ->whereDate('p.fecha_inicio', '2026-08-16')
    ->select('l.base_total', 'l.base_telefonia', 'l.base_otros')
    ->first();

echo "=== Por qué \$2,967.09 en comisiones ===\n\n";
echo "Comisiones (columna Ventas / base_total):     $".number_format((float) $liq->base_total, 2)."\n";
echo "  = suma líneas × precio_venta (BRUTO):       $".number_format($brutoLineas, 2)."\n\n";
echo "Profit / ventas_documentos (NETO documento): $".number_format($netoDocs, 2)."\n";
echo "  = suma líneas × precio_neto:               $".number_format($netoLineas, 2)."\n\n";
echo "Diferencia (descuentos no restados en UI):   $".number_format($brutoLineas - $netoDocs, 2)."\n\n";
echo "Desglose comisión:\n";
echo "  Base tel:   $".number_format((float) $liq->base_telefonia, 2)."\n";
echo "  Base otros: $".number_format((float) $liq->base_otros, 2)."\n";
echo "  Suma:       $".number_format((float) $liq->base_telefonia + (float) $liq->base_otros, 2)." (= base_total)\n";
