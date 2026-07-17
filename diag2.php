<?php
require __DIR__ . '/vendor/autoload.php';

$bancos = [
    'BANCAMIGA' => __DIR__ . '/Nueva carpeta/BANCAMIGA.xlsx',
    'BANCARIBE' => __DIR__ . '/Nueva carpeta/BANCARIBE.csv',
    'BANESCO'   => __DIR__ . '/Nueva carpeta/BANESCO.xlsx',
    'BBVA'      => __DIR__ . '/Nueva carpeta/BBVA.CSV',
    'BNC'       => __DIR__ . '/Nueva carpeta/BNC.xlsx',
];

foreach ($bancos as $b => $p) {
    echo "\n=== $b ===\n";
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    $rows = [];
    if ($ext === 'xlsx') {
        $x = \Shuchkin\SimpleXLSX::parse($p);
        $rows = $x ? $x->rows() : [];
    } else {
        $h = fopen($p, 'r');
        while (($r = fgetcsv($h, 2000, ',')) !== false) {
            if (count($r) === 1 && strpos($r[0], ';') !== false) $r = str_getcsv($r[0], ';');
            $rows[] = $r;
        }
        fclose($h);
    }
    echo "Filas: " . count($rows) . "\n";
    for ($i = 0; $i < min(25, count($rows)); $i++) {
        echo "F[$i]: ";
        foreach ($rows[$i] as $c => $v) {
            if (trim((string)$v) !== '') {
                echo "[$c]=" . substr(trim((string)$v), 0, 38) . "  ";
            }
        }
        echo "\n";
    }
}
