<?php

foreach (['DB_CONNECTION', 'DB_DATABASE', 'DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    putenv($key);
    unset($_ENV[$key], $_SERVER[$key]);
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$q = app(App\Services\MetaQuincenaService::class)->resolverQuincenaListado(null);
$row = DB::table('meta_quincena_productos')
    ->whereDate('quincena_inicio', $q['inicio']->toDateString())
    ->selectRaw('COUNT(*) as total')
    ->selectRaw('COUNT(responsable_empleado_id) as con_responsable')
    ->first();
echo 'metas='.$row->total.' con_responsable='.$row->con_responsable.PHP_EOL;

$res = app(App\Services\IncentivoEvaluacionService::class)->evaluar('ventas', config('incentivos.modulos.ventas'));
$est = [];
foreach ($res['filas'] as $fila) {
    $clave = ($fila['meta_prod_estado'] ?? 'null').' '.($fila['meta_prod_cumplidas'] ?? '-').'/'.($fila['meta_prod_total'] ?? '-');
    $est[$clave] = ($est[$clave] ?? 0) + 1;
}
foreach ($est as $k => $n) {
    echo $n.' '.$k.PHP_EOL;
}
