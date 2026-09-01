<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesCollections;
use App\Services\ProductRepository;
use App\Services\RequisicionPersonalizadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventarioController extends Controller
{
    use PaginatesCollections;

    public function __construct(
        private ProductRepository $products,
        private RequisicionPersonalizadaService $reqPersonalizada,
    ) {}

    /**
     * Returns the email to use as user-scope filter for requisiciones.
     * Admins and gerentes see the full sede; everyone else sees only their own.
     */
    private function scopedUsuario(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }
        if ($user->isAdmin() || $user->isGerente()) {
            return null; // sin filtro: ven todas las requisiciones de la sede
        }
        return $user->email;
    }

    public function index(Request $request): View
    {
        ini_set('memory_limit', '512M');
        $viewData = $this->buildIndexData($request);

        if ($request->header('X-Partial') === 'content') {
            return view('inventario._content', $viewData);
        }

        return view('inventario.index', $viewData);
    }

    private function buildIndexData(Request $request): array
    {
        $sede = (string) $request->session()->get('sede_local');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'categoria' => (string) $request->query('categoria', 'Ninguno'),
            'subcategoria' => (string) $request->query('subcategoria', 'Ninguno'),
        ];

        if (config('database.default') === 'pgsql') {
            $page = (int) $request->query('page', 1);
            $perPage = (int) config('inventario.per_page', 75);
            if ($perPage <= 0) $perPage = 75;

            $whereClauses = ["p.activo = true", "(p.oculto = false OR p.oculto IS NULL)"];
            $bindings = ['sedeLocal' => $sede];

            if ($filters['categoria'] !== 'Ninguno') {
                $whereClauses[] = "p.categoria = :categoria";
                $bindings['categoria'] = $filters['categoria'];
            }
            if ($filters['subcategoria'] !== 'Ninguno') {
                $whereClauses[] = "p.subcategoria = :subcategoria";
                $bindings['subcategoria'] = $filters['subcategoria'];
            }
            if ($filters['q'] !== '') {
                $whereClauses[] = "(LOWER(p.codigo) LIKE :search OR LOWER(p.nombre) LIKE :search)";
                $bindings['search'] = '%' . mb_strtolower($filters['q']) . '%';
            }

            if (auth()->check() && auth()->user()->isTelefonia()) {
                $whereClauses[] = 'LOWER(p.categoria) IN ('.$this->sqlCategoriasTelefonia().')';
            }

            $whereSql = implode(' AND ', $whereClauses);

            // 1. Get total count
            $countSql = "
                SELECT COUNT(*) as total_count 
                FROM inventario_v2.productos p 
                WHERE {$whereSql} AND EXISTS (
                    SELECT 1 FROM inventario_v2.stock_actual sa 
                    WHERE sa.producto_id = p.id AND sa.sede != :sedeLocal AND sa.existencia > 0
                )
            ";
            $totalCountRow = \Illuminate\Support\Facades\DB::connection('pgsql')->selectOne($countSql, $bindings);
            $totalCount = $totalCountRow ? (int) $totalCountRow->total_count : 0;

            // 2. Get paginated items
            $offset = ($page - 1) * $perPage;
            $itemsSql = "
                SELECT p.id, p.codigo, p.nombre, p.categoria, p.subcategoria, p.proveedor, p.precio_unidad, p.precio_mayor, p.url_imagen
                FROM inventario_v2.productos p 
                WHERE {$whereSql} AND EXISTS (
                    SELECT 1 FROM inventario_v2.stock_actual sa 
                    WHERE sa.producto_id = p.id AND sa.sede != :sedeLocal AND sa.existencia > 0
                )
                ORDER BY p.codigo 
                LIMIT {$perPage} OFFSET {$offset}
            ";
            $dbItems = \Illuminate\Support\Facades\DB::connection('pgsql')->select($itemsSql, $bindings);

            // 3. Load stock and sales metrics for page items
            $products = collect();
            if (count($dbItems) > 0) {
                $productIds = array_map(fn($item) => (int) $item->id, $dbItems);
                $sedes = config('inventario.sedes_stock');

                $dbStocks = \Illuminate\Support\Facades\DB::connection('pgsql')
                    ->table('stock_actual')
                    ->whereIn('producto_id', $productIds)
                    ->get(['producto_id', 'sede', 'existencia']);
                
                $stocksByProduct = [];
                foreach ($dbStocks as $row) {
                    $stocksByProduct[(int) $row->producto_id][$row->sede] = (int) $row->existencia;
                }

                $dbVentas = \Illuminate\Support\Facades\DB::connection('pgsql')
                    ->table('ventas_historicas')
                    ->whereIn('producto_id', $productIds)
                    ->get(['producto_id', 'sede', 'venta_promedio', 'ventas_60d', 'ultima_venta', 'ultima_compra']);

                $ventasByProduct = [];
                foreach ($dbVentas as $row) {
                    $ventasByProduct[(int) $row->producto_id][$row->sede] = [
                        'venta_promedio' => (float) $row->venta_promedio,
                        'ventas_60d' => (float) $row->ventas_60d,
                        'ultima_venta' => $row->ultima_venta,
                        'ultima_compra' => $row->ultima_compra,
                    ];
                }

                foreach ($dbItems as $item) {
                    $productId = (int) $item->id;
                    $stockMap = $stocksByProduct[$productId] ?? [];
                    $ventaMap = $ventasByProduct[$productId] ?? [];
                    $localVenta = $ventaMap[$sede] ?? null;

                    $stocks = [];
                    $ventasInternas = [];
                    $ventasInternas15d = [];
                    $ultimasVentas = [];
                    $ultimasCompras = [];
                    foreach ($sedes as $s) {
                        $ventaSede = $ventaMap[$s] ?? null;
                        $stocks[$s] = $stockMap[$s] ?? 0;
                        $ventasInternas[$s] = $ventaSede ? (int) $ventaSede['ventas_60d'] : 0;
                        $ventasInternas15d[$s] = $ventaSede ? (float) $ventaSede['venta_promedio'] : 0;
                        
                        $uv = $ventaSede['ultima_venta'] ?? null;
                        $ultimasVentas[$s] = $uv ? date('d/m/Y', strtotime((string) $uv)) : null;
                        $uc = $ventaSede['ultima_compra'] ?? null;
                        $ultimasCompras[$s] = $uc ? date('d/m/Y', strtotime((string) $uc)) : null;
                    }

                    $ultimaVenta = $localVenta['ultima_venta'] ?? null;
                    if ($ultimaVenta && ! is_string($ultimaVenta)) {
                        $ultimaVenta = (string) $ultimaVenta;
                    }

                    $products->push([
                        'id'              => $productId,
                        'cod_centro'      => $item->codigo,
                        'producto'        => $item->nombre,
                        'categoria'       => $item->categoria,
                        'subcategoria'    => $item->subcategoria,
                        'proveedor'       => $item->proveedor,
                        'url_imagen'      => $item->url_imagen ?? '',
                        'precio_unidad'   => (float) ($item->precio_unidad ?? 0),
                        'precio_mayor'    => (float) ($item->precio_mayor ?? 0),
                        'existencia'      => $stockMap[$sede] ?? 0,
                        'venta'           => $localVenta ? (float) $localVenta['venta_promedio'] : 0,
                        'ventas_60d'      => $localVenta ? (float) $localVenta['ventas_60d'] : 0.0,
                        'ultima_venta'    => $ultimaVenta ? date('d/m/Y', strtotime($ultimaVenta)) : null,
                        'stocks'          => $stocks,
                        'ventas_internas' => $ventasInternas,
                        'ventas_internas_15d' => $ventasInternas15d,
                        'ultimas_ventas'  => $ultimasVentas,
                        'ultimas_compras' => $ultimasCompras,
                    ]);
                }
            }

            // 4. Load manuales for these products (scoped by user when not admin)
            $manuales = $this->reqPersonalizada->loadManuales($sede, false, $this->scopedUsuario());
            $base = $this->reqPersonalizada->buildRows(
                $products,
                $sede,
                $manuales
            );

            $rows = new \Illuminate\Pagination\LengthAwarePaginator(
                $base,
                $totalCount,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            // 5. Query categories and subcategories for filter selectors (Cached using lastStockUpdate)
            $stockUpdatedAt = $this->products->lastStockUpdate();
            $stockUpdateMd5 = md5((string) $stockUpdatedAt);

            $isTelefonia = auth()->check() && auth()->user()->isTelefonia();
            $roleKey = $isTelefonia ? '_telefonia_v2' : '';

            $cacheKeyCat = "inventario_cats_{$sede}_{$stockUpdateMd5}{$roleKey}";
            $categorias = \Illuminate\Support\Facades\Cache::remember($cacheKeyCat, 1800, function () use ($sede, $isTelefonia) {
                $query = \Illuminate\Support\Facades\DB::connection('pgsql')
                    ->table('productos as p')
                    ->where('p.activo', true)
                    ->whereNotNull('p.categoria')
                    ->where('p.categoria', '!=', '')
                    ->whereExists(function ($q) use ($sede) {
                        $q->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('stock_actual as sa')
                            ->whereColumn('sa.producto_id', 'p.id')
                            ->where('sa.sede', '!=', $sede)
                            ->where('sa.existencia', '>', 0);
                    });

                if ($isTelefonia) {
                    $query->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(p.categoria)'), $this->categoriasTelefoniaLower());
                }

                return $query->distinct()
                    ->orderBy('p.categoria')
                    ->pluck('p.categoria')
                    ->all();
            });

            $cacheKeySubcat = "inventario_subcats_{$sede}_{$stockUpdateMd5}_" . md5($filters['categoria']) . $roleKey;
            $subcategorias = \Illuminate\Support\Facades\Cache::remember($cacheKeySubcat, 1800, function () use ($sede, $filters, $isTelefonia) {
                $subQuery = \Illuminate\Support\Facades\DB::connection('pgsql')
                    ->table('productos as p')
                    ->where('p.activo', true)
                    ->whereNotNull('p.subcategoria')
                    ->where('p.subcategoria', '!=', '')
                    ->whereExists(function ($query) use ($sede) {
                        $query->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('stock_actual as sa')
                            ->whereColumn('sa.producto_id', 'p.id')
                            ->where('sa.sede', '!=', $sede)
                            ->where('sa.existencia', '>', 0);
                    });
                    
                if ($filters['categoria'] !== 'Ninguno') {
                    $subQuery->where('p.categoria', $filters['categoria']);
                }

                if ($isTelefonia) {
                    $subQuery->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(p.categoria)'), $this->categoriasTelefoniaLower());
                }
                
                return $subQuery->distinct()
                    ->orderBy('p.subcategoria')
                    ->pluck('p.subcategoria')
                    ->all();
            });

            $sedesStock = collect(config('inventario.sedes_stock'))
                ->reject(fn ($s) => $s === $sede)
                ->values()
                ->all();

            $stockUpdatedAt = $this->products->lastStockUpdate();
            $manualUpdatedAt = $this->reqPersonalizada->lastUpdatedAt($sede, $this->scopedUsuario());
            $updatedAt = $stockUpdatedAt && $manualUpdatedAt
                ? max($stockUpdatedAt, $manualUpdatedAt)
                : ($stockUpdatedAt ?: $manualUpdatedAt);

            return [
                'sede' => $sede,
                'rows' => $rows,
                'filters' => $filters,
                'categorias' => $categorias,
                'subcategorias' => $subcategorias,
                'sedesOrigen' => $this->reqPersonalizada->sedesOrigen($sede),
                'sedesStock' => $sedesStock,
                'totalManual' => $this->reqPersonalizada->countPendientes($sede, $this->scopedUsuario()),
                'stockUpdatedAt' => $updatedAt,
            ];
        }

        $products = $this->products->loadForSede($sede);
        if ($filters['q'] !== '' || $filters['categoria'] !== 'Ninguno' || $filters['subcategoria'] !== 'Ninguno') {
            $qLower = mb_strtolower($filters['q']);
            $products = $products->filter(function (array $row) use ($filters, $qLower) {
                if ($filters['categoria'] !== 'Ninguno' && ($row['categoria'] ?? '') !== $filters['categoria']) {
                    return false;
                }
                if ($filters['subcategoria'] !== 'Ninguno' && ($row['subcategoria'] ?? '') !== $filters['subcategoria']) {
                    return false;
                }
                if ($qLower === '') {
                    return true;
                }

                return str_contains(mb_strtolower((string) ($row['cod_centro'] ?? '')), $qLower)
                    || str_contains(mb_strtolower((string) ($row['producto'] ?? '')), $qLower);
            })->values();
        }

        $sedesStock = collect(config('inventario.sedes_stock'))
            ->reject(fn ($s) => $s === $sede)
            ->values()
            ->all();

        $base = $this->reqPersonalizada->buildRows(
            $products,
            $sede,
            $this->reqPersonalizada->loadManuales($sede, false, $this->scopedUsuario()),
        );
        $rows = $this->paginateCollection(
            $this->reqPersonalizada->applyFilters($base, $filters),
            $request
        );
        $stockUpdatedAt = $this->products->lastStockUpdate();
        $manualUpdatedAt = $this->reqPersonalizada->lastUpdatedAt($sede, $this->scopedUsuario());
        $updatedAt = $stockUpdatedAt && $manualUpdatedAt
            ? max($stockUpdatedAt, $manualUpdatedAt)
            : ($stockUpdatedAt ?: $manualUpdatedAt);

        return [
            'sede' => $sede,
            'rows' => $rows,
            'filters' => $filters,
            'categorias' => $this->reqPersonalizada->categorias($base),
            'subcategorias' => $this->reqPersonalizada->subcategorias(
                $base,
                $filters['categoria'] !== 'Ninguno' ? $filters['categoria'] : null
            ),
            'sedesOrigen' => $this->reqPersonalizada->sedesOrigen($sede),
            'sedesStock' => $sedesStock,
            'totalManual' => $this->reqPersonalizada->countPendientes($sede, $this->scopedUsuario()),
            'stockUpdatedAt' => $updatedAt,
        ];
    }

    public function storeManual(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $sede = (string) $request->session()->get('sede_local');

        $data = $request->validate([
            'codigo' => ['required', 'string'],
            'producto' => ['required', 'string'],
            'sede_origen' => ['required', 'string'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->reqPersonalizada->confirmar(
                $sede,
                $data['codigo'],
                $data['producto'],
                $data['sede_origen'],
                (int) $data['cantidad'],
                auth()->user()?->email,
            );
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['manual' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Requisición guardada. El stock se aplicará al exportar el CSV.',
                'total_manual' => $this->reqPersonalizada->countPendientes($sede, $this->scopedUsuario()),
                'manuales_list' => $this->reqPersonalizada->getManualesListForProduct($sede, $data['codigo'], $this->scopedUsuario()),
            ]);
        }

        return redirect()
            ->route('inventario.index', [
                'q' => $request->query('q', $request->input('q')),
                'categoria' => $request->query('categoria', $request->input('categoria', 'Ninguno')),
                'subcategoria' => $request->query('subcategoria', $request->input('subcategoria', 'Ninguno')),
                'page' => $request->query('page', $request->input('page', 1)),
            ])
            ->with('status', 'Requisición guardada. El stock se aplicará al exportar el CSV.');
    }

    public function storeManualBatch(Request $request): JsonResponse
    {
        $sede = (string) $request->session()->get('sede_local');

        $data = $request->validate([
            'codigo' => ['required', 'string'],
            'producto' => ['required', 'string'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $userEmail = auth()->user()?->email;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($sede, $data, $userEmail) {
                foreach ($data['quantities'] as $origen => $qty) {
                    $qty = ($qty !== null && $qty !== '') ? (int) $qty : 0;
                    if ($qty > 0) {
                        $this->reqPersonalizada->confirmar(
                            $sede,
                            $data['codigo'],
                            $data['producto'],
                            $origen,
                            $qty,
                            $userEmail
                        );
                    } else {
                        $this->reqPersonalizada->eliminar($sede, $data['codigo'], $origen);
                    }
                }
            });
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Requisiciones actualizadas.',
            'total_manual' => $this->reqPersonalizada->countPendientes($sede, $this->scopedUsuario()),
            'manuales_list' => $this->reqPersonalizada->getManualesListForProduct($sede, $data['codigo'], $this->scopedUsuario()),
        ]);
    }

    public function destroyManual(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $sede = (string) $request->session()->get('sede_local');

        $data = $request->validate([
            'codigo'      => ['required', 'string'],
            'sede_origen' => ['required', 'string'],
        ]);

        $deleted = $this->reqPersonalizada->eliminar(
            $sede,
            $data['codigo'],
            $data['sede_origen'],
        );

        if (! $deleted) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No se encontró la requisición pendiente para eliminar.'], 422);
            }
            return back()->withErrors(['manual' => 'No se encontró la requisición pendiente para eliminar.']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Requisición eliminada correctamente.',
                'total_manual' => $this->reqPersonalizada->countPendientes($sede, $this->scopedUsuario()),
                'manuales_list' => $this->reqPersonalizada->getManualesListForProduct($sede, $data['codigo'], $this->scopedUsuario()),
            ]);
        }

        return redirect()
            ->route('inventario.index', [
                'q'           => $request->query('q', $request->input('q')),
                'categoria'   => $request->query('categoria', $request->input('categoria', 'Ninguno')),
                'subcategoria' => $request->query('subcategoria', $request->input('subcategoria', 'Ninguno')),
                'page'         => $request->query('page', $request->input('page', 1)),
            ])
            ->with('status', 'Requisición eliminada correctamente.');
    }

    public function metricasManual(Request $request): JsonResponse
    {
        $sede = (string) $request->session()->get('sede_local');
        $codigo = (string) $request->query('codigo');
        $sedeOrigen = (string) $request->query('sede_origen');
        $tp = (float) $request->session()->get('tiempo_pronostico', config('inventario.tiempo_pronostico_default'));

        $product = $this->products->findForSedeByCodigo($sede, $codigo);
        if (! $product) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        $m = $this->reqPersonalizada->metricasOrigen($product, $sedeOrigen, $tp);
        [$msg, $faltante] = $this->reqPersonalizada->mensajeValidacion(
            (int) $request->query('cantidad', 1),
            $m['excedente'],
        );

        return response()->json([
            'metricas' => $m,
            'mensaje' => $msg,
            'faltante' => $faltante,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $sede = (string) $request->session()->get('sede_local');
        $since = (string) $request->query('since', '');

        $updatedAt = collect([
                $this->products->lastStockUpdate(),
                $this->reqPersonalizada->lastUpdatedAt($sede, $this->scopedUsuario()),
            ])
            ->filter()
            ->map(fn ($value) => is_string($value) ? $value : (string) $value)
            ->max();

        $changed = $since && $updatedAt !== $since;
        $totalManual = $this->reqPersonalizada->countPendientes($sede, $this->scopedUsuario());

        return response()->json([
            'updated_at' => $updatedAt,
            'changed' => $changed,
            'total_manual' => $totalManual,
        ]);
    }

    /**
     * @return list<string>
     */
    private function categoriasTelefoniaLower(): array
    {
        return array_map(
            static fn (string $cat) => mb_strtolower(trim($cat), 'UTF-8'),
            config('inventario.categorias_telefonia', [])
        );
    }

    private function sqlCategoriasTelefonia(): string
    {
        return collect($this->categoriasTelefoniaLower())
            ->map(fn (string $cat) => "'".str_replace("'", "''", $cat)."'")
            ->implode(', ');
    }
}
