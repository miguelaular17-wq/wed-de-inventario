<?php

// Solo lectura: mide la latencia de ida y vuelta (SELECT 1) contra la BD de producción.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
    env('SYNC_DESKTOP_DB_HOST'), env('SYNC_DESKTOP_DB_PORT', '6543'), env('SYNC_DESKTOP_DB_DATABASE', 'postgres'));
$pdo = new PDO($dsn, env('SYNC_DESKTOP_DB_USER'), env('SYNC_DESKTOP_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]);
$pdo->query('SELECT 1')->fetchColumn();
$t = [];
for ($i = 0; $i < 20; $i++) {
    $s = microtime(true);
    $pdo->query('SELECT 1')->fetchColumn();
    $t[] = (microtime(true) - $s) * 1000;
}
sort($t);
printf("RTT SELECT 1 (ms): min=%.1f mediana=%.1f max=%.1f\n", $t[0], $t[10], $t[19]);
$c = $pdo->query("SELECT count(*) FROM inventario_v2.conciliacion_lineas")->fetchColumn();
echo "conciliacion_lineas en producción: $c\n";
foreach ($pdo->query("SELECT tablename, indexname, indexdef FROM pg_indexes WHERE schemaname='inventario_v2' AND tablename IN ('conciliacion_lineas','flujo_cajas','compra_divisas','tesoreria_ingresos') ORDER BY 1,2")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  {$r['tablename']}: {$r['indexdef']}\n";
}
$top = $pdo->query("SELECT UPPER(TRIM(banco)) b, COUNT(*) n FROM inventario_v2.conciliacion_lineas GROUP BY 1 ORDER BY 2 DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
foreach ($top as $r) {
    echo "  filas por banco: {$r['b']}={$r['n']}\n";
}
