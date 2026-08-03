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

$json1 = loadJson($file1);
$json2 = loadJson($file2);

$map1 = [];
foreach ($json1 as $item) {
    $sku = trim(explode('/', $item['codigo'] ?? '')[0]);
    if ($sku) {
        $map1[$sku] = $item;
    }
}

$map2 = [];
foreach ($json2 as $item) {
    $sku = trim(explode('/', $item['codigo'] ?? '')[0]);
    if ($sku) {
        $map2[$sku] = $item;
    }
}

$missingInJson2 = [];
$nameMismatches = [];

foreach ($map1 as $sku => $item1) {
    if (!isset($map2[$sku])) {
        $missingInJson2[] = $sku;
    } else {
        $item2 = $map2[$sku];
        
        $desc1 = trim(strtoupper($item1['descripcion'] ?? ''));
        $desc2 = trim(strtoupper($item2['descripcion'] ?? ''));
        
        // Sometimes the description has a leading slash, let's normalize it
        $desc1 = ltrim($desc1, '/');
        $desc2 = ltrim($desc2, '/');

        if ($desc1 !== $desc2) {
            $nameMismatches[] = [
                'sku' => $sku,
                'desc1' => $desc1,
                'desc2' => $desc2
            ];
        }
    }
}

$md = "# Comparación de Archivos JSON\n\n";
$md .= "Analizamos los archivos:\n";
$md .= "1. `articulos_global_20260715_1707-2.json` (" . count($map1) . " SKUs únicos)\n";
$md .= "2. `productos.json` (" . count($map2) . " SKUs únicos)\n\n";

if (count($missingInJson2) === 0) {
    $md .= "✅ **TODOS** los SKUs que están en `articulos_global` se encuentran presentes en `productos.json`.\n\n";
} else {
    $md .= "❌ Faltan **" . count($missingInJson2) . "** SKUs en `productos.json` que sí están en `articulos_global`.\n";
    $md .= "Ejemplos de los que faltan: " . implode(', ', array_slice($missingInJson2, 0, 10)) . "...\n\n";
}

if (count($nameMismatches) === 0) {
    $md .= "✅ **Mismos Productos:** Las descripciones de los productos coinciden exactamente.\n";
} else {
    $md .= "⚠️ **Diferencias de Nombre:** Se encontraron **" . count($nameMismatches) . "** productos donde el SKU es igual, pero el nombre es distinto.\n\n";
    $md .= "| SKU | Nombre en `articulos_global` | Nombre en `productos.json` |\n";
    $md .= "|---|---|---|\n";
    $limit = min(50, count($nameMismatches));
    for ($i = 0; $i < $limit; $i++) {
        $m = $nameMismatches[$i];
        $md .= "| {$m['sku']} | {$m['desc1']} | {$m['desc2']} |\n";
    }
    if (count($nameMismatches) > 50) {
        $md .= "| ... | ... | y " . (count($nameMismatches) - 50) . " más... |\n";
    }
}

$artifactPath = 'C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/reporte_comparacion_json.md';
file_put_contents($artifactPath, $md);
echo "Reporte generado en: " . $artifactPath . "\n";
