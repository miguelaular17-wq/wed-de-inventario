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

$jsonSkus = [];
foreach ($json as $item) {
    $sku = trim(explode('/', $item['codigo'] ?? '')[0]);
    if ($sku) {
        $jsonSkus[$sku] = true;
    }
}

echo "Consultando DB...\n";
// Nos traemos todos para poder iterar, o podriamos hacer querys especificas. Traer todos es rapido
$dbProducts = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->select('id', 'codigo')->get();

$successCount = 0;
$errorCount = 0;

foreach ($dbProducts as $prod) {
    $parts = array_map('trim', explode('/', $prod->codigo));
    if (count($parts) > 1) {
        $firstPart = $parts[0];
        
        // Si el primero NO esta en JSON, pero alguno de los demas SI esta en JSON
        if (!isset($jsonSkus[$firstPart])) {
            $foundIndex = -1;
            for ($i = 1; $i < count($parts); $i++) {
                if (isset($jsonSkus[$parts[$i]])) {
                    $foundIndex = $i;
                    break;
                }
            }
            
            if ($foundIndex !== -1) {
                $targetSku = $parts[$foundIndex];
                
                // Reordenar: Target de primero, luego todos los demas
                unset($parts[$foundIndex]);
                array_unshift($parts, $targetSku);
                $newCode = implode(' / ', $parts);
                
                try {
                    \Illuminate\Support\Facades\DB::connection('supabase')
                        ->table('inventario_v2.productos')
                        ->where('id', $prod->id)
                        ->update(['codigo' => $newCode]);
                    $successCount++;
                } catch (\Exception $e) {
                    echo "Error actualizando ID {$prod->id} ({$prod->codigo} -> {$newCode}): " . $e->getMessage() . "\n";
                    $errorCount++;
                }
            }
        }
    }
}

echo "\nCompletado.\n";
echo "SKUs reordenados exitosamente: {$successCount}\n";
echo "Errores: {$errorCount}\n";
