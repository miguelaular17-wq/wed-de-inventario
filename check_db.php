<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = DB::connection('pgsql')->select("SELECT id, comprobante_url, comprobantes FROM flujo_cajas WHERE comprobante_url IS NOT NULL OR comprobantes IS NOT NULL ORDER BY id DESC LIMIT 5");
echo json_encode($rows, JSON_PRETTY_PRINT);
