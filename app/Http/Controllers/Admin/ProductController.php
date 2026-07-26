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
                'p.id', 'p.codigo', 'p.nombre as producto', 'p.categoria', 'p.proveedor',
                'sa.existencia as stock',
                'vh.ultima_venta', 'vh.ultima_compra', 'vh.venta_promedio', 'vh.ventas_60d'
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
                    'id' => $product->id,
                    'codigo' => $product->cod_centro,
                    'producto' => $product->producto,
                    'categoria' => $product->categoria,
                    'proveedor' => $product->proveedor,
                    'stock' => $metric ? $metric->existencia : 0,
                    'ultima_venta' => $metric ? $metric->ultima_venta : null,
                    'ultima_compra' => null, // Not tracked in old sqlite model
                    'venta_promedio' => 0,
                    'ventas_60d' => 0,
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

        if (config('database.default') === 'pgsql') {
            $categoriasTree = DB::connection('pgsql')->table('inventario_v2.productos')
                ->select('categoria', 'subcategoria')
                ->where('activo', true)
                ->whereNotNull('categoria')
                ->where('categoria', '!=', '')
                ->distinct()
                ->get()
                ->groupBy('categoria')
                ->map(function ($items) {
                    return $items->pluck('subcategoria')->filter(function ($val) { return $val !== null && $val !== ''; })->values()->toArray();
                })->toArray();
        } else {
            $categoriasTree = Product::whereNotNull('categoria')->where('categoria', '!=', '')->distinct()->pluck('categoria')
                ->mapWithKeys(function ($cat) {
                    return [$cat => []];
                })->toArray();
        }

        return view('admin.productos.index', [
            'rows' => $paginator,
            'search' => $search,
            'sede' => $sede,
            'sedes' => $sedes,
            'sedeSeleccionada' => $sede,
            'buscar' => $search,
            'categoriasTree' => $categoriasTree,
        ]);
    }

    public function destroy($id)
    {
        if (config('database.default') === 'pgsql') {
            $producto = Producto::find($id);
            if ($producto) {
                // Delete related records to prevent foreign key constraint violations
                DB::connection('pgsql')->table('inventario_v2.stock_actual')->where('producto_id', $id)->delete();
                DB::connection('pgsql')->table('inventario_v2.ventas_historicas')->where('producto_id', $id)->delete();
                DB::connection('pgsql')->table('inventario_v2.historial_ventas_mensuales')->where('producto_id', $id)->delete();
                DB::connection('pgsql')->table('movimientos')->where('producto_id', $id)->delete();
                
                // Now delete the product
                $producto->delete();
            }
        } else {
            $product = Product::find($id);
            if ($product) {
                $product->delete();
            }
        }

        return redirect()->back()->with('success', 'Producto eliminado exitosamente.');
    }

    public function exportJson(Request $request)
    {
        $categorias = $request->input('categorias', []);
        $subcategorias = $request->input('subcategorias', []);
        $conExistencia = $request->input('con_existencia', 0);

        if (config('database.default') === 'pgsql') {
            // PostgreSQL: fetch all active products with global existencia (sum across all sedes)
            $query = DB::connection('pgsql')
                ->table('inventario_v2.productos as p')
                ->leftJoin(
                    DB::connection('pgsql')->raw('(
                        SELECT producto_id, SUM(existencia) as existencia_global
                        FROM inventario_v2.stock_actual
                        GROUP BY producto_id
                    ) as sg'),
                    'p.id', '=', 'sg.producto_id'
                )
                ->where('p.activo', true);

            if (!empty($categorias)) {
                $query->whereIn('p.categoria', $categorias);
            }
            if (!empty($subcategorias)) {
                $query->whereIn('p.subcategoria', $subcategorias);
            }
            if ($conExistencia) {
                $query->whereRaw('COALESCE(sg.existencia_global, 0) > 0');
            }

            $productos = $query->orderBy('p.nombre')
                ->select([
                    'p.id',
                    'p.codigo',
                    'p.nombre',
                    'p.categoria',
                    'p.subcategoria',
                    'p.proveedor',
                    'p.precio_unidad',
                    'p.precio_mayor',
                    'p.url_imagen',
                    DB::connection('pgsql')->raw('COALESCE(sg.existencia_global, 0) as existencia_global'),
                ])
                ->get()
                ->map(function ($p) {
                    return $p;
                });
        } else {
            // SQLite fallback
            $query = Product::with('sedeMetrics');

            if (!empty($categorias)) {
                $query->whereIn('categoria', $categorias);
            }
            
            $productos = $query->get()->map(function($p) {
                return (object)[
                    'id'               => $p->id,
                    'codigo'           => $p->cod_centro,
                    'nombre'           => $p->producto,
                    'categoria'        => $p->categoria,
                    'subcategoria'     => '',
                    'proveedor'        => $p->proveedor ?? '',
                    'precio_unidad'    => 0,
                    'precio_mayor'     => 0,
                    'existencia_global' => $p->sedeMetrics->sum('existencia'),
                    'url_imagen'       => $p->url_imagen ?? '',
                ];
            });

            if ($conExistencia) {
                $productos = $productos->filter(function($p) {
                    return $p->existencia_global > 0;
                })->values();
            }
        }

        $output = $productos->map(function($p) {
            $cat = trim($p->categoria ?? '');
            $sub = trim($p->subcategoria ?? '');
            $categories = $cat;
            if ($sub && $sub !== $cat) {
                $categories = $cat . ',' . $sub;
            }

            $urlImagen = $p->url_imagen ?? '';
            if (empty($urlImagen) && !empty($p->codigo)) {
                $codigos = explode('/', $p->codigo);
                if (count($codigos) === 1) {
                    $codigos = explode(' ', $p->codigo);
                }
                $primary_code = trim($codigos[0]);
                if ($primary_code) {
                    $urlImagen = "https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/object/public/imagenes_producto/imagenes/" . rawurlencode($primary_code) . ".jpg";
                }
            }

            return [
                'id'                  => (int) $p->id,
                'codigo'              => $p->codigo ?? '',
                'descripcion'         => trim($p->nombre ?? ''),
                'descripcion_ampliada' => null,
                'precio1'             => (float) ($p->precio_unidad ?? 0),
                'precio2'             => (float) ($p->precio_unidad ?? 0),
                'precio3'             => (float) ($p->precio_mayor ?? 0),
                'existencia'          => (float) ($p->existencia_global ?? 0),
                'url_imagen'          => $urlImagen,
                'categories'          => strtoupper($categories),
                'codigo_padre'        => null,
                'atributo'            => null,
                'termino'             => null,
            ];
        })->values();

        $filename = 'productos_' . date('Y-m-d_His') . '.json';
        $json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type'        => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($json),
        ]);
    }
}
