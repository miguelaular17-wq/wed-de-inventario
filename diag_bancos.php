<?php
// Script de diagnóstico de formatos bancarios
require __DIR__ . '/vendor/autoload.php';

$archivos = [
    'BANCAMIGA'  => __DIR__ . '/Nueva carpeta/BANCAMIGA.xlsx',
    'BANCARIBE'  => __DIR__ . '/Nueva carpeta/BANCARIBE.csv',
    'BANESCO'    => __DIR__ . '/Nueva carpeta/BANESCO.xlsx',
    'BBVA'       => __DIR__ . '/Nueva carpeta/BBVA.CSV',
    'BNC'        => __DIR__ . '/Nueva carpeta/BNC.xlsx',
    'MERCANTIL'  => __DIR__ . '/Nueva carpeta/MERCANTIL.xlsx',
    'TESORO'     => __DIR__ . '/Nueva carpeta/TESORO.xlsx',
    'VENEZUELA'  => __DIR__ . '/Nueva carpeta/VENEZUELA.xlsx',
];

foreach ($archivos as $banco => $path) {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "BANCO: $banco\n";
    echo str_repeat('=', 70) . "\n";

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $rows = [];

    if ($ext === 'xlsx') {
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($path)) {
            $rows = $xlsx->rows();
        } else {
            echo "ERROR al parsear: " . \Shuchkin\SimpleXLSX::parseError() . "\n";
            continue;
        }
    } else {
        // CSV
        $handle = fopen($path, 'r');
        while (($row = fgetcsv($handle, 2000, ',')) !== false) {
            if (count($row) === 1 && strpos($row[0], ';') !== false) {
                $row = str_getcsv($row[0], ';');
            }
            $rows[] = $row;
        }
        fclose($handle);
    }

    $total = count($rows);
    echo "Total filas: $total\n\n";

    // Mostrar primeras 20 filas con índice
    $limit = min(20, $total);
    for ($i = 0; $i < $limit; $i++) {
        echo "Fila[$i]: ";
        foreach ($rows[$i] as $ci => $val) {
            $v = trim((string)$val);
            if ($v !== '') {
                echo "[$ci]=" . substr($v, 0, 40) . "  ";
            }
        }
        echo "\n";
    }

    // También mostrar las últimas 5 filas
    if ($total > 20) {
        echo "\n... (filas " . ($limit) . " a " . ($total-6) . " omitidas) ...\n\n";
        for ($i = max($total-5, $limit); $i < $total; $i++) {
            echo "Fila[$i]: ";
            foreach ($rows[$i] as $ci => $val) {
                $v = trim((string)$val);
                if ($v !== '') {
                    echo "[$ci]=" . substr($v, 0, 40) . "  ";
                }
            }
            echo "\n";
        }
    }
}
echo "\n¡Diagnóstico completado!\n";
