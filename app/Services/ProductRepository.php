<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ProductRepository
{
    public function __construct(
        private InventarioV2Repository $v2,
    ) {}

    public function loadForSede(string $sedeLocal): Collection
    {
        if (config('database.default') === 'pgsql') {
            return $this->v2->loadForSede($sedeLocal);
        }

        return $this->loadFromSqlite($sedeLocal);
    }

    private function loadFromSqlite(string $sedeLocal): Collection
    {
        $sedes = config('inventario.sedes_stock');

        return \App\Models\Product::query()
            ->with(['sedeMetrics'])
            ->get()
            ->map(function ($product) use ($sedeLocal, $sedes) {
                $metricsBySede = $product->sedeMetrics->keyBy('sede');
                $local = $metricsBySede->get($sedeLocal);

                $stocks = [];
                $ventasInternas = [];
                $ventasInternas15d = [];
                foreach ($sedes as $sede) {
                    $m = $metricsBySede->get($sede);
                    $stocks[$sede] = $m?->existencia ?? 0;
                    $ventasInternas[$sede] = $m?->ventas_60d ?? 0;
                    $ventasInternas15d[$sede] = $m?->promedio_15d ?? 0;
                }

                return [
                    'id' => $product->id,
                    'cod_centro' => $product->cod_centro,
                    'producto' => $product->producto,
                    'categoria' => $product->categoria,
                    'subcategoria' => $product->subcategoria,
                    'proveedor' => $product->proveedor,
                    'existencia' => $local?->existencia ?? 0,
                    'venta' => $local?->promedio_15d ?? 0,
                    'ventas_60d' => $local?->ventas_60d ?? 0,
                    'ultima_venta' => $local?->ultima_venta?->format('d/m/Y'),
                    'stocks' => $stocks,
                    'ventas_internas' => $ventasInternas,
                    'ventas_internas_15d' => $ventasInternas15d,
                ];
            });
    }

    public function lastStockUpdate(): ?string
    {
        if (config('database.default') === 'pgsql') {
            return $this->v2->lastStockUpdate();
        }

        return \App\Models\ProductSedeMetric::query()->max('updated_at');
    }
}
