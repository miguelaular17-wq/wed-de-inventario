<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    App\Models\CobranzaResumen::create([
        'sede_nombre' => 'TEST',
        'total_clientes' => 0,
        'total_saldo' => 0,
        'critico_clientes' => 0,
        'critico_saldo' => 0,
        'moroso_clientes' => 0,
        'moroso_saldo' => 0,
        'reciente_clientes' => 0,
        'reciente_saldo' => 0,
        'apartado_clientes' => 0,
        'apartado_saldo' => 0
    ]);
    echo "OK\n";
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
