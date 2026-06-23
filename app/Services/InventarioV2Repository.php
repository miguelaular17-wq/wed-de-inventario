<?php

namespace App\Services;

use App\Models\V2\Movimiento;
use App\Models\V2\Producto;
use App\Models\V2\StockActual;
use App\Models\V2\VentaHistorica;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventarioV2Repository
{
    public function isActive(): bool
    {
        return config('database.default') === 'pgsql';
    }

    public function loadForSede(string $sedeLocal): Collection
    {
        $sedes = config('inventario.sedes_stock');

        $productos = DB::connection('pgsql')
            ->table('productos')
            ->where('activo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'categoria', 'subcategoria', 'proveedor', 'precio_unidad', 'precio_mayor']);

        if ($productos->isEmpty()) {
            return collect();
        }

        $stocksByProduct = [];
        foreach (DB::connection('pgsql')
            ->table('stock_actual')
            ->get(['producto_id', 'sede', 'existencia']) as $row) {
            $stocksByProduct[(int) $row->producto_id][$row->sede] = (int) $row->existencia;
        }

        $ventasByProduct = [];
        foreach (DB::connection('pgsql')
            ->table('ventas_historicas')
            ->get(['producto_id', 'sede', 'venta_promedio', 'ventas_60d', 'ultima_venta', 'ultima_compra']) as $row) {
            $ventasByProduct[(int) $row->producto_id][$row->sede] = [
                'venta_promedio' => (int) $row->venta_promedio,
                'ventas_60d' => (float) $row->ventas_60d,
                'ultima_venta' => $row->ultima_venta,
                'ultima_compra' => $row->ultima_compra,
            ];
        }

        $rows = [];
        foreach ($productos as $p) {
            $productoId = (int) $p->id;
            $stockMap = $stocksByProduct[$productoId] ?? [];
            $ventaMap = $ventasByProduct[$productoId] ?? [];
            $localVenta = $ventaMap[$sedeLocal] ?? null;

            $stocks = [];
            $ventasInternas = [];
            $ventasInternas15d = [];
            $ultimasVentas = [];
            $ultimasCompras = []; // Not available in DB currently
            foreach ($sedes as $sede) {
                $ventaSede = $ventaMap[$sede] ?? null;
                $stocks[$sede] = $stockMap[$sede] ?? 0;
                $ventasInternas[$sede] = $ventaSede ? (int) $ventaSede['ventas_60d'] : 0;
                $ventasInternas15d[$sede] = $ventaSede ? (int) $ventaSede['venta_promedio'] : 0;
                
                $uv = $ventaSede['ultima_venta'] ?? null;
                $ultimasVentas[$sede] = $uv ? date('d/m/Y', strtotime((string) $uv)) : null;
                $uc = $ventaSede['ultima_compra'] ?? null;
                $ultimasCompras[$sede] = $uc ? date('d/m/Y', strtotime((string) $uc)) : null;
            }

            $ultimaVenta = $localVenta['ultima_venta'] ?? null;
            if ($ultimaVenta && ! is_string($ultimaVenta)) {
                $ultimaVenta = (string) $ultimaVenta;
            }

            $rows[] = [
                'id'              => $productoId,
                'cod_centro'      => $p->codigo,
                'producto'        => $p->nombre,
                'categoria'       => $p->categoria,
                'subcategoria'    => $p->subcategoria,
                'proveedor'       => $p->proveedor,
                'precio_unidad'   => (float) ($p->precio_unidad ?? 0),
                'precio_mayor'    => (float) ($p->precio_mayor ?? 0),
                'existencia'      => $stockMap[$sedeLocal] ?? 0,
                'venta'           => $localVenta ? (int) $localVenta['venta_promedio'] : 0,
                'ventas_60d'      => $localVenta ? (float) $localVenta['ventas_60d'] : 0.0,
                'ultima_venta'    => $ultimaVenta ? date('d/m/Y', strtotime($ultimaVenta)) : null,
                'stocks'          => $stocks,
                'ventas_internas' => $ventasInternas,
                'ventas_internas_15d' => $ventasInternas15d,
                'ultimas_ventas'  => $ultimasVentas,
                'ultimas_compras' => $ultimasCompras,
            ];
        }

        return collect($rows);
    }

    public function findForSedeByCodigo(string $sedeLocal, string $codigo): ?array
    {
        $producto = DB::connection('pgsql')
            ->table('productos')
            ->where('activo', true)
            ->where('codigo', $codigo)
            ->first(['id', 'codigo', 'nombre', 'categoria', 'subcategoria', 'proveedor']);

        if (! $producto) {
            return null;
        }

        $sedes = config('inventario.sedes_stock');

        $stocks = DB::connection('pgsql')
            ->table('stock_actual')
            ->where('producto_id', $producto->id)
            ->get(['sede', 'existencia'])
            ->keyBy('sede');

        $ventas = DB::connection('pgsql')
            ->table('ventas_historicas')
            ->where('producto_id', $producto->id)
            ->get(['sede', 'venta_promedio', 'ventas_60d', 'ultima_venta', 'ultima_compra'])
            ->keyBy('sede');

        $localStock = $stocks->get($sedeLocal);
        $localVenta = $ventas->get($sedeLocal);

        $stockValues = [];
        $ventasInternas = [];
        $ventasInternas15d = [];
        $ultimasVentas = [];
        $ultimasCompras = [];
        foreach ($sedes as $sede) {
            $stockValues[$sede] = (int) ($stocks->get($sede)?->existencia ?? 0);
            $ventasInternas[$sede] = (int) ($ventas->get($sede)?->ventas_60d ?? 0);
            $ventasInternas15d[$sede] = (int) ($ventas->get($sede)?->venta_promedio ?? 0);
            
            $uv = $ventas->get($sede)?->ultima_venta;
            $ultimasVentas[$sede] = $uv ? date('d/m/Y', strtotime((string) $uv)) : null;
            $uc = $ventas->get($sede)?->ultima_compra;
            $ultimasCompras[$sede] = $uc ? date('d/m/Y', strtotime((string) $uc)) : null;
        }

        $ultimaVenta = $localVenta?->ultima_venta ?? null;
        if ($ultimaVenta && ! is_string($ultimaVenta)) {
            $ultimaVenta = (string) $ultimaVenta;
        }

        return [
            'id' => (int) $producto->id,
            'cod_centro' => $producto->codigo,
            'producto' => $producto->nombre,
            'categoria' => $producto->categoria,
            'subcategoria' => $producto->subcategoria,
            'proveedor' => $producto->proveedor,
            'existencia' => (int) ($localStock?->existencia ?? 0),
            'venta' => (int) ($localVenta?->venta_promedio ?? 0),
            'ventas_60d' => (float) ($localVenta?->ventas_60d ?? 0),
            'ultima_venta' => $ultimaVenta ? date('d/m/Y', strtotime($ultimaVenta)) : null,
            'stocks' => $stockValues,
            'ventas_internas' => $ventasInternas,
            'ventas_internas_15d' => $ventasInternas15d,
            'ultimas_ventas' => $ultimasVentas,
            'ultimas_compras' => $ultimasCompras,
        ];
    }

    public function lastStockUpdate(): ?string
    {
        $ts = StockActual::query()->max('updated_at');

        return $ts ? (string) $ts : null;
    }

    public function importFromArray(array $rows): int
    {
        $count = count($rows);
        if ($count === 0) {
            return 0;
        }

        DB::connection('pgsql')->transaction(function () use ($rows) {
            $now = now();

            DB::connection('pgsql')->statement(
                'TRUNCATE TABLE inventario_v2.stock_actual, inventario_v2.ventas_historicas, inventario_v2.reposicion, inventario_v2.inventario_derivado RESTART IDENTITY'
            );
            
            // Mark all existing products as inactive (instead of deleting them, to avoid foreign key violations in movements)
            Producto::query()->update(['activo' => false]);

            $productRows = [];
            $seenCodes = [];
            foreach ($rows as $row) {
                $codigo = (string) ($row['cod_centro'] ?? '');
                if ($codigo === '' || isset($seenCodes[$codigo])) {
                    continue;
                }
                $seenCodes[$codigo] = true;
                $productRows[] = [
                    'codigo'         => $codigo,
                    'nombre'         => (string) ($row['producto'] ?? ''),
                    'categoria'      => (string) ($row['categoria'] ?? ''),
                    'subcategoria'   => (string) ($row['subcategoria'] ?? ''),
                    'proveedor'      => (string) ($row['proveedor'] ?? ''),
                    'precio_unidad'  => (float) ($row['precio_unidad'] ?? 0),
                    'precio_mayor'   => (float) ($row['precio_mayor'] ?? 0),
                    'activo'         => true,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }

            foreach (array_chunk($productRows, 1500) as $chunk) {
                DB::connection('pgsql')->table('productos')->upsert(
                    $chunk,
                    ['codigo'],
                    ['nombre', 'categoria', 'subcategoria', 'proveedor', 'precio_unidad', 'precio_mayor', 'activo', 'updated_at']
                );
            }

            $idByCodigo = DB::connection('pgsql')
                ->table('productos')
                ->pluck('id', 'codigo');

            $stockRows = [];
            $ventaRows = [];
            foreach ($rows as $row) {
                $codigo = (string) ($row['cod_centro'] ?? '');
                $productoId = $idByCodigo[$codigo] ?? null;
                if (! $productoId) {
                    continue;
                }

                foreach ($row['sedes'] ?? [] as $sede => $m) {
                    $stockRows[] = [
                        'producto_id' => $productoId,
                        'sede' => $sede,
                        'existencia' => max(0, (int) ($m['existencia'] ?? 0)),
                        'updated_at' => $now,
                    ];
                    $ventaRows[] = [
                        'producto_id' => $productoId,
                        'sede' => $sede,
                        'venta_promedio' => (int) ($m['promedio_15d'] ?? 0),
                        'ventas_60d' => (float) ($m['ventas_60d'] ?? 0),
                        'ultima_venta' => $m['ultima_venta'] ?? null,
                        'ultima_compra' => $m['ultima_compra'] ?? null,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($stockRows, 5000) as $chunk) {
                DB::connection('pgsql')->table('stock_actual')->insert($chunk);
            }

            foreach (array_chunk($ventaRows, 4000) as $chunk) {
                DB::connection('pgsql')->table('ventas_historicas')->insert($chunk);
            }
        });

        // Re-apply all app movements on top of the freshly imported baseline.
        // Movements are only created by the app (requisitions/exports), so they
        // represent transfers between sedes that the external Excel does not track.
        // We replay them in chronological order to reconstruct the correct stock.
        $this->replayMovements();

        return $count;
    }

    /**
     * Re-apply every movement in the movimientos table to stock_actual.
     * Called after each Excel import to restore app-level transfers.
     */
    private function replayMovements(): void
    {
        // Load all active product IDs so we can skip orphaned movements safely
        $activeProductIds = DB::connection('pgsql')
            ->table('productos')
            ->where('activo', true)
            ->pluck('id')
            ->flip()
            ->toArray();

        // Step 1: Accumulate net changes in memory to avoid N+1 database queries
        $adjustments = []; // [productId => [sede => delta]]
        
        Movimiento::query()
            ->orderBy('created_at', 'asc')
            ->each(function (Movimiento $m) use ($activeProductIds, &$adjustments) {
                if (! isset($activeProductIds[$m->producto_id])) {
                    return;
                }

                if (! empty($m->origen)) {
                    if (! isset($adjustments[$m->producto_id][$m->origen])) {
                        $adjustments[$m->producto_id][$m->origen] = 0;
                    }
                    $adjustments[$m->producto_id][$m->origen] -= $m->cantidad;
                }

                if (! empty($m->destino)) {
                    if (! isset($adjustments[$m->producto_id][$m->destino])) {
                        $adjustments[$m->producto_id][$m->destino] = 0;
                    }
                    $adjustments[$m->producto_id][$m->destino] += $m->cantidad;
                }
            });

        // Step 2: Flat list of non-zero adjustments
        $flatAdjustments = [];
        foreach ($adjustments as $productId => $sedes) {
            foreach ($sedes as $sede => $delta) {
                if ($delta !== 0) {
                    $flatAdjustments[] = [
                        'producto_id' => (int) $productId,
                        'sede' => (string) $sede,
                        'delta' => (int) $delta,
                    ];
                }
            }
        }

        if (empty($flatAdjustments)) {
            return;
        }

        // Step 3: Run bulk UPDATE queries using PostgreSQL VALUES expression (max 500 records per chunk)
        DB::connection('pgsql')->transaction(function () use ($flatAdjustments) {
            foreach (array_chunk($flatAdjustments, 500) as $chunk) {
                $valuesList = [];
                $bindings = [];
                $idx = 0;
                
                foreach ($chunk as $adj) {
                    $pIdKey = "pId_{$idx}";
                    $sedeKey = "sede_{$idx}";
                    $deltaKey = "delta_{$idx}";
                    
                    $valuesList[] = "(:{$pIdKey}::bigint, :{$sedeKey}::varchar, :{$deltaKey}::integer)";
                    
                    $bindings[$pIdKey] = $adj['producto_id'];
                    $bindings[$sedeKey] = $adj['sede'];
                    $bindings[$deltaKey] = $adj['delta'];
                    
                    $idx++;
                }

                $valuesSql = implode(', ', $valuesList);
                $sql = "
                    UPDATE inventario_v2.stock_actual as sa
                    SET existencia = GREATEST(0, sa.existencia + v.delta),
                        updated_at = NOW()
                    FROM (VALUES {$valuesSql}) as v(producto_id, sede, delta)
                    WHERE sa.producto_id = v.producto_id AND sa.sede = v.sede
                ";

                DB::connection('pgsql')->statement($sql, $bindings);
            }
        });
    }

    public function applyRequisition(Collection $lines, string $sedeOrigen, string $sedeDestino, ?string $usuario = null): int
    {
        $applied = 0;

        DB::connection('pgsql')->transaction(function () use ($lines, $sedeOrigen, $sedeDestino, $usuario, &$applied) {
            $codigos = $lines->pluck('codigo')->filter()->unique()->values()->all();

            $productosByCodigo = Producto::query()
                ->whereIn('codigo', $codigos)
                ->get(['id', 'codigo'])
                ->keyBy('codigo');

            foreach ($lines as $line) {
                $cod = (string) ($line['codigo'] ?? '');
                $qty = (int) ($line['cantidad'] ?? 0);
                if ($cod === '' || $qty <= 0) {
                    continue;
                }

                $producto = $productosByCodigo->get($cod);
                if (! $producto) {
                    continue;
                }

                $this->adjustStock($producto->id, $sedeOrigen, -$qty);
                $this->adjustStock($producto->id, $sedeDestino, $qty);

                $sedeLabel = config('inventario.display')[$sedeOrigen] ?? $sedeOrigen;
                Movimiento::create([
                    'producto_id' => $producto->id,
                    'origen' => $sedeOrigen,
                    'destino' => $sedeDestino,
                    'tipo' => 'REQUISICION',
                    'cantidad' => $qty,
                    'usuario' => $usuario ?? $sedeLabel,
                    'metadata' => ['codigo' => $cod],
                ]);

                $applied++;
            }
        });

        return $applied;
    }

    private function adjustStock(int $productoId, string $sede, int $delta): void
    {
        $row = StockActual::query()
            ->where('producto_id', $productoId)
            ->where('sede', $sede)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            StockActual::create([
                'producto_id' => $productoId,
                'sede' => $sede,
                'existencia' => max(0, $delta),
            ]);

            return;
        }

        $row->existencia = max(0, $row->existencia + $delta);
        $row->updated_at = now();
        $row->save();
    }

    public function sampleForExport(int $limit = 25): array
    {
        return Producto::query()
            ->with(['stock', 'ventas'])
            ->orderBy('codigo')
            ->limit($limit)
            ->get()
            ->map(function (Producto $p) {
                $sedes = [];
                foreach (config('inventario.sedes_stock') as $sede) {
                    $sedes[$sede] = [
                        'existencia' => (int) ($p->stock->firstWhere('sede', $sede)?->existencia ?? 0),
                        'promedio_15d' => (int) ($p->ventas->firstWhere('sede', $sede)?->venta_promedio ?? 0),
                        'ventas_60d' => (float) ($p->ventas->firstWhere('sede', $sede)?->ventas_60d ?? 0),
                    ];
                }

                return [
                    'cod_centro' => $p->codigo,
                    'producto' => $p->nombre,
                    'categoria' => $p->categoria,
                    'subcategoria' => $p->subcategoria,
                    'proveedor' => $p->proveedor,
                    'sedes' => $sedes,
                ];
            })
            ->all();
    }
}
