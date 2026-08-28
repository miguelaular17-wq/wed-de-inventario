<?php

namespace App\Services;

use App\Support\ProfitMotivos;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GerencialAnalyticsService
{
    public function __construct(
        private GerencialDashboardService $base,
        private GerencialAbcService $abc,
    ) {}

    public function devoluciones(array $periodo, ?string $sede, ?string $vendedor, ?string $producto, bool $conDetalle = false): array
    {
        $sedes = $this->base->filtrarSedes($sede);
        $precio = $this->campoPrecio();
        $costo = Schema::hasColumn('ventas_detalle', 'costo_unitario') ? 'COALESCE(vd.costo_unitario, 0)' : '0';
        $usaLineas = (bool) ($vendedor || $producto);
        $ventas = $this->base->kpisPorSede($periodo['inicio'], $periodo['fin'], $sedes, $usaLineas, null, $vendedor, $producto);
        $ventasUsd = collect($ventas)->sum('ventas_usd');

        $kpis = [
            'documentos' => 0,
            'usd' => 0.0,
            'unidades' => 0.0,
            'costo' => 0.0,
            'margen' => 0.0,
            'pct_ventas' => 0.0,
            'ventas_usd' => round((float) $ventasUsd, 2),
        ];
        $porMotivo = collect();
        $porSede = collect();
        $porProducto = collect();
        $motivoTop = null;
        $detalle = new LengthAwarePaginator([], 0, 25);

        if (! Schema::hasTable('ventas_detalle')) {
            return compact('kpis', 'porMotivo', 'porSede', 'porProducto', 'motivoTop', 'detalle');
        }

        $dev = $this->base->queryLineas($periodo['inicio'], $periodo['fin'], $sedes, null, $vendedor, $producto)
            ->whereRaw("UPPER(TRIM(vd.tipo_documento)) = 'DEV'");

        $agg = (clone $dev)
            ->selectRaw('COUNT(DISTINCT vd.numero_documento) as documentos')
            ->selectRaw('SUM(ABS(vd.cantidad)) as unidades')
            ->selectRaw("SUM(ABS(vd.cantidad * {$precio})) as usd")
            ->selectRaw("SUM(ABS(vd.cantidad * {$costo})) as costo")
            ->first();
        $kpis['documentos'] = (int) ($agg->documentos ?? 0);
        $kpis['unidades'] = round((float) ($agg->unidades ?? 0), 2);
        $kpis['usd'] = round((float) ($agg->usd ?? 0), 2);
        $kpis['costo'] = round((float) ($agg->costo ?? 0), 2);
        $kpis['margen'] = round($kpis['usd'] - $kpis['costo'], 2);

        if (Schema::hasColumn('ventas_detalle', 'motivo_devolucion')) {
            $porMotivo = (clone $dev)
                ->selectRaw("COALESCE(NULLIF(TRIM(vd.motivo_devolucion), ''), 'Sin motivo') as motivo")
                ->selectRaw("COUNT(DISTINCT vd.sede || '-' || vd.numero_documento) as veces")
                ->selectRaw('SUM(ABS(vd.cantidad)) as unidades')
                ->selectRaw("SUM(ABS(vd.cantidad * {$precio})) as usd")
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(vd.motivo_devolucion), ''), 'Sin motivo')"))
                ->orderByDesc('veces')
                ->limit(40)
                ->get();
            $porMotivo = $this->consolidarMotivos($porMotivo, 'devolucion')
                ->map(function ($row) use ($kpis) {
                    $row->pct = $kpis['usd'] > 0 ? round((float) $row->usd / $kpis['usd'] * 100, 1) : 0.0;
                    return $row;
                })
                ->take(12)
                ->values();
            $motivoTop = $porMotivo->first();
        }

        $porSede = collect($ventas)->map(function ($fila) {
            $valorDev = (float) ($fila['devoluciones_usd'] ?? 0);
            $ventasSede = (float) ($fila['ventas_usd'] ?? 0) + $valorDev;
            return (object) [
                'sede' => $fila['sede'],
                'ventas_usd' => round($ventasSede, 2),
                'devoluciones' => (int) ($fila['devoluciones'] ?? 0),
                'pct' => $ventasSede > 0 ? round($valorDev / $ventasSede * 100, 1) : 0.0,
                'valor_dev' => round($valorDev, 2),
            ];
        })->sortByDesc('valor_dev')->values();
        $kpis['ventas_usd'] = round($porSede->sum('ventas_usd'), 2);
        $kpis['pct_ventas'] = $kpis['ventas_usd'] > 0 ? round($kpis['usd'] / $kpis['ventas_usd'] * 100, 1) : 0.0;

        $mix = $this->base->queryLineas($periodo['inicio'], $periodo['fin'], $sedes, null, $vendedor, $producto)
            ->selectRaw('COALESCE(vd.nombre_producto, vd.codigo_producto, \'Sin nombre\') as nombre')
            ->selectRaw('COALESCE(vd.codigo_producto, \'\') as codigo')
            ->selectRaw("SUM(CASE WHEN UPPER(vd.tipo_documento)='FAC' THEN ABS(vd.cantidad) ELSE 0 END) as vendidas")
            ->selectRaw("SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN ABS(vd.cantidad) ELSE 0 END) as devueltas")
            ->selectRaw("SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN ABS(vd.cantidad * {$precio}) ELSE 0 END) as valor_dev")
            ->groupBy(DB::raw('COALESCE(vd.nombre_producto, vd.codigo_producto, \'Sin nombre\')'), DB::raw("COALESCE(vd.codigo_producto, '')"))
            ->havingRaw("SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN ABS(vd.cantidad) ELSE 0 END) > 0")
            ->orderByDesc('valor_dev')
            ->limit(15)
            ->get();

        $motivosProd = collect();
        if (Schema::hasColumn('ventas_detalle', 'motivo_devolucion')) {
            $motivosProd = (clone $dev)
                ->selectRaw('COALESCE(vd.nombre_producto, vd.codigo_producto, \'Sin nombre\') as nombre')
                ->selectRaw("COALESCE(NULLIF(TRIM(vd.motivo_devolucion), ''), 'Sin motivo') as motivo")
                ->selectRaw('COUNT(*) as veces')
                ->groupBy(DB::raw('COALESCE(vd.nombre_producto, vd.codigo_producto, \'Sin nombre\')'), DB::raw("COALESCE(NULLIF(TRIM(vd.motivo_devolucion), ''), 'Sin motivo')"))
                ->orderByDesc('veces')
                ->get()
                ->groupBy('nombre')
                ->map(fn ($rows) => ProfitMotivos::devolucion($rows->first()->motivo));
        }
        $porProducto = $mix->map(function ($row) use ($motivosProd) {
            $vendidas = (float) $row->vendidas;
            $row->pct = $vendidas > 0 ? round((float) $row->devueltas / $vendidas * 100, 1) : 100.0;
            $row->motivo = $motivosProd[$row->nombre] ?? '—';
            return $row;
        });

        if ($conDetalle) {
            $detalleQ = (clone $dev)
                ->select([
                    'vd.numero_documento',
                    'vd.fecha',
                    'vd.sede',
                    'vd.cliente',
                    'vd.vendedor',
                    'vd.nombre_producto',
                    'vd.codigo_producto',
                    'vd.cantidad',
                    'vd.precio_venta',
                ]);
            if (Schema::hasColumn('ventas_detalle', 'precio_neto')) {
                $detalleQ->addSelect('vd.precio_neto');
            }
            if (Schema::hasColumn('ventas_detalle', 'motivo_devolucion')) {
                $detalleQ->addSelect('vd.motivo_devolucion');
            }
            if (Schema::hasColumn('ventas_detalle', 'usuario')) {
                $detalleQ->addSelect('vd.usuario');
            } else {
                $detalleQ->selectRaw("NULL as usuario");
            }
            $detalle = $detalleQ
                ->orderByDesc('vd.fecha')
                ->orderByDesc('vd.numero_documento')
                ->paginate(25)
                ->withQueryString();
            $detalle->getCollection()->transform(function ($row) {
                if (isset($row->motivo_devolucion)) {
                    $row->motivo_devolucion = ProfitMotivos::devolucion($row->motivo_devolucion);
                }

                return $row;
            });
        }

        return compact('kpis', 'porMotivo', 'porSede', 'porProducto', 'motivoTop', 'detalle');
    }

    public function valorizados(array $periodo, ?string $sede, ?string $categoria, ?string $producto): array
    {
        $sedes = $this->base->filtrarSedes($sede);
        $hoy = Carbon::now('America/Caracas')->startOfDay();
        $vacio = $this->valorizadosVacio($sedes);
        if (! Schema::hasTable('stock_actual')) {
            return $vacio;
        }

        $query = DB::table('stock_actual as sa')
            ->whereIn(DB::raw('UPPER(TRIM(sa.sede))'), $sedes)
            ->where('sa.existencia', '>', 0);
        $join = Schema::hasTable('productos');
        if ($join) {
            $query->leftJoin('productos as p', 'p.id', '=', 'sa.producto_id');
        }
        if ($categoria && $join) {
            $query->whereRaw('UPPER(TRIM(p.categoria)) = ?', [mb_strtoupper(trim($categoria), 'UTF-8')]);
        }
        if ($producto) {
            $like = '%'.$producto.'%';
            $query->where(function ($q) use ($like, $join) {
                if ($join) {
                    $q->where('p.codigo', 'like', $like)->orWhere('p.nombre', 'like', $like);
                }
            });
        }
        $costoSql = $join && Schema::hasColumn('productos', 'costo_actual')
            ? 'COALESCE(NULLIF(p.costo_actual, 0), 0)'
            : '0';

        $tot = (clone $query)
            ->selectRaw('SUM(sa.existencia) as unidades')
            ->selectRaw("SUM(sa.existencia * {$costoSql}) as costo")
            ->selectRaw('COUNT(DISTINCT sa.producto_id) as productos')
            ->selectRaw('COUNT(DISTINCT UPPER(TRIM(sa.sede))) as sedes')
            ->first();

        $porSede = (clone $query)
            ->selectRaw('UPPER(TRIM(sa.sede)) as sede')
            ->selectRaw('SUM(sa.existencia) as unidades')
            ->selectRaw("SUM(sa.existencia * {$costoSql}) as costo")
            ->groupBy(DB::raw('UPPER(TRIM(sa.sede))'))
            ->orderByDesc('costo')
            ->get();
        $totalCosto = (float) ($tot->costo ?? 0);
        $porSede = $porSede->map(function ($row) use ($totalCosto) {
            $row->valor = (float) $row->costo;
            $row->pct = $totalCosto > 0 ? round((float) $row->costo / $totalCosto * 100, 1) : 0.0;
            return $row;
        });

        $porCategoria = collect();
        $porMarca = collect();
        if ($join) {
            $porCategoria = (clone $query)
                ->selectRaw("COALESCE(NULLIF(TRIM(p.categoria), ''), 'Sin categoría') as nombre")
                ->selectRaw('SUM(sa.existencia) as unidades')
                ->selectRaw("SUM(sa.existencia * {$costoSql}) as valor")
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(p.categoria), ''), 'Sin categoría')"))
                ->orderByDesc('valor')
                ->limit(12)
                ->get();
            if (Schema::hasColumn('productos', 'proveedor')) {
                $porMarca = (clone $query)
                    ->selectRaw("COALESCE(NULLIF(TRIM(p.proveedor), ''), 'Sin marca') as nombre")
                    ->selectRaw('SUM(sa.existencia) as unidades')
                    ->selectRaw("SUM(sa.existencia * {$costoSql}) as valor")
                    ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(p.proveedor), ''), 'Sin marca')"))
                    ->orderByDesc('valor')
                    ->limit(12)
                    ->get();
            }
        }

        $metricas = $this->metricasProductoInventario($sedes, $hoy, $categoria, $producto);
        $clases = [
            'Normal' => ['productos' => 0, 'valor' => 0.0, 'color' => 'verde'],
            'Vigilar' => ['productos' => 0, 'valor' => 0.0, 'color' => 'amarillo'],
            'Sobrestock' => ['productos' => 0, 'valor' => 0.0, 'color' => 'naranja'],
            'Crítico / Sin Rotación' => ['productos' => 0, 'valor' => 0.0, 'color' => 'rojo'],
        ];
        $sinRotacion = 0.0;
        $gt90 = 0.0;
        $gt6m = 0.0;
        $rotacion = [];
        foreach ($metricas as $item) {
            $clase = $item['clase'];
            if (! isset($clases[$clase])) {
                $clase = 'Crítico / Sin Rotación';
            }
            $clases[$clase]['productos']++;
            $clases[$clase]['valor'] += $item['valor'];
            if ($item['dias_sin_venta'] >= 90) {
                $sinRotacion += $item['valor'];
                $gt90 += $item['valor'];
            }
            if ($item['meses_inventario'] > 6 || $item['dias_sin_venta'] >= 180) {
                $gt6m += $item['valor'];
            }
            $rotacion[] = $item;
        }
        usort($rotacion, fn ($a, $b) => $b['meses_inventario'] <=> $a['meses_inventario']);
        $rotacion = array_slice($rotacion, 0, 20);

        $kpis = [
            'valor' => round($totalCosto, 2),
            'costo' => round($totalCosto, 2),
            'unidades' => round((float) ($tot->unidades ?? 0), 2),
            'productos' => (int) ($tot->productos ?? 0),
            'sedes' => (int) ($tot->sedes ?? 0),
            'sin_rotacion' => round($sinRotacion, 2),
            'gt90' => round($gt90, 2),
            'gt6m' => round($gt6m, 2),
        ];

        $abc = $this->abcInventario($periodo, $sedes, $categoria, $producto, $metricas);

        return [
            'kpis' => $kpis,
            'por_sede' => $porSede,
            'por_categoria' => $porCategoria,
            'por_marca' => $porMarca,
            'clasificacion' => $clases,
            'rotacion' => $rotacion,
        ] + $abc;
    }

    public function ajustes(array $periodo, ?string $sede, ?string $tipo): array
    {
        $sedes = $this->base->filtrarSedes($sede);
        $vacio = [
            'kpis' => [
                'movimientos' => 0, 'unidades' => 0.0, 'valor' => 0.0,
                'entradas_und' => 0.0, 'salidas_und' => 0.0,
                'positivos' => 0, 'negativos' => 0,
            ],
            'por_tipo' => collect(),
            'por_sede' => collect(),
            'por_motivo' => collect(),
            'motivo_top' => null,
            'usuarios' => collect(),
            'alertas' => [],
            'tipos' => collect(),
        ];
        if (! Schema::hasTable('ajustes_inventario')) {
            return $vacio;
        }

        $tiposOk = $this->base->tiposAjustePermitidos();
        $docs = $this->base->sqlCountDocumentosAjuste();
        $baseAjustes = DB::table('ajustes_inventario')
            ->whereBetween('fecha', [$periodo['inicio']->toDateString(), $periodo['fin']->toDateString()])
            ->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes)
            ->whereIn(DB::raw('UPPER(TRIM(tipo_movimiento))'), $tiposOk);
        $tipos = collect($tiposOk);
        $query = clone $baseAjustes;
        $tipoNorm = $tipo ? mb_strtoupper(trim($tipo), 'UTF-8') : '';
        if ($tipoNorm !== '' && in_array($tipoNorm, $tiposOk, true)) {
            $query->whereRaw('UPPER(TRIM(tipo_movimiento)) = ?', [$tipoNorm]);
        }

        $kpisRow = (clone $query)
            ->selectRaw("{$docs} as movimientos")
            ->selectRaw('SUM(cantidad) as unidades')
            ->selectRaw('SUM(cantidad * COALESCE(costo_unitario, 0)) as valor')
            ->selectRaw('SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) as entradas_und')
            ->selectRaw('SUM(CASE WHEN cantidad < 0 THEN ABS(cantidad) ELSE 0 END) as salidas_und')
            ->selectRaw('SUM(CASE WHEN cantidad > 0 THEN 1 ELSE 0 END) as positivos')
            ->selectRaw('SUM(CASE WHEN cantidad < 0 THEN 1 ELSE 0 END) as negativos')
            ->first();

        $porTipo = (clone $query)
            ->selectRaw('UPPER(TRIM(tipo_movimiento)) as tipo')
            ->selectRaw("{$docs} as movimientos")
            ->selectRaw('SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) as entradas')
            ->selectRaw('SUM(CASE WHEN cantidad < 0 THEN ABS(cantidad) ELSE 0 END) as salidas')
            ->selectRaw('SUM(cantidad * COALESCE(costo_unitario, 0)) as valor')
            ->groupBy(DB::raw('UPPER(TRIM(tipo_movimiento))'))
            ->orderByDesc('movimientos')
            ->get();

        $porSede = (clone $query)
            ->selectRaw('UPPER(TRIM(sede)) as sede')
            ->selectRaw("{$docs} as movimientos")
            ->selectRaw('SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) as entradas')
            ->selectRaw('SUM(CASE WHEN cantidad < 0 THEN ABS(cantidad) ELSE 0 END) as salidas')
            ->selectRaw('SUM(cantidad) as diferencia')
            ->selectRaw('SUM(cantidad * COALESCE(costo_unitario, 0)) as valor')
            ->groupBy(DB::raw('UPPER(TRIM(sede))'))
            ->orderByDesc('movimientos')
            ->get();

        $porMotivo = collect();
        $motivoTop = null;
        if (Schema::hasColumn('ajustes_inventario', 'motivo')) {
            $porMotivo = (clone $query)
                ->selectRaw("COALESCE(NULLIF(TRIM(motivo), ''), 'Sin motivo') as motivo")
                ->selectRaw('UPPER(TRIM(tipo_movimiento)) as tipo')
                ->selectRaw("{$docs} as veces")
                ->selectRaw('SUM(cantidad) as unidades')
                ->selectRaw('SUM(cantidad * COALESCE(costo_unitario, 0)) as valor')
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(motivo), ''), 'Sin motivo')"), DB::raw('UPPER(TRIM(tipo_movimiento))'))
                ->orderByDesc('veces')
                ->limit(40)
                ->get();
            $porMotivo = $this->consolidarMotivos($porMotivo, 'ajuste')->take(12)->values();
            $motivoTop = $porMotivo->first();
        }

        $usuarios = collect();
        if (Schema::hasColumn('ajustes_inventario', 'usuario')) {
            $usuarios = (clone $query)
                ->selectRaw("COALESCE(NULLIF(TRIM(usuario), ''), 'Sin usuario') as usuario")
                ->selectRaw("{$docs} as movimientos")
                ->selectRaw('SUM(cantidad * COALESCE(costo_unitario, 0)) as valor')
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(usuario), ''), 'Sin usuario')"))
                ->orderByDesc('movimientos')
                ->limit(40)
                ->get();
            $usuarios = $this->resolverUsuariosAjustes($usuarios)->take(10)->values();
        }

        $alertas = $this->alertasAjustes($porSede, $usuarios, $query, $docs);

        return [
            'kpis' => [
                'movimientos' => (int) ($kpisRow->movimientos ?? 0),
                'unidades' => round((float) ($kpisRow->unidades ?? 0), 2),
                'valor' => round((float) ($kpisRow->valor ?? 0), 2),
                'entradas_und' => round((float) ($kpisRow->entradas_und ?? 0), 2),
                'salidas_und' => round((float) ($kpisRow->salidas_und ?? 0), 2),
                'positivos' => (int) ($kpisRow->positivos ?? 0),
                'negativos' => (int) ($kpisRow->negativos ?? 0),
            ],
            'por_tipo' => $porTipo,
            'por_sede' => $porSede,
            'por_motivo' => $porMotivo,
            'motivo_top' => $motivoTop,
            'usuarios' => $usuarios,
            'alertas' => $alertas,
            'tipos' => $tipos,
        ];
    }

    public function rentabilidad(array $periodo, ?string $sede, ?string $categoria, ?string $vendedor, ?string $producto): array
    {
        $sedes = $this->base->filtrarSedes($sede);
        $precio = $this->campoPrecio();
        $costo = Schema::hasColumn('ventas_detalle', 'costo_unitario') ? 'COALESCE(vd.costo_unitario, 0)' : '0';
        $vacio = [
            'kpis' => ['ventas' => 0.0, 'costo' => 0.0, 'utilidad' => 0.0, 'margen_pct' => 0.0],
            'por_sede' => collect(),
            'por_categoria' => collect(),
            'por_producto' => collect(),
            'por_vendedor' => collect(),
            'poco_margen' => collect(),
            'mejor_margen' => collect(),
        ];
        if (! Schema::hasTable('ventas_detalle')) {
            return $vacio;
        }

        $base = $this->base->queryLineas($periodo['inicio'], $periodo['fin'], $sedes, $categoria, $vendedor, $producto);
        $ventasSql = "SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN -ABS(vd.cantidad * {$precio}) ELSE ABS(vd.cantidad * {$precio}) END)";
        $costoSql = "SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN -ABS(vd.cantidad * {$costo}) ELSE ABS(vd.cantidad * {$costo}) END)";

        $tot = (clone $base)
            ->selectRaw("{$ventasSql} as ventas")
            ->selectRaw("{$costoSql} as costo")
            ->first();
        $ventas = round((float) ($tot->ventas ?? 0), 2);
        $costoT = round((float) ($tot->costo ?? 0), 2);
        $utilidad = round($ventas - $costoT, 2);

        $mapMargen = function ($row) {
            $ventas = (float) $row->ventas;
            $costo = (float) $row->costo;
            $row->utilidad = round($ventas - $costo, 2);
            $row->margen_pct = $ventas > 0 ? round(($ventas - $costo) / $ventas * 100, 1) : 0.0;
            return $row;
        };

        $porSede = (clone $base)
            ->selectRaw('UPPER(TRIM(vd.sede)) as nombre')
            ->selectRaw("{$ventasSql} as ventas")
            ->selectRaw("{$costoSql} as costo")
            ->groupBy(DB::raw('UPPER(TRIM(vd.sede))'))
            ->orderByDesc('ventas')
            ->get()
            ->map($mapMargen);

        $porCategoria = collect();
        if (Schema::hasTable('productos')) {
            $catQ = $this->base->queryLineas($periodo['inicio'], $periodo['fin'], $sedes, $categoria, $vendedor, $producto);
            if (! $categoria) {
                $catQ->leftJoin('productos as p', 'p.id', '=', 'vd.producto_id');
            }
            $porCategoria = $catQ
                ->selectRaw("COALESCE(NULLIF(TRIM(p.categoria), ''), 'Sin categoría') as nombre")
                ->selectRaw("{$ventasSql} as ventas")
                ->selectRaw("{$costoSql} as costo")
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(p.categoria), ''), 'Sin categoría')"))
                ->orderByDesc('ventas')
                ->limit(12)
                ->get()
                ->map($mapMargen);
        }

        $porProducto = (clone $base)
            ->selectRaw('COALESCE(vd.nombre_producto, vd.codigo_producto, \'Sin nombre\') as nombre')
            ->selectRaw("{$ventasSql} as ventas")
            ->selectRaw("{$costoSql} as costo")
            ->groupBy(DB::raw('COALESCE(vd.nombre_producto, vd.codigo_producto, \'Sin nombre\')'))
            ->orderByDesc('ventas')
            ->limit(40)
            ->get()
            ->map($mapMargen);

        $porVendedor = (clone $base)
            ->selectRaw("COALESCE(NULLIF(TRIM(vd.vendedor), ''), 'Sin vendedor') as nombre")
            ->selectRaw("{$ventasSql} as ventas")
            ->selectRaw("{$costoSql} as costo")
            ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(vd.vendedor), ''), 'Sin vendedor')"))
            ->orderByDesc('ventas')
            ->limit(12)
            ->get()
            ->map($mapMargen);

        $conVentas = $porProducto->filter(fn ($r) => (float) $r->ventas > 0);
        $pocoMargen = $conVentas->sortBy('margen_pct')->take(8)->values();
        $mejorMargen = $conVentas->sortByDesc('margen_pct')->take(8)->values();

        return [
            'kpis' => [
                'ventas' => $ventas,
                'costo' => $costoT,
                'utilidad' => $utilidad,
                'margen_pct' => $ventas > 0 ? round($utilidad / $ventas * 100, 1) : 0.0,
            ],
            'por_sede' => $porSede,
            'por_categoria' => $porCategoria,
            'por_producto' => $porProducto->take(12)->values(),
            'por_vendedor' => $porVendedor,
            'poco_margen' => $pocoMargen,
            'mejor_margen' => $mejorMargen,
        ];
    }

    private function campoPrecio(): string
    {
        return Schema::hasColumn('ventas_detalle', 'precio_neto')
            ? 'COALESCE(vd.precio_neto, vd.precio_venta)'
            : 'vd.precio_venta';
    }

    private function valorizadosVacio(array $sedes): array
    {
        return [
            'kpis' => ['valor' => 0, 'costo' => 0, 'unidades' => 0, 'productos' => 0, 'sedes' => 0, 'sin_rotacion' => 0, 'gt90' => 0, 'gt6m' => 0],
            'por_sede' => collect(),
            'por_categoria' => collect(),
            'por_marca' => collect(),
            'clasificacion' => [],
            'rotacion' => [],
            'abc_resumen_rotacion' => ['A' => $this->abcResumenVacio(), 'B' => $this->abcResumenVacio(), 'C' => $this->abcResumenVacio()],
            'abc_resumen_margen' => ['A' => $this->abcResumenVacio(), 'B' => $this->abcResumenVacio(), 'C' => $this->abcResumenVacio()],
            'abc_pareto' => collect(),
            'abc_matriz' => $this->abcMatrizVacia(),
            'abc_alertas' => [],
            'abc_total' => 0,
        ];
    }

    /**
     * @return array{productos:int,pct_items:float,pct_valor:float}
     */
    private function abcResumenVacio(): array
    {
        return ['productos' => 0, 'pct_items' => 0.0, 'pct_valor' => 0.0];
    }

    /**
     * @return array<string, array<string, array{productos:int,unidades:float,valor_inv:float}>>
     */
    private function abcMatrizVacia(): array
    {
        $celda = static fn () => ['productos' => 0, 'unidades' => 0.0, 'valor_inv' => 0.0];

        return [
            'A' => ['A' => $celda(), 'B' => $celda(), 'C' => $celda()],
            'B' => ['A' => $celda(), 'B' => $celda(), 'C' => $celda()],
            'C' => ['A' => $celda(), 'B' => $celda(), 'C' => $celda()],
        ];
    }

    /**
     * @param  list<string>  $sedes
     * @param  list<array<string, mixed>>  $metricas
     * @return array<string, mixed>
     */
    private function abcInventario(array $periodo, array $sedes, ?string $categoria, ?string $producto, array $metricas): array
    {
        $vacio = [
            'abc_resumen_rotacion' => ['A' => $this->abcResumenVacio(), 'B' => $this->abcResumenVacio(), 'C' => $this->abcResumenVacio()],
            'abc_resumen_margen' => ['A' => $this->abcResumenVacio(), 'B' => $this->abcResumenVacio(), 'C' => $this->abcResumenVacio()],
            'abc_pareto' => collect(),
            'abc_matriz' => $this->abcMatrizVacia(),
            'abc_alertas' => [],
            'abc_total' => 0,
        ];
        if (! Schema::hasTable('ventas_detalle')) {
            return $vacio;
        }

        $unidadesSql = $this->base->sqlUnidadesNetas();
        $precio = $this->campoPrecio();
        $costo = Schema::hasColumn('ventas_detalle', 'costo_unitario') ? 'COALESCE(vd.costo_unitario, 0)' : '0';
        $ventasSql = "SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN -ABS(vd.cantidad * {$precio}) ELSE ABS(vd.cantidad * {$precio}) END)";
        $costoSql = "SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN -ABS(vd.cantidad * {$costo}) ELSE ABS(vd.cantidad * {$costo}) END)";

        $query = $this->base->queryLineas($periodo['inicio'], $periodo['fin'], $sedes, $categoria, null, $producto);
        if (Schema::hasTable('productos') && ! $categoria) {
            $query->leftJoin('productos as p', 'p.id', '=', 'vd.producto_id');
        }
        $catSql = Schema::hasTable('productos')
            ? "COALESCE(NULLIF(TRIM(MAX(p.categoria)), ''), 'Sin categoría')"
            : "'Sin categoría'";

        $ventas = $query
            ->selectRaw("COALESCE(CAST(vd.producto_id AS TEXT), vd.codigo_producto, 'sin') as clave")
            ->selectRaw('MAX(vd.producto_id) as producto_id')
            ->selectRaw('MAX(vd.codigo_producto) as codigo')
            ->selectRaw("MAX(COALESCE(vd.nombre_producto, vd.codigo_producto, 'Sin nombre')) as nombre")
            ->selectRaw("{$catSql} as categoria")
            ->selectRaw("{$unidadesSql} as unidades")
            ->selectRaw("{$ventasSql} as ventas")
            ->selectRaw("{$costoSql} as costo")
            ->groupBy(DB::raw("COALESCE(CAST(vd.producto_id AS TEXT), vd.codigo_producto, 'sin')"))
            ->havingRaw("{$unidadesSql} > 0")
            ->get()
            ->map(function ($row) {
                $row->utilidad = round((float) $row->ventas - (float) $row->costo, 2);
                $row->unidades = round((float) $row->unidades, 2);

                return $row;
            });

        if ($ventas->isEmpty()) {
            return $vacio;
        }

        $porRotacion = $this->abc->clasificar($ventas->map(fn ($row) => clone $row), 'unidades');
        $porMargen = $this->abc->clasificar($ventas->map(fn ($row) => clone $row), 'utilidad');
        $margenPorClave = $porMargen->mapWithKeys(fn ($row) => [(string) $row->clave => $row->abc]);

        $stock = collect($metricas)->keyBy(fn ($m) => (string) ($m['producto_id'] ?? ''));
        $matriz = $this->abcMatrizVacia();
        $alertas = [];
        foreach ($porRotacion as $row) {
            $row->abc_rotacion = $row->abc;
            $row->abc_margen = $margenPorClave[(string) $row->clave] ?? 'C';
            $inv = $stock->get((string) ($row->producto_id ?? ''));
            $row->clase_inv = $inv['clase'] ?? 'Sin stock';
            $row->valor_inv = (float) ($inv['valor'] ?? 0);
            $rot = $row->abc_rotacion;
            $mar = $row->abc_margen;
            $matriz[$rot][$mar]['productos']++;
            $matriz[$rot][$mar]['unidades'] += (float) $row->unidades;
            $matriz[$rot][$mar]['valor_inv'] += $row->valor_inv;
            $nombre = $row->nombre ?: $row->codigo;
            if ($rot === 'A' && ($row->clase_inv === 'Crítico / Sin Rotación')) {
                $alertas[] = "{$nombre}: A en rotación y stock crítico — prioridad de compra.";
            } elseif ($rot === 'A' && $row->clase_inv === 'Sobrestock') {
                $alertas[] = "{$nombre}: A en rotación con sobrestock — vende mucho y hay de más.";
            } elseif ($rot === 'C' && $row->clase_inv === 'Sobrestock') {
                $alertas[] = "{$nombre}: C en rotación con sobrestock — candidato a liquidar.";
            }
        }

        return [
            'abc_resumen_rotacion' => $this->abc->resumen($porRotacion),
            'abc_resumen_margen' => $this->abc->resumen($porMargen),
            'abc_pareto' => $porRotacion->take(20)->values(),
            'abc_matriz' => $matriz,
            'abc_alertas' => array_slice($alertas, 0, 8),
            'abc_total' => $porRotacion->count(),
        ];
    }

    /**
     * @param  list<string>  $sedes
     * @return list<array<string, mixed>>
     */
    private function metricasProductoInventario(array $sedes, Carbon $hoy, ?string $categoria = null, ?string $producto = null): array
    {
        if (! Schema::hasTable('stock_actual') || ! Schema::hasTable('productos')) {
            return [];
        }

        $stock = DB::table('stock_actual as sa')
            ->join('productos as p', 'p.id', '=', 'sa.producto_id')
            ->whereIn(DB::raw('UPPER(TRIM(sa.sede))'), $sedes)
            ->where('sa.existencia', '>', 0);
        if ($categoria) {
            $stock->whereRaw('UPPER(TRIM(p.categoria)) = ?', [mb_strtoupper(trim($categoria), 'UTF-8')]);
        }
        if ($producto) {
            $like = '%'.$producto.'%';
            $stock->where(function ($q) use ($like) {
                $q->where('p.codigo', 'like', $like)->orWhere('p.nombre', 'like', $like);
            });
        }
        $stock = $stock
            ->selectRaw('sa.producto_id')
            ->selectRaw('MAX(p.codigo) as codigo')
            ->selectRaw('MAX(p.nombre) as nombre')
            ->selectRaw('SUM(sa.existencia) as unidades')
            ->selectRaw('SUM(sa.existencia * COALESCE(p.costo_actual, 0)) as valor')
            ->groupBy('sa.producto_id')
            ->get()
            ->keyBy('producto_id');

        $ventas60 = collect();
        $ultima = collect();
        if (Schema::hasTable('ventas_historicas')) {
            $vh = DB::table('ventas_historicas')
                ->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes)
                ->selectRaw('producto_id')
                ->selectRaw('SUM(ventas_60d) as ventas_60d')
                ->selectRaw('MAX(ultima_venta) as ultima_venta')
                ->groupBy('producto_id')
                ->get();
            $ventas60 = $vh->pluck('ventas_60d', 'producto_id');
            $ultima = $vh->pluck('ultima_venta', 'producto_id');
        } elseif (Schema::hasTable('ventas_detalle')) {
            $desde = $hoy->copy()->subDays(60)->toDateString();
            $agg = DB::table('ventas_detalle')
                ->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes)
                ->where('fecha', '>=', $desde)
                ->whereRaw("UPPER(TRIM(tipo_documento)) = 'FAC'")
                ->selectRaw('producto_id')
                ->selectRaw('SUM(ABS(cantidad)) as ventas_60d')
                ->selectRaw('MAX(fecha) as ultima_venta')
                ->groupBy('producto_id')
                ->get();
            $ventas60 = $agg->pluck('ventas_60d', 'producto_id');
            $ultima = $agg->pluck('ultima_venta', 'producto_id');
        }

        $out = [];
        foreach ($stock as $id => $row) {
            $v60 = (float) ($ventas60[$id] ?? 0);
            $ult = $ultima[$id] ?? null;
            $dias = $ult ? $hoy->diffInDays(Carbon::parse($ult)->startOfDay()) : 999;
            $promMes = $v60 / 2;
            $meses = $promMes > 0 ? round((float) $row->unidades / $promMes, 1) : 999.0;
            $out[] = [
                'producto_id' => (int) $id,
                'codigo' => $row->codigo,
                'nombre' => $row->nombre,
                'unidades' => (float) $row->unidades,
                'valor' => (float) $row->valor,
                'ventas_30d' => round($v60 / 2, 1),
                'ventas_60d' => $v60,
                'dias_sin_venta' => (int) $dias,
                'meses_inventario' => $meses,
                'clase' => $this->clasificar($dias, $meses),
            ];
        }

        return $out;
    }

    private function clasificar(int $diasSinVenta, float $meses): string
    {
        if ($meses >= 999 || $diasSinVenta > 90) {
            return 'Crítico / Sin Rotación';
        }
        if ($meses <= 2) {
            return 'Normal';
        }
        if ($meses <= 4) {
            return 'Vigilar';
        }
        if ($meses <= 6) {
            return 'Sobrestock';
        }

        return 'Crítico / Sin Rotación';
    }

    private function alertasAjustes(Collection $porSede, Collection $usuarios, $query, string $docs): array
    {
        $alertas = [];
        $avgMov = $porSede->avg('movimientos') ?: 0;
        foreach ($porSede as $fila) {
            if ($avgMov > 0 && $fila->movimientos > $avgMov * 2 && $fila->movimientos >= 20) {
                $alertas[] = "{$fila->sede} tiene ".number_format($fila->movimientos).' movimientos, más del doble del promedio.';
            }
        }
        $avgUser = $usuarios->avg('movimientos') ?: 0;
        $topUser = $usuarios->first();
        if ($topUser && $avgUser > 0 && $topUser->movimientos > $avgUser * 2) {
            $alertas[] = "El usuario {$topUser->usuario} registró ".number_format($topUser->movimientos).' ajustes.';
        }

        $negativos = (clone $query)
            ->where('cantidad', '<', 0)
            ->selectRaw('SUM(cantidad * COALESCE(costo_unitario, 0)) as valor')
            ->value('valor');
        if ((float) $negativos < -5000) {
            $alertas[] = 'Hay ajustes negativos por $'.number_format(abs((float) $negativos), 2).'.';
        }

        if (Schema::hasColumn('ajustes_inventario', 'codigo_producto')) {
            $prod = (clone $query)
                ->selectRaw("COALESCE(NULLIF(TRIM(codigo_producto), ''), 'Sin código') as codigo")
                ->selectRaw("{$docs} as veces")
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(codigo_producto), ''), 'Sin código')"))
                ->orderByDesc('veces')
                ->first();
            if ($prod && $prod->veces >= 15) {
                $alertas[] = "El producto {$prod->codigo} se ajustó {$prod->veces} veces en el período.";
            }
        }

        return array_slice($alertas, 0, 6);
    }

    private function consolidarMotivos(Collection $rows, string $contexto): Collection
    {
        return $rows
            ->map(function ($row) use ($contexto) {
                $row->motivo = $contexto === 'devolucion'
                    ? ProfitMotivos::devolucion($row->motivo ?? null)
                    : ProfitMotivos::ajuste($row->motivo ?? null, $row->tipo ?? null);

                return $row;
            })
            ->groupBy('motivo')
            ->map(function (Collection $grupo) {
                $first = clone $grupo->first();
                $first->veces = $grupo->sum('veces');
                if (isset($first->unidades)) {
                    $first->unidades = $grupo->sum(fn ($r) => (float) $r->unidades);
                }
                if (isset($first->usd)) {
                    $first->usd = $grupo->sum(fn ($r) => (float) $r->usd);
                }
                if (isset($first->valor)) {
                    $first->valor = $grupo->sum(fn ($r) => (float) $r->valor);
                }

                return $first;
            })
            ->sortByDesc('veces')
            ->values();
    }

    private function resolverUsuariosAjustes(Collection $usuarios): Collection
    {
        if ($usuarios->isEmpty()) {
            return $usuarios;
        }

        $catalogo = $this->catalogoClientes();

        return $usuarios
            ->map(function ($row) use ($catalogo) {
                $codigo = trim((string) $row->usuario);
                $match = $this->resolverCliente($codigo, $catalogo);
                $claveRaw = $this->claveCedula($codigo);
                $row->nombre = $match['nombre'] ?? null;
                $row->codigo = $match['cedula'] ?? $codigo;
                $row->usuario = $row->nombre ?: $codigo;
                $row->clave = $match['clave'] ?? ($claveRaw !== '' ? $claveRaw : strtoupper($codigo));

                return $row;
            })
            ->groupBy('clave')
            ->map(function (Collection $grupo) {
                $first = clone $grupo->first();
                $first->movimientos = $grupo->sum('movimientos');
                $first->valor = $grupo->sum(fn ($r) => (float) $r->valor);
                if ($first->nombre) {
                    $first->usuario = $first->nombre;
                }

                return $first;
            })
            ->sortByDesc('movimientos')
            ->values();
    }

    /**
     * @return array{por_clave: array<string, array{nombre: string, cedula: string, clave: string}>, lista: list<array{nombre: string, cedula: string, clave: string}>}
     */
    private function catalogoClientes(): array
    {
        if (! Schema::hasTable('clientes') || ! Schema::hasColumn('clientes', 'cedula')) {
            return ['por_clave' => [], 'lista' => []];
        }

        $porClave = [];
        $lista = [];
        foreach (DB::table('clientes')->get(['cedula', 'nombre']) as $cliente) {
            $nombre = trim((string) $cliente->nombre);
            if ($nombre === '') {
                continue;
            }
            $clave = $this->claveCedula((string) $cliente->cedula);
            if ($clave === '') {
                continue;
            }
            $item = [
                'nombre' => $nombre,
                'cedula' => $clave,
                'clave' => $clave,
            ];
            $lista[] = $item;
            $porClave[$clave] = $item;
        }

        return ['por_clave' => $porClave, 'lista' => $lista];
    }

    /**
     * @param  array{por_clave: array<string, array{nombre: string, cedula: string, clave: string}>, lista: list<array{nombre: string, cedula: string, clave: string}>}  $catalogo
     * @return array{nombre: string, cedula: string, clave: string}|null
     */
    private function resolverCliente(string $codigo, array $catalogo): ?array
    {
        $clave = $this->claveCedula($codigo);
        if ($clave === '') {
            return null;
        }
        if (isset($catalogo['por_clave'][$clave])) {
            return $catalogo['por_clave'][$clave];
        }

        // Profit a veces guarda la cédula con un dígito de menos (V3065986 = V30657986).
        if (strlen($clave) < 6) {
            return null;
        }
        $hits = [];
        foreach ($catalogo['lista'] as $cliente) {
            if (strlen($cliente['clave']) === strlen($clave) + 1
                && $this->esSubsecuencia($clave, $cliente['clave'])) {
                $hits[] = $cliente;
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }

    private function esSubsecuencia(string $needle, string $haystack): bool
    {
        $i = $j = 0;
        $nNeedle = strlen($needle);
        $nHay = strlen($haystack);
        while ($i < $nHay && $j < $nNeedle) {
            if ($haystack[$i] === $needle[$j]) {
                $j++;
            }
            $i++;
        }

        return $j === $nNeedle;
    }

    private function claveCedula(string $raw): string
    {
        return preg_replace('/\D+/', '', strtoupper(trim($raw))) ?? '';
    }
}
