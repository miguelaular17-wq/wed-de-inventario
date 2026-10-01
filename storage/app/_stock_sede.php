<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$data = app(App\Services\StockSedeDashboardService::class)->resumen();
echo json_encode([
    'totales' => $data['totales'],
    'por_sede' => $data['por_sede'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
