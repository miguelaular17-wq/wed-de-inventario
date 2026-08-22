<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\DB;

class InventoryBreakReportService
{
    /**
     * @return LazyCollection<int, array<string, int|float|string|null>>
     */
    public function generate(int $minimumStock, int $days, ?string $branch = null): LazyCollection
    {
        $endDate = CarbonImmutable::today();
        $startDate = $endDate->subDays($days - 1);
        $branches = config('inventario.sedes_stock', []);

        if ($branch !== null) {
            $branches = [$branch];
        }

        $sales = DB::connection('pgsql')
            ->table('ventas_detalle as vd')
            ->select(['vd.producto_id', 'vd.sede'])
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'FAC' THEN ABS(vd.cantidad) WHEN vd.tipo_documento = 'DEV' THEN -ABS(vd.cantidad) ELSE 0 END) AS total_vendido")
            ->whereBetween('vd.fecha', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereIn('vd.tipo_documento', ['FAC', 'DEV'])
            ->where('vd.anulado', false)
            ->groupBy('vd.producto_id', 'vd.sede');

        $rows = DB::connection('pgsql')
            ->table('productos as p')
            ->join('stock_actual as sa', 'sa.producto_id', '=', 'p.id')
            ->leftJoinSub($sales, 'ventas_periodo', function ($join) {
                $join->on('ventas_periodo.producto_id', '=', 'p.id')
                    ->on('ventas_periodo.sede', '=', 'sa.sede');
            })
            ->where('p.activo', true)
            ->whereIn('sa.sede', $branches)
            ->where('sa.existencia', '<=', $minimumStock)
            ->where(function ($query) {
                $query->whereNull('p.excluir_compras')
                    ->orWhere('p.excluir_compras', false);
            })
            ->orderByRaw('COALESCE(ventas_periodo.total_vendido, 0) DESC')
            ->orderBy('sa.existencia')
            ->orderBy('p.codigo')
            ->orderBy('sa.sede')
            ->select([
                'p.codigo',
                'p.nombre',
                'sa.sede',
                'sa.existencia as stock_actual',
                DB::raw('COALESCE(ventas_periodo.total_vendido, 0) AS total_vendido'),
            ])
            ->cursor();

        return $rows->map(function ($row) {
            return [
                'codigo' => (string) $row->codigo,
                'producto' => (string) $row->nombre,
                'sede' => (string) $row->sede,
                'stock_actual' => (int) $row->stock_actual,
                'total_vendido' => max(0, (int) $row->total_vendido),
            ];
        });
    }
}
