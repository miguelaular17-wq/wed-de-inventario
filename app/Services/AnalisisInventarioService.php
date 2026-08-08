<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for computing inventory analysis indicators:
 *  - Rotation classification
 *  - Overstock detection
 *  - Immobilized / unnecessary-purchase flags
 *  - Priority index
 *
 * Designed to be efficient for 50k+ products by executing math in PostgreSQL.
 */
class AnalisisInventarioService
{
    private const DIAS_VENTANA_ANALISIS = 60;
    private const MESES_NORMAL = 2;
    private const MESES_VIGILAR = 4;
    private const MESES_SOBRESTOCK = 6;
    private const DIAS_SIN_ROTACION = 90;

    public function __construct(
        private ProductRepository $products
    ) {}

    /**
     * Build the full analysis dataset with all indicators computed.
     * Filters, paginates, and sorts server-side.
     */
    public function getAnalysis(array $filters = []): Collection
    {
        ini_set('memory_limit', '512M');
        if (config('database.default') !== 'pgsql') {
            return collect();
        }

        $stockUpdatedAt = $this->products->lastStockUpdate();
        $stockUpdateMd5 = md5((string) $stockUpdatedAt);
        // Build a unique cache key for these base filters
        $filtersHash = md5(json_encode([
            $filters['categoria'] ?? 'Ninguno',
            $filters['subcategoria'] ?? 'Ninguno',
            $filters['proveedor'] ?? 'Ninguno',
            $filters['buscar'] ?? ''
        ]));
        $cacheKey = "analisis_inv_base_{$stockUpdateMd5}_{$filtersHash}";

        $bindings = [];
        $whereClauses = [];

        if (!empty($filters['categoria']) && $filters['categoria'] !== 'Ninguno') {
            $whereClauses[] = "p.categoria = :categoria";
            $bindings['categoria'] = $filters['categoria'];
        }
        if (!empty($filters['subcategoria']) && $filters['subcategoria'] !== 'Ninguno') {
            $whereClauses[] = "p.subcategoria = :subcategoria";
            $bindings['subcategoria'] = $filters['subcategoria'];
        }
        if (!empty($filters['proveedor']) && $filters['proveedor'] !== 'Ninguno') {
            $whereClauses[] = "p.proveedor = :proveedor";
            $bindings['proveedor'] = $filters['proveedor'];
        }
        if (!empty($filters['buscar'])) {
            $whereClauses[] = "(LOWER(p.codigo) LIKE :buscar OR LOWER(p.nombre) LIKE :buscar)";
            $bindings['buscar'] = '%' . mb_strtolower($filters['buscar']) . '%';
        }

        $whereSql = $whereClauses ? 'AND ' . implode(' AND ', $whereClauses) : '';

        $sql = "
            /*
             * REFACTORIZACIÓN HISTÓRICA:
             * Históricamente, el sistema utilizaba la columna 'venta_promedio' asumiendo que 
             * representaba un promedio mensual, cuando en realidad el script de Python enviaba 
             * un promedio diario. Al dividir (Stock / Promedio Diario), el resultado se leía 
             * como 'Meses de inventario' pero en realidad eran 'Días de inventario', causando 
             * que el 90% del inventario se clasificara falsamente como 'Sobrestock Crítico'.
             * 
             * SOLUCIÓN ARQUITECTÓNICA:
             * 1. Ignoramos por completo 'venta_promedio' para proteger la compatibilidad de otros módulos.
             * 2. Utilizamos 'ventas_60d' (Unidades totales vendidas en los últimos 60 días).
             * 3. Calculamos promedios y reglas en cascada (CTEs) de forma centralizada sin repetir matemáticas.
             * 
             * VARIABLES Y UNIDADES DE MEDIDA:
             * - total_vendido:     Unidades totales vendidas en la ventana de análisis.
             * - promedio_diario:   Unidades por día (total_vendido / DIAS_VENTANA_ANALISIS).
             * - promedio_mensual:  Unidades por mes (total_vendido / (DIAS_VENTANA_ANALISIS / 30)).
             * - dias_sin_venta:    Días transcurridos desde la última venta registrada globalmente.
             * - meses_inventario:  Meses estimados que durará el stock actual (total_stock / promedio_mensual).
             */
WITH product_metrics AS (
                SELECT 
                    p.id,
                    p.codigo,
                    p.nombre,
                    p.categoria,
                    p.subcategoria,
                    p.proveedor,
                    p.precio_mayor,
                    p.ultimo_costo_compra,
                    p.ultima_cantidad_compra,
                    COALESCE(sa.total_stock, 0) as total_stock,
                    vh.ultima_venta,
                    vh.ultima_compra,
                    COALESCE(vh.total_ventas_60d, 0) as total_ventas_60d
                FROM inventario_v2.productos p
                LEFT JOIN (
                    SELECT producto_id, SUM(existencia) as total_stock 
                    FROM inventario_v2.stock_actual 
                    GROUP BY producto_id
                ) sa ON p.id = sa.producto_id
                LEFT JOIN (
                    SELECT producto_id,
                        MAX(ultima_venta) as ultima_venta,
                        MAX(ultima_compra) as ultima_compra,
                        SUM(ventas_60d) as total_ventas_60d
                    FROM inventario_v2.ventas_historicas 
                    GROUP BY producto_id
                ) vh ON p.id = vh.producto_id
                WHERE p.activo = true {$whereSql}
            ),
            multisede_raw AS (
                SELECT 
                    p.id as producto_id,
                    sedes.sede,
                    COALESCE(s.existencia, 0) as stock,
                    COALESCE(v.ventas_60d, 0) as ventas
                FROM inventario_v2.productos p
                CROSS JOIN (SELECT DISTINCT sede FROM inventario_v2.stock_actual) sedes
                LEFT JOIN inventario_v2.stock_actual s ON p.id = s.producto_id AND sedes.sede = s.sede
                LEFT JOIN inventario_v2.ventas_historicas v ON p.id = v.producto_id AND sedes.sede = v.sede
                WHERE p.activo = true {$whereSql}
            ),
            origen_calc AS (
                SELECT DISTINCT ON (producto_id) 
                    producto_id, 
                    sede as sede_origen, 
                    stock as stock_origen, 
                    ventas as ventas_origen,
                    (stock - ventas) as exceso
                FROM multisede_raw
                ORDER BY producto_id, (stock - ventas) DESC
            ),
            destino_calc AS (
                SELECT DISTINCT ON (producto_id) 
                    producto_id, 
                    sede as sede_destino, 
                    stock as stock_destino, 
                    ventas as ventas_destino,
                    (ventas - stock) as demanda
                FROM multisede_raw
                ORDER BY producto_id, (ventas - stock) DESC
            ),
            multisede_agg AS (
                SELECT 
                    o.producto_id,
                    o.sede_origen,
                    o.stock_origen,
                    o.ventas_origen,
                    d.sede_destino,
                    d.stock_destino,
                    d.ventas_destino,
                    CASE 
                        WHEN o.exceso > 0 AND d.demanda > 0 AND o.sede_origen != d.sede_destino THEN LEAST(o.exceso, d.demanda)
                        ELSE 0
                    END as cantidad_sugerida
                FROM origen_calc o
                JOIN destino_calc d ON o.producto_id = d.producto_id
            ),
            time_metrics AS (
                SELECT pm.*,
                    msa.sede_origen,
                    msa.stock_origen,
                    msa.ventas_origen,
                    msa.sede_destino,
                    msa.stock_destino,
                    msa.ventas_destino,
                    msa.cantidad_sugerida,
                    pm.total_ventas_60d as total_vendido,
                    ROUND((pm.total_ventas_60d::numeric / " . self::DIAS_VENTANA_ANALISIS . "), 2) as promedio_diario,
                    ROUND((pm.total_ventas_60d::numeric / (" . self::DIAS_VENTANA_ANALISIS . " / 30.0)), 2) as promedio_mensual,
                    (CURRENT_DATE - pm.ultima_venta) as dias_sin_venta_raw,
                    (CURRENT_DATE - pm.ultima_compra) as dias_sin_compra_raw
                FROM product_metrics pm
                LEFT JOIN multisede_agg msa ON pm.id = msa.producto_id
                WHERE pm.total_stock > 0
            ),
            inventory_months AS (
                SELECT *,
                    COALESCE(dias_sin_venta_raw, 999) as dias_sin_venta,
                    dias_sin_compra_raw as dias_sin_compra,
                    CASE 
                        WHEN promedio_mensual > 0 THEN ROUND((total_stock::numeric / promedio_mensual), 2)
                        ELSE NULL 
                    END as meses_inventario
                FROM time_metrics
            ),
            classification_rules AS (
                SELECT *,
                    -- 1. Regla de Rotación
                    CASE 
                        WHEN dias_sin_venta_raw IS NULL THEN 'Sin rotación'
                        WHEN dias_sin_venta <= 30 THEN 'Normal'
                        WHEN dias_sin_venta <= 60 THEN 'Lenta'
                        WHEN dias_sin_venta <= " . self::DIAS_SIN_ROTACION . " THEN 'Riesgo'
                        ELSE 'Sin rotación'
                    END as rotacion,
                    CASE 
                        WHEN dias_sin_venta_raw IS NULL THEN 'rojo'
                        WHEN dias_sin_venta <= 30 THEN 'verde'
                        WHEN dias_sin_venta <= 60 THEN 'amarillo'
                        WHEN dias_sin_venta <= " . self::DIAS_SIN_ROTACION . " THEN 'naranja'
                        ELSE 'rojo'
                    END as rotacion_color,
                    
                    -- 2. Regla de Sobrestock
                    CASE 
                        WHEN meses_inventario IS NULL AND dias_sin_venta > " . self::DIAS_SIN_ROTACION . " THEN 'Crítico / Sin Rotación'
                        WHEN meses_inventario IS NULL THEN 'Crítico / Sin Rotación'
                        WHEN meses_inventario <= " . self::MESES_NORMAL . " THEN 'Normal'
                        WHEN meses_inventario <= " . self::MESES_VIGILAR . " THEN 'Vigilar'
                        WHEN meses_inventario <= " . self::MESES_SOBRESTOCK . " THEN 'Sobrestock'
                        ELSE 'Crítico / Sin Rotación'
                    END as sobrestock,
                    CASE 
                        WHEN meses_inventario IS NULL THEN 'rojo'
                        WHEN meses_inventario <= " . self::MESES_NORMAL . " THEN 'verde'
                        WHEN meses_inventario <= " . self::MESES_VIGILAR . " THEN 'amarillo'
                        WHEN meses_inventario <= " . self::MESES_SOBRESTOCK . " THEN 'naranja'
                        ELSE 'rojo'
                    END as sobrestock_color
                FROM inventory_months
            ),
            indicators_with_states AS (
                SELECT *,
                    (total_stock * dias_sin_venta) as riesgo_economico,
                    (total_stock * COALESCE(precio_mayor, 0)) as valor_inmovilizado,
                    CASE 
                        WHEN total_stock > 0 THEN ROUND(((promedio_mensual / total_stock::numeric) * total_vendido), 4)
                        ELSE 0
                    END as oportunidad_compra,

                    -- ACCIÓN RECOMENDADA
                    CASE 
                        WHEN promedio_mensual > 0 AND (meses_inventario IS NOT NULL AND meses_inventario <= 1) AND dias_sin_venta <= 90 THEN 'Comprar urgente'
                        WHEN cantidad_sugerida > 0 THEN 'Redistribuir'
                        WHEN dias_sin_venta > 90 THEN 'Liquidar por falta de rotación'
                        WHEN meses_inventario > 6 THEN 'Detener compra por exceso'
                        WHEN meses_inventario > 4 OR dias_sin_venta > 60 THEN 'Revisar compra'
                        ELSE 'Mantener'
                    END as accion_recomendada,
                    
                    CASE 
                        WHEN promedio_mensual > 0 AND (meses_inventario IS NOT NULL AND meses_inventario <= 1) AND dias_sin_venta <= 90 THEN 'azul'
                        WHEN cantidad_sugerida > 0 THEN 'naranja'
                        WHEN dias_sin_venta > 90 THEN 'rojo'
                        WHEN meses_inventario > 6 THEN 'rojo'
                        WHEN meses_inventario > 4 OR dias_sin_venta > 60 THEN 'amarillo'
                        ELSE 'verde'
                    END as accion_color,

                    CASE 
                        WHEN dias_sin_venta > 90 THEN 'Sin movimiento'
                        WHEN meses_inventario > 6 THEN 'Sobrestock'
                        ELSE 'No crítico'
                    END as motivo_critico

                FROM classification_rules
            )
            SELECT * FROM indicators_with_states
        ";

        $items = \Illuminate\Support\Facades\Cache::remember($cacheKey, 1800, function () use ($sql, $bindings) {
            $rows = DB::connection('pgsql')->select($sql, $bindings);
            $items = collect();
            foreach ($rows as $row) {
                $ultimaVentaDate = $row->ultima_venta ? \Carbon\Carbon::parse($row->ultima_venta) : null;
                $ultimaCompraDate = $row->ultima_compra ? \Carbon\Carbon::parse($row->ultima_compra) : null;

                $items->push([
                    'id' => (int) $row->id,
                    'codigo' => $row->codigo,
                    'producto' => $row->nombre,
                    'categoria' => $row->categoria ?? '—',
                    'subcategoria' => $row->subcategoria ?? '—',
                    'proveedor' => $row->proveedor ?: 'Sin Proveedor',
                    'total_stock' => (int) $row->total_stock,
                    'promedio_venta' => (float) $row->promedio_diario, // Mantenemos esta llave por compatibilidad pero con el dato correcto
                    'total_vendido' => (int) $row->total_vendido,
                    'promedio_diario' => (float) $row->promedio_diario,
                    'promedio_mensual' => (float) $row->promedio_mensual,
                    'dias_sin_venta' => (int) $row->dias_sin_venta,
                    'dias_sin_compra' => $row->dias_sin_compra ? (int) $row->dias_sin_compra : null,
                    'ultima_venta' => $ultimaVentaDate ? $ultimaVentaDate->format('d/m/Y') : null,
                    'ultima_compra' => $ultimaCompraDate ? $ultimaCompraDate->format('d/m/Y') : null,
                    'rotacion' => $row->rotacion,
                    'rotacion_color' => $row->rotacion_color,
                    'meses_inventario' => $row->meses_inventario !== null ? (float) $row->meses_inventario : 999, // Mantenemos 999 para el frontend/sort pero la logica SQL usa NULL
                    'sobrestock' => $row->sobrestock,
                    'sobrestock_color' => $row->sobrestock_color,
                    'riesgo_economico' => (float) $row->riesgo_economico,
                    'valor_inmovilizado' => (float) $row->valor_inmovilizado,
                    'oportunidad_compra' => (float) $row->oportunidad_compra,
                    'accion_recomendada' => $row->accion_recomendada,
                    'accion_color' => $row->accion_color,
                    'motivo_critico' => $row->motivo_critico,
                    'sede_origen' => $row->sede_origen,
                    'stock_origen' => (int) $row->stock_origen,
                    'ventas_origen' => (float) $row->ventas_origen,
                    'sede_destino' => $row->sede_destino,
                    'stock_destino' => (int) $row->stock_destino,
                    'ventas_destino' => (float) $row->ventas_destino,
                    'cantidad_sugerida' => (int) $row->cantidad_sugerida,
                    'precio_mayor' => (float) ($row->precio_mayor ?? 0),
                    'ultimo_costo_compra' => (float) ($row->ultimo_costo_compra ?? 0),
                    'ultima_cantidad_compra' => (float) ($row->ultima_cantidad_compra ?? 0),
                    'prioridad' => (int) $row->riesgo_economico, // Compatibilidad legacy
                    'semaforo' => $row->accion_color, // Actualizado semáforo
                    'regla_aplicada' => 'Acción: ' . $row->accion_recomendada . '. Motivo: ' . $row->motivo_critico,
                    'stocks_por_sede' => [], // Will be loaded dynamically for the current page items in the controller
                    'ventas_por_sede' => [],
                ]);
            }
            return $items;
        });

        // Apply advanced filters that are calculated or need post-filtering
        if (!empty($filters['rotacion_filter']) && $filters['rotacion_filter'] !== 'Todos') {
            $items = $items->filter(fn($item) => $item['rotacion'] === $filters['rotacion_filter']);
        }
        if (!empty($filters['sobrestock_filter']) && $filters['sobrestock_filter'] !== 'Todos') {
            $items = $items->filter(fn($item) => $item['sobrestock'] === $filters['sobrestock_filter']);
        }
        if (!empty($filters['estado_filter']) && $filters['estado_filter'] !== 'Todos') {
            if ($filters['estado_filter'] === 'Sin estado') {
                $items = $items->filter(fn($item) => ($item['estado'] ?? null) === null);
            } else {
                $items = $items->filter(fn($item) => ($item['estado'] ?? null) === $filters['estado_filter']);
            }
        }
        if (!empty($filters['semaforo_filter']) && $filters['semaforo_filter'] !== 'Todos') {
            $items = $items->filter(fn($item) => $item['semaforo'] === $filters['semaforo_filter']);
        }
        if (!empty($filters['min_dias_sin_venta'])) {
            $min = (int)$filters['min_dias_sin_venta'];
            $items = $items->filter(fn($item) => $item['dias_sin_venta'] >= $min);
        }
        if (!empty($filters['min_existencia'])) {
            $min = (int)$filters['min_existencia'];
            $items = $items->filter(fn($item) => $item['total_stock'] >= $min);
        }

        // If filtering by specific sede, we only keep items having stock in that sede.
        // For filtering we need to fetch which products have stock in the filtered sede.
        if (!empty($filters['sede']) && $filters['sede'] !== 'Todas') {
            $sede = $filters['sede'];
            $validProductIds = DB::connection('pgsql')
                ->table('stock_actual')
                ->where('sede', $sede)
                ->where('existencia', '>', 0)
                ->pluck('producto_id')
                ->flip()
                ->toArray();
            
            $items = $items->filter(fn($item) => isset($validProductIds[$item['id']]));
        }

        return $items;
    }

