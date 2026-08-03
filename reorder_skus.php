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

$jsonSkus = [];
foreach ($jsonProducts as $item) {
    $codigo = trim(explode('/', $item['codigo'] ?? '')[0]);
    if ($codigo) {
        $jsonSkus[] = $codigo;
    }
}
$jsonSkus = array_unique($jsonSkus);

$dbResults = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->get();

$updates = [];

foreach ($dbResults as $v) {
    $parts = explode('/', $v->codigo ?? '');
    
    // Solo revisar si tiene múltiples SKUs
    if (count($parts) > 1) {
        $foundPrimary = null;
        
        // Buscar el SKU del JSON en las partes (ignorando la primera si ya estuviera ahí)
        for ($i = 1; $i < count($parts); $i++) {
            $skuPart = trim($parts[$i]);
            if ($skuPart && in_array($skuPart, $jsonSkus)) {
                $foundPrimary = $skuPart;
                break; // Tomar el primero que coincida
            }
        }
        
        if ($foundPrimary) {
            // Eliminar el $foundPrimary del array de partes
            $newParts = [];
            foreach ($parts as $p) {
                if (trim($p) !== $foundPrimary) {
                    $newParts[] = trim($p);
                }
            }
            // Eliminar vacios
            $newParts = array_filter($newParts);
            
            // Colocar $foundPrimary al inicio
            array_unshift($newParts, $foundPrimary);
            
            // Reconstruir string
            $newCodigoString = implode(' / ', $newParts);
            
            $updates[] = [
                'id' => $v->id,
                'old_codigo' => $v->codigo,
                'new_codigo' => $newCodigoString
            ];
        }
    }
}

echo "Se encontraron " . count($updates) . " productos para reordenar.\n";

$updatedCount = 0;
foreach ($updates as $u) {
    try {
        \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')
            ->where('id', $u['id'])
            ->update([
                'codigo' => $u['new_codigo']
            ]);
        $updatedCount++;
    } catch (\Exception $e) {
        echo "Omitido id {$u['id']} por conflicto de llave única: {$u['new_codigo']}\n";
    }
}

echo "✅ Se reordenaron los SKUs de {$updatedCount} productos en la base de datos web.\n";
