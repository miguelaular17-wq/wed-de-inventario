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
            foreach ($sedes as $sede) {
                $salesVal = (float) ($ventasInternas[$sede] ?? 0);
                $demandVal = ($salesVal / $tv) * $tp;
                $totalDemand += (int) round($demandVal);
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

        $publicitadosData = [];
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
