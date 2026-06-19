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
            ->get(['id', 'codigo', 'nombre', 'categoria', 'subcategoria', 'proveedor']);

        if ($productos->isEmpty()) {
            return collect();
        }

        $stockByProduct = DB::connection('pgsql')
            ->table('stock_actual')
            ->get(['producto_id', 'sede', 'existencia'])
            ->groupBy('producto_id');

        $ventasByProduct = DB::connection('pgsql')
            ->table('ventas_historicas')
            ->get(['producto_id', 'sede', 'venta_promedio', 'ventas_60d', 'ultima_venta'])
            ->groupBy('producto_id');

        return $productos->map(function ($p) use ($sedeLocal, $sedes, $stockByProduct, $ventasByProduct) {
            $stockMap = ($stockByProduct->get($p->id) ?? collect())->keyBy('sede');
            $ventaMap = ($ventasByProduct->get($p->id) ?? collect())->keyBy('sede');
            $localStock = $stockMap->get($sedeLocal);
            $localVenta = $ventaMap->get($sedeLocal);

            $stocks = [];
            $ventasInternas = [];
            $ventasInternas15d = [];
            foreach ($sedes as $sede) {
                $stocks[$sede] = (int) ($stockMap->get($sede)?->existencia ?? 0);
                $ventasInternas[$sede] = (int) ($ventaMap->get($sede)?->ventas_60d ?? 0);
                $ventasInternas15d[$sede] = (int) ($ventaMap->get($sede)?->venta_promedio ?? 0);
            }

            $ultimaVenta = $localVenta?->ultima_venta ?? null;
            if ($ultimaVenta && ! is_string($ultimaVenta)) {
                $ultimaVenta = (string) $ultimaVenta;
            }

            return [
                'id' => (int) $p->id,
                'cod_centro' => $p->codigo,
                'producto' => $p->nombre,
                'categoria' => $p->categoria,
                'subcategoria' => $p->subcategoria,
                'proveedor' => $p->proveedor,
                'existencia' => (int) ($localStock?->existencia ?? 0),
                'venta' => (int) ($localVenta?->venta_promedio ?? 0),
                'ventas_60d' => (float) ($localVenta?->ventas_60d ?? 0),
                'ultima_venta' => $ultimaVenta ? date('d/m/Y', strtotime($ultimaVenta)) : null,
                'stocks' => $stocks,
                'ventas_internas' => $ventasInternas,
                'ventas_internas_15d' => $ventasInternas15d,
            ];
        });
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
                'TRUNCATE TABLE inventario_v2.stock_actual, inventario_v2.ventas_historicas RESTART IDENTITY'
            );
            Producto::query()->delete();

            $productRows = [];
            foreach ($rows as $row) {
                $productRows[] = [
                    'codigo' => (string) ($row['cod_centro'] ?? ''),
                    'nombre' => (string) ($row['producto'] ?? ''),
                    'categoria' => (string) ($row['categoria'] ?? ''),
                    'subcategoria' => (string) ($row['subcategoria'] ?? ''),
                    'proveedor' => (string) ($row['proveedor'] ?? ''),
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($productRows, 400) as $chunk) {
                DB::connection('pgsql')->table('productos')->insert($chunk);
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
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($stockRows, 800) as $chunk) {
                DB::connection('pgsql')->table('stock_actual')->insert($chunk);
            }

            foreach (array_chunk($ventaRows, 800) as $chunk) {
                DB::connection('pgsql')->table('ventas_historicas')->insert($chunk);
            }
        });

        return $count;
    }

    public function applyRequisition(Collection $lines, string $sedeOrigen, string $sedeDestino, ?string $usuario = null): int
    {
        $applied = 0;

        DB::connection('pgsql')->transaction(function () use ($lines, $sedeOrigen, $sedeDestino, $usuario, &$applied) {
            foreach ($lines as $line) {
                $cod = (string) ($line['codigo'] ?? '');
                $qty = (int) ($line['cantidad'] ?? 0);
                if ($cod === '' || $qty <= 0) {
                    continue;
                }

                $producto = Producto::query()->where('codigo', $cod)->first();
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
