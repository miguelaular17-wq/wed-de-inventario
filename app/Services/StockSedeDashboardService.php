<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockSedeDashboardService
{
    /**
     * Existencia actual por sede y por SKU. No usa ventas.
     *
     * @return array{
     *     sedes: list<string>,
     *     por_sede: list<array{sede:string,nombre:string,unidades:int,skus:int}>,
     *     totales: array{unidades:int,skus:int,sedes:int}
     * }
     */
    public function resumen(): array
    {
        $sedes = array_values(array_filter(
            config('inventario.sedes_stock', []),
            fn ($sedeConfig) => is_string($sedeConfig) && preg_match('/^[A-Z0-9_]+$/', $sedeConfig)
        ));

        $porSede = [];
        foreach ($sedes as $codigo) {
            $porSede[$codigo] = [
                'sede' => $codigo,
                'nombre' => config('inventario.display.'.$codigo, $codigo),
                'unidades' => 0,
                'skus' => 0,
                'valorizado' => 0.0,
                'traslado' => 0,
            ];
        }

        $vacio = [
            'sedes' => $sedes,
            'por_sede' => array_values($porSede),
            'totales' => ['unidades' => 0, 'skus' => 0, 'sedes' => 0, 'valorizado' => 0.0, 'traslado' => 0],
        ];

        if (! Schema::hasTable('stock_actual') || $sedes === []) {
            return $vacio;
        }

        $tieneCosto = Schema::hasTable('productos') && Schema::hasColumn('productos', 'costo_actual');
        $resumen = DB::table('stock_actual as sa')
            ->where('sa.existencia', '>', 0)
            ->whereIn(DB::raw('UPPER(TRIM(sa.sede))'), $sedes)
            ->selectRaw('UPPER(TRIM(sa.sede)) as sede')
            ->selectRaw('SUM(sa.existencia) as unidades')
            ->selectRaw('COUNT(DISTINCT sa.producto_id) as skus')
            ->selectRaw($tieneCosto
                ? 'SUM(sa.existencia * COALESCE(p.costo_actual, 0)) as valorizado'
                : 'SUM(0) as valorizado')
            ->groupBy(DB::raw('UPPER(TRIM(sa.sede))'));
        if ($tieneCosto) {
            $resumen->leftJoin('productos as p', 'p.id', '=', 'sa.producto_id');
        }

        foreach ($resumen->get() as $row) {
            $codigo = (string) $row->sede;
            if (! isset($porSede[$codigo])) {
                continue;
            }
            $porSede[$codigo]['unidades'] = (int) $row->unidades;
            $porSede[$codigo]['skus'] = (int) $row->skus;
            $porSede[$codigo]['valorizado'] = round((float) $row->valorizado, 2);
        }

        foreach ($this->trasladosPorSede($sedes) as $codigo => $cantidad) {
            if (isset($porSede[$codigo])) {
                $porSede[$codigo]['traslado'] = $cantidad;
            }
        }

        $conStock = array_filter($porSede, fn (array $fila) => $fila['unidades'] > 0);

        return [
            'sedes' => $sedes,
            'por_sede' => array_values($porSede),
            'totales' => [
                'unidades' => (int) array_sum(array_column($porSede, 'unidades')),
                'skus' => (int) DB::table('stock_actual')
                    ->where('existencia', '>', 0)
                    ->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes)
                    ->distinct()
                    ->count('producto_id'),
                'sedes' => count($conStock),
                'valorizado' => round((float) array_sum(array_column($porSede, 'valorizado')), 2),
                'traslado' => (int) array_sum(array_column($porSede, 'traslado')),
            ],
        ];
    }

    /**
     * Unidades en requisiciones todavía no aplicadas, hacia cada sede.
     *
     * @param  list<string>  $sedes
     * @return array<string, int>
     */
    private function trasladosPorSede(array $sedes): array
    {
        if (! Schema::hasTable('requisiciones_manuales') || ! Schema::hasColumn('requisiciones_manuales', 'cantidad')) {
            return [];
        }

        $query = DB::table('requisiciones_manuales')
            ->whereIn(DB::raw('UPPER(TRIM(sede_local))'), $sedes)
            ->selectRaw('UPPER(TRIM(sede_local)) as sede')
            ->selectRaw('SUM(cantidad) as unidades')
            ->groupBy(DB::raw('UPPER(TRIM(sede_local))'));

        if (Schema::hasColumn('requisiciones_manuales', 'aplicada_at')) {
            $query->whereNull('aplicada_at');
        }

        $out = [];
        foreach ($query->get() as $row) {
            $out[(string) $row->sede] = (int) $row->unidades;
        }

        return $out;
    }
}
