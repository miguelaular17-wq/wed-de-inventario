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
        $sedes = config('inventario.sedes_stock');

        // ── Step 1: Fetch raw data from PostgreSQL with aggregations ──
        $query = DB::connection('pgsql')
            ->table('productos as p')
            ->where('p.activo', true)
            ->leftJoin(
                DB::raw('(SELECT producto_id, SUM(existencia) as total_stock FROM stock_actual GROUP BY producto_id) sa'),
                'p.id', '=', 'sa.producto_id'
            )
            ->leftJoin(
                DB::raw("(SELECT producto_id,
                    MAX(ultima_venta) as ultima_venta,
                    MAX(ultima_compra) as ultima_compra,
                    SUM(venta_promedio) as promedio_venta_total
                FROM ventas_historicas GROUP BY producto_id) vh"),
                'p.id', '=', 'vh.producto_id'
            )
            ->select([
                'p.id',
                'p.codigo',
                'p.nombre',
                'p.categoria',
                'p.subcategoria',
                'p.proveedor',
                DB::raw('COALESCE(sa.total_stock, 0) as total_stock'),
                'vh.ultima_venta',
                'vh.ultima_compra',
                DB::raw('COALESCE(vh.promedio_venta_total, 0) as promedio_venta'),
            ]);

        // Only products with stock > 0
        $query->where('sa.total_stock', '>', 0);

        // Apply categoria filter
        if (!empty($filters['categoria']) && $filters['categoria'] !== 'Ninguno') {
            $query->where('p.categoria', $filters['categoria']);
        }
        if (!empty($filters['subcategoria']) && $filters['subcategoria'] !== 'Ninguno') {
            $query->where('p.subcategoria', $filters['subcategoria']);
        }
        if (!empty($filters['proveedor']) && $filters['proveedor'] !== 'Ninguno') {
            $query->where('p.proveedor', $filters['proveedor']);
        }

        $rawRows = $query->get();

        // ── Step 2: Compute indicators in PHP (keeping SQL simple & portable) ──
        $now = now();
        $items = collect();

        // Also load per-sede stock for detail display
        $stockBySede = [];
        foreach (DB::connection('pgsql')->table('stock_actual')->get(['producto_id', 'sede', 'existencia']) as $row) {
            $stockBySede[(int)$row->producto_id][$row->sede] = (int)$row->existencia;
        }

        // Load per-sede ventas for detail
        $ventasBySede = [];
        foreach (DB::connection('pgsql')->table('ventas_historicas')->get(['producto_id', 'sede', 'ultima_venta', 'ultima_compra', 'venta_promedio']) as $row) {
            $ventasBySede[(int)$row->producto_id][$row->sede] = [
                'ultima_venta' => $row->ultima_venta,
                'ultima_compra' => $row->ultima_compra,
                'venta_promedio' => (float)$row->venta_promedio,
            ];
        }

        foreach ($rawRows as $row) {
            $totalStock = (int)$row->total_stock;
            $promedioVenta = (float)$row->promedio_venta;

            // ── Días sin venta ──
            $diasSinVenta = null;
            $ultimaVentaDate = null;
            if ($row->ultima_venta) {
                $ultimaVentaDate = \Carbon\Carbon::parse($row->ultima_venta);
                $diasSinVenta = (int)$now->diffInDays($ultimaVentaDate, true);
            }

            // ── Días sin compra ──
            $diasSinCompra = null;
            $ultimaCompraDate = null;
            if ($row->ultima_compra) {
                $ultimaCompraDate = \Carbon\Carbon::parse($row->ultima_compra);
                $diasSinCompra = (int)$now->diffInDays($ultimaCompraDate, true);
            }

            // ── Clasificación de Rotación ──
            if ($diasSinVenta === null) {
                $rotacion = 'Sin rotación';
                $rotacionColor = 'rojo';
                $diasSinVenta = 999; // For sorting purposes
            } elseif ($diasSinVenta <= 30) {
                $rotacion = 'Normal';
                $rotacionColor = 'verde';
            } elseif ($diasSinVenta <= 60) {
                $rotacion = 'Lenta';
                $rotacionColor = 'amarillo';
            } elseif ($diasSinVenta <= 90) {
                $rotacion = 'Riesgo';
                $rotacionColor = 'naranja';
            } else {
                $rotacion = 'Sin rotación';
                $rotacionColor = 'rojo';
            }

            // ── Meses de Inventario & Sobrestock ──
            $mesesInventario = null;
            $sobrestock = 'N/A';
            $sobrestockColor = 'gris';
            if ($promedioVenta > 0) {
                $mesesInventario = round($totalStock / $promedioVenta, 1);
                if ($mesesInventario <= 2) {
                    $sobrestock = 'Normal';
                    $sobrestockColor = 'verde';
                } elseif ($mesesInventario <= 4) {
                    $sobrestock = 'Vigilar';
                    $sobrestockColor = 'amarillo';
                } elseif ($mesesInventario <= 6) {
                    $sobrestock = 'Sobrestock';
                    $sobrestockColor = 'naranja';
                } else {
                    $sobrestock = 'Sobrestock Crítico';
                    $sobrestockColor = 'rojo';
                }
            } elseif ($totalStock > 0) {
                // Sin ventas pero con stock = sobrestock crítico
                $sobrestock = 'Sobrestock Crítico';
                $sobrestockColor = 'rojo';
                $mesesInventario = 999;
            }

            // ── Estado especial ──
            $estado = null;
            $estadoColor = null;
            if ($totalStock > 0 && $diasSinVenta > 90 && $diasSinCompra !== null && $diasSinCompra <= 30) {
                $estado = 'Compra Reciente Sin Rotación';
                $estadoColor = 'rojo';
            } elseif ($totalStock > 0 && $diasSinVenta > 90) {
                $estado = 'Inventario Inmovilizado';
                $estadoColor = 'naranja';
            }

            // ── Índice de Prioridad ──
            $prioridad = $totalStock * $diasSinVenta;

            // ── Semáforo global ──
            // Highest severity wins
            $semaforo = 'verde';
            if ($rotacionColor === 'rojo' || $sobrestockColor === 'rojo') {
                $semaforo = 'rojo';
            } elseif ($rotacionColor === 'naranja' || $sobrestockColor === 'naranja') {
                $semaforo = 'naranja';
            } elseif ($rotacionColor === 'amarillo' || $sobrestockColor === 'amarillo') {
                $semaforo = 'amarillo';
            }

            $items->push([
                'id' => (int)$row->id,
                'codigo' => $row->codigo,
                'producto' => $row->nombre,
                'categoria' => $row->categoria ?? '—',
                'subcategoria' => $row->subcategoria ?? '—',
                'proveedor' => $row->proveedor ?: 'Sin Proveedor',
                'total_stock' => $totalStock,
                'promedio_venta' => $promedioVenta,
                'dias_sin_venta' => $diasSinVenta,
                'dias_sin_compra' => $diasSinCompra,
                'ultima_venta' => $ultimaVentaDate ? $ultimaVentaDate->format('d/m/Y') : null,
                'ultima_compra' => $ultimaCompraDate ? $ultimaCompraDate->format('d/m/Y') : null,
                'rotacion' => $rotacion,
                'rotacion_color' => $rotacionColor,
                'meses_inventario' => $mesesInventario,
                'sobrestock' => $sobrestock,
                'sobrestock_color' => $sobrestockColor,
                'estado' => $estado,
                'estado_color' => $estadoColor,
                'prioridad' => $prioridad,
                'semaforo' => $semaforo,
                'stocks_por_sede' => $stockBySede[(int)$row->id] ?? [],
                'ventas_por_sede' => $ventasBySede[(int)$row->id] ?? [],
            ]);
        }

        // ── Step 3: Apply advanced filters ──

        // Filter by sede (only show products that have stock in that sede)
        if (!empty($filters['sede']) && $filters['sede'] !== 'Todas') {
            $sede = $filters['sede'];
            $items = $items->filter(fn($item) => ($item['stocks_por_sede'][$sede] ?? 0) > 0);
        }

        // Filter by rotacion classification
        if (!empty($filters['rotacion_filter']) && $filters['rotacion_filter'] !== 'Todos') {
            $items = $items->filter(fn($item) => $item['rotacion'] === $filters['rotacion_filter']);
        }

        // Filter by sobrestock classification
        if (!empty($filters['sobrestock_filter']) && $filters['sobrestock_filter'] !== 'Todos') {
            $items = $items->filter(fn($item) => $item['sobrestock'] === $filters['sobrestock_filter']);
        }

        // Filter by estado
        if (!empty($filters['estado_filter']) && $filters['estado_filter'] !== 'Todos') {
            if ($filters['estado_filter'] === 'Sin estado') {
                $items = $items->filter(fn($item) => $item['estado'] === null);
            } else {
                $items = $items->filter(fn($item) => $item['estado'] === $filters['estado_filter']);
            }
        }

        // Filter by semaforo color
        if (!empty($filters['semaforo_filter']) && $filters['semaforo_filter'] !== 'Todos') {
            $items = $items->filter(fn($item) => $item['semaforo'] === $filters['semaforo_filter']);
        }

        // Filter by minimum dias sin venta
        if (!empty($filters['min_dias_sin_venta'])) {
            $min = (int)$filters['min_dias_sin_venta'];
            $items = $items->filter(fn($item) => $item['dias_sin_venta'] >= $min);
        }

        // Filter by minimum existencia
        if (!empty($filters['min_existencia'])) {
            $min = (int)$filters['min_existencia'];
            $items = $items->filter(fn($item) => $item['total_stock'] >= $min);
        }

        // Search text
        if (!empty($filters['buscar'])) {
            $q = mb_strtolower(trim($filters['buscar']));
            $items = $items->filter(function ($item) use ($q) {
                return str_contains(mb_strtolower($item['codigo']), $q)
                    || str_contains(mb_strtolower($item['producto']), $q);
            });
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
    public function getResumenPorSede(Collection $items): array
    {
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
