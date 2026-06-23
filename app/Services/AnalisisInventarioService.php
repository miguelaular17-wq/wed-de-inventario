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
    /**
     * Build the full analysis dataset with all indicators computed.
     * Filters, paginates, and sorts server-side.
     */
    public function getAnalysis(array $filters = []): Collection
    {
        if (config('database.default') !== 'pgsql') {
            return collect();
        }

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
            WITH product_metrics AS (
                SELECT 
                    p.id,
                    p.codigo,
                    p.nombre,
                    p.categoria,
                    p.subcategoria,
                    p.proveedor,
                    COALESCE(sa.total_stock, 0) as total_stock,
                    vh.ultima_venta,
                    vh.ultima_compra,
                    COALESCE(vh.promedio_venta_total, 0) as promedio_venta
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
                        SUM(venta_promedio) as promedio_venta_total
                    FROM inventario_v2.ventas_historicas 
                    GROUP BY producto_id
                ) vh ON p.id = vh.producto_id
                WHERE p.activo = true {$whereSql}
            ),
            calculated_indicators AS (
                SELECT *,
                    EXTRACT(DAY FROM CURRENT_DATE - ultima_venta) as dias_sin_venta_raw,
                    EXTRACT(DAY FROM CURRENT_DATE - ultima_compra) as dias_sin_compra_raw
                FROM product_metrics
                WHERE total_stock > 0
            ),
            indicators AS (
                SELECT *,
                    COALESCE(dias_sin_venta_raw, 999) as dias_sin_venta,
                    dias_sin_compra_raw as dias_sin_compra,
                    CASE 
                        WHEN dias_sin_venta_raw IS NULL THEN 'Sin rotación'
                        WHEN dias_sin_venta_raw <= 30 THEN 'Normal'
                        WHEN dias_sin_venta_raw <= 60 THEN 'Lenta'
                        WHEN dias_sin_venta_raw <= 90 THEN 'Riesgo'
                        ELSE 'Sin rotación'
                    END as rotacion,
                    CASE 
                        WHEN dias_sin_venta_raw IS NULL THEN 'rojo'
                        WHEN dias_sin_venta_raw <= 30 THEN 'verde'
                        WHEN dias_sin_venta_raw <= 60 THEN 'amarillo'
                        WHEN dias_sin_venta_raw <= 90 THEN 'naranja'
                        ELSE 'rojo'
                    END as rotacion_color,
                    CASE 
                        WHEN promedio_venta > 0 THEN ROUND((total_stock::numeric / promedio_venta::numeric), 1)
                        ELSE 999
                    END as meses_inventario,
                    CASE 
                        WHEN promedio_venta > 0 THEN 
                            CASE 
                                WHEN (total_stock::numeric / promedio_venta::numeric) <= 2 THEN 'Normal'
                                WHEN (total_stock::numeric / promedio_venta::numeric) <= 4 THEN 'Vigilar'
                                WHEN (total_stock::numeric / promedio_venta::numeric) <= 6 THEN 'Sobrestock'
                                ELSE 'Sobrestock Crítico'
                            END
                        WHEN total_stock > 0 THEN 'Sobrestock Crítico'
                        ELSE 'N/A'
                    END as sobrestock,
                    CASE 
                        WHEN promedio_venta > 0 THEN 
                            CASE 
                                WHEN (total_stock::numeric / promedio_venta::numeric) <= 2 THEN 'verde'
                                WHEN (total_stock::numeric / promedio_venta::numeric) <= 4 THEN 'amarillo'
                                WHEN (total_stock::numeric / promedio_venta::numeric) <= 6 THEN 'naranja'
                                ELSE 'rojo'
                            END
                        WHEN total_stock > 0 THEN 'rojo'
                        ELSE 'gris'
                    END as sobrestock_color
                FROM calculated_indicators
            ),
            indicators_with_states AS (
                SELECT *,
                    CASE 
                        WHEN total_stock > 0 AND dias_sin_venta > 90 AND dias_sin_compra IS NOT NULL AND dias_sin_compra <= 30 THEN 'Compra Reciente Sin Rotación'
                        WHEN total_stock > 0 AND dias_sin_venta > 90 THEN 'Inventario Inmovilizado'
                        ELSE NULL
                    END as estado,
                    CASE 
                        WHEN total_stock > 0 AND dias_sin_venta > 90 AND dias_sin_compra IS NOT NULL AND dias_sin_compra <= 30 THEN 'rojo'
                        WHEN total_stock > 0 AND dias_sin_venta > 90 THEN 'naranja'
                        ELSE NULL
                    END as estado_color,
                    (total_stock * dias_sin_venta) as prioridad
                FROM indicators
            ),
            indicators_with_semaforo AS (
                SELECT *,
                    CASE 
                        WHEN rotacion_color = 'rojo' OR sobrestock_color = 'rojo' THEN 'rojo'
                        WHEN rotacion_color = 'naranja' OR sobrestock_color = 'naranja' THEN 'naranja'
                        WHEN rotacion_color = 'amarillo' OR sobrestock_color = 'amarillo' THEN 'amarillo'
                        ELSE 'verde'
                    END as semaforo
                FROM indicators_with_states
            )
            SELECT * FROM indicators_with_semaforo
        ";

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
                'promedio_venta' => (float) $row->promedio_venta,
                'dias_sin_venta' => (int) $row->dias_sin_venta,
                'dias_sin_compra' => $row->dias_sin_compra ? (int) $row->dias_sin_compra : null,
                'ultima_venta' => $ultimaVentaDate ? $ultimaVentaDate->format('d/m/Y') : null,
                'ultima_compra' => $ultimaCompraDate ? $ultimaCompraDate->format('d/m/Y') : null,
                'rotacion' => $row->rotacion,
                'rotacion_color' => $row->rotacion_color,
                'meses_inventario' => (float) $row->meses_inventario,
                'sobrestock' => $row->sobrestock,
                'sobrestock_color' => $row->sobrestock_color,
                'estado' => $row->estado,
                'estado_color' => $row->estado_color,
                'prioridad' => (int) $row->prioridad,
                'semaforo' => $row->semaforo,
                'stocks_por_sede' => [], // Will be loaded dynamically for the current page items in the controller
                'ventas_por_sede' => [],
            ]);
        }

        // Apply advanced filters that are calculated or need post-filtering
        if (!empty($filters['rotacion_filter']) && $filters['rotacion_filter'] !== 'Todos') {
            $items = $items->filter(fn($item) => $item['rotacion'] === $filters['rotacion_filter']);
        }
        if (!empty($filters['sobrestock_filter']) && $filters['sobrestock_filter'] !== 'Todos') {
            $items = $items->filter(fn($item) => $item['sobrestock'] === $filters['sobrestock_filter']);
        }
        if (!empty($filters['estado_filter']) && $filters['estado_filter'] !== 'Todos') {
            if ($filters['estado_filter'] === 'Sin estado') {
                $items = $items->filter(fn($item) => $item['estado'] === null);
            } else {
                $items = $items->filter(fn($item) => $item['estado'] === $filters['estado_filter']);
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
                    COUNT(CASE WHEN vh.ultima_venta IS NULL OR EXTRACT(DAY FROM CURRENT_DATE - vh.ultima_venta) > 90 THEN 1 END) as sin_rotacion,
                    COUNT(CASE WHEN vh.venta_promedio > 0 AND (sa.existencia::numeric / vh.venta_promedio::numeric) > 4 THEN 1 END) as sobrestock,
                    COUNT(CASE WHEN sa.existencia > 0 AND EXTRACT(DAY FROM CURRENT_DATE - vh.ultima_venta) > 90 THEN 1 END) as inmovilizados
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
