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

$foundAsSecondary = [];

foreach ($dbResults as $v) {
    $parts = explode('/', $v->codigo ?? '');
    // Solo revisar si tiene múltiples SKUs
    if (count($parts) > 1) {
        // Ignorar el primero (index 0)
        for ($i = 1; $i < count($parts); $i++) {
            $skuPart = trim($parts[$i]);
            if ($skuPart && in_array($skuPart, $jsonSkus)) {
                $foundAsSecondary[] = [
                    'json_sku' => $skuPart,
                    'db_codigo_completo' => $v->codigo,
                    'nombre' => $v->nombre
                ];
            }
        }
    }
}

$md = "# Productos con SKU Secundario en la Web\n\n";
$md .= "Estos son los productos donde el SKU del JSON fue encontrado en tu base de datos web, pero **NO** estaba de primero (estaba después de una barra `/`).\n\n";
$md .= "**Total encontrados:** " . count($foundAsSecondary) . "\n\n";

if (count($foundAsSecondary) > 0) {
    $md .= "| SKU del JSON | Código Completo en DB | Nombre del Producto |\n";
    $md .= "|---|---|---|\n";
    foreach ($foundAsSecondary as $m) {
        $md .= "| `{$m['json_sku']}` | `{$m['db_codigo_completo']}` | {$m['nombre']} |\n";
    }
}

file_put_contents('C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/reporte_secundarios.md', $md);
echo "Encontrados " . count($foundAsSecondary) . " productos. Reporte guardado en reporte_secundarios.md\n";
