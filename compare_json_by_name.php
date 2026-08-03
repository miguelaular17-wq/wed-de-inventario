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

$names1 = [];
foreach ($json1 as $item) {
    $name = trim(strtoupper($item['descripcion'] ?? ''));
    $name = ltrim($name, '/');
    if ($name) {
        $names1[$name] = $item;
    }
}

$names2 = [];
foreach ($json2 as $item) {
    $name = trim(strtoupper($item['descripcion'] ?? ''));
    $name = ltrim($name, '/');
    if ($name) {
        $names2[$name] = true;
    }
}

$missingNames = [];
foreach ($names1 as $name => $item) {
    if (!isset($names2[$name])) {
        $missingNames[] = [
            'nombre' => $name,
            'sku' => $item['codigo']
        ];
    }
}

$md = "# Comparación por Nombres\n\n";
$md .= "Analizamos los archivos cruzando por **Nombre del Producto**:\n";
$md .= "1. `articulos_global_20260715_1707-2.json` (" . count($names1) . " nombres únicos)\n";
$md .= "2. `productos.json` (" . count($names2) . " nombres únicos)\n\n";

if (count($missingNames) === 0) {
    $md .= "✅ **TODOS** los nombres de `articulos_global` se encuentran presentes en `productos.json`.\n\n";
} else {
    $md .= "❌ Faltan **" . count($missingNames) . "** productos en `productos.json` (buscando por nombre exacto).\n\n";
    $md .= "### Ejemplos de los que faltan:\n";
    $md .= "| Nombre | SKU original |\n";
    $md .= "|---|---|\n";
    $limit = min(50, count($missingNames));
    for ($i = 0; $i < $limit; $i++) {
        $md .= "| {$missingNames[$i]['nombre']} | {$missingNames[$i]['sku']} |\n";
    }
    if (count($missingNames) > 50) {
        $md .= "| ... | ... |\n";
        $md .= "*(Y " . (count($missingNames) - 50) . " productos más)*\n";
    }
}

$artifactPath = 'C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/reporte_comparacion_nombres.md';
file_put_contents($artifactPath, $md);
echo "Reporte generado en: " . $artifactPath . "\n";
