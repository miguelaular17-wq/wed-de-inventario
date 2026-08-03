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

// Get existing names
$dbProductsAll = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->select('nombre')->get();
$dbNames = [];
foreach ($dbProductsAll as $prod) {
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

$mismatches = [];

foreach ($missing as $item) {
    $sku = $item['codigo'] ?? '';
    if (!$sku) continue;
    
    // We search the DB for a product that contains this SKU
    $dbProd = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')
        ->where('codigo', 'LIKE', '%' . $sku . '%')
        ->first();
        
    if ($dbProd) {
        $mismatches[] = [
            'sku' => $sku,
            'nombre_json' => $item['descripcion'],
            'nombre_bd' => $dbProd->nombre
        ];
    }
}

$md = "# Comparación de los 15 Productos (Diferencias de Nombre)\n\n";
$md .= "Estos son los productos que intentamos insertar pero que la base de datos rechazó porque el código ya existía. Aquí puedes ver cómo se llamaban en tu archivo global vs cómo están registrados actualmente en tu Base de Datos.\n\n";

$md .= "| Código (SKU) | Nombre Original (articulos_global) | Nombre Actual (Base de Datos) |\n";
$md .= "|---|---|---|\n";
foreach ($mismatches as $m) {
    $md .= "| `{$m['sku']}` | {$m['nombre_json']} | {$m['nombre_bd']} |\n";
}

$artifactPath = 'C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/reporte_15_nombres.md';
file_put_contents($artifactPath, $md);
echo "Reporte generado en: " . $artifactPath . "\n";
