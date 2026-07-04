<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use App\Models\Cobranza;

Schedule::call(function () {
    Cobranza::truncate();
    \Illuminate\Support\Facades\Log::info('Tabla de clientes detallados truncada exitosamente a las 2 AM.');
})->dailyAt('02:00');

Schedule::command('finanzas:reset')->dailyAt('02:00');
