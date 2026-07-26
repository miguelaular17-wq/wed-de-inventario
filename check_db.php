<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Configurar conexión a la BD de producción de Supabase
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

$content = file_get_contents(__DIR__ . '/articulos_global_20260715_1707-2.json');
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    $content = substr($content, 3);
}
$jsonProducts = json_decode($content, true);

$skus = [];
$jsonMap = [];
foreach ($jsonProducts as $item) {
    // Process codigo to match DB correctly
    $codigo = trim(explode('/', $item['codigo'] ?? '')[0]);
    if ($codigo) {
        $skus[] = $codigo;
        $jsonMap[$codigo] = $item;
    }
}
$skus = array_unique($skus);

echo "Cargando todos los productos de la base de datos de PRODUCCIÓN (Supabase)...\n";
$dbResults = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')
    ->get();

$dbProducts = [];
foreach ($dbResults as $v) {
    // Si la DB tiene SKUs múltiples (ej: "SKU1 / SKU2"), indexamos por TODOS los SKUs
    $parts = explode('/', $v->codigo ?? '');
    foreach ($parts as $part) {
        $skuPart = trim($part);
        if ($skuPart) {
            $dbProducts[$skuPart] = $v;
        }
    }
}

$mismatches = [];
$notFound = [];

foreach ($jsonMap as $sku => $jsonData) {
    if (!isset($dbProducts[$sku])) {
        $notFound[] = [
            'sku' => $sku,
            'nombre' => $jsonData['descripcion'] ?? ''
        ];
        continue;
    }

    $dbProd = $dbProducts[$sku];
    $cat = trim($dbProd->categoria ?? '');
    $sub = trim($dbProd->subcategoria ?? '');
    
    $dbCategoriesStr = $cat;
    if ($sub && $sub !== $cat) {
        $dbCategoriesStr = $cat . ',' . $sub;
    }
    $dbCategoriesStr = strtoupper($dbCategoriesStr);
    
    $jsonCategoriesStr = strtoupper(trim($jsonData['categories'] ?? ''));

    $dbCategoriesStr = str_replace(', ', ',', $dbCategoriesStr);
    $jsonCategoriesStr = str_replace(', ', ',', $jsonCategoriesStr);

    if ($dbCategoriesStr !== $jsonCategoriesStr) {
        $mismatches[] = [
            'sku' => $sku,
            'nombre' => $dbProd->nombre,
            'db' => $dbCategoriesStr,
            'json' => $jsonCategoriesStr
        ];
    }
}

$md = "# Análisis de JSON vs Base de Datos (Producción)\n\n";
$md .= "**Archivo analizado:** `articulos_global_20260715_1707-2.json`\n";
$md .= "**Productos en el JSON:** " . count($jsonMap) . "\n";
$md .= "**Productos del JSON que NO existen en la DB:** " . count($notFound) . "\n";
$md .= "**Productos con categorías diferentes:** " . count($mismatches) . "\n\n";

if (count($notFound) > 0) {
    $md .= "## ❌ Productos en JSON pero NO en la Web\n\n";
    $md .= "| Código (SKU) | Nombre del Producto |\n";
    $md .= "|---|---|\n";
    foreach ($notFound as $m) {
        $md .= "| `{$m['sku']}` | {$m['nombre']} |\n";
    }
    $md .= "\n";
}

if (count($mismatches) > 0) {
    $md .= "## ⚠️ Diferencias en Categorías\n\n";
    $md .= "| Código (SKU) | Nombre del Producto | Categoría en DB Web | Categoría en JSON |\n";
    $md .= "|---|---|---|---|\n";
    foreach ($mismatches as $m) {
        $md .= "| `{$m['sku']}` | {$m['nombre']} | {$m['db']} | {$m['json']} |\n";
    }
}

$artifactPath = 'C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/reporte_json_vs_web.md';
file_put_contents($artifactPath, $md);
echo "Reporte generado en reporte_json_vs_web.md\n";







