<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Models\StOrden;
use App\Models\StOrdenEvento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Órdenes que creó alguien con sede fija (telefonía, supervisor, vendedor…) pero quedaron con otra sede de origen:
 * se les pone como origen la sede del creador. La orden sigue en la sede donde está y conserva su número.
 */
class CorregirSedeOrigenServicioCommand extends Command
{
    protected $signature = 'servicio:corregir-sede-origen
        {--usuario=* : Correo o nombre exacto del creador; sin esto se revisan todos}
        {--aplicar : Guarda los cambios (sin esto solo muestra lo que cambiaría)}';

    protected $description = 'Pone como sede de origen la sede del creador en las órdenes de servicio técnico de usuarios con sede fija.';

    public function handle(): int
    {
        $sedes = config('inventario.sedes_locales', []);
        $usuarios = array_values(array_filter(array_map(fn ($u) => mb_strtolower(trim((string) $u), 'UTF-8'), (array) $this->option('usuario'))));
        $pendientes = StOrden::query()
            ->with('creador')
            ->whereNotNull('created_by')
            ->when($usuarios !== [], fn ($q) => $q->whereHas('creador', fn ($u) => $u
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(email)'), $usuarios)
                ->orWhereIn(\Illuminate\Support\Facades\DB::raw('LOWER(name)'), $usuarios)))
            ->orderBy('id')
            ->get()
            ->filter(function (StOrden $orden) use ($sedes) {
                $creador = $orden->creador;
                if (! $creador || ! $creador->creaServicioEnSuSede()) {
                    return false;
                }
                $sedeCreador = strtoupper((string) $creador->sede);

                return in_array($sedeCreador, $sedes, true) && $orden->sedeOrigen() !== $sedeCreador;
            })
            ->values();

        if ($pendientes->isEmpty()) {
            $this->info('No hay órdenes con la sede de origen equivocada.');

            return self::SUCCESS;
        }

        $this->table(
            ['Orden', 'Creada por', 'Sede del creador', 'Origen actual', 'Está en'],
            $pendientes->map(fn (StOrden $o) => [
                $o->codigo(), $o->creador->name, strtoupper((string) $o->creador->sede), $o->sedeOrigen(), $o->sede,
            ])->all()
        );

        if (! $this->option('aplicar')) {
            $this->warn($pendientes->count().' órdenes por corregir. Vuelve a correr con --aplicar para guardar.');

            return self::SUCCESS;
        }

        foreach ($pendientes as $orden) {
            $sedeCreador = strtoupper((string) $orden->creador->sede);
            $antes = $orden->sedeOrigen();
            Auditoria::conMotivo("Corrección: la orden nació en la sede del creador ({$sedeCreador})", function () use ($orden, $sedeCreador, $antes) {
                DB::transaction(function () use ($orden, $sedeCreador, $antes) {
                    $orden->update(['sede_origen_transfer' => $sedeCreador]);
                    StOrdenEvento::create([
                        'orden_id' => $orden->id,
                        'user_id' => null,
                        'tipo' => StOrdenEvento::TIPO_NOTA,
                        'descripcion' => "Sede de origen corregida: {$antes} → {$sedeCreador} (sede de quien la creó).",
                        'meta' => ['antes' => $antes, 'despues' => $sedeCreador],
                        'created_at' => now(),
                    ]);
                });
            });
        }

        $this->info($pendientes->count().' órdenes corregidas.');

        return self::SUCCESS;
    }
}
