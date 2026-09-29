<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\BdvMediosPagoConciliacionService;
use Illuminate\Support\Facades\DB;

$path = 'c:/Users/freyg/Downloads/Conciliación medios de pago (2).xlsx';
if (! is_file($path)) {
    fwrite(STDERR, "File missing: $path\n");
    exit(1);
}

echo "=== Antes ===\n";
$antes = DB::table('conciliacion_lineas')
    ->whereRaw("UPPER(banco) LIKE '%VENEZUELA%'")
    ->whereRaw("(UPPER(descripcion) LIKE '%LIQ.TARJETA%' OR UPPER(descripcion) LIKE '%LIQUIDACION T/%')")
    ->whereBetween('fecha', ['2026-08-01', '2026-08-31'])
    ->selectRaw('estado, COUNT(*) n')
    ->groupBy('estado')
    ->get();
foreach ($antes as $r) {
    echo "{$r->estado}\t{$r->n}\n";
}

$svc = app(BdvMediosPagoConciliacionService::class);
$lotes = $svc->parseExcel($path);
echo 'Lotes parseados: '.count($lotes)."\n";
if ($lotes !== []) {
    echo 'Sample: '.json_encode($lotes[0], JSON_UNESCAPED_UNICODE)."\n";
}

$res = $svc->importarYConciliar($path, 'VENEZUELA', null);
echo 'Resultado: '.json_encode([
    'lotes' => $res['lotes'],
    'conciliados' => $res['conciliados'],
    'creados' => $res['creados'],
    'sin_match' => count($res['sin_match']),
], JSON_UNESCAPED_UNICODE)."\n";

if ($res['sin_match'] !== []) {
    echo "Sin match (primeros 10):\n";
    foreach (array_slice($res['sin_match'], 0, 10) as $m) {
        echo "  {$m['fecha']} lote {$m['lote']} neto {$m['neto']}\n";
    }
}

echo "\n=== Después ===\n";
$despues = DB::table('conciliacion_lineas')
    ->whereRaw("UPPER(banco) LIKE '%VENEZUELA%'")
    ->whereRaw("(UPPER(descripcion) LIKE '%LIQ.TARJETA%' OR UPPER(descripcion) LIKE '%LIQUIDACION T/%')")
    ->whereBetween('fecha', ['2026-08-01', '2026-08-31'])
    ->selectRaw('estado, COUNT(*) n')
    ->groupBy('estado')
    ->get();
foreach ($despues as $r) {
    echo "{$r->estado}\t{$r->n}\n";
}
