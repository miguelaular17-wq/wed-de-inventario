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

$fileJson = 'C:/Users/freyg/Downloads/laravel_app/articulos_global_20260715_1707-2.json';
$content = file_get_contents($fileJson);
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    $content = substr($content, 3);
}
$json = json_decode($content, true) ?: [];

// Get existing names to find the missing 19
$dbProducts = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->select('nombre')->get();
$dbNames = [];
foreach ($dbProducts as $prod) {
    $name = trim(strtoupper($prod->nombre));
    $name = ltrim($name, '/');
    if ($name) $dbNames[$name] = true;
}

$missing = [];
$jsonNamesMap = [];

foreach ($json as $item) {
    $name = trim(strtoupper($item['descripcion'] ?? ''));
    $name = ltrim($name, '/');
    if ($name && !isset($jsonNamesMap[$name])) {
        $jsonNamesMap[$name] = $item;
        
        if (!isset($dbNames[$name])) {
            $missing[] = $item;
        }
    }
}

echo "Se encontraron " . count($missing) . " productos faltantes. Insertando...\n";

$inserted = 0;
$errors = 0;

foreach ($missing as $item) {
    $parts = array_map('trim', explode(',', $item['categories'] ?? ''));
    $cat = $parts[0] ?? '';
    $sub = isset($parts[1]) ? trim($parts[1]) : '';

    try {
        \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->insert([
            'codigo' => $item['codigo'] ?? null,
            'nombre' => $item['descripcion'] ?? null,
            'precio_unidad' => (float)($item['precio1'] ?? 0),
            'precio_mayor' => (float)($item['precio3'] ?? 0),
            'categoria' => $cat,
            'subcategoria' => $sub,
            'activo' => true
        ]);
        $inserted++;
    } catch (\Exception $e) {
        echo "Error insertando " . ($item['descripcion'] ?? '') . ": " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "Proceso finalizado. Insertados: $inserted, Errores: $errors\n";
