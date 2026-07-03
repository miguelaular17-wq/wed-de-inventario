<?php
require 'vendor/autoload.php';
\ = require_once 'bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();
try {
    echo app(\App\Http\Controllers\FinanzasController::class)->flujoCaja()->render();
} catch (\Exception \) {
    echo \->getMessage();
}
