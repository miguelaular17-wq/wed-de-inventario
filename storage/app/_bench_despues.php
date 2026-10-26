<?php

// Benchmark de la importación NUEVA. Datos sintéticos, BD local, transacción revertida.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\ProcesarImportacionExtracto;
use App\Models\ConciliacionImportacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$filas = (int) ($argv[1] ?? 6000);
$origen = __DIR__."/_bench_{$filas}.xlsx";
if (! is_file($origen)) {
    $rows = [['Fecha', 'Referencia', 'Descripción', 'Monto', 'Balance']];
    mt_srand(42);
    for ($i = 1; $i <= $filas; $i++) {
        $dia = str_pad((string) (1 + ($i % 28)), 2, '0', STR_PAD_LEFT);
        $monto = (mt_rand(0, 1) ? -1 : 1) * (mt_rand(100, 9999999) / 100);
        $rows[] = ["{$dia}/08/2026", (string) (900000000 + $i), 'MOVIMIENTO PRUEBA '.$i, number_format($monto, 2, ',', '.'), '0,00'];
    }
    file_put_contents($origen, App\Support\SimpleXlsxWriter::toString(['Hoja1' => $rows]));
}

$destino = 'conciliaciones/_bench_'.$filas.'.xlsx';
Storage::disk('local')->put($destino, file_get_contents($origen));

$queries = 0;
$consultasImport = 0;
DB::listen(function () use (&$queries) { $queries++; });

DB::beginTransaction();
try {
    $imp = ConciliacionImportacion::create([
        'banco' => 'BANESCO', 'titular' => 'ZZ PRUEBA PERF', 'archivo_nombre' => 'bench.xlsx',
        'archivo_path' => $destino, 'extension' => 'xlsx', 'estado' => 'pendiente',
    ]);
    $queries = 0;
    $t0 = microtime(true);
    $imp = app(App\Services\Conciliacion\ExtractoImporter::class)->importarArchivo($imp);
    $tImport = microtime(true) - $t0;
    $consultasImport = $queries;
    $memImport = memory_get_peak_usage(true) / 1048576;

    $imp->forceFill(['estado' => 'procesando', 'finalizado_at' => null])->save();
    $queries = 0;
    $t1 = microtime(true);
    $job = new ProcesarImportacionExtracto($imp->id);
    $m = new ReflectionMethod($job, 'conciliarNuevas');
    $m->setAccessible(true);
    $m->invoke($job, $imp, app(App\Services\Conciliacion\ConciliacionAutoMatcher::class));
    $tMatch = microtime(true) - $t1;
    $imp->refresh();

    printf("DESPUES filas=%d leidas=%d importadas=%d duplicadas=%d rechazadas=%d estado=%s\n",
        $filas, $imp->filas_leidas, $imp->filas_importadas, $imp->filas_duplicadas, $imp->filas_rechazadas, $imp->estado);
    printf("  importacion: consultas=%d tiempo=%.2fs memoria_pico=%.1fMB\n", $consultasImport, $tImport, $memImport);
    printf("  conciliacion automatica: consultas=%d tiempo=%.2fs conciliadas=%d pendientes=%d\n", $queries, $tMatch, $imp->filas_conciliadas, $imp->filas_pendientes);

    // Reintento: no debe insertar nada nuevo.
    $imp->forceFill(['estado' => 'procesando', 'finalizado_at' => null, 'ultima_fila' => 0, 'archivo_path' => $destino])->save();
    Storage::disk('local')->put($destino, file_get_contents($origen));
    $antes = DB::table('conciliacion_lineas')->where('titular', 'ZZ PRUEBA PERF')->count();
    $imp2 = ConciliacionImportacion::create([
        'banco' => 'BANESCO', 'titular' => 'ZZ PRUEBA PERF', 'archivo_nombre' => 'bench.xlsx',
        'archivo_path' => $destino, 'extension' => 'xlsx', 'estado' => 'pendiente',
    ]);
    $imp2 = app(App\Services\Conciliacion\ExtractoImporter::class)->importarArchivo($imp2);
    $despues = DB::table('conciliacion_lineas')->where('titular', 'ZZ PRUEBA PERF')->count();
    printf("  re-subida del mismo archivo: nuevas=%d duplicadas=%d (lineas antes=%d despues=%d)\n", $imp2->filas_importadas, $imp2->filas_duplicadas, $antes, $despues);
} finally {
    DB::rollBack();
    Storage::disk('local')->delete($destino);
}
