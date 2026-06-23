<?php

namespace App\Services;

use App\Models\RequisicionManual;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RequisicionPersonalizadaService
{
    private const STOCK_ORDER = ['JRZ', 'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL'];

    /**
     * Build inventory rows enriched with any pending/exported manual requisitions.
     * A product can now have requisitions from multiple sede_origns simultaneously,
     * so we group by 'codigo' and merge all of them into the row data.
     */
    public function buildRows(Collection $products, string $sedeLocal, Collection $manuales): Collection
    {
        // Group by codigo so a product can have N requisitions (one per sede_origen)
        $manualByCod = $manuales->groupBy('codigo');

        return $products
            ->map(function (array $row) use ($sedeLocal, $manualByCod) {
                if (! $this->tieneStockOtrasSedes($row, $sedeLocal)) {
                    return null;
                }

                /** @var Collection $entries */
                $entries = $manualByCod->get($row['cod_centro'], collect());

                $reqManual      = $entries->isNotEmpty();
                $origenManual   = '';   // legacy single-value (first pending, for backward compat)
                $cantidadManual = 0;    // legacy single-value
                $accion         = '';

                // Build the multi-sede array used by the new UI
                $manualesList = $entries->map(fn ($m) => [
                    'id'        => $m->id,
                    'sede_origen' => $m->sede_origen,
                    'cantidad'  => (int) $m->cantidad,
                    'pendiente' => $m->isPendiente(),
                    'accion'    => $this->textoAccionManual($m->sede_origen, (int) $m->cantidad, $m->isPendiente()),
                ])->values()->all();

                if ($reqManual) {
                    // Legacy fields: prefer the first pendiente entry
                    $first = $entries->first();
                    $origenManual   = $first->sede_origen;
                    $cantidadManual = (int) $first->cantidad;
                    $accion = $this->textoAccionManualMulti($manualesList);
                }

                return array_merge($row, [
                    'req_manual'      => $reqManual,
                    'origen_manual'   => $origenManual,
                    'cantidad_manual' => $cantidadManual,
                    'accion_manual'   => $accion,
                    'manual_pendiente' => $entries->contains(fn ($m) => $m->isPendiente()),
                    // New: full list of requisitions for this product
                    'manuales_list'   => $manualesList,
                ]);
            })
            ->filter()
            ->values();
    }

    public function applyFilters(Collection $rows, array $filters): Collection
    {
        $out = $rows;

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $out = $out->filter(fn (array $r) => str_contains(mb_strtolower($r['producto'] ?? ''), $qLower)
                || str_contains(mb_strtolower($r['cod_centro'] ?? ''), $qLower));
        }

        $cat = (string) ($filters['categoria'] ?? 'Ninguno');
        if ($cat !== '' && $cat !== 'Ninguno') {
            $out = $out->filter(fn (array $r) => ($r['categoria'] ?? '') === $cat);
        }

        $sub = (string) ($filters['subcategoria'] ?? 'Ninguno');
        if ($sub !== '' && $sub !== 'Ninguno') {
            $out = $out->filter(fn (array $r) => ($r['subcategoria'] ?? '') === $sub);
        }

        return $out->values();
    }

    public function categorias(Collection $rows): array
    {
        return $rows->pluck('categoria')->filter()->unique()->sort()->values()->all();
    }

    public function subcategorias(Collection $rows, ?string $categoria): array
    {
        $filtered = $categoria && $categoria !== 'Ninguno'
            ? $rows->filter(fn (array $r) => ($r['categoria'] ?? '') === $categoria)
            : $rows;

        return $filtered->pluck('subcategoria')->filter()->unique()->sort()->values()->all();
    }

    public function sedesOrigen(string $sedeLocal): array
    {
        return collect(self::STOCK_ORDER)->reject(fn ($s) => $s === $sedeLocal)->values()->all();
    }

    public function metricasOrigen(array $row, string $sedeOrigen, float $tiempoPronostico): array
    {
        $tv = (float) config('inventario.tiempo_venta_sede', 15);
        $tp = max($tiempoPronostico, 1.0);
        $stock = (int) ($row['stocks'][$sedeOrigen] ?? 0);
        $venta = (float) ($row['ventas_internas'][$sedeOrigen] ?? 0);
        $demanda = ($venta / max($tv, 1.0)) * $tp;
        $excedente = max(0, (int) floor($stock - $demanda));

        return [
            'stock'    => $stock,
            'demanda'  => round($demanda, 2),
            'excedente' => $excedente,
        ];
    }

    public function mensajeValidacion(int $cantidad, int $excedente): array
    {
        if ($cantidad <= $excedente) {
            return ['Cantidad segura. No afecta la demanda proyectada de la sede origen.', null];
        }

        return [
            'Advertencia: esta requisición consume stock necesario para cubrir la demanda de la sede origen.',
            $cantidad - $excedente,
        ];
    }

    /**
     * Save or update a manual requisition for a specific (sede_local, codigo, sede_origen) tuple.
     * Multiple sede_origen values are now allowed for the same product.
     */
    public function confirmar(
        string $sedeLocal,
        string $codigo,
        string $producto,
        string $sedeOrigen,
        int $cantidad,
        ?string $usuario,
    ): void {
        $sedeOrigen = strtoupper($sedeOrigen);
        $sedeLocal  = strtoupper($sedeLocal);

        if ($cantidad <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }

        if (! in_array($sedeOrigen, $this->sedesOrigen($sedeLocal), true)) {
            throw new \InvalidArgumentException('Sede origen inválida.');
        }

        // Unique key is now (sede_local, codigo, sede_origen)
        RequisicionManual::query()->updateOrCreate(
            [
                'sede_local'  => $sedeLocal,
                'codigo'      => $codigo,
                'sede_origen' => $sedeOrigen,
            ],
            [
                'producto'    => $producto,
                'cantidad'    => $cantidad,
                'usuario'     => $usuario,
                'aplicada_at' => null,
            ]
        );
    }

    /**
     * Delete a specific manual requisition by (sede_local, codigo, sede_origen).
     * Returns true if a record was deleted.
     */
    public function eliminar(
        string $sedeLocal,
        string $codigo,
        string $sedeOrigen,
    ): bool {
        $deleted = RequisicionManual::query()
            ->where('sede_local', strtoupper($sedeLocal))
            ->where('codigo', $codigo)
            ->where('sede_origen', strtoupper($sedeOrigen))
            ->whereNull('aplicada_at')
            ->delete();

        return $deleted > 0;
    }

    public function buildExport(
        string $sedeLocal,
        ?string $sedeOrigen = null,
        ?string $categoria = null,
        ?string $subcategoria = null,
        bool $soloPendientes = true,
    ): Collection {
        $query = RequisicionManual::query()->where('sede_local', $sedeLocal);

        if ($soloPendientes) {
            $query->whereNull('aplicada_at');
        }

        if ($sedeOrigen !== null && $sedeOrigen !== '') {
            $query->where('sede_origen', strtoupper($sedeOrigen));
        }

        $rows = $query->orderBy('codigo')->get();

        if ($categoria || ($subcategoria && $subcategoria !== 'Todas')) {
            $productRepo = app(ProductRepository::class);
            $products = $productRepo->loadForSede($sedeLocal)->keyBy('cod_centro');

            $rows = $rows->filter(function (RequisicionManual $m) use ($products, $categoria, $subcategoria) {
                $p = $products->get($m->codigo);
                if (! $p) {
                    return false;
                }
                if ($categoria && $categoria !== 'Todas' && ($p['categoria'] ?? '') !== $categoria) {
                    return false;
                }
                if ($subcategoria && $subcategoria !== 'Todas' && ($p['subcategoria'] ?? '') !== $subcategoria) {
                    return false;
                }

                return true;
            });
        }

        return $rows->map(fn (RequisicionManual $m) => [
            'codigo'      => $m->codigo,
            'unidad'      => 'UND',
            'cantidad'    => $m->cantidad,
            'producto'    => $m->producto,
            'sede_origen' => $m->sede_origen,
        ])->values();
    }

    /** Aplica movimiento de stock y marca las líneas como exportadas. */
    public function applyExport(
        Collection $lines,
        string $sedeLocal,
        StockMovementService $stock,
        ?string $usuario = null,
    ): int {
        if ($lines->isEmpty()) {
            return 0;
        }

        $applied = 0;

        DB::transaction(function () use ($lines, $sedeLocal, $stock, $usuario, &$applied) {
            foreach ($lines->groupBy('sede_origen') as $origen => $group) {
                $origenKey = strtoupper((string) $origen);
                $codigos   = $group->pluck('codigo')->filter()->values()->all();

                $applied += $stock->applyRequisition(
                    $group->values(),
                    $origenKey,
                    strtoupper($sedeLocal),
                    $usuario,
                );

                RequisicionManual::query()
                    ->where('sede_local', strtoupper($sedeLocal))
                    ->where('sede_origen', $origenKey)
                    ->whereIn('codigo', $codigos)
                    ->whereNull('aplicada_at')
                    ->update(['aplicada_at' => now()]);
            }
        });

        return $applied;
    }

    public function loadManuales(string $sedeLocal, bool $soloPendientes = false): Collection
    {
        $query = RequisicionManual::query()->where('sede_local', $sedeLocal);

        if ($soloPendientes) {
            $query->whereNull('aplicada_at');
        }

        return $query->get();
    }

    public function countPendientes(string $sedeLocal): int
    {
        return RequisicionManual::query()
            ->where('sede_local', $sedeLocal)
            ->whereNull('aplicada_at')
            ->count();
    }

    public function lastUpdatedAt(string $sedeLocal): ?string
    {
        return RequisicionManual::query()
            ->where('sede_local', $sedeLocal)
            ->max('updated_at');
    }

    public function getManualesListForProduct(string $sedeLocal, string $codigo): array
    {
        $manuales = $this->loadManuales($sedeLocal)->where('codigo', $codigo);
        return $manuales->map(fn ($m) => [
            'id'        => $m->id,
            'sede_origen' => $m->sede_origen,
            'cantidad'  => (int) $m->cantidad,
            'pendiente' => $m->isPendiente(),
            'accion'    => $this->textoAccionManual($m->sede_origen, (int) $m->cantidad, $m->isPendiente()),
        ])->values()->all();
    }

    private function tieneStockOtrasSedes(array $row, string $sedeLocal): bool
    {
        foreach ($row['stocks'] ?? [] as $sede => $qty) {
            if ($sede !== $sedeLocal && (int) $qty > 0) {
                return true;
            }
        }

        return false;
    }

    private function textoAccionManual(string $sedeOrigen, int $cantidad, bool $pendiente): string
    {
        $label = config('inventario.display.'.$sedeOrigen, $sedeOrigen);

        if ($pendiente) {
            return "PENDIENTE ({$label}: {$cantidad})";
        }

        return "EXPORTADA ({$label}: {$cantidad})";
    }

    /**
     * Build a single summary string when there are multiple sede-origen requisitions.
     */
    private function textoAccionManualMulti(array $manualesList): string
    {
        if (empty($manualesList)) {
            return '';
        }

        $parts = array_map(function (array $m) {
            $label = config('inventario.display.'.$m['sede_origen'], $m['sede_origen']);
            $prefix = $m['pendiente'] ? 'PEND' : 'EXP';
            return "{$prefix} {$label}: {$m['cantidad']}";
        }, $manualesList);

        return implode(' | ', $parts);
    }
}
