<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\V2\Producto;
use App\Services\ProductoEdicionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private ProductoEdicionService $ediciones,
    ) {
    }

    public function index(Request $request)
    {
        $sedes = config('inventario.sedes_stock');
        $sede = strtoupper($request->query('sede', $sedes[0] ?? ''));
        $search = $request->query('buscar', '');
        $filtroOculto = $request->query('oculto', ''); // '' = visibles, '1' = ocultos

        $perPage = 50;
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;

        if (config('database.default') === 'pgsql') {
            $query = DB::connection('pgsql')->table('inventario_v2.productos as p')
                ->where('p.activo', true);

            // Filtro oculto: '' = solo visibles (default), '1' = solo ocultos (flag global, no por sede)
            if ($filtroOculto == 1) {
                $query->where('p.oculto', true);
            } else {
                $query->where(fn ($q) => $q->where('p.oculto', false)->orWhereNull('p.oculto'));
            }

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
                'p.id', 'p.codigo', 'p.nombre as producto', 'p.categoria', 'p.proveedor', 'p.oculto',
                'sa.existencia as stock',
                'vh.ultima_venta', 'vh.ultima_compra', 'vh.venta_promedio', 'vh.ventas_60d'
            ]);
            $query->orderBy('p.nombre');
            
            \Illuminate\Support\Facades\Log::info('ProductController Index Query', [
                'filtroOculto' => $filtroOculto,
                'total' => $total,
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            $items = $query->forPage($page, $perPage)->get();
            
            // Convert to Collection + aplicar ediciones manuales (prioridad sobre sync)
            $items = $this->ediciones->aplicarAColeccion(
                collect($items)->map(fn ($item) => (array) $item)
            );
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
                ->where(fn ($q) => $q->where('oculto', false)->orWhereNull('oculto'))
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
            'rows'             => $paginator,
            'search'           => $search,
            'sede'             => $sede,
            'sedes'            => $sedes,
            'sedeSeleccionada' => $sede,
            'buscar'           => $search,
            'categoriasTree'   => $categoriasTree,
            'filtroOculto'     => $filtroOculto,
        ]);
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        if (config('database.default') !== 'pgsql') {
            return redirect()->route('admin.productos.index')->with('error', 'Producto no disponible.');
        }

        $producto = Producto::query()->find($id);
        if (! $producto) {
            return redirect()->route('admin.productos.index')->with('error', 'Producto no encontrado.');
        }

        $sede = strtoupper((string) $request->query('sede', config('inventario.sedes_stock')[0] ?? ''));
        $efectivo = $this->ediciones->efectivo($producto);
        $stocks = DB::connection('pgsql')->table('inventario_v2.stock_actual')
            ->where('producto_id', $producto->id)
            ->orderBy('sede')
            ->get(['sede', 'existencia']);

        return view('admin.productos.show', [
            'producto' => $producto,
            'efectivo' => $efectivo,
            'sede' => $sede,
            'sedes' => config('inventario.sedes_stock'),
            'stocks' => $stocks,
            'editando' => $request->boolean('editar'),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if (config('database.default') !== 'pgsql') {
            return redirect()->route('admin.productos.index')->with('error', 'Producto no disponible.');
        }

        $producto = Producto::query()->find($id);
        if (! $producto) {
            return redirect()->route('admin.productos.index')->with('error', 'Producto no encontrado.');
        }

        $data = $request->validate([
            'nombre' => ['nullable', 'string', 'max:2000'],
            'categoria' => ['nullable', 'string', 'max:256'],
            'subcategoria' => ['nullable', 'string', 'max:256'],
            'proveedor' => ['nullable', 'string', 'max:256'],
            'precio_unidad' => ['nullable', 'numeric', 'min:0'],
            'precio_mayor' => ['nullable', 'numeric', 'min:0'],
            'lote_inicial' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'url_imagen' => ['nullable', 'string', 'max:500'],
            'descripcion_ampliada' => ['nullable', 'string', 'max:5000'],
            'sede' => ['nullable', 'string', 'max:32'],
        ]);

        $this->ediciones->guardar($producto, $data, auth()->id());

        Cache::forget('catalogo_categorias');
        Cache::forget('catalogo_subcategorias');
        Cache::forget('catalogo_cat_subcat_map');

        return redirect()
            ->route('admin.productos.show', ['id' => $producto->id, 'sede' => $data['sede'] ?? null])
            ->with('success', 'Edición guardada. El catálogo usa estos datos; el sincronizador no los pisa.');
    }

    public function restaurarSync(Request $request, int $id): RedirectResponse
    {
        if (config('database.default') !== 'pgsql') {
            return redirect()->route('admin.productos.index')->with('error', 'Producto no disponible.');
        }

        $producto = Producto::query()->find($id);
        if (! $producto) {
            return redirect()->route('admin.productos.index')->with('error', 'Producto no encontrado.');
        }

        $this->ediciones->restaurar($producto);

        return redirect()
            ->route('admin.productos.show', ['id' => $producto->id, 'sede' => $request->input('sede')])
            ->with('success', 'Se restauró la información del sincronizador (se borró la edición manual).');
    }

    /**
     * Oculta o restaura un producto en todas las sedes, catálogos y vistas.
     */
    public function toggleOculto($id)
    {
        if (config('database.default') !== 'pgsql') {
            return redirect()->back()->with('error', 'Producto no encontrado.');
        }

        $producto = Producto::find($id);
        if (! $producto) {
            return redirect()->back()->with('error', 'Producto no encontrado.');
        }

        $producto->oculto = ! (bool) $producto->oculto;
        $producto->save();

        // Invalida cachés de catálogo / inventario para que deje de verse de inmediato.
        Cache::forget('inventario_v2.productos_updated_at');
        Cache::forget('catalogo_categorias');
        Cache::forget('catalogo_subcategorias');
        Cache::forget('catalogo_cat_subcat_map');
        Cache::forget('existencias_categorias');
        Cache::forget('existencias_proveedores');
        Cache::forget('vendedor_jrz_proveedores');
        Cache::forget('dashboard.productos_activos');

        $label = $producto->oculto
            ? 'ocultado en todos los catálogos y vistas'
            : 'restaurado (vuelve a verse en catálogos)';

        return redirect()->back()->with('success', "Producto {$label}.");
    }

    public function exportJson(Request $request)
    {
        $categorias = $request->input('categorias', []);
        $subcategorias = $request->input('subcategorias', []);
        $conExistencia = $request->input('con_existencia', 0);

        if (config('database.default') === 'pgsql') {
            // PostgreSQL: fetch all active, visible products with global existencia
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
                ->where('p.activo', true)
                ->where(fn ($q) => $q->where('p.oculto', false)->orWhereNull('p.oculto'));

            if (!empty($categorias)) {
                $query->whereIn('p.categoria', $categorias);
            }
            if (!empty($subcategorias)) {
                $query->whereIn('p.subcategoria', $subcategorias);
            }
            if ($conExistencia) {
                $query->whereRaw('COALESCE(sg.existencia_global, 0) > 0');
            }

            $productos = $this->ediciones->aplicarAColeccion(
                $query->orderBy('p.nombre')
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
            );
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

            return [
                'id'                  => (int) $p->id,
                'codigo'              => trim(explode('/', $p->codigo ?? '')[0]),
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
