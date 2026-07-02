<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncPythonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:python';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta el script de Python de sincronización y limpia la caché';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronización con Python...');
        
        $scriptPath = base_path('scripts/sync_sales/sync_app.py');
        $command = "python \"{$scriptPath}\"";
        
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0) {
            $this->info('Sincronización de Python completada con éxito.');
            foreach ($output as $line) {
                $this->line($line);
            }
            
            // Clear the cache since new filters/products might have arrived
            $this->info('Limpiando caché...');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            $this->info('Caché limpiada. Proceso finalizado.');
        } else {
            $this->error('Error ejecutando el script de Python.');
            foreach ($output as $line) {
                $this->error($line);
            }
        }
    }
}
