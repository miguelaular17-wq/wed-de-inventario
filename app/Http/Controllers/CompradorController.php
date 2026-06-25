<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Services\AnalisisInventarioService;
use App\Services\ProductRepository;
use App\Services\VentasCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CompradorController extends Controller
{
    public function __construct(
        private ProductRepository $products,
        private VentasCalculator $ventas,
        private AnalisisInventarioService $analisis,
    ) {}

    /**
     * Display the Comprador panel showing products to buy and poor distributions.
     */
    public function index(Request $request): View
    {
        $tp = (float) config('inventario.tiempo_pronostico_default', 15);
        $tv = (float) config('inventario.tiempo_venta_sede', 15);
        $sedes = config('inventario.sedes_stock');

        if (config('database.default') === 'pgsql') {
            $tp = (float) config('inventario.tiempo_pronostico_default', 15);
            $tv = (float) config('inventario.tiempo_venta_sede', 15);
            $sedes = config('inventario.sedes_stock');

            $search = trim((string) $request->query('q', ''));
            $category = (string) $request->query('categoria', 'Ninguno');
            $proveedor = (string) $request->query('proveedor', 'Ninguno');
            $subcategoria = (string) $request->query('subcategoria', 'Ninguno');
            $statusFilter = (string) $request->query('status', 'Todos'); // Todos, Comprar, MalaDistribucion
            $page = (int) $request->query('page', 1);
            $perPage = 25;

            $bindings = [
                'tv' => $tv,
                'tp' => $tp,
            ];

            $whereClauses = [];
            if ($category !== 'Ninguno') {
                $whereClauses[] = "p.categoria = :categoria";
                $bindings['categoria'] = $category;
            }
            if ($subcategoria !== 'Ninguno') {
                $whereClauses[] = "p.subcategoria = :subcategoria";
                $bindings['subcategoria'] = $subcategoria;
            }
            if ($proveedor !== 'Ninguno') {
                $whereClauses[] = "p.proveedor = :proveedor";
                $bindings['proveedor'] = $proveedor;
            }
            if ($search !== '') {
                $whereClauses[] = "(LOWER(p.codigo) LIKE :search OR LOWER(p.nombre) LIKE :search)";
                $bindings['search'] = '%' . mb_strtolower($search) . '%';
            }

            $whereSql = $whereClauses ? 'AND ' . implode(' AND ', $whereClauses) : '';

            $cteSql = "
                WITH product_metrics AS (
                    SELECT 
                        p.id,
                        p.codigo,
                        p.nombre,
                        p.categoria,
                        p.subcategoria,
                        p.proveedor,
                        p.precio_unidad,
                        p.precio_mayor,
                        COALESCE(SUM(sa.existencia), 0) as total_stock,
                        COALESCE(SUM(ROUND((vh.ventas_60d / :tv) * :tp)), 0) as total_demand,
                        COUNT(CASE WHEN COALESCE(sa.existencia, 0) < ROUND((COALESCE(vh.ventas_60d, 0) / :tv) * :tp) THEN 1 END) as shortages_count,
                        COUNT(CASE WHEN COALESCE(sa.existencia, 0) > ROUND((COALESCE(vh.ventas_60d, 0) / :tv) * :tp) THEN 1 END) as surpluses_count
                    FROM inventario_v2.productos p
                    LEFT JOIN inventario_v2.stock_actual sa ON p.id = sa.producto_id
                    LEFT JOIN inventario_v2.ventas_historicas vh ON p.id = vh.producto_id AND sa.sede = vh.sede
                    WHERE p.activo = true {$whereSql}
                    GROUP BY p.id, p.codigo, p.nombre, p.categoria, p.subcategoria, p.proveedor, p.precio_unidad, p.precio_mayor
                ),
                classified_products AS (
                    SELECT *,
                        CASE 
                            WHEN total_stock < total_demand THEN 'COMPRAR'
                            WHEN total_stock >= total_demand AND shortages_count > 0 AND surpluses_count > 0 THEN 'MALA DISTRIBUCIÓN'
                            ELSE 'OK'
                        END as status
                    FROM product_metrics
                )
                SELECT * 
                FROM classified_products
                WHERE status IN ('COMPRAR', 'MALA DISTRIBUCIÓN')
            ";

            if ($statusFilter === 'Comprar') {
                $cteSql .= " AND status = 'COMPRAR'";
            } elseif ($statusFilter === 'MalaDistribucion') {
                $cteSql .= " AND status = 'MALA DISTRIBUCIÓN'";
            }

            $countSql = "SELECT COUNT(*) as total_count FROM ({$cteSql}) as tmp";
            $totalCountRow = \Illuminate\Support\Facades\DB::connection('pgsql')->selectOne($countSql, $bindings);
            $totalCount = $totalCountRow ? (int) $totalCountRow->total_count : 0;

            $offset = ($page - 1) * $perPage;
            $itemsSql = $cteSql . " ORDER BY codigo LIMIT {$perPage} OFFSET {$offset}";
            $dbItems = \Illuminate\Support\Facades\DB::connection('pgsql')->select($itemsSql, $bindings);

            $items = collect();
            if (count($dbItems) > 0) {
                $productIds = array_map(fn($item) => (int) $item->id, $dbItems);

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
                        'venta_promedio' => (int) $row->venta_promedio,
                        'ventas_60d' => (float) $row->ventas_60d,
                        'ultima_venta' => $row->ultima_venta,
                        'ultima_compra' => $row->ultima_compra,
                    ];
                }

                foreach ($dbItems as $item) {
                    $productoId = (int) $item->id;
                    $stockMap = $stocksByProduct[$productoId] ?? [];
                    $ventaMap = $ventasByProduct[$productoId] ?? [];

                    $stocks = [];
                    $sedeDemands = [];
                    $shortages = [];
                    $surpluses = [];
                    $ultimasVentas = [];
                    $ultimasCompras = [];

                    foreach ($sedes as $sede) {
                        $stockVal = $stockMap[$sede] ?? 0;
                        $ventaSede = $ventaMap[$sede] ?? null;
                        $salesVal = $ventaSede ? (float) $ventaSede['ventas_60d'] : 0.0;
                        $demandVal = ($salesVal / $tv) * $tp;
                        $demandInt = (int) round($demandVal);

                        $stocks[$sede] = $stockVal;
                        $sedeDemands[$sede] = $demandInt;

                        if ($stockVal < $demandInt) {
                            $shortages[$sede] = $demandInt - $stockVal;
                        } elseif ($stockVal > $demandInt) {
                            $surpluses[$sede] = $stockVal - $demandInt;
                        }

                        $uv = $ventaSede['ultima_venta'] ?? null;
                        $ultimasVentas[$sede] = $uv ? date('d/m/Y', strtotime((string) $uv)) : null;
                        $uc = $ventaSede['ultima_compra'] ?? null;
                        $ultimasCompras[$sede] = $uc ? date('d/m/Y', strtotime((string) $uc)) : null;
                    }

                    $items->push([
                        'cod_centro' => $item->codigo,
                        'producto' => $item->nombre,
                        'categoria' => $item->categoria ?? '—',
                        'subcategoria' => $item->subcategoria ?? '—',
                        'total_stock' => (int) $item->total_stock,
                        'total_demanda' => (int) $item->total_demand,
                        'status' => $item->status,
                        'shortages' => $shortages,
                        'surpluses' => $surpluses,
                        'stocks' => $stocks,
                        'demands' => $sedeDemands,
                        'ultimas_ventas' => $ultimasVentas,
                        'ultimas_compras' => $ultimasCompras,
                    ]);
                }
            }

            $paginatedItems = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $totalCount,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            // Tab 2: Grouped by provider for products to buy (totalStock < totalDemand)
            $toBuySql = "
                WITH product_metrics AS (
                    SELECT 
                        p.id,
                        p.codigo as cod_centro,
                        p.nombre as producto,
                        p.categoria,
                        p.subcategoria,
                        COALESCE(p.proveedor, '') as proveedor,
                        COALESCE(SUM(sa.existencia), 0) as total_stock,
                        COALESCE(SUM(ROUND((vh.ventas_60d / :tv) * :tp)), 0) as total_demand
                    FROM inventario_v2.productos p
                    LEFT JOIN inventario_v2.stock_actual sa ON p.id = sa.producto_id
                    LEFT JOIN inventario_v2.ventas_historicas vh ON p.id = vh.producto_id AND sa.sede = vh.sede
                    WHERE p.activo = true
                    GROUP BY p.id, p.codigo, p.nombre, p.categoria, p.subcategoria, p.proveedor
                )
                SELECT * 
                FROM product_metrics
                WHERE total_stock < total_demand
            ";

            $dbToBuy = \Illuminate\Support\Facades\DB::connection('pgsql')->select($toBuySql, [
                'tv' => $tv,
                'tp' => $tp,
            ]);

            $productIds = [];
            foreach ($dbToBuy as $row) {
                $productIds[] = (int) $row->id;
            }

            $stocksByProduct = [];
            $ventasByProduct = [];

            if (count($productIds) > 0) {
                $dbStocks = \Illuminate\Support\Facades\DB::connection('pgsql')
                    ->table('stock_actual')
                    ->whereIn('producto_id', $productIds)
                    ->get(['producto_id', 'sede', 'existencia']);
                
                foreach ($dbStocks as $row) {
                    $stocksByProduct[(int) $row->producto_id][$row->sede] = (int) $row->existencia;
                }

                $dbVentas = \Illuminate\Support\Facades\DB::connection('pgsql')
                    ->table('ventas_historicas')
                    ->whereIn('producto_id', $productIds)
                    ->get(['producto_id', 'sede', 'ventas_60d']);

                foreach ($dbVentas as $row) {
                    $ventasByProduct[(int) $row->producto_id][$row->sede] = (float) $row->ventas_60d;
                }
            }

            $productsToBuy = collect();
            foreach ($dbToBuy as $row) {
                $pId = (int) $row->id;
                $pStocks = [];
                $pDemands = [];
                foreach ($sedes as $sede) {
                    $stockVal = $stocksByProduct[$pId][$sede] ?? 0;
                    $salesVal = $ventasByProduct[$pId][$sede] ?? 0.0;
                    $demandVal = ($salesVal / $tv) * $tp;
                    $pStocks[$sede] = $stockVal;
                    $pDemands[$sede] = (int) round($demandVal);
                }

                $productsToBuy->push([
                    'cod_centro' => $row->cod_centro,
                    'producto' => $row->producto,
                    'categoria' => $row->categoria ?? '—',
                    'subcategoria' => $row->subcategoria ?? '—',
                    'proveedor' => $row->proveedor ?: 'Sin Proveedor',
                    'total_stock' => (int) $row->total_stock,
                    'total_demanda' => (int) $row->total_demand,
                    'faltante' => (int) ($row->total_demand - $row->total_stock),
                    'stocks' => $pStocks,
                    'demands' => $pDemands,
                ]);
            }

            $byProvider = $productsToBuy->groupBy('proveedor')->map(function ($items, $providerName) {
                return [
                    'proveedor' => $providerName,
                    'total_productos' => $items->count(),
                    'total_unidades' => $items->sum('faltante'),
                    'productos' => $items->values(),
                ];
            })->sortByDesc('total_unidades')->values();

            $categorias = \Illuminate\Support\Facades\DB::connection('pgsql')
                ->table('productos')
                ->where('activo', true)
                ->whereNotNull('categoria')
                ->where('categoria', '!=', '')
                ->distinct()
                ->orderBy('categoria')
                ->pluck('categoria')
                ->all();

            $proveedores = \Illuminate\Support\Facades\DB::connection('pgsql')
                ->table('productos')
                ->where('activo', true)
                ->whereNotNull('proveedor')
                ->where('proveedor', '!=', '')
                ->distinct()
                ->orderBy('proveedor')
                ->pluck('proveedor')
                ->all();

            $subcatDb = \Illuminate\Support\Facades\DB::connection('pgsql')
                ->table('productos')
                ->where('activo', true)
                ->whereNotNull('categoria')
                ->where('categoria', '!=', '')
                ->whereNotNull('subcategoria')
                ->where('subcategoria', '!=', '')
                ->select(['categoria', 'subcategoria'])
                ->distinct()
                ->get();
            
            $subcatMap = [];
            foreach ($subcatDb as $row) {
                $subcatMap[$row->categoria][] = $row->subcategoria;
            }
            foreach ($subcatMap as $cat => $subcats) {
                sort($subcatMap[$cat]);
            }

            // Tab 3 filters setting
            $ssFilters = [
                'categoria' => (string) $request->query('ss_categoria', 'Ninguno'),
                'subcategoria' => (string) $request->query('ss_subcategoria', 'Ninguno'),
                'proveedor' => (string) $request->query('ss_proveedor', 'Ninguno'),
                'sede' => (string) $request->query('ss_sede', 'Todas'),
                'rotacion_filter' => (string) $request->query('ss_rotacion', 'Todos'),
                'sobrestock_filter' => (string) $request->query('ss_sobrestock', 'Todos'),
                'estado_filter' => (string) $request->query('ss_estado', 'Todos'),
                'semaforo_filter' => (string) $request->query('ss_semaforo', 'Todos'),
                'min_dias_sin_venta' => $request->query('ss_min_dias'),
                'min_existencia' => $request->query('ss_min_stock'),
                'buscar' => (string) $request->query('ss_buscar', ''),
            ];

            $analysisItems = $this->analisis->getAnalysis($ssFilters);

            $sortBy = (string) $request->query('ss_sort', 'prioridad');
            $sortDir = (string) $request->query('ss_dir', 'desc');
            $validSorts = ['prioridad', 'dias_sin_venta', 'total_stock', 'meses_inventario', 'codigo', 'producto'];
            if (!in_array($sortBy, $validSorts)) $sortBy = 'prioridad';

            if ($sortDir === 'asc') {
                $analysisItems = $analysisItems->sortBy($sortBy)->values();
            } else {
                $analysisItems = $analysisItems->sortByDesc($sortBy)->values();
            }

            $resumenRiesgo = $this->analisis->getResumenRiesgo($analysisItems);
            $resumenPorSede = $this->analisis->getResumenPorSede($analysisItems, $ssFilters);

            $pageSobreStock = (int) $request->query('page_sobre_stock', 1);
            $perPageSS = 50;

            $slicedItems = $analysisItems->slice(($pageSobreStock - 1) * $perPageSS, $perPageSS)->values();

            if ($slicedItems->isNotEmpty()) {
                $productIds = $slicedItems->pluck('id')->all();

                $dbStocks = \Illuminate\Support\Facades\DB::connection('pgsql')
                    ->table('stock_actual')
                    ->whereIn('producto_id', $productIds)
                    ->get(['producto_id', 'sede', 'existencia']);
                
                $stockByProduct = [];
                foreach ($dbStocks as $row) {
                    $stockByProduct[(int) $row->producto_id][$row->sede] = (int) $row->existencia;
                }

                $dbVentas = \Illuminate\Support\Facades\DB::connection('pgsql')
                    ->table('ventas_historicas')
                    ->whereIn('producto_id', $productIds)
                    ->get(['producto_id', 'sede', 'venta_promedio', 'ventas_60d', 'ultima_venta', 'ultima_compra']);

                $ventasByProduct = [];
                foreach ($dbVentas as $row) {
                    $ventasByProduct[(int) $row->producto_id][$row->sede] = [
                        'venta_promedio' => (int) $row->venta_promedio,
                        'ventas_60d' => (float) $row->ventas_60d,
                        'ultima_venta' => $row->ultima_venta,
                        'ultima_compra' => $row->ultima_compra,
                    ];
                }

                $slicedItems = $slicedItems->map(function ($item) use ($stockByProduct, $ventasByProduct) {
                    $productId = (int) $item['id'];
                    $item['stocks_por_sede'] = $stockByProduct[$productId] ?? [];
                    $item['ventas_por_sede'] = $ventasByProduct[$productId] ?? [];
                    return $item;
                });
            }

            $paginatedAnalysis = new \Illuminate\Pagination\LengthAwarePaginator(
                $slicedItems,
                $analysisItems->count(),
                $perPageSS,
                $pageSobreStock,
                ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'page_sobre_stock']
            );
        } else {
            // Load all products (we can use the central sede JRZ or any)
            $rawProducts = $this->products->loadForSede('JRZ');
            $allProducts = $rawProducts;

            $search = trim((string) $request->query('q', ''));
            $category = (string) $request->query('categoria', 'Ninguno');
            $proveedor = (string) $request->query('proveedor', 'Ninguno');
            $subcategoria = (string) $request->query('subcategoria', 'Ninguno');
            $statusFilter = (string) $request->query('status', 'Todos'); // Todos, Comprar, MalaDistribucion

            if ($search !== '' || $category !== 'Ninguno' || $proveedor !== 'Ninguno' || $subcategoria !== 'Ninguno') {
                $qLower = mb_strtolower($search);
                $rawProducts = $rawProducts->filter(function (array $row) use ($category, $proveedor, $subcategoria, $qLower) {
                    if ($category !== 'Ninguno' && ($row['categoria'] ?? '') !== $category) {
                        return false;
                    }
                    if ($proveedor !== 'Ninguno' && ($row['proveedor'] ?? '') !== $proveedor) {
                        return false;
                    }
                    if ($subcategoria !== 'Ninguno' && ($row['subcategoria'] ?? '') !== $subcategoria) {
                        return false;
                    }
                    if ($qLower === '') {
                        return true;
                    }
                    return str_contains(mb_strtolower((string) ($row['cod_centro'] ?? '')), $qLower)
                        || str_contains(mb_strtolower((string) ($row['producto'] ?? '')), $qLower);
                });
            }

            $items = collect();

            foreach ($rawProducts as $product) {
                $stocks = $product['stocks'] ?? [];
                $ventasInternas = $product['ventas_internas'] ?? [];

                $totalStock = array_sum($stocks);
                $totalDemand = 0;
                $sedeDemands = [];
                $shortages = [];
                $surpluses = [];

                foreach ($sedes as $sede) {
                    $stockVal = (int) ($stocks[$sede] ?? 0);
                    $salesVal = (float) ($ventasInternas[$sede] ?? 0);
                    $demandVal = ($salesVal / $tv) * $tp;
                    $demandInt = (int) round($demandVal);

                    $totalDemand += $demandInt;
                    $sedeDemands[$sede] = $demandInt;

                    if ($stockVal < $demandInt) {
                        $shortages[$sede] = $demandInt - $stockVal;
                    } elseif ($stockVal > $demandInt) {
                        $surpluses[$sede] = $stockVal - $demandInt;
                    }
                }

                $necesitaCompra = $totalStock < $totalDemand;
                $malaDistribucion = !$necesitaCompra && count($shortages) > 0 && count($surpluses) > 0;

                if (!$necesitaCompra && !$malaDistribucion) {
                    continue;
                }

                $status = $necesitaCompra ? 'COMPRAR' : 'MALA DISTRIBUCIÓN';

                if ($statusFilter === 'Comprar' && $status !== 'COMPRAR') {
                    continue;
                }
                if ($statusFilter === 'MalaDistribucion' && $status !== 'MALA DISTRIBUCIÓN') {
                    continue;
                }

                $items->push([
                    'cod_centro' => $product['cod_centro'],
                    'producto' => $product['producto'],
                    'categoria' => $product['categoria'] ?? '—',
                    'subcategoria' => $product['subcategoria'] ?? '—',
                    'total_stock' => $totalStock,
                    'total_demanda' => $totalDemand,
                    'status' => $status,
                    'shortages' => $shortages,
                    'surpluses' => $surpluses,
                    'stocks' => $stocks,
                    'demands' => $sedeDemands,
                    'ultimas_ventas' => $product['ultimas_ventas'] ?? [],
                    'ultimas_compras' => $product['ultimas_compras'] ?? [],
                ]);
            }

            // Paginate the collection manually for performance
            $page = (int) $request->query('page', 1);
            $perPage = 25;
            $paginatedItems = new \Illuminate\Pagination\LengthAwarePaginator(
                $items->slice(($page - 1) * $perPage, $perPage)->values(),
                $items->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $productsToBuy = collect();
            foreach ($allProducts as $product) {
                $stocks = $product['stocks'] ?? [];
                $ventasInternas = $product['ventas_internas'] ?? [];
                $totalStock = array_sum($stocks);
                $totalDemand = 0;
                $pDemands = [];
                foreach ($sedes as $sede) {
                    $salesVal = (float) ($ventasInternas[$sede] ?? 0);
                    $demandVal = ($salesVal / $tv) * $tp;
                    $demandInt = (int) round($demandVal);
                    $totalDemand += $demandInt;
                    $pDemands[$sede] = $demandInt;
                }
                if ($totalStock < $totalDemand) {
                    $productsToBuy->push([
                        'cod_centro' => $product['cod_centro'],
                        'producto' => $product['producto'],
                        'categoria' => $product['categoria'] ?? '—',
                        'subcategoria' => $product['subcategoria'] ?? '—',
                        'proveedor' => $product['proveedor'] ?: 'Sin Proveedor',
                        'total_stock' => $totalStock,
                        'total_demanda' => $totalDemand,
                        'faltante' => $totalDemand - $totalStock,
                        'stocks' => $stocks,
                        'demands' => $pDemands,
                    ]);
                }
            }

            $byProvider = $productsToBuy->groupBy('proveedor')->map(function ($items, $providerName) {
                return [
                    'proveedor' => $providerName,
                    'total_productos' => $items->count(),
                    'total_unidades' => $items->sum('faltante'),
                    'productos' => $items,
                ];
            })->sortByDesc('total_unidades')->values();

            // ── Tab 3: Análisis de Inventario (Sobre Stock / Sin Rotación) ──
            $ssFilters = [
                'categoria' => (string) $request->query('ss_categoria', 'Ninguno'),
                'subcategoria' => (string) $request->query('ss_subcategoria', 'Ninguno'),
                'proveedor' => (string) $request->query('ss_proveedor', 'Ninguno'),
                'sede' => (string) $request->query('ss_sede', 'Todas'),
                'rotacion_filter' => (string) $request->query('ss_rotacion', 'Todos'),
                'sobrestock_filter' => (string) $request->query('ss_sobrestock', 'Todos'),
                'estado_filter' => (string) $request->query('ss_estado', 'Todos'),
                'semaforo_filter' => (string) $request->query('ss_semaforo', 'Todos'),
                'min_dias_sin_venta' => $request->query('ss_min_dias'),
                'min_existencia' => $request->query('ss_min_stock'),
                'buscar' => (string) $request->query('ss_buscar', ''),
            ];

            $analysisItems = $this->analisis->getAnalysis($ssFilters);

            // Sort
            $sortBy = (string) $request->query('ss_sort', 'prioridad');
            $sortDir = (string) $request->query('ss_dir', 'desc');
            $validSorts = ['prioridad', 'dias_sin_venta', 'total_stock', 'meses_inventario', 'codigo', 'producto'];
            if (!in_array($sortBy, $validSorts)) $sortBy = 'prioridad';

            if ($sortDir === 'asc') {
                $analysisItems = $analysisItems->sortBy($sortBy)->values();
            } else {
                $analysisItems = $analysisItems->sortByDesc($sortBy)->values();
            }

            // Compute risk summaries BEFORE pagination
            $resumenRiesgo = $this->analisis->getResumenRiesgo($analysisItems);
            $resumenPorSede = $this->analisis->getResumenPorSede($analysisItems);

            // Paginate
            $pageSobreStock = (int) $request->query('page_sobre_stock', 1);
            $perPageSS = 50;
            $paginatedAnalysis = new \Illuminate\Pagination\LengthAwarePaginator(
                $analysisItems->slice(($pageSobreStock - 1) * $perPageSS, $perPageSS)->values(),
                $analysisItems->count(),
                $perPageSS,
                $pageSobreStock,
                ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'page_sobre_stock']
            );

            $categorias = $allProducts->pluck('categoria')->filter()->unique()->sort()->values()->all();
            $proveedores = $allProducts->pluck('proveedor')->filter()->unique()->sort()->values()->all();
            
            $subcatMap = [];
            foreach ($allProducts as $p) {
                $cat = $p['categoria'] ?? '';
                $subcat = $p['subcategoria'] ?? '';
                if ($cat !== '' && $subcat !== '') {
                    $subcatMap[$cat][$subcat] = true;
                }
            }
            foreach ($subcatMap as $cat => $subcats) {
                $subcatMap[$cat] = array_keys($subcats);
                sort($subcatMap[$cat]);
            }
        }

        $publicitadosData = [];
        $advertisedProductIds = [];

        if (config('database.default') === 'pgsql') {
            // Fetch advertised products (Publicidad)
            $publicitados = \Illuminate\Support\Facades\DB::connection('pgsql')
                ->table('publicidad_productos as pub')
                ->join('productos as p', 'pub.producto_id', '=', 'p.id')
                ->leftJoin(
                    \Illuminate\Support\Facades\DB::raw('(SELECT producto_id, SUM(existencia) as total_stock FROM stock_actual GROUP BY producto_id) sa'),
                    'p.id', '=', 'sa.producto_id'
                )
                ->leftJoin(
                    \Illuminate\Support\Facades\DB::raw("(SELECT producto_id,
                        MAX(ultima_venta) as ultima_venta,
                        SUM(venta_promedio) as promedio_venta_total
                    FROM ventas_historicas GROUP BY producto_id) vh"),
                    'p.id', '=', 'vh.producto_id'
                )
                ->select([
                    'p.id',
                    'p.codigo',
                    'p.nombre',
                    'p.categoria',
                    'p.proveedor',
                    'pub.fecha_publicidad',
                    'pub.ultima_venta_original',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(sa.total_stock, 0) as total_stock'),
                    'vh.ultima_venta as ultima_venta_actual',
                ])
                ->orderBy('pub.fecha_publicidad', 'desc')
                ->get();

            foreach ($publicitados as $row) {
                $tuvoVentas = false;
                if ($row->ultima_venta_actual) {
                    $dateActual = \Carbon\Carbon::parse($row->ultima_venta_actual);
                    $datePub = \Carbon\Carbon::parse($row->fecha_publicidad);
                    if ($row->ultima_venta_original === null 
                        || $dateActual->gt(\Carbon\Carbon::parse($row->ultima_venta_original)) 
                        || $dateActual->greaterThanOrEqualTo($datePub->startOfDay())
                    ) {
                        $tuvoVentas = true;
                    }
                }
                
                $publicitadosData[] = [
                    'id' => $row->id,
                    'codigo' => $row->codigo,
                    'producto' => $row->nombre,
                    'categoria' => $row->categoria,
                    'proveedor' => $row->proveedor,
                    'total_stock' => (int)$row->total_stock,
                    'fecha_publicidad' => \Carbon\Carbon::parse($row->fecha_publicidad)->format('d/m/Y H:i'),
                    'ultima_venta_original' => $row->ultima_venta_original ? \Carbon\Carbon::parse($row->ultima_venta_original)->format('d/m/Y') : 'Sin datos',
                    'ultima_venta_actual' => $row->ultima_venta_actual ? \Carbon\Carbon::parse($row->ultima_venta_actual)->format('d/m/Y') : 'Sin datos',
                    'tuvo_ventas' => $tuvoVentas,
                ];
            }

            $advertisedProductIds = \Illuminate\Support\Facades\DB::connection('pgsql')
                ->table('publicidad_productos')
                ->pluck('producto_id')
                ->toArray();
        }

        return view('comprador.index', [
            'productos' => $paginatedItems,
            'sobreStock' => $paginatedAnalysis,
            'categorias' => $categorias,
            'proveedores' => $proveedores,
            'subcategoriasByCategoria' => $subcatMap,
            'byProvider' => $byProvider,
            'q' => $search,
            'selectedCategoria' => $category,
            'selectedProveedor' => $proveedor,
            'selectedSubcategoria' => $subcategoria,
            'statusFilter' => $statusFilter,
            'ssFilters' => $ssFilters,
            'ssSortBy' => $sortBy,
            'ssSortDir' => $sortDir,
            'resumenRiesgo' => $resumenRiesgo,
            'resumenPorSede' => $resumenPorSede,
            'sedes' => config('inventario.sedes_stock'),
            'sedeDisplay' => config('inventario.display'),
            'publicitadosData' => $publicitadosData,
            'advertisedProductIds' => $advertisedProductIds,
        ]);
    }

    /**
     * Notify the supervisor(s) of a sede about a poor product distribution.
     */
    public function notifyRedistribution(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string'],
            'producto' => ['required', 'string'],
            'sede_destino' => ['required', 'string'],
            'sede_origen' => ['required', 'string'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);

        $destSede = strtoupper($data['sede_destino']);
        $origSede = strtoupper($data['sede_origen']);

        // Find supervisors or users assigned to that target sede
        $receivers = User::where('sede', $destSede)->get();

        if ($receivers->isEmpty()) {
            // Fallback to all supervisors if no specific sede user is found
            $receivers = User::where('role', User::ROLE_SUPERVISOR)->get();
        }

        if ($receivers->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'errors' => ['notify' => ['No se encontraron usuarios o supervisores asignados a la sede ' . $destSede . ' para notificar.']]
                ], 422);
            }
            return back()->withErrors(['notify' => 'No se encontraron usuarios o supervisores asignados a la sede ' . $destSede . ' para notificar.']);
        }

        $message = sprintf(
            'Alerta de Redistribución: El producto "%s" (%s) tiene desabasto en la sede %s. Se sugiere realizar un traslado de %d unidades desde la sede %s, la cual cuenta con excedentes.',
            $data['producto'],
            $data['codigo'],
            $destSede,
            $data['cantidad'],
            $origSede
        );

        foreach ($receivers as $receiver) {
            Notification::create([
                'sender_id' => $request->user()->id,
                'receiver_id' => $receiver->id,
                'message' => $message,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notificación de redistribución enviada con éxito al personal de la sede ' . $destSede . '.'
            ]);
        }

        return back()->with('status', 'Notificación de redistribución enviada con éxito al personal de la sede ' . $destSede . '.');
    }

    /**
     * Export the filtered products list as CSV/Excel.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $tp = (float) config('inventario.tiempo_pronostico_default', 15);
        $tv = (float) config('inventario.tiempo_venta_sede', 15);
        $sedes = config('inventario.sedes_stock');

        // Load all products
        $rawProducts = $this->products->loadForSede('JRZ');

        $search = trim((string) $request->query('q', ''));
        $category = (string) $request->query('categoria', 'Ninguno');
        $proveedor = (string) $request->query('proveedor', 'Ninguno');
        $subcategoria = (string) $request->query('subcategoria', 'Ninguno');
        $statusFilter = (string) $request->query('status', 'Todos');

        if ($search !== '' || $category !== 'Ninguno' || $proveedor !== 'Ninguno' || $subcategoria !== 'Ninguno') {
            $qLower = mb_strtolower($search);
            $rawProducts = $rawProducts->filter(function (array $row) use ($category, $proveedor, $subcategoria, $qLower) {
                if ($category !== 'Ninguno' && ($row['categoria'] ?? '') !== $category) {
                    return false;
                }
                if ($proveedor !== 'Ninguno' && ($row['proveedor'] ?? '') !== $proveedor) {
                    return false;
                }
                if ($subcategoria !== 'Ninguno' && ($row['subcategoria'] ?? '') !== $subcategoria) {
                    return false;
                }
                if ($qLower === '') {
                    return true;
                }
                return str_contains(mb_strtolower((string) ($row['cod_centro'] ?? '')), $qLower)
                    || str_contains(mb_strtolower((string) ($row['producto'] ?? '')), $qLower);
            });
        }

        $items = collect();

        foreach ($rawProducts as $product) {
            $stocks = $product['stocks'] ?? [];
            $ventasInternas = $product['ventas_internas'] ?? [];

            $totalStock = array_sum($stocks);
            $totalDemand = 0;
            $shortages = [];
            $surpluses = [];

            foreach ($sedes as $sede) {
                $stockVal = (int) ($stocks[$sede] ?? 0);
                $salesVal = (float) ($ventasInternas[$sede] ?? 0);
                $demandVal = ($salesVal / $tv) * $tp;
                $demandInt = (int) round($demandVal);

                $totalDemand += $demandInt;

                if ($stockVal < $demandInt) {
                    $shortages[$sede] = $demandInt - $stockVal;
                } elseif ($stockVal > $demandInt) {
                    $surpluses[$sede] = $stockVal - $demandInt;
                }
            }

            $necesitaCompra = $totalStock < $totalDemand;
            $malaDistribucion = !$necesitaCompra && count($shortages) > 0 && count($surpluses) > 0;

            if (!$necesitaCompra && !$malaDistribucion) {
                continue;
            }

            $status = $necesitaCompra ? 'COMPRAR' : 'MALA DISTRIBUCIÓN';

            if ($statusFilter === 'Comprar' && $status !== 'COMPRAR') {
                continue;
            }
            if ($statusFilter === 'MalaDistribucion' && $status !== 'MALA DISTRIBUCIÓN') {
                continue;
            }

            // Suggestions string builder
            $detallesStr = '';
            if ($necesitaCompra) {
                $detallesStr = 'Faltan ' . ($totalDemand - $totalStock) . ' unidades globalmente.';
            } else {
                // SUGGESTED TRANSFERS
                $redistributions = [];
                reset($surpluses);
                reset($shortages);
                $tempSurpluses = $surpluses;
                $tempShortages = $shortages;

                foreach ($tempShortages as $destSede => $needed) {
                    foreach ($tempSurpluses as $origSede => $available) {
                        if ($needed <= 0 || $available <= 0) continue;
                        $transferAmt = min($needed, $available);
                        $redistributions[] = "Mover {$transferAmt} de {$origSede} a {$destSede}";
                        $needed -= $transferAmt;
                        $tempSurpluses[$origSede] -= $transferAmt;
                    }
                }
                $detallesStr = implode(' | ', $redistributions);
            }

            $items->push([
                'cod_centro' => $product['cod_centro'],
                'producto' => $product['producto'],
                'categoria' => $product['categoria'] ?? '—',
                'subcategoria' => $product['subcategoria'] ?? '—',
                'total_stock' => $totalStock,
                'total_demanda' => $totalDemand,
                'status' => $status,
                'detalles' => $detallesStr,
            ]);
        }

        // Generate CSV output with BOM (for Excel Spanish accent support)
        $out = "\ufeff";
        $out .= "Código;Producto;Categoría;Subcategoría;Stock Global;Demanda Global;Estado;Detalles / Sugerencias\n";

        foreach ($items as $item) {
            $prodEscaped = str_replace(';', ',', $item['producto']);
            $catEscaped = str_replace(';', ',', $item['categoria']);
            $subcatEscaped = str_replace(';', ',', $item['subcategoria']);
            $detailsEscaped = str_replace(';', ',', $item['detalles']);

            $out .= sprintf(
                "%s;%s;%s;%s;%d;%d;%s;%s\n",
                $item['cod_centro'],
                $prodEscaped,
                $catEscaped,
                $subcatEscaped,
                $item['total_stock'],
                $item['total_demanda'],
                $item['status'],
                $detailsEscaped
            );
        }

        $filename = 'compras_global_' . date('Y-m-d_His') . '.csv';

        return response($out, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Toggle advertising status on a product.
     */
    public function togglePublicidad(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'producto_id' => ['required', 'integer'],
        ]);
        
        $productoId = $data['producto_id'];
        
        if (config('database.default') !== 'pgsql') {
            return response()->json([
                'success' => true,
                'status' => 'removed',
                'message' => 'Campaña de publicidad no disponible en SQLite.'
            ]);
        }
        
        $exists = \Illuminate\Support\Facades\DB::connection('pgsql')
            ->table('publicidad_productos')
            ->where('producto_id', $productoId)
            ->first();
            
        if ($exists) {
            \Illuminate\Support\Facades\DB::connection('pgsql')
                ->table('publicidad_productos')
                ->where('producto_id', $productoId)
                ->delete();
                
            return response()->json([
                'success' => true,
                'status' => 'removed',
                'message' => 'Producto retirado de la campaña de publicidad.'
            ]);
        } else {
            $lastSaleRow = \Illuminate\Support\Facades\DB::connection('pgsql')
                ->table('ventas_historicas')
                ->where('producto_id', $productoId)
                ->select(\Illuminate\Support\Facades\DB::raw('MAX(ultima_venta) as ultima_venta'))
                ->first();
            
            $lastSaleDate = $lastSaleRow ? $lastSaleRow->ultima_venta : null;
            
            \Illuminate\Support\Facades\DB::connection('pgsql')
                ->table('publicidad_productos')
                ->insert([
                    'producto_id' => $productoId,
                    'fecha_publicidad' => now(),
                    'ultima_venta_original' => $lastSaleDate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
            return response()->json([
                'success' => true,
                'status' => 'added',
                'message' => 'Producto marcado para campaña de publicidad con éxito.'
            ]);
        }
    }
}
