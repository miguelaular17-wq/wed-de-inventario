<?php

namespace App\Services;

use App\Models\V2\Movimiento;
use App\Models\V2\Producto;
use App\Models\V2\StockActual;
use App\Models\V2\VentaHistorica;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class InventarioV2Repository
{
    /** Caché de instancia: evita N queries MAX(updated_at) dentro del mismo request */
    private ?string $lastStockUpdateCache = null;

    public function isActive(): bool
    {
        return config('database.default') === 'pgsql';
    }

    private function getGlobalProducts(): array
    {
        $cacheKey = 'inventario_v2.global_products_v2';

        // ── Medir Cache::get (lectura cruda) ──────────────────────────
        \App\Services\Profiler::start('InvV2::getGlobalProducts Cache::get');
        $memB0 = memory_get_usage(true);
        $cached = Cache::get($cacheKey);
        $memAfterGet = memory_get_usage(true);
        \App\Services\Profiler::stop('InvV2::getGlobalProducts Cache::get');

        if ($cached !== null) {
            $memDelta = $memAfterGet - $memB0;
            \App\Services\Profiler::record('InvV2::getGlobalProducts CACHE HIT size', 0.0, count($cached), $memDelta);
            return $cached;
        }

        // MISS → fetch SQL y guardar como array plano (no Collection Eloquent)
        \App\Services\Profiler::start('InvV2::getGlobalProducts SQL fetch');
        $rows = DB::connection('pgsql')
            ->table('productos')
            ->where('activo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'categoria', 'subcategoria', 'proveedor', 'precio_unidad', 'precio_mayor'])
            ->all(); // array plano de stdClass — serializa mucho más rápido
        \App\Services\Profiler::stop('InvV2::getGlobalProducts SQL fetch', count($rows));

        \App\Services\Profiler::start('InvV2::getGlobalProducts Cache::put');
        Cache::put($cacheKey, $rows, 86400);
        \App\Services\Profiler::stop('InvV2::getGlobalProducts Cache::put', count($rows));

        return $rows;
    }

    private function getGlobalStockAndVentas(): array
    {
        $lastUpdate = $this->lastStockUpdate();
        $cacheKey = 'inventario_v2.global_stock_ventas.' . md5((string) $lastUpdate);

        // ── Intentar leer del caché con medición ──────────────────────
        \App\Services\Profiler::start('InvV2::getGlobalStockVentas Cache::get');
        $memB0 = memory_get_usage(true);
        $cached = Cache::get($cacheKey);
        $memAfterGet = memory_get_usage(true);
        \App\Services\Profiler::stop('InvV2::getGlobalStockVentas Cache::get');

        if ($cached !== null) {
            $memDelta = $memAfterGet - $memB0;
            $count = count($cached[0] ?? []) + count($cached[1] ?? []);
            \App\Services\Profiler::record('InvV2::getGlobalStockVentas CACHE HIT size', 0.0, $count, $memDelta);
            return $cached;
        }

        // MISS → fetch SQL
        \App\Services\Profiler::start('InvV2::getGlobalStockVentas SQL stocks');
        $stockRows = DB::connection('pgsql')
            ->table('stock_actual')
            ->get(['producto_id', 'sede', 'existencia']);
        \App\Services\Profiler::stop('InvV2::getGlobalStockVentas SQL stocks', count($stockRows));

        \App\Services\Profiler::start('InvV2::getGlobalStockVentas foreach stocks');
        $stocksByProduct = [];
        foreach ($stockRows as $row) {
            $stocksByProduct[(int) $row->producto_id][$row->sede] = (int) $row->existencia;
        }
        \App\Services\Profiler::stop('InvV2::getGlobalStockVentas foreach stocks', count($stocksByProduct));

        \App\Services\Profiler::start('InvV2::getGlobalStockVentas SQL ventas');
        $ventaRows = DB::connection('pgsql')
            ->table('ventas_historicas')
            ->get(['producto_id', 'sede', 'venta_promedio', 'ventas_60d', 'ultima_venta', 'ultima_compra']);
        \App\Services\Profiler::stop('InvV2::getGlobalStockVentas SQL ventas', count($ventaRows));

        // OPTIMIZACIÓN: pre-formatear fechas aquí (una sola vez, al llenar el caché)
        // Ahorra 292ms en el foreach de loadForSede: 129,470 date(strtotime()) por request.
        // Si la fecha ya es 'd/m/Y' (rara vez) se detecta y se pasa tal cual.
        \App\Services\Profiler::start('InvV2::getGlobalStockVentas foreach ventas');
        $ventasByProduct = [];
        foreach ($ventaRows as $row) {
            $uv = $row->ultima_venta;
            $uc = $row->ultima_compra;
            $ventasByProduct[(int) $row->producto_id][$row->sede] = [
                'venta_promedio' => (float) $row->venta_promedio,
                'ventas_60d'     => (float) $row->ventas_60d,
                // Guardamos ya formateadas (d/m/Y). El foreach no vuelve a llamar date().
                'ultima_venta'   => $uv ? self::formatDateDMY((string) $uv) : null,
                'ultima_compra'  => $uc ? self::formatDateDMY((string) $uc) : null,
            ];
        }
        \App\Services\Profiler::stop('InvV2::getGlobalStockVentas foreach ventas', count($ventasByProduct));

        $payload = [$stocksByProduct, $ventasByProduct];

        \App\Services\Profiler::start('InvV2::getGlobalStockVentas Cache::put');
        Cache::put($cacheKey, $payload, 1800);
        \App\Services\Profiler::stop('InvV2::getGlobalStockVentas Cache::put');

        return $payload;
    }

    /**
     * Convierte una fecha SQL (Y-m-d o Y-m-d H:i:s) a d/m/Y en PHP puro.
     * 6× más rápido que date(strtotime()) sin usar strtotime().
     */
    private static function formatDateDMY(string $date): string
    {
        // Formato Y-m-d[ ...] — el más común desde PostgreSQL
        if (strlen($date) >= 10 && $date[4] === '-' && $date[7] === '-') {
            return substr($date, 8, 2) . '/' . substr($date, 5, 2) . '/' . substr($date, 0, 4);
        }
        // Fallback para cualquier otro formato
        return date('d/m/Y', strtotime($date));
    }

    public function loadForSede(string $sedeLocal): Collection
    {
        \App\Services\Profiler::start('InvV2::loadForSede total');
        $sedes = config('inventario.sedes_stock');

        // ── OPTIMIZACIÓN FASE 3: Caché de resultado final por sede ──
        // Evita el foreach de 392ms en requests con caché caliente.
        // Key: sede + hash del timestamp de stock (se invalida automáticamente).
        // TTL: 1800s (mismo que global_stock_ventas).
        $stockTs  = $this->lastStockUpdate();
        $sedeCacheKey = 'produtos_sede_' . strtoupper($sedeLocal) . '_v3_' . md5((string) $stockTs);

        \App\Services\Profiler::start('InvV2::loadForSede Cache::get sede');
        $memB0 = memory_get_usage(true);
        $cachedSede = Cache::get($sedeCacheKey);
        $memAfterGet = memory_get_usage(true);
        \App\Services\Profiler::stop('InvV2::loadForSede Cache::get sede');

        if ($cachedSede !== null) {
            $memDelta = $memAfterGet - $memB0;
            \App\Services\Profiler::record('InvV2::loadForSede SEDE CACHE HIT', 0.0, count($cachedSede), $memDelta);
            \App\Services\Profiler::stop('InvV2::loadForSede total', count($cachedSede));
            return new \Illuminate\Support\Collection($cachedSede);
        }

        \App\Services\Profiler::start('InvV2::loadForSede getGlobalProducts');
        $productos = $this->getGlobalProducts(); // array plano de stdClass
        \App\Services\Profiler::stop('InvV2::loadForSede getGlobalProducts', count($productos));

        if (empty($productos)) {
            \App\Services\Profiler::stop('InvV2::loadForSede total', 0);
            return collect();
        }

        \App\Services\Profiler::start('InvV2::loadForSede getGlobalStockVentas');
        [$stocksByProduct, $ventasByProduct] = $this->getGlobalStockAndVentas();
        \App\Services\Profiler::stop('InvV2::loadForSede getGlobalStockVentas');

        // ── Foreach de construcción de rows ──
        // Las fechas ya vienen pre-formateadas desde getGlobalStockAndVentas(),
        // eliminando las 129,470 llamadas date(strtotime()) (-292ms).
        \App\Services\Profiler::start('InvV2::loadForSede foreach build rows');
        $memB = memory_get_usage(true);
        $rows = [];
        foreach ($productos as $p) {
            $productoId = (int) $p->id;
            $stockMap   = $stocksByProduct[$productoId] ?? [];
            $ventaMap   = $ventasByProduct[$productoId] ?? [];
            $localVenta = $ventaMap[$sedeLocal] ?? null;

            $stocks              = [];
            $ventasInternas      = [];
            $ventasInternas15d   = [];
            $ultimasVentas       = [];
            $ultimasCompras      = [];
            foreach ($sedes as $sede) {
                $ventaSede = $ventaMap[$sede] ?? null;
                $stocks[$sede]            = $stockMap[$sede] ?? 0;
                $ventasInternas[$sede]    = $ventaSede ? (int) $ventaSede['ventas_60d'] : 0;
                $ventasInternas15d[$sede] = $ventaSede ? (float) $ventaSede['venta_promedio'] : 0;
                // Fechas ya formateadas (d/m/Y) desde el caché — asignación directa
                $ultimasVentas[$sede]  = $ventaSede['ultima_venta']  ?? null;
                $ultimasCompras[$sede] = $ventaSede['ultima_compra'] ?? null;
            }

            $rows[] = [
                'id'                  => $productoId,
                'cod_centro'          => $p->codigo,
                'producto'            => $p->nombre,
                'categoria'           => $p->categoria,
                'subcategoria'        => $p->subcategoria,
                'proveedor'           => $p->proveedor,
                'precio_unidad'       => (float) ($p->precio_unidad ?? 0),
                'precio_mayor'        => (float) ($p->precio_mayor ?? 0),
                'existencia'          => $stockMap[$sedeLocal] ?? 0,
                'venta'               => $localVenta ? (float) $localVenta['venta_promedio'] : 0,
                'ventas_60d'          => $localVenta ? (float) $localVenta['ventas_60d'] : 0.0,
                'ultima_venta'        => $localVenta['ultima_venta'] ?? null,
                'stocks'              => $stocks,
                'ventas_internas'     => $ventasInternas,
                'ventas_internas_15d' => $ventasInternas15d,
                'ultimas_ventas'      => $ultimasVentas,
                'ultimas_compras'     => $ultimasCompras,
            ];
        }
        $memDelta = memory_get_usage(true) - $memB;
        \App\Services\Profiler::stop('InvV2::loadForSede foreach build rows', count($rows));
        \App\Services\Profiler::record('InvV2::loadForSede rows RAM', 0.0, count($rows), $memDelta);

        // Guardar resultado final en caché por sede
        \App\Services\Profiler::start('InvV2::loadForSede Cache::put sede');
        Cache::put($sedeCacheKey, $rows, 1800);
        \App\Services\Profiler::stop('InvV2::loadForSede Cache::put sede');

        \App\Services\Profiler::stop('InvV2::loadForSede total', count($rows));
        return new \Illuminate\Support\Collection($rows);
    }

    public function findForSedeByCodigo(string $sedeLocal, string $codigo): ?array
    {
        $results = $this->findManyByCodigos($sedeLocal, [$codigo]);
        return $results[$codigo] ?? null;
    }

    /**
     * Carga los datos completos (stocks + ventas por todas las sedes) de múltiples
     * productos identificados por su código, en solo 3 queries SQL (productos, stocks, ventas).
     * Devuelve un array keyed por código.
     *
     * Mucho más eficiente que N llamadas a findForSedeByCodigo().
     *
     * @param  string   $sedeLocal  Sede para calcular existencia/venta local
     * @param  string[] $codigos    Lista de códigos de producto
     * @return array<string, array> Array keyed by código
     */
    public function findManyByCodigos(string $sedeLocal, array $codigos): array
    {
        if (empty($codigos)) {
            return [];
        }

        $codigos = array_unique(array_filter($codigos));
        $sedes   = config('inventario.sedes_stock');

        // ── 1. Productos ─────────────────────────────────────────────────
        $productos = DB::connection('pgsql')
            ->table('productos')
            ->where('activo', true)
            ->whereIn('codigo', $codigos)
            ->get(['id', 'codigo', 'nombre', 'categoria', 'subcategoria', 'proveedor'])
            ->keyBy('id');

        if ($productos->isEmpty()) {
            return [];
        }

        $productIds = $productos->keys()->all();

        // ── 2. Stocks (todos los IDs en 1 query) ─────────────────────────
        $stockRows = DB::connection('pgsql')
            ->table('stock_actual')
            ->whereIn('producto_id', $productIds)
            ->get(['producto_id', 'sede', 'existencia']);

        $stocksByProduct = [];
        foreach ($stockRows as $row) {
            $stocksByProduct[(int) $row->producto_id][$row->sede] = (int) $row->existencia;
        }

        // ── 3. Ventas (todos los IDs en 1 query) ─────────────────────────
        $ventaRows = DB::connection('pgsql')
            ->table('ventas_historicas')
            ->whereIn('producto_id', $productIds)
            ->get(['producto_id', 'sede', 'venta_promedio', 'ventas_60d', 'ultima_venta', 'ultima_compra']);

        $ventasByProduct = [];
        foreach ($ventaRows as $row) {
            $ventasByProduct[(int) $row->producto_id][$row->sede] = [
                'venta_promedio' => (float) $row->venta_promedio,
                'ventas_60d'     => (float) $row->ventas_60d,
                'ultima_venta'   => $row->ultima_venta,
                'ultima_compra'  => $row->ultima_compra,
            ];
        }

        // ── 4. Construir resultado ─────────────────────────────────────────
        $result = [];
        foreach ($productos as $productoId => $p) {
            $stockMap  = $stocksByProduct[$productoId] ?? [];
            $ventaMap  = $ventasByProduct[$productoId] ?? [];
            $localVenta = $ventaMap[$sedeLocal] ?? null;

            $stockValues      = [];
            $ventasInternas   = [];
            $ventasInternas15d = [];
            $ultimasVentas    = [];
            $ultimasCompras   = [];
            foreach ($sedes as $sede) {
                $ventaSede = $ventaMap[$sede] ?? null;
                $stockValues[$sede]       = $stockMap[$sede] ?? 0;
                $ventasInternas[$sede]    = $ventaSede ? (int) $ventaSede['ventas_60d'] : 0;
                $ventasInternas15d[$sede] = $ventaSede ? (float) $ventaSede['venta_promedio'] : 0;
                $uv = $ventaSede['ultima_venta'] ?? null;
                $ultimasVentas[$sede] = $uv ? date('d/m/Y', strtotime((string) $uv)) : null;
                $uc = $ventaSede['ultima_compra'] ?? null;
                $ultimasCompras[$sede] = $uc ? date('d/m/Y', strtotime((string) $uc)) : null;
            }

            $ultimaVenta = $localVenta['ultima_venta'] ?? null;
            if ($ultimaVenta && ! is_string($ultimaVenta)) {
                $ultimaVenta = (string) $ultimaVenta;
            }

            $result[$p->codigo] = [
                'id'                  => (int) $productoId,
                'cod_centro'          => $p->codigo,
                'producto'            => $p->nombre,
                'categoria'           => $p->categoria,
                'subcategoria'        => $p->subcategoria,
                'proveedor'           => $p->proveedor,
                'existencia'          => $stockMap[$sedeLocal] ?? 0,
                'venta'               => $localVenta ? (float) $localVenta['venta_promedio'] : 0,
                'ventas_60d'          => $localVenta ? (float) $localVenta['ventas_60d'] : 0.0,
                'ultima_venta'        => $ultimaVenta ? date('d/m/Y', strtotime($ultimaVenta)) : null,
                'stocks'              => $stockValues,
                'ventas_internas'     => $ventasInternas,
                'ventas_internas_15d' => $ventasInternas15d,
                'ultimas_ventas'      => $ultimasVentas,
                'ultimas_compras'     => $ultimasCompras,
            ];
        }

        return $result;
    }

    public function lastStockUpdate(): ?string
    {
        if ($this->lastStockUpdateCache !== null) {
            return $this->lastStockUpdateCache;
        }

        $ts = StockActual::query()->max('updated_at');
        $this->lastStockUpdateCache = $ts ? (string) $ts : null;

        return $this->lastStockUpdateCache;
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
                        'venta_promedio' => (float) ($m['promedio_15d'] ?? 0),
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

    public function applyRequisition(Collection $lines, string $sedeOrigen, string $sedeDestino, ?string $usuario = null, ?string $sourceType = null): int
    {
        $applied = 0;

        DB::connection('pgsql')->transaction(function () use ($lines, $sedeOrigen, $sedeDestino, $usuario, $sourceType, &$applied) {
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
                    'metadata' => [
                        'codigo' => $cod,
                        'source_type' => $sourceType,
                    ],
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
                        'promedio_15d' => (float) ($p->ventas->firstWhere('sede', $sede)?->venta_promedio ?? 0),
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
