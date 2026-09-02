<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$empId = 14;
$desde = '2026-08-16';
$hasta = '2026-08-31';

echo "=== EGRESOS 058 ===\n";
$egresos = DB::table('flujo_cajas')
    ->where('nomina_empleado_id', $empId)
    ->where('tipo_gasto', '058 - SERVICIO TECNICO (GARANTIAS)')
    ->whereBetween('fecha', [$desde, $hasta])
    ->orderBy('fecha')
    ->get();

$sumEgr = 0;
foreach ($egresos as $e) {
    $usd = (float) ($e->monto_usd ?: 0);
    if (abs($usd) < 0.001 && $e->tasa_cambio > 0) {
        $usd = abs((float) $e->monto_bs) / (float) $e->tasa_cambio;
    }
    $usd = abs($usd);
    $sumEgr += $usd;
    echo "{$e->fecha} | $" . number_format($usd, 2) . " | " . substr($e->descripcion ?? '', 0, 50) . "\n";
}
echo "TOTAL EGRESOS: $" . number_format($sumEgr, 2) . "\n\n";

echo "=== LÍNEAS ST (ventas_detalle) vendedor JORGE DIAZ ===\n";
$lineas = DB::table('ventas_detalle as vd')
    ->leftJoin('productos as p', 'p.id', '=', 'vd.producto_id')
    ->whereBetween('vd.fecha', [$desde, $hasta])
    ->whereRaw('UPPER(TRIM(vd.vendedor)) = ?', ['JORGE DIAZ'])
    ->where(function ($q) {
        $q->whereRaw("UPPER(COALESCE(vd.nombre_producto,'')) LIKE '%SERVICIO TECNICO%'")
            ->orWhereRaw("UPPER(COALESCE(vd.codigo_producto,'')) LIKE '%SERVICIO TECNICO%'")
            ->orWhereRaw("UPPER(COALESCE(p.categoria,'')) LIKE '%SERVICIO TECNICO%'")
            ->orWhereRaw("UPPER(COALESCE(p.subcategoria,'')) LIKE '%SERVICIO TECNICO%'");
    })
    ->select('vd.fecha', 'vd.numero_documento', 'vd.nombre_producto', 'vd.cantidad', 'vd.precio_venta', 'vd.precio_neto', 'vd.tipo_documento')
    ->orderBy('vd.fecha')
    ->get();

$sumSt = 0;
foreach ($lineas as $l) {
    $signo = strtoupper($l->tipo_documento ?? 'FAC') === 'DEV' ? -1 : 1;
    $neto = $l->precio_neto !== null
        ? $signo * abs((float) $l->cantidad * (float) $l->precio_neto)
        : $signo * abs((float) $l->cantidad * (float) $l->precio_venta) * 0.95; // approx if no neto
    $sumSt += $neto;
    echo "{$l->fecha} FAC {$l->numero_documento} | {$l->nombre_producto} | $" . number_format($neto, 2) . "\n";
}
echo "TOTAL ST (aprox): $" . number_format($sumSt, 2) . "\n\n";

$row = DB::table('flujo_cajas')
    ->where('nomina_empleado_id', $empId)
    ->where('tipo_gasto', '058 - SERVICIO TECNICO (GARANTIAS)')
    ->whereBetween('fecha', [$desde, $hasta])
    ->selectRaw('COALESCE(SUM(CASE WHEN monto_usd IS NOT NULL AND monto_usd <> 0 THEN ABS(monto_usd) WHEN tasa_cambio IS NOT NULL AND tasa_cambio > 0 THEN ABS(monto_bs) / tasa_cambio ELSE 0 END), 0) AS total')
    ->first();
echo "Egresos (fórmula comisiones): $" . number_format($row->total, 2) . "\n";
echo "Base neta ST: $" . number_format(max(0, $sumSt - $row->total), 2) . "\n";
echo "Comisión ST 50%: $" . number_format(max(0, $sumSt - $row->total) * 0.5, 2) . "\n";
