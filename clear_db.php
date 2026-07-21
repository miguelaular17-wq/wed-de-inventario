<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\DB::table('gastos_fijos')->truncate();
\DB::table('gasto_fijo_pagos')->update(['gasto_fijo_id' => null]);

echo "DB Cleared\n";
