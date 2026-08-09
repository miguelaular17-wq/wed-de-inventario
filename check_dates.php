<?php
require 'vendor/autoload.php'; 
$app = require_once 'bootstrap/app.php'; 
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); 
$res = Illuminate\Support\Facades\DB::connection('pgsql')->select("
    SELECT p.codigo, vh.sede, vh.ultima_venta, vh.ultima_compra 
    FROM inventario_v2.ventas_historicas vh
    JOIN inventario_v2.productos p ON p.id = vh.producto_id
    WHERE p.codigo IN ('8697420537753', '75599055000507')
");
print_r($res);
