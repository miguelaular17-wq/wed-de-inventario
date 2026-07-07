<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CuentaBancaria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
    protected $description = 'Ejecuta el cierre diario de finanzas de forma segura preservando el historial.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $inicio = microtime(true);
        $fechaInicio = now();
        $origen = app()->runningInConsole() ? 'Scheduler/Artisan' : 'Manual';
        $usuario = auth()->check() ? auth()->user()->email : 'Sistema';

        // 1. Exclusión Mutua mediante Atomic Lock en Caché (5 minutos máximo)
        $lock = Cache::lock('cierre_diario_finanzas', 300);

        if (!$lock->get()) {
            Log::warning('CIERRE DIARIO FALLO', [
                'motivo' => 'Ejecución simultánea detectada',
                'origen' => $origen,
                'usuario' => $usuario,
                'fecha' => now()->toDateTimeString()
            ]);
            $this->warn('El proceso de cierre diario ya se encuentra en ejecución.');
            return Command::FAILURE;
        }

        try {
            $cuentasActualizadas = 0;

            DB::transaction(function () use (&$cuentasActualizadas) {
                // Reiniciar únicamente los saldos ciegos diarios. 
                // Se preserva el historial de FlujoCaja y FinanzasResumen.
                $cuentasActualizadas = CuentaBancaria::query()->update([
                    'bs_tc' => 0,
                    'bs_disponibles' => 0,
                    'usd_tc' => 0,
                    'usd_disp' => 0,
                    'reporte_bs' => 0,
                    'reporte_usd' => 0,
                    'reporte_bs_fin' => 0,
                    'reporte_usd_fin' => 0,
                ]);
            });

            $duracion = round(microtime(true) - $inicio, 2);
            
            // Auditoría del proceso exitoso
            Log::info('CIERRE DIARIO SUCCESS', [
                'fecha_inicio' => $fechaInicio->toDateTimeString(),
                'fecha_fin' => now()->toDateTimeString(),
                'duracion_segundos' => $duracion,
                'origen' => $origen,
                'usuario' => $usuario,
                'cuentas_actualizadas' => $cuentasActualizadas,
                'resultado' => 'SUCCESS'
            ]);

            $this->info("Cierre diario completado exitosamente en {$duracion}s.");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $duracion = round(microtime(true) - $inicio, 2);
            
            // Auditoría del fallo (El rollback ya fue ejecutado automáticamente por DB::transaction)
            Log::error('CIERRE DIARIO FAILURE', [
                'fecha_inicio' => $fechaInicio->toDateTimeString(),
                'fecha_fin' => now()->toDateTimeString(),
                'duracion_segundos' => $duracion,
                'origen' => $origen,
                'usuario' => $usuario,
                'error' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'resultado' => 'FAILURE'
            ]);
            
            $this->error('Falló el cierre diario. Rollback ejecutado de forma segura. Error: ' . $e->getMessage());
            return Command::FAILURE;
            
        } finally {
            // Siempre liberar el lock al finalizar
            $lock->release();
        }
    }
}
