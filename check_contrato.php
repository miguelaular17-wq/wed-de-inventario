<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$contracts = \App\Models\Contrato::select('id', 'numero_contrato', 'cliente')->get();
foreach ($contracts as $c) {
    echo $c->id . " | " . $c->numero_contrato . " | " . $c->cliente . PHP_EOL;
}
