<?php

namespace App\Console\Commands;

use App\Models\VentaDiariaMeta;
use App\Services\VentasDiariasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga las metas mensuales por tienda como en la pantalla «Metas de tienda»: guarda venta histórica,
 * venta meta, productos y clientes, y recalcula las metas diarias (lunes a sábado y domingo) del período.
 * Cada --sede va como SEDE:venta_historica:venta_meta:productos:clientes.
 */
class CargarMetasTiendaCommand extends Command
{
    protected $signature = 'metas:cargar-tiendas
        {periodo : Ej. "META MES NOVIEMBRE 2026"}
        {--sede=* : SEDE:venta_historica:venta_meta:productos:clientes}
        {--aplicar : Guarda los cambios (sin esto solo muestra qué quedaría)}';

    protected $description = 'Carga las metas mensuales de las tiendas y recalcula las metas diarias.';

    public function handle(VentasDiariasService $ventasDiarias): int
    {
        // El pooler de Supabase (modo transacción) no conserva el search_path de la sesión entre consultas.
        return DB::transaction(function () use ($ventasDiarias) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('set local search_path to '.config('database.connections.pgsql.search_path'));
            }

            return $this->cargar($ventasDiarias);
        });
    }

    private function cargar(VentasDiariasService $ventasDiarias): int
    {
        $periodo = mb_strtoupper(trim((string) $this->argument('periodo')), 'UTF-8');
        $cal = $ventasDiarias->calendarioDelPeriodo($periodo);
        $this->line('Base de datos: '.config('database.connections.'.config('database.default').'.host').' / '.config('database.connections.'.config('database.default').'.database'));
        $this->line("Período: {$periodo} · {$cal['dias']} días, {$cal['domingos']} domingos");

        $filas = [];
        foreach ((array) $this->option('sede') as $linea) {
            $partes = explode(':', (string) $linea);
            if (count($partes) !== 5) {
                $this->error("Formato inválido: {$linea}");

                return self::FAILURE;
            }
            [$sede, $hist, $meta, $prod, $cli] = $partes;
            $sede = mb_strtoupper(trim($sede), 'UTF-8');
            $venta = $ventasDiarias->metaVentaDiaria((float) $meta, $cal['dias'], $cal['domingos']);
            $productos = $ventasDiarias->metaVentaDiaria((float) $prod, $cal['dias'], $cal['domingos']);
            $actual = VentaDiariaMeta::query()->where('sede', $sede)->first();

            $filas[] = [
                'modelo' => $actual,
                'sede' => $sede,
                'datos' => [
                    'meta_venta_lv_sab' => $venta['diaria'],
                    'meta_venta_domingo' => $venta['domingo'],
                    'meta_prod_lv_sab' => $productos['diaria'],
                    'meta_prod_domingo' => $productos['domingo'],
                    'venta_historica' => (float) $hist,
                    'venta_meta_mes' => (float) $meta,
                    'productos_meta_mes' => (float) $prod,
                    'clientes_meta_mes' => (float) $cli,
                    'periodo_label' => $periodo,
                ],
            ];
        }

        $this->table(
            ['Sede', 'Antes (meta mes)', 'Venta 2025', 'Venta meta', 'Productos', 'Clientes', 'Diaria L-S', 'Domingo', 'Prod. diarios'],
            array_map(fn ($f) => [
                $f['sede'].($f['modelo'] ? '' : ' (nueva)'),
                $f['modelo'] ? number_format((float) $f['modelo']->venta_meta_mes, 2, ',', '.').' · '.$f['modelo']->periodo_label : '—',
                number_format($f['datos']['venta_historica'], 2, ',', '.'),
                number_format($f['datos']['venta_meta_mes'], 2, ',', '.'),
                number_format($f['datos']['productos_meta_mes'], 0, ',', '.'),
                number_format($f['datos']['clientes_meta_mes'], 0, ',', '.'),
                number_format($f['datos']['meta_venta_lv_sab'], 2, ',', '.'),
                number_format($f['datos']['meta_venta_domingo'], 2, ',', '.'),
                number_format($f['datos']['meta_prod_lv_sab'], 2, ',', '.'),
            ], $filas)
        );

        $sinFila = VentaDiariaMeta::query()->whereNotIn('sede', array_column($filas, 'sede'))->pluck('sede')->all();
        if ($sinFila) {
            $this->warn('Estas sedes tienen meta y no vienen en la carga (no se tocan): '.implode(', ', $sinFila));
        }

        if (! $this->option('aplicar')) {
            $this->warn('No se cambió nada. Vuelve a correr con --aplicar para guardar.');

            return self::SUCCESS;
        }

        foreach ($filas as $f) {
            ($f['modelo'] ?? new VentaDiariaMeta(['sede' => $f['sede']]))->fill($f['datos'])->save();
        }
        $this->info(count($filas).' metas de tienda guardadas.');

        return self::SUCCESS;
    }
}
