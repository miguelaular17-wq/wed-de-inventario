<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'Driver: '.(extension_loaded('pdo_pgsql') ? 'pdo_pgsql OK' : 'MISSING').PHP_EOL;
echo 'Productos: '.App\Models\V2\Producto::count().PHP_EOL;