    /**
     * Get summary statistics by risk category.
     */
    public function getResumenRiesgo(Collection $items): array
    {
        return [
            'rotacion' => [
                'Normal' => $items->where('rotacion', 'Normal')->count(),
                'Lenta' => $items->where('rotacion', 'Lenta')->count(),
                'Riesgo' => $items->where('rotacion', 'Riesgo')->count(),
                'Sin rotación' => $items->where('rotacion', 'Sin rotación')->count(),
            ],
            'sobrestock' => [
                'Normal' => $items->where('sobrestock', 'Normal')->count(),
                'Vigilar' => $items->where('sobrestock', 'Vigilar')->count(),
                'Sobrestock' => $items->where('sobrestock', 'Sobrestock')->count(),
                'Sobrestock Crítico' => $items->where('sobrestock', 'Sobrestock Crítico')->count(),
                'N/A' => $items->where('sobrestock', 'N/A')->count(),
            ],
            'estados' => [
                'Inventario Inmovilizado' => $items->where('estado', 'Inventario Inmovilizado')->count(),
                'Compra Reciente Sin Rotación' => $items->where('estado', 'Compra Reciente Sin Rotación')->count(),
            ],
            'semaforo' => [
                'verde' => $items->where('semaforo', 'verde')->count(),
                'amarillo' => $items->where('semaforo', 'amarillo')->count(),
                'naranja' => $items->where('semaforo', 'naranja')->count(),
                'rojo' => $items->where('semaforo', 'rojo')->count(),
            ],
            'total' => $items->count(),
        ];
    }

