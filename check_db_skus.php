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
$dbProducts = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->select('codigo')->get();

$dbMapFirst = [];
$dbMapOther = [];

foreach ($dbProducts as $prod) {
    $parts = array_map('trim', explode('/', $prod->codigo));
    if (count($parts) > 0) {
        $dbMapFirst[$parts[0]] = true;
        for ($i = 1; $i < count($parts); $i++) {
            $dbMapOther[$parts[$i]] = true;
        }
    }
}

$missing = [];
$notFirst = [];
$ok = 0;

foreach ($jsonSkus as $sku => $val) {
    if (isset($dbMapFirst[$sku])) {
        $ok++;
    } elseif (isset($dbMapOther[$sku])) {
        $notFirst[] = $sku;
    } else {
        $missing[] = $sku;
    }
}

$md = "# Verificación de SKUs en Base de Datos\n\n";
$md .= "Revisamos los **" . count($jsonSkus) . " códigos únicos** del archivo `articulos_global` contra tu base de datos actual (Supabase).\n\n";
$md .= "- **Están en BD y de PRIMERO (Correcto):** {$ok}\n";
$md .= "- **Están en BD pero NO de primero:** " . count($notFirst) . "\n";
$md .= "- **Faltan por completo en la BD:** " . count($missing) . "\n\n";

if (count($notFirst) > 0) {
    $md .= "### SKUs que NO están de primero (Ejemplos)\n";
    $md .= "Estos están en tu base de datos, pero el código quedó relegado a posiciones secundarias (después de un `/`).\n";
    foreach (array_slice($notFirst, 0, 50) as $s) {
        $md .= "- `{$s}`\n";
    }
    if (count($notFirst) > 50) $md .= "- *(...y " . (count($notFirst) - 50) . " más)*\n";
    $md .= "\n";
}

if (count($missing) > 0) {
    $md .= "### SKUs que FALTAN por completo en la BD (Ejemplos)\n";
    foreach (array_slice($missing, 0, 50) as $s) {
        $md .= "- `{$s}`\n";
    }
    if (count($missing) > 50) $md .= "- *(...y " . (count($missing) - 50) . " más)*\n";
}

$artifactPath = 'C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/reporte_db_skus.md';
file_put_contents($artifactPath, $md);
echo "Reporte generado en: " . $artifactPath . "\n";
