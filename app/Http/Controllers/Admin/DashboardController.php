<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MovimientoQueryService;
use App\Services\ProductRepository;
use App\Services\VentasCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ProductRepository $products, MovimientoQueryService $movimientos, VentasCalculator $ventas): View
    {
        $sede = config('inventario.sedes_locales')[0] ?? config('inventario.sedes_stock')[0] ?? 'CENTRO';
        $proveedor = trim((string) $request->query('proveedor', ''));
        $tp = (float) config('inventario.tiempo_pronostico_default');

        $calculated = $ventas->calcular($products->loadForSede($sede), $sede, $tp);
        $proveedores = $calculated->pluck('proveedor')->filter()->unique()->sort()->values()->all();

        if ($proveedor !== '') {
            $calculated = $calculated->filter(fn (array $row) => ($row['proveedor'] ?? '') === $proveedor)->values();
        }

        $recomendados = $calculated
            ->map(function (array $row) use ($tp) {
                $totalStock = array_sum($row['stocks'] ?? []);
                $totalDemand = (int) round(collect($row['ventas_internas'] ?? [])->sum() / 60 * $tp);
                $suggested = max(0, $totalDemand - $totalStock);

                return array_merge($row, [
                    'accion' => 'COMPRAR',
                    'total_stock' => $totalStock,
                    'total_demanda' => $totalDemand,
                    'sugerido' => $suggested,
                ]);
            })
            ->filter(fn (array $row) => ($row['sugerido'] ?? 0) > 0)
            ->sortByDesc(fn (array $row) => $row['sugerido'] ?? 0)
            ->take(20)
            ->values();

        return view('admin.dashboard', [
            'productCount' => $this->productCount($products),
            'movementStats' => $movimientos->stats(),
            'lastImport' => $products->lastStockUpdate(),
            'recomendados' => $recomendados,
            'proveedores' => $proveedores,
            'selectedProveedor' => $proveedor,
            'recommendedProductCount' => $recomendados->count(),
            'recommendedTotalUnits' => $recomendados->sum('sugerido'),
        ]);
    }

    private function productCount(ProductRepository $products): int
    {
        if (config('database.default') === 'pgsql') {
            return \App\Models\V2\Producto::query()->where('activo', true)->count();
        }

        return \App\Models\Product::query()->count();
    }
}
