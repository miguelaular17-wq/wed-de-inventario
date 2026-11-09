<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Models\StEquipo;
use App\Models\StOrden;
use App\Support\MarcasServicio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Unifica la marca de los equipos de servicio técnico: mayúsculas, sin espacios de más, y las variantes
 * escritas distinto (CUBOTT, IPHONE, TECNO SPARK…) a una sola marca. Si la variante era una línea o un modelo,
 * se pasa al inicio del modelo para no perder el dato. Antes de cambiar guarda un respaldo JSON.
 */
class NormalizarMarcasServicioCommand extends Command
{
    protected $signature = 'servicio:normalizar-marcas {--aplicar : Guarda los cambios (sin esto solo muestra qué cambiaría)}';

    protected $description = 'Unifica las marcas de los equipos de servicio técnico.';

    private static function normalizar(?string $marca, ?string $modelo): array
    {
        return MarcasServicio::normalizar($marca, $modelo);
    }

    public function handle(): int
    {
        $this->line('Base de datos: '.config('database.connections.'.config('database.default').'.host').' / '.config('database.connections.'.config('database.default').'.database'));

        $equipos = StEquipo::query()->whereNotNull('marca')->orderBy('id')->get(['id', 'marca', 'modelo']);
        $cambiosEquipos = $equipos->map(function (StEquipo $e) {
            [$marca, $modelo] = self::normalizar($e->marca, $e->modelo);

            return ($marca !== $e->marca || $modelo !== $e->modelo)
                ? ['equipo' => $e, 'marca' => $marca, 'modelo' => $modelo]
                : null;
        })->filter()->values();

        $ordenes = StOrden::query()->whereNotNull('atributos')->orderBy('id')->get(['id', 'atributos']);
        $cambiosOrdenes = $ordenes->map(function (StOrden $o) {
            $attrs = is_array($o->atributos) ? $o->atributos : [];
            if (trim((string) ($attrs['marca'] ?? '')) === '') {
                return null;
            }
            [$marca, $modelo] = self::normalizar($attrs['marca'], $attrs['modelo'] ?? null);

            return ($marca !== $attrs['marca'] || $modelo !== ($attrs['modelo'] ?? null))
                ? ['orden' => $o, 'atributos' => array_merge($attrs, ['marca' => $marca, 'modelo' => $modelo])]
                : null;
        })->filter()->values();

        $resumen = [];
        foreach ($cambiosEquipos as $c) {
            $clave = $c['equipo']->marca.' → '.$c['marca'];
            $resumen[$clave] ??= [$c['equipo']->marca, $c['marca'], 0, $c['equipo']->modelo.' → '.$c['modelo']];
            $resumen[$clave][2]++;
        }
        foreach ($cambiosOrdenes as $c) {
            $antes = $c['orden']->atributos['marca'];
            $clave = $antes.' → '.$c['atributos']['marca'];
            $resumen[$clave] ??= [$antes, $c['atributos']['marca'], 0, ($c['orden']->atributos['modelo'] ?? '').' → '.$c['atributos']['modelo']];
            $resumen[$clave][2]++;
        }
        ksort($resumen);

        $this->table(['Marca actual', 'Queda', 'Registros', 'Ejemplo de modelo'], array_values($resumen));
        $this->info('Equipos a cambiar: '.$cambiosEquipos->count().' · Órdenes (marca escrita en la orden): '.$cambiosOrdenes->count());

        $this->line('Marcas que quedarían:');
        $finales = $equipos->map(fn (StEquipo $e) => self::normalizar($e->marca, $e->modelo)[0])->countBy()->sortKeys();
        $this->table(['Marca', 'Equipos'], $finales->map(fn ($n, $m) => [$m, $n])->values()->all());

        if (($cambiosEquipos->isEmpty() && $cambiosOrdenes->isEmpty()) || ! $this->option('aplicar')) {
            if ($cambiosEquipos->isNotEmpty() || $cambiosOrdenes->isNotEmpty()) {
                $this->warn('No se cambió nada. Vuelve a correr con --aplicar para guardar.');
            }

            return self::SUCCESS;
        }

        $archivo = 'respaldos/marcas_servicio_'.now()->format('Ymd_His').'.json';
        Storage::disk('local')->put($archivo, json_encode([
            'equipos' => $cambiosEquipos->map(fn ($c) => $c['equipo']->only(['id', 'marca', 'modelo']))->all(),
            'ordenes' => $cambiosOrdenes->map(fn ($c) => ['id' => $c['orden']->id, 'atributos' => $c['orden']->atributos])->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        $this->line('Respaldo: '.Storage::disk('local')->path($archivo));

        Auditoria::conMotivo('Normalización de marcas de servicio técnico', function () use ($cambiosEquipos, $cambiosOrdenes) {
            DB::transaction(function () use ($cambiosEquipos, $cambiosOrdenes) {
                foreach ($cambiosEquipos as $c) {
                    $c['equipo']->update(['marca' => $c['marca'], 'modelo' => $c['modelo']]);
                }
                foreach ($cambiosOrdenes as $c) {
                    $c['orden']->update(['atributos' => $c['atributos']]);
                }
            });
        });

        $this->info('Listo: '.$cambiosEquipos->count().' equipos y '.$cambiosOrdenes->count().' órdenes actualizados.');

        return self::SUCCESS;
    }
}
