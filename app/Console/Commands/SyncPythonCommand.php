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
        
        // Usamos Symfony Process con python -u (unbuffered) para salida en tiempo real
        $process = \Symfony\Component\Process\Process::fromShellCommandline("python -u \"{$scriptPath}\"");
        $process->setTimeout(null); // Sin límite de tiempo por si la sincronización es larga
        
        $process->run(function ($type, $buffer) {
            // Escribir la salida directamente en consola a medida que llega
            $this->output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $this->info("\nSincronización de Python completada con éxito.");
            
            // Clear the cache since new filters/products might have arrived
            $this->info('Limpiando caché...');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            $this->info('Caché limpiada. Proceso finalizado.');
        } else {
            $this->error("\nError ejecutando el script de Python.");
        }
    }
}
