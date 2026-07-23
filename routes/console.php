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
Schedule::command('contratos:notificar')->dailyAt('08:00');

Schedule::call(function () {
    $limite = \Illuminate\Support\Carbon::now()->subDays(2);
    
    if (config('database.default') === 'pgsql') {
        \App\Models\V2\Movimiento::where('created_at', '<', $limite)->delete();
    } else {
        \App\Models\StockMovement::where('created_at', '<', $limite)->delete();
    }
    
    \Illuminate\Support\Facades\Log::info('Movimientos con más de 2 días de antigüedad fueron eliminados.');
})->dailyAt('03:00');
