<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$mov = \App\Models\FlujoCaja::whereNotNull('comprobantes')->latest('id')->first();
echo gettype($mov->comprobantes) . "\n";
print_r($mov->comprobantes);

$allComprobantes = array_filter(array_merge(
    $mov->comprobantes ?? [],
    ($mov->comprobante_url && !in_array($mov->comprobante_url, $mov->comprobantes ?? [])) ? [$mov->comprobante_url] : []
));
echo gettype($allComprobantes) . "\n";
print_r($allComprobantes);

$html = \Illuminate\Support\Facades\Blade::render(
    "<button type=\"button\" onclick='abrirGaleria(@json(array_values(\$allComprobantes)))'>📎 {{ count(\$allComprobantes) }}</button>",
    ['allComprobantes' => $allComprobantes]
);
echo $html;
