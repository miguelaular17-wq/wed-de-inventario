<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReimportExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:reimport';

    protected $description = 'Clean DB and reimport RELACION DE CONTRATOS - COBRANZAS.xlsx';

    public function handle()
    {
        $this->info('Limpiando tablas de contratos...');
        \DB::statement('TRUNCATE TABLE contratos CASCADE');
        \DB::statement('TRUNCATE TABLE contrato_cuotas CASCADE');
        \DB::statement('TRUNCATE TABLE contrato_seguimientos CASCADE');
        
        $this->info('Importando archivo...');
        
        $path = base_path('RELACION DE CONTRATOS - COBRANZAS.xlsx');
        if (!file_exists($path)) {
            $this->error("Archivo no encontrado: $path");
            return;
        }

        $controller = app(\App\Http\Controllers\ContratoController::class);
        $resultado = $controller->procesarExcel($path);
        
        $this->info('Importación finalizada.');
        $this->info(json_encode($resultado));
    }
}
