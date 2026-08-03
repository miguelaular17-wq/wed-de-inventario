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

echo "Consultando nombres en DB...\n";
$dbProducts = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->select('nombre')->get();

$dbNames = [];
foreach ($dbProducts as $prod) {
    $name = trim(strtoupper($prod->nombre));
    $name = ltrim($name, '/');
    if ($name) {
        $dbNames[$name] = true;
    }
}

$missing = [];
$found = 0;
$jsonNamesMap = [];

foreach ($json as $item) {
    $name = trim(strtoupper($item['descripcion'] ?? ''));
    $name = ltrim($name, '/');
    if ($name && !isset($jsonNamesMap[$name])) {
        $jsonNamesMap[$name] = $item;
        
        if (isset($dbNames[$name])) {
            $found++;
        } else {
            $missing[] = [
                'nombre' => $name,
                'sku' => $item['codigo']
            ];
        }
    }
}

$md = "# Comparación de Nombres: `articulos_global` vs `Base de Datos`\n\n";
$md .= "Revisamos los **" . count($jsonNamesMap) . " nombres únicos** del archivo `articulos_global` contra los nombres registrados en tu base de datos (Supabase).\n\n";
$md .= "- **Nombres encontrados en la BD:** {$found}\n";
$md .= "- **Nombres FALTANTES en la BD:** " . count($missing) . "\n\n";

if (count($missing) > 0) {
    $md .= "### Productos que NO están en la base de datos (por nombre)\n";
    $md .= "| Nombre | SKU |\n";
    $md .= "|---|---|\n";
    foreach (array_slice($missing, 0, 50) as $m) {
        $md .= "| `{$m['nombre']}` | `{$m['sku']}` |\n";
    }
    if (count($missing) > 50) {
        $md .= "| ... | ... |\n";
        $md .= "*(Y " . (count($missing) - 50) . " productos más)*\n";
    }
} else {
    $md .= "¡Excelente! TODOS los nombres del archivo `articulos_global` existen en tu base de datos.\n";
}

$artifactPath = 'C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/reporte_db_nombres.md';
file_put_contents($artifactPath, $md);
echo "Reporte generado en: " . $artifactPath . "\n";
