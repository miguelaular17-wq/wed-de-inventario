<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProductRepository
{
    private array $loadForSedeCache = [];
    private ?string $lastStockUpdateCache = null;

    public function __construct(
        private InventarioV2Repository $v2,
    ) {}

    public function loadForSede(string $sedeLocal): Collection
    {
        $stockUpdatedAt = $this->lastStockUpdate();
        $cacheKey = 'product_repository.load_for_sede.'.$sedeLocal.'.'.md5((string) $stockUpdatedAt);

        $cacheSeconds = max(60, (int) config('inventario.load_for_sede_cache_seconds', 1800));

        $products = $this->loadForSedeCache[$sedeLocal] ??= Cache::remember($cacheKey, $cacheSeconds, function () use ($sedeLocal) {
            return config('database.default') === 'pgsql'
                ? $this->v2->loadForSede($sedeLocal)
                : $this->loadFromSqlite($sedeLocal);
        });

        if (auth()->check() && auth()->user()->isTelefonia()) {
            $products = $products->filter(function ($row) {
                return $this->isAllowedCategoryForTelefonia($row['categoria'] ?? '');
            })->values();
        }

        return $products;
    }

    public function findForSedeByCodigo(string $sedeLocal, string $codigo): ?array
    {
        $product = null;
        if (isset($this->loadForSedeCache[$sedeLocal])) {
            $product = $this->loadForSedeCache[$sedeLocal]->firstWhere('cod_centro', $codigo);
        } elseif (config('database.default') === 'pgsql') {
            $product = $this->v2->findForSedeByCodigo($sedeLocal, $codigo);
        } else {
            $product = $this->findFromSqliteByCodigo($sedeLocal, $codigo);
        }

        if ($product && auth()->check() && auth()->user()->isTelefonia()) {
            if (!$this->isAllowedCategoryForTelefonia($product['categoria'] ?? '')) {
                return null;
            }
        }

        return $product;
    }

    private function isAllowedCategoryForTelefonia(string $category): bool
    {
        $normalized = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'],
            ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'],
            mb_strtoupper(trim($category))
        );

        $allowed = [
            'TELEFONIA',
            'ACCESORIOS ELECTRONICOS',
            'ACCESORIOS TECNOLOGICOS',
            'ELECTRONICA'
        ];

        return in_array($normalized, $allowed, true);
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
        if ($this->lastStockUpdateCache !== null) {
            return $this->lastStockUpdateCache;
        }

        $ttl = max(1, (int) config('inventario.last_stock_update_cache_seconds', 30));

        return $this->lastStockUpdateCache = Cache::remember(
            'product_repository.last_stock_update_ts',
            $ttl,
            fn () => config('database.default') === 'pgsql'
                ? $this->v2->lastStockUpdate()
                : \App\Models\ProductSedeMetric::query()->max('updated_at')
        );
    }

    public function findFromSqliteByCodigo(string $sedeLocal, string $codigo): ?array
    {
        return $this->loadForSede($sedeLocal)->firstWhere('cod_centro', $codigo);
    }
}
