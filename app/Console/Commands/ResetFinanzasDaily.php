<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FlujoCaja;
use App\Models\CuentaBancaria;
use App\Models\FinanzasResumen;

class ResetFinanzasDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finanzas:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina todos los datos de finanzas para empezar el día en blanco.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Limpiar todos los movimientos de caja (egresos y otros egresos)
        FlujoCaja::truncate();

        // 2. Reiniciar todas las cuentas bancarias a 0
        CuentaBancaria::query()->update([
            'bs_tc' => 0,
            'bs_disponibles' => 0,
            'usd_tc' => 0,
            'usd_disp' => 0,
            'reporte_bs' => 0,
            'reporte_usd' => 0,
        ]);

        // 3. Limpiar los resúmenes financieros
        FinanzasResumen::truncate();

        $this->info('Los datos de Finanzas se han reseteado correctamente para empezar el nuevo día.');
    }
}
