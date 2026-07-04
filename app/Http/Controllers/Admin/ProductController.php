<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\V2\Producto;
use App\Models\Product;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $sedes = config('inventario.sedes_stock');
        $sede = strtoupper($request->query('sede', $sedes[0] ?? ''));
        $search = $request->query('buscar', '');

        $perPage = 50;
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;

        if (config('database.default') === 'pgsql') {
            $query = DB::connection('pgsql')->table('inventario_v2.productos as p')
                ->where('p.activo', true);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('p.codigo', 'ilike', '%' . $search . '%')
                      ->orWhere('p.nombre', 'ilike', '%' . $search . '%');
                });
            }

            // Subqueries to fetch stock and dates for the specific sede
            $query->leftJoin('inventario_v2.stock_actual as sa', function($join) use ($sede) {
                $join->on('p.id', '=', 'sa.producto_id')
                     ->where('sa.sede', '=', $sede);
            });
            $query->leftJoin('inventario_v2.ventas_historicas as vh', function($join) use ($sede) {
                $join->on('p.id', '=', 'vh.producto_id')
                     ->where('vh.sede', '=', $sede);
            });

            $total = $query->count();
            
            $query->select([
                'p.codigo', 'p.nombre as producto', 'p.categoria', 'p.proveedor',
                'sa.existencia as stock',
                'vh.ultima_venta', 'vh.ultima_compra'
            ]);
            $query->orderBy('p.nombre');
            
            $items = $query->forPage($page, $perPage)->get();
            
            // Convert to Collection
            $items = collect($items)->map(function ($item) {
                return (array) $item;
            });
        } else {
            // SQLite fallback
            $query = Product::query()->with(['sedeMetrics' => function($q) use ($sede) {
                $q->where('sede', $sede);
            }]);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('cod_centro', 'like', '%' . $search . '%')
                      ->orWhere('producto', 'like', '%' . $search . '%');
                });
            }

            $total = $query->count();
            $query->orderBy('producto');
            
            $items = $query->forPage($page, $perPage)->get()->map(function($product) {
                $metric = $product->sedeMetrics->first();
                return [
                    'codigo' => $product->cod_centro,
                    'producto' => $product->producto,
                    'categoria' => $product->categoria,
                    'proveedor' => $product->proveedor,
                    'stock' => $metric ? $metric->existencia : 0,
                    'ultima_venta' => $metric ? $metric->ultima_venta : null,
                    'ultima_compra' => null, // Not tracked in old sqlite model
                ];
            });
        }

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.productos.index', [
            'rows' => $paginator,
            'sedes' => $sedes,
            'sedeSeleccionada' => $sede,
            'buscar' => $search
        ]);
    }
}