    /**
     * Get summary by sede.
     */
    public function getResumenPorSede(Collection $items, array $filters = []): array
    {
        if (config('database.default') === 'pgsql') {
            $sedes = config('inventario.sedes_stock');
            $display = config('inventario.display');

            $stockUpdatedAt = $this->products->lastStockUpdate();
            $stockUpdateMd5 = md5((string) $stockUpdatedAt);
            $filtersHash = md5(json_encode([
                $filters['categoria'] ?? 'Ninguno',
                $filters['subcategoria'] ?? 'Ninguno',
                $filters['proveedor'] ?? 'Ninguno',
                $filters['buscar'] ?? ''
            ]));
            $cacheKey = "analisis_resumen_sedes_{$stockUpdateMd5}_{$filtersHash}";

            return \Illuminate\Support\Facades\Cache::remember($cacheKey, 1800, function () use ($sedes, $display, $filters) {
                $bindings = [];
                $whereClauses = [];
            if (!empty($filters['categoria']) && $filters['categoria'] !== 'Ninguno') {
                $whereClauses[] = "p.categoria = :categoria";
                $bindings['categoria'] = $filters['categoria'];
            }
            if (!empty($filters['subcategoria']) && $filters['subcategoria'] !== 'Ninguno') {
                $whereClauses[] = "p.subcategoria = :subcategoria";
                $bindings['subcategoria'] = $filters['subcategoria'];
            }
            if (!empty($filters['proveedor']) && $filters['proveedor'] !== 'Ninguno') {
                $whereClauses[] = "p.proveedor = :proveedor";
                $bindings['proveedor'] = $filters['proveedor'];
            }
            if (!empty($filters['buscar'])) {
                $whereClauses[] = "(LOWER(p.codigo) LIKE :buscar OR LOWER(p.nombre) LIKE :buscar)";
                $bindings['buscar'] = '%' . mb_strtolower($filters['buscar']) . '%';
            }

            $whereSql = $whereClauses ? 'AND ' . implode(' AND ', $whereClauses) : '';

            $sql = "
                SELECT 
                    sa.sede,
                    COUNT(DISTINCT p.id) as total_productos,
                    SUM(sa.existencia) as stock_total,
                    COUNT(CASE WHEN vh.ultima_venta IS NULL OR (CURRENT_DATE - vh.ultima_venta) > 90 THEN 1 END) as sin_rotacion,
                    COUNT(CASE WHEN vh.venta_promedio > 0 AND (sa.existencia::numeric / vh.venta_promedio::numeric) > 4 THEN 1 END) as sobrestock,
                    COUNT(CASE WHEN sa.existencia > 0 AND (CURRENT_DATE - vh.ultima_venta) > 90 THEN 1 END) as inmovilizados
                FROM inventario_v2.productos p
                LEFT JOIN inventario_v2.stock_actual sa ON p.id = sa.producto_id
                LEFT JOIN inventario_v2.ventas_historicas vh ON p.id = vh.producto_id AND sa.sede = vh.sede
                WHERE p.activo = true AND sa.existencia > 0 {$whereSql}
                GROUP BY sa.sede
            ";

            $dbRows = DB::connection('pgsql')->select($sql, $bindings);
            $dbRowsBySede = collect($dbRows)->keyBy('sede');

                $resumen = [];
                foreach ($sedes as $sede) {
                    $row = $dbRowsBySede->get($sede);
                    $resumen[] = [
                        'sede' => $sede,
                        'display' => $display[$sede] ?? $sede,
                        'total_productos' => $row ? (int) $row->total_productos : 0,
                        'stock_total' => $row ? (int) $row->stock_total : 0,
                        'sin_rotacion' => $row ? (int) $row->sin_rotacion : 0,
                        'sobrestock' => $row ? (int) $row->sobrestock : 0,
                        'inmovilizados' => $row ? (int) $row->inmovilizados : 0,
                    ];
                }

                return $resumen;
            });
        }

        $sedes = config('inventario.sedes_stock');
        $display = config('inventario.display');
        $resumen = [];

        foreach ($sedes as $sede) {
            $sedeItems = $items->filter(fn($item) => ($item['stocks_por_sede'][$sede] ?? 0) > 0);
            $resumen[] = [
                'sede' => $sede,
                'display' => $display[$sede] ?? $sede,
                'total_productos' => $sedeItems->count(),
                'stock_total' => $sedeItems->sum(fn($item) => $item['stocks_por_sede'][$sede] ?? 0),
                'sin_rotacion' => $sedeItems->where('rotacion', 'Sin rotación')->count(),
                'sobrestock' => $sedeItems->filter(fn($item) => in_array($item['sobrestock'], ['Sobrestock', 'Sobrestock Crítico']))->count(),
                'inmovilizados' => $sedeItems->whereNotNull('estado')->count(),
            ];
        }

        return $resumen;
    }
}
