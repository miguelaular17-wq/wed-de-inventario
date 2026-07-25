<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$deleted = \App\Models\FinanzasResumen::where('fecha', '!=', '2026-07-24')->delete();
echo "Se eliminaron $deleted registros.";
