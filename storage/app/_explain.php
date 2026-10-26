<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

DB::statement('SET enable_seqscan = off');
$planes = [
    'deduplicación del importador' => [
        "SELECT fecha, monto, referencia, descripcion, tipo FROM conciliacion_lineas
         WHERE LOWER(TRIM(banco)) = ? AND UPPER(TRIM(COALESCE(titular, ''))) = ? AND fecha IN (?, ?) AND monto IN (?, ?)",
        ['banesco', 'DORAL', '2026-08-01', '2026-08-02', '10.00', '20.00'],
    ],
    'idsExtractoVisibles (pantalla)' => [
        "SELECT id FROM conciliacion_lineas WHERE fecha >= ? AND fecha <= ? AND LOWER(TRIM(banco)) IN (?, ?)",
        ['2026-08-01', '2026-08-31', 'banesco', 'banesco banco universal'],
    ],
    'egresos candidatos' => [
        "SELECT id FROM flujo_cajas WHERE tipo = 'egreso' AND fecha >= ? AND fecha <= ? AND es_conciliado = false",
        ['2026-08-01', '2026-08-31'],
    ],
];
foreach ($planes as $nombre => [$sql, $b]) {
    echo "== $nombre\n";
    foreach (DB::select('EXPLAIN '.$sql, $b) as $fila) {
        echo '   '.((array) $fila)['QUERY PLAN']."\n";
    }
}
