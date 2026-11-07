<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Models\StOrden;
use App\Models\StOrdenEvento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pasa las órdenes de «Servicio técnico» a «Reparación interna» cambiando solo el tipo: los datos del cliente
 * se conservan. Antes de cambiar guarda un respaldo JSON de las órdenes afectadas en storage/app/respaldos.
 */
class PasarServiciosAReparacionInternaCommand extends Command
{
    protected $signature = 'servicio:pasar-st-a-interna {--aplicar : Guarda los cambios (sin esto solo cuenta)}';

    protected $description = 'Cambia todas las órdenes de servicio técnico a reparación interna, conservando los datos del cliente.';

    public function handle(): int
    {
        $base = StOrden::query()->where(fn ($q) => $q->where('tipo_gestion', StOrden::TIPO_ST)->orWhereNull('tipo_gestion'));
        $total = (clone $base)->count();

        $this->line('Base de datos: '.config('database.connections.'.config('database.default').'.host').' / '.config('database.connections.'.config('database.default').'.database'));
        $this->table(['Sede', 'Estado', 'Órdenes'], (clone $base)
            ->selectRaw('sede, estado, COUNT(*) as n')
            ->groupBy('sede', 'estado')
            ->orderBy('sede')->orderBy('estado')
            ->get()
            ->map(fn ($r) => [$r->sede, StOrden::ESTADOS[$r->estado] ?? $r->estado, $r->n])
            ->all());
        $this->info("Total de servicio técnico: {$total}");

        if ($total === 0 || ! $this->option('aplicar')) {
            if ($total > 0) {
                $this->warn('No se cambió nada. Vuelve a correr con --aplicar para pasarlas a reparación interna.');
            }

            return self::SUCCESS;
        }

        $ordenes = (clone $base)->orderBy('id')->get();
        $archivo = 'respaldos/st_a_interna_'.now()->format('Ymd_His').'.json';
        Storage::disk('local')->put($archivo, json_encode(
            $ordenes->map(fn (StOrden $o) => $o->getRawOriginal())->values()->all(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        ));
        $this->line('Respaldo: '.Storage::disk('local')->path($archivo));

        $motivo = 'Paso masivo de servicio técnico a reparación interna';
        $hechas = 0;
        Auditoria::conMotivo($motivo, function () use ($ordenes, $motivo, &$hechas) {
            foreach ($ordenes->chunk(200) as $lote) {
                DB::transaction(function () use ($lote, $motivo, &$hechas) {
                    foreach ($lote as $orden) {
                        $orden->update(['tipo_gestion' => StOrden::TIPO_REPARACION_INTERNA]);
                        StOrdenEvento::create([
                            'orden_id' => $orden->id,
                            'user_id' => null,
                            'tipo' => StOrdenEvento::TIPO_NOTA,
                            'descripcion' => "Tipo de gestión cambiado: Servicio técnico → Reparación interna. Motivo: {$motivo}",
                            'meta' => ['antes' => StOrden::TIPO_ST, 'despues' => StOrden::TIPO_REPARACION_INTERNA, 'motivo' => $motivo],
                            'created_at' => now(),
                        ]);
                        $hechas++;
                    }
                });
            }
        });

        $this->info("{$hechas} órdenes pasadas a reparación interna.");

        return self::SUCCESS;
    }
}
