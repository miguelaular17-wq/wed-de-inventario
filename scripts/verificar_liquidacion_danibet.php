<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$row = DB::table('nomina_liquidaciones_comision as l')
    ->join('nomina_empleados as e', 'e.id', '=', 'l.empleado_id')
    ->join('clientes as c', 'c.id', '=', 'e.cliente_id')
    ->join('nomina_periodos as p', 'p.id', '=', 'l.periodo_id')
    ->where('c.nombre', 'like', '%DANIB%')
    ->whereDate('p.fecha_inicio', '2026-08-16')
    ->select('l.*', 'p.etiqueta', 'c.nombre')
    ->first();

if (! $row) {
    echo "Sin liquidación para el período.\n";
    exit(0);
}

echo "Empleado: {$row->nombre}\n";
echo "Período: {$row->etiqueta}\n";
echo "base_total: {$row->base_total}\n";
echo "base_telefonia: {$row->base_telefonia}\n";
echo "base_otros: {$row->base_otros}\n";
echo "comision_total: {$row->comision_total}\n";
echo "lineas (snapshot): ".json_encode(json_decode($row->snapshot ?? '{}', true), JSON_PRETTY_PRINT)."\n";
