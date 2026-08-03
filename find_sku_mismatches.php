<?php

$file1 = 'C:/Users/freyg/Downloads/laravel_app/articulos_global_20260715_1707-2.json';
$file2 = 'C:/Users/freyg/Downloads/laravel_app/productos_2026-07-26_215732.json';

function loadJson($path) {
    $content = file_get_contents($path);
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    return json_decode($content, true) ?: [];
}

$json1 = loadJson($file1);
$json2 = loadJson($file2);

// Map for file 2 by SKU and by Name
$skuMap2 = [];
$nameMap2 = [];
foreach ($json2 as $item) {
    $sku = trim(explode('/', $item['codigo'] ?? '')[0]);
    $name = trim(strtoupper($item['descripcion'] ?? ''));
    $name = ltrim($name, '/');
    if ($sku) {
        $skuMap2[$sku] = $item;
    }
    if ($name) {
        $nameMap2[$name] = $item;
    }
}

$foundByNameDifferentSku = [];

foreach ($json1 as $item1) {
    $sku1 = trim(explode('/', $item1['codigo'] ?? '')[0]);
    $name1 = trim(strtoupper($item1['descripcion'] ?? ''));
    $name1 = ltrim($name1, '/');
    
    // Si NO se encuentra por SKU
    if ($sku1 && !isset($skuMap2[$sku1])) {
        // Pero SI se encuentra por Nombre
        if ($name1 && isset($nameMap2[$name1])) {
            $item2 = $nameMap2[$name1];
            $sku2 = trim(explode('/', $item2['codigo'] ?? '')[0]);
            
            $foundByNameDifferentSku[] = [
                'nombre' => $name1,
                'sku_global' => $sku1,
                'sku_nuevo' => $sku2
            ];
        }
    }
}

$md = "# Productos encontrados por Nombre pero con SKU Diferente\n\n";
$md .= "Estos son los productos que no se encontraron cruzando los códigos (SKUs), pero que **SÍ ESTÁN** en el nuevo archivo porque su nombre es exactamente el mismo.\n\n";
$md .= "**Total de casos:** " . count($foundByNameDifferentSku) . "\n\n";

if (count($foundByNameDifferentSku) > 0) {
    $md .= "| Nombre del Producto | SKU en `articulos_global` | SKU en `productos_...` (Nuevo) |\n";
    $md .= "|---|---|---|\n";
    foreach ($foundByNameDifferentSku as $m) {
        $md .= "| {$m['nombre']} | `{$m['sku_global']}` | `{$m['sku_nuevo']}` |\n";
    }
}

$artifactPath = 'C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/reporte_skus_diferentes.md';
file_put_contents($artifactPath, $md);
echo "Reporte generado en: " . $artifactPath . "\n";
