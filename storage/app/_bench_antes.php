<?php

// Benchmark de la importación ACTUAL (antes de optimizar). Datos sintéticos, BD local, transacción revertida.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$filas = (int) ($argv[1] ?? 6000);
$path = __DIR__."/_bench_{$filas}.xlsx";
if (! is_file($path)) {
    $rows = [['Fecha', 'Referencia', 'Descripción', 'Monto', 'Balance']];
    mt_srand(42);
    for ($i = 1; $i <= $filas; $i++) {
        $dia = str_pad((string) (1 + ($i % 28)), 2, '0', STR_PAD_LEFT);
        $monto = (mt_rand(0, 1) ? -1 : 1) * (mt_rand(100, 9999999) / 100);
        $rows[] = ["{$dia}/08/2026", (string) (900000000 + $i), 'MOVIMIENTO PRUEBA '.$i, number_format($monto, 2, ',', '.'), '0,00'];
    }
    file_put_contents($path, App\Support\SimpleXlsxWriter::toString(['Hoja1' => $rows]));
}

$queries = 0;
DB::listen(function () use (&$queries) { $queries++; });

$ctrl = app(App\Http\Controllers\FinanzasController::class);
$guardar = new ReflectionMethod($ctrl, 'guardarLineaExtracto');
$guardar->setAccessible(true);

$t0 = microtime(true);
$m0 = memory_get_usage(true);
DB::beginTransaction();
try {
    $xlsx = Shuchkin\SimpleXLSX::parse($path);
    $all = $xlsx->rows();
    $tParse = microtime(true) - $t0;
    $ok = 0;
    foreach ($all as $idx => $data) {
        if ($idx < 1) {
            continue;
        }
        $raw = str_replace('.', '', (string) $data[3]);
        $monto = (float) str_replace(',', '.', $raw);
        $fecha = Carbon\Carbon::parse(str_replace('/', '-', (string) $data[0]))->format('Y-m-d');
        if ($guardar->invoke($ctrl, [
            'session_id' => 'bench', 'banco' => 'BANESCO', 'titular' => 'ZZ PRUEBA PERF',
            'fecha' => $fecha, 'descripcion' => (string) $data[2], 'referencia' => (string) $data[1],
            'monto' => abs($monto), 'estado' => 'pendiente', 'tipo' => $monto < 0 ? 'cargo' : 'abono',
        ])) {
            $ok++;
        }
    }
} finally {
    DB::rollBack();
}
$t = microtime(true) - $t0;
printf("ANTES filas=%d insertadas=%d consultas=%d tiempo_total=%.2fs parse=%.2fs memoria_pico=%.1fMB\n",
    $filas, $ok, $queries, $t, $tParse, memory_get_peak_usage(true) / 1048576);
