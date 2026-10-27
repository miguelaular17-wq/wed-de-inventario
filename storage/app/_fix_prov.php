<?php

// Actualiza productos.proveedor con el proveedor de la última compra (reporte de Profit "ult compra prove.xlsx").
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$aplicar = in_array('--aplicar', $argv, true);
$rows = \Shuchkin\SimpleXLSX::parse('C:/Users/freyg/Downloads/ult compra prove.xlsx')->rows();
$ultima = [];
foreach (array_slice($rows, 7) as $r) {
    $cod = trim((string) $r[2]);
    $fecha = (string) $r[0];
    $prov = trim((string) $r[8]);
    if ($cod === '' || $prov === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha)) continue;
    if (! isset($ultima[$cod]) || strcmp($fecha, $ultima[$cod]['fecha']) >= 0) {
        $ultima[$cod] = ['fecha' => $fecha, 'prov' => $prov];
    }
}

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
    env('SYNC_DESKTOP_DB_HOST'), env('SYNC_DESKTOP_DB_PORT', '6543'), env('SYNC_DESKTOP_DB_DATABASE', 'postgres'));
$pdo = new PDO($dsn, env('SYNC_DESKTOP_DB_USER'), env('SYNC_DESKTOP_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]);

$prod = [];
foreach ($pdo->query('SELECT id, codigo, proveedor FROM inventario_v2.productos') as $p) $prod[trim($p['codigo'])] = $p;

$cambios = [];
$omitidos = ['prueba' => 0];
foreach ($ultima as $cod => $u) {
    if (! isset($prod[$cod])) continue;
    $hoy = (string) $prod[$cod]['proveedor'];
    if ($hoy === $u['prov']) continue;
    if (strtoupper($u['prov']) === 'PRUEBA') { $omitidos['prueba']++; continue; }
    $cambios[] = ['id' => (int) $prod[$cod]['id'], 'codigo' => $cod, 'antes' => $hoy, 'despues' => mb_substr($u['prov'], 0, 256), 'fecha' => substr($u['fecha'], 0, 10)];
}
$llenan = count(array_filter($cambios, fn ($c) => trim($c['antes']) === ''));
printf("cambios: %d (se llenan %d vacíos · cambian %d) · omitidos PRUEBA: %d\n", count($cambios), $llenan, count($cambios) - $llenan, $omitidos['prueba']);
if (! $aplicar) {
    echo "(simulación: usa --aplicar para escribir)\n";
    exit;
}

$backup = __DIR__ . '/backup_proveedores_' . date('Ymd_His') . '.csv';
$fh = fopen($backup, 'w');
fputcsv($fh, ['id', 'codigo', 'proveedor_antes', 'proveedor_despues', 'fecha_ultima_compra']);
foreach ($cambios as $c) fputcsv($fh, [$c['id'], $c['codigo'], $c['antes'], $c['despues'], $c['fecha']]);
fclose($fh);
echo "respaldo: {$backup}\n";

$pdo->exec('BEGIN');
try {
    $total = 0;
    foreach (array_chunk($cambios, 400) as $lote) {
        $vals = [];
        $params = [];
        foreach ($lote as $c) {
            $vals[] = '(?::bigint, ?::text, ?::text)';
            array_push($params, $c['id'], $c['antes'], $c['despues']);
        }
        $st = $pdo->prepare('UPDATE inventario_v2.productos p SET proveedor = v.despues, updated_at = NOW()
            FROM (VALUES ' . implode(',', $vals) . ') AS v(id, antes, despues)
            WHERE p.id = v.id AND p.proveedor = v.antes');
        $st->execute($params);
        $total += $st->rowCount();
    }
    if ($total !== count($cambios)) {
        throw new RuntimeException("se actualizaron {$total} de " . count($cambios) . ' (algún producto cambió mientras tanto)');
    }
    $pdo->exec('COMMIT');
    echo "COMMIT: {$total} productos actualizados.\n";
} catch (Throwable $e) {
    $pdo->exec('ROLLBACK');
    echo 'ROLLBACK: ' . $e->getMessage() . "\n";
    exit(1);
}
