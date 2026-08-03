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
        $notFound[] = $jsonData;
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
            'db_id' => $dbProd->id,
            'sku' => $sku,
            'json' => $jsonCategoriesStr
        ];
    }
}

echo "Se encontraron " . count($notFound) . " productos para insertar.\n";
echo "Se encontraron " . count($mismatches) . " productos para actualizar categorías.\n";

$insertedCount = 0;
foreach ($notFound as $data) {
    $parts = explode(',', $data['categories'] ?? '');
    $cat = trim($parts[0] ?? '');
    $sub = isset($parts[1]) ? trim($parts[1]) : '';
    
    \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->insert([
        'codigo' => $data['codigo'],
        'nombre' => $data['descripcion'] ?? '',
        'categoria' => $cat,
        'subcategoria' => $sub,
        'precio_unidad' => (float)($data['precio1'] ?? 0),
        'precio_mayor' => (float)($data['precio3'] ?? 0),
        'activo' => true,
    ]);
    $insertedCount++;
}
echo "✅ Se insertaron exitosamente {$insertedCount} productos nuevos.\n";

$updatedCount = 0;
foreach ($mismatches as $m) {
    $parts = explode(',', $m['json']);
    $cat = trim($parts[0] ?? '');
    $sub = isset($parts[1]) ? trim($parts[1]) : '';

    \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')
        ->where('id', $m['db_id'])
        ->update([
            'categoria' => $cat,
            'subcategoria' => $sub
        ]);
    $updatedCount++;
}
echo "✅ Se actualizaron exitosamente las categorías de {$updatedCount} productos.\n";
