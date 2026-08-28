<?php

namespace App\Console\Commands;

use App\Models\Nomina\NominaPeriodo;
use App\Services\Nomina\PayrollPeriodService;
use Illuminate\Console\Command;

class RevertirCalculoNominaCommand extends Command
{
    protected $signature = 'nomina:revertir-calculo {periodo : ID del período} {--force : No pedir confirmación}';

    protected $description = 'Deshace un cálculo de nómina accidental y deja la quincena ABIERTA.';

    public function handle(PayrollPeriodService $periods): int
    {
        $periodo = NominaPeriodo::query()->findOrFail((int) $this->argument('periodo'));

        if (! $this->option('force') && ! $this->confirm("¿Deshacer el cálculo del período #{$periodo->id} ({$periodo->etiqueta}, {$periodo->estado})?")) {
            return self::SUCCESS;
        }

        $periods->revertirCalculo($periodo, null);
        $this->info('Listo. El período volvió a ABIERTA.');

        return self::SUCCESS;
    }
}
