<?php

$file1 = 'C:/Users/freyg/Downloads/laravel_app/articulos_global_20260715_1707-2.json';
$file2 = 'C:/Users/freyg/Downloads/laravel_app/productos_2026-07-26_221716.json';

function loadJson($path) {
    $content = file_get_contents($path);
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    return json_decode($content, true) ?: [];
}

$jsonGlobal = loadJson($file1);
$jsonWeb = loadJson($file2);

$globalSkus = [];
foreach ($jsonGlobal as $item) {
    $sku = trim(explode('/', $item['codigo'] ?? '')[0]);
    if ($sku) {
        $globalSkus[$sku] = true;
    }
}

$missingInGlobal = 0;
$foundInGlobal = 0;

foreach ($jsonWeb as $item) {
    $sku = trim(explode('/', $item['codigo'] ?? '')[0]);
    if ($sku) {
        if (isset($globalSkus[$sku])) {
            $foundInGlobal++;
        } else {
            $missingInGlobal++;
        }
    }
}

echo "Total en Web (productos_...json): " . count($jsonWeb) . "\n";
echo "Total en Global (articulos_...json): " . count($jsonGlobal) . "\n";
echo "Productos de la Web que SI estan en Global: $foundInGlobal\n";
echo "Productos de la Web que NO estan en Global (Extras de la web): $missingInGlobal\n";
