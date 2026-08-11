<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$allComprobantes = ["https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/object/public/comprobantes/comprobante_96255966_20260807_201743_6a7675a7bb47b.png"];
$html = \Illuminate\Support\Facades\Blade::render(
    "<button type=\"button\" onclick='abrirGaleria(@json(array_values(\$allComprobantes)))'>📎 {{ count(\$allComprobantes) }}</button>",
    ['allComprobantes' => $allComprobantes]
);
echo $html;
