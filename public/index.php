<?php
$inicioGlobal = microtime(true);

/**
 * Front controller de Laravel.
 * Requiere un proyecto Laravel completo con vendor instalado.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

\Illuminate\Support\Facades\Log::info('TOTAL REQUEST: ' . round((microtime(true) - $inicioGlobal) * 1000, 2) . ' ms');

$response->send();

$kernel->terminate($request, $response);
