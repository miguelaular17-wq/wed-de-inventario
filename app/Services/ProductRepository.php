<?php

namespace App\Services;

use App\Services\Profiler;
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
        Profiler::start('ProductRepository::loadForSede total');

        // Verificar caché de instancia (misma request: no re-deserializa)
        if (isset($this->loadForSedeCache[$sedeLocal])) {
            $cached = $this->loadForSedeCache[$sedeLocal];
            Profiler::record('ProductRepository::loadForSede [INSTANCE HIT]', 0.0, count($cached));
            Profiler::stop('ProductRepository::loadForSede total', count($cached));
            return $cached;
        }

        // ── Paso 1: timestamp del último stock ─────────────────
        Profiler::start('ProductRepository::lastStockUpdate');
        $this->lastStockUpdate(); // populate cache
        Profiler::stop('ProductRepository::lastStockUpdate');

        // ── Paso 2: Construir desde las dos capas del repositorio ──
        // Las capas individuales (global_products_v2 + global_stock_ventas) ya están
        // cacheadas por InventarioV2Repository con payloads mucho más pequeños.
        // No se guarda el resultado combinado en caché para evitar serializar
        // 12,947 arrays de 20 campos (~14 MB de payload).
        Profiler::start('ProductRepository::loadForSede [BUILD] v2->loadForSede');
        $products = config('database.default') === 'pgsql'
            ? $this->v2->loadForSede($sedeLocal)
            : $this->loadFromSqlite($sedeLocal);
        Profiler::stop('ProductRepository::loadForSede [BUILD] v2->loadForSede', count($products));

        // Guardar en caché de instancia (reutilización dentro del mismo request)
        $this->loadForSedeCache[$sedeLocal] = $products;

        // ── Filtro telefonía ────────────────────────────────
        if (auth()->check() && auth()->user()->isTelefonia()) {
            Profiler::start('ProductRepository::loadForSede filter(telefonia)');
            $products = $products->filter(function ($row) {
                return $this->isAllowedCategoryForTelefonia($row['categoria'] ?? '');
            })->values();
            Profiler::stop('ProductRepository::loadForSede filter(telefonia)', count($products));
        }

        Profiler::stop('ProductRepository::loadForSede total', count($products));
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

        $allowed = array_map(
            static fn (string $cat) => mb_strtoupper(trim($cat), 'UTF-8'),
            config('inventario.categorias_telefonia', [])
        );

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

        Profiler::start('ProductRepository::lastStockUpdate');
        $result = $this->lastStockUpdateCache = Cache::remember(
            'product_repository.last_stock_update_ts',
            $ttl,
            fn () => config('database.default') === 'pgsql'
                ? $this->v2->lastStockUpdate()
                : \App\Models\ProductSedeMetric::query()->max('updated_at')
        );
        Profiler::stop('ProductRepository::lastStockUpdate');

        return $result;
    }

    public function findFromSqliteByCodigo(string $sedeLocal, string $codigo): ?array
    {
        return $this->loadForSede($sedeLocal)->firstWhere('cod_centro', $codigo);
    }
}
