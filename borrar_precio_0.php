<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.connections.supabase' => [
    'driver' => 'pgsql',
    'host' => 'aws-1-us-east-2.pooler.supabase.com',
    'port' => '6543',
    'database' => 'postgres',
    'username' => 'postgres.hbhqbmzixgcvxkilwsau',
    'password' => 'W@mqkdhf#snW@68',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]]);

// Delete the specific ones with price 0
$deleted = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')
    ->whereIn('codigo', [
        '197575949244 / 197575027492',
        '137287 / 2024080137270'
    ])
    ->where('precio_unidad', 0)
    ->delete();

echo "Borrados: " . $deleted . "\n";
