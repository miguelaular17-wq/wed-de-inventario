<?php

namespace App\Services;

use App\Models\RequisicionManual;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\V2\Movimiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MovimientoQueryService
{
    private array $manualProductRowCache = [];

    public function list(array $filters): Collection
    {
        if (config('database.default') === 'pgsql') {
            return $this->withDisplayNames($this->listPg($filters));
        }

        return $this->withDisplayNames($this->listSqlite($filters));
    }

    public function stats(): array
    {
        if (config('database.default') === 'pgsql') {
            $total = Movimiento::query()->count();
            $requisiciones = Movimiento::query()->where('tipo', 'REQUISICION')->count();
            $sincronizaciones = Movimiento::query()->where('usuario', 'sistema_sync')->count();

            return compact('total', 'requisiciones', 'sincronizaciones');
        }

        $total = StockMovement::query()->count();
        $requisiciones = StockMovement::query()->where('tipo', 'requisicion')->count();

        return compact('total', 'requisiciones');
    }

    private function listPg(array $filters): Collection
    {
        $query = Movimiento::query()
            ->with('producto')
            ->orderByDesc('created_at');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('producto', function ($sub) use ($q) {
                $sub->where('codigo', 'ilike', "%{$q}%")
                    ->orWhere('nombre', 'ilike', "%{$q}%");
            });
        }

        if ($filters['sede'] !== '') {
            $sede = $filters['sede'];
            $query->where(function ($sub) use ($sede) {
                $sub->where('origen', $sede)->orWhere('destino', $sede);
            });
        }

        if ($filters['tipo'] !== '') {
            $query->where('tipo', $filters['tipo']);
        }

        if ($filters['desde'] !== '') {
            $query->whereDate('created_at', '>=', $filters['desde']);
        }

        if ($filters['hasta'] !== '') {
            $query->whereDate('created_at', '<=', $filters['hasta']);
        }

        $movimientos = $query->limit(500)->get()->map(fn (Movimiento $m) => [
            'id' => $m->id,
            'codigo' => $m->producto?->codigo ?? ($m->metadata['codigo'] ?? '—'),
            'producto' => $m->producto?->nombre ?? '—',
            'origen' => $m->origen,
            'destino' => $m->destino,
            'tipo' => $m->tipo,
            'cantidad' => $m->cantidad,
            'usuario' => $m->usuario ?: '—',
            'created_at' => $m->created_at?->format('d/m/Y H:i'),
            'created_at_ts' => $m->created_at?->getTimestamp() ?? 0,
            'metadata' => $m->metadata,
            'is_manual' => false,
        ]);

        return $movimientos
            ->concat($this->listPendingManualRequisitions($filters))
            ->sortByDesc('created_at_ts')
            ->values();
    }

    private function listPendingManualRequisitions(array $filters): Collection
    {
        if ($filters['tipo'] !== '' && strtoupper((string) $filters['tipo']) !== 'REQUISICION') {
            return collect();
        }

        $query = RequisicionManual::query();

        if ($filters['sede'] !== '') {
            $sede = strtoupper($filters['sede']);
            $query->where(function ($sub) use ($sede) {
                $sub->where('sede_origen', $sede)
                    ->orWhere('sede_local', $sede);
            });
        }

        if ($filters['desde'] !== '') {
            $query->whereDate('created_at', '>=', $filters['desde']);
        }

        if ($filters['hasta'] !== '') {
            $query->whereDate('created_at', '<=', $filters['hasta']);
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            if (config('database.default') === 'pgsql') {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'ilike', "%{$q}%")
                        ->orWhere('producto', 'ilike', "%{$q}%");
                });
            } else {
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'like', "%{$q}%")
                        ->orWhere('producto', 'like', "%{$q}%");
                });
            }
        }

        return $query->orderByDesc('created_at')->limit(500)->get()->map(function (RequisicionManual $manual) {
            $demandInfo = $this->manualDemandInfo($manual);

            return [
                'id' => 'manual-'.$manual->id,
                'codigo' => $manual->codigo,
                'producto' => $this->resolveManualProductName($manual) ?? $manual->producto,
                'origen' => $manual->sede_origen,
                'destino' => $manual->sede_local,
                'tipo' => 'REQUISICION',
                'cantidad' => $manual->cantidad,
                'usuario' => $manual->usuario ?: '—',
                'created_at' => $manual->created_at?->format('d/m/Y H:i'),
                'created_at_ts' => $manual->created_at?->getTimestamp() ?? 0,
                'is_manual' => true,
            'manual_exported' => $manual->aplicada_at !== null,
            ];
        });
    }

    private function manualDemandInfo(RequisicionManual $manual): array
    {
        $product = $this->resolveManualProductRow($manual);
        if (! $product) {
            return [
                'excedente' => null,
                'faltante' => null,
                'warning' => null,
            ];
        }

        $service = app(RequisicionPersonalizadaService::class);
        $metrics = $service->metricasOrigen(
            $product,
            $manual->sede_origen,
            (float) config('inventario.tiempo_pronostico_default')
        );

        [$warning, $faltante] = $service->mensajeValidacion((int) $manual->cantidad, $metrics['excedente']);

        return [
            'excedente' => $metrics['excedente'],
            'faltante' => $faltante,
            'warning' => $warning,
        ];
    }

    private function resolveManualProductRow(RequisicionManual $manual): ?array
    {
        $sede = strtoupper($manual->sede_local);

        if (! isset($this->manualProductRowCache[$sede])) {
            $products = app(ProductRepository::class)->loadForSede($sede)->keyBy('cod_centro');
            $this->manualProductRowCache[$sede] = $products;
        }

        return $this->manualProductRowCache[$sede]->get($manual->codigo);
    }

    private function resolveManualProductName(RequisicionManual $manual): ?string
    {
        return $this->resolveManualProductRow($manual)['producto'] ?? null;
    }

    public function lastUpdatedAt(): ?string
    {
        if (config('database.default') === 'pgsql') {
            $movimientoUpdated = Movimiento::query()->max('created_at');
        } else {
            $movimientoUpdated = StockMovement::query()->max('created_at');
        }

        $manualUpdated = RequisicionManual::query()->max('updated_at');
        $latest = collect([$movimientoUpdated, $manualUpdated])->filter()->max();

        return $latest ? Carbon::parse($latest)->format('Y-m-d H:i:s') : null;
    }

    public function listSince(string $since, array $filters): array
    {
        $sinceAt = false;
        try {
            $sinceAt = Carbon::parse($since);
        } catch (\Throwable $e) {
            return ['rows' => collect(), 'removed' => []];
        }

        $rows = collect();
        $removed = [];

        if (config('database.default') === 'pgsql') {
            $rows = $rows->concat($this->listMovimientosSince($sinceAt, $filters));
        } else {
            $rows = $rows->concat($this->listSqliteMovimientosSince($sinceAt, $filters));
        }

        $manualChanges = $this->listManualChangesSince($sinceAt, $filters);
        $rows = $rows->concat($manualChanges['rows']);
        $removed = $manualChanges['removed'];

        $sorted = $rows->sortByDesc('created_at_ts')->values();

        return ['rows' => $sorted, 'removed' => array_values($removed)];
    }

    private function listMovimientosSince(Carbon $sinceAt, array $filters): Collection
    {
        $query = Movimiento::query()->with('producto');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('producto', function ($sub) use ($q) {
                $sub->where('codigo', 'ilike', "%{$q}%")
                    ->orWhere('nombre', 'ilike', "%{$q}%");
            });
        }

        if ($filters['sede'] !== '') {
            $sede = $filters['sede'];
            $query->where(function ($sub) use ($sede) {
                $sub->where('origen', $sede)->orWhere('destino', $sede);
            });
        }

        if ($filters['tipo'] !== '') {
            $query->where('tipo', $filters['tipo']);
        }

        $query->where('created_at', '>', $sinceAt);

        return $query->orderByDesc('created_at')->limit(500)->get()->map(fn (Movimiento $m) => [
            'id' => $m->id,
            'codigo' => $m->producto?->codigo ?? ($m->metadata['codigo'] ?? '—'),
            'producto' => $m->producto?->nombre ?? '—',
            'origen' => $m->origen,
            'destino' => $m->destino,
            'tipo' => $m->tipo,
            'cantidad' => $m->cantidad,
            'usuario' => $m->usuario ?: '—',
            'created_at' => $m->created_at?->format('d/m/Y H:i'),
            'created_at_ts' => $m->created_at?->getTimestamp() ?? 0,
            'is_manual' => false,
            'manual_note' => null,
            'metadata' => $m->metadata,
        ]);
    }

    private function listSqliteMovimientosSince(Carbon $sinceAt, array $filters): Collection
    {
        $query = StockMovement::query();

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where('cod_centro', 'like', "%{$q}%");
        }

        if ($filters['sede'] !== '') {
            $sede = $filters['sede'];
            $query->where(function ($sub) use ($sede) {
                $sub->where('sede_origen', $sede)->orWhere('sede_destino', $sede);
            });
        }

        if ($filters['tipo'] !== '') {
            $query->where('tipo', strtolower($filters['tipo']));
        }

        $query->where('created_at', '>', $sinceAt);

        return $query->orderByDesc('created_at')->limit(500)->get()->map(fn (StockMovement $m) => [
            'id' => $m->id,
            'codigo' => $m->cod_centro,
            'producto' => '—',
            'origen' => $m->sede_origen,
            'destino' => $m->sede_destino,
            'tipo' => strtoupper($m->tipo),
            'cantidad' => $m->cantidad,
            'usuario' => '—',
            'created_at' => $m->created_at?->format('d/m/Y H:i'),
            'created_at_ts' => $m->created_at?->getTimestamp() ?? 0,
            'is_manual' => false,
            'manual_note' => null,
        ]);
    }

    private function listManualChangesSince(Carbon $sinceAt, array $filters): array
    {
        $query = RequisicionManual::query();

        if ($filters['sede'] !== '') {
            $sede = strtoupper($filters['sede']);
            $query->where(function ($sub) use ($sede) {
                $sub->where('sede_origen', $sede)
                    ->orWhere('sede_local', $sede);
            });
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($sub) use ($q, $operator) {
                $sub->where('codigo', $operator, "%{$q}%")
                    ->orWhere('producto', $operator, "%{$q}%");
            });
        }

        $query->where('updated_at', '>', $sinceAt);

        $rows = $query->orderByDesc('updated_at')->limit(500)->get();

        $changedRows = $rows->map(function (RequisicionManual $manual) {
            $demandInfo = $this->manualDemandInfo($manual);

            return [
                'id' => 'manual-'.$manual->id,
                'codigo' => $manual->codigo,
                'producto' => $this->resolveManualProductName($manual) ?? $manual->producto,
                'origen' => $manual->sede_origen,
                'destino' => $manual->sede_local,
                'tipo' => 'REQUISICION',
                'cantidad' => $manual->cantidad,
                'usuario' => $manual->usuario ?: '—',
                'created_at' => $manual->created_at?->format('d/m/Y H:i'),
                'created_at_ts' => $manual->created_at?->getTimestamp() ?? 0,
                'is_manual' => true,
                'manual_exported' => $manual->aplicada_at !== null,
                'manual_note' => $demandInfo['warning'],
            ];
        })->values();

        return ['rows' => $changedRows, 'removed' => []];
    }

    private function withDisplayNames(Collection $rows): Collection
    {
        $emails = $rows
            ->pluck('usuario')
            ->filter(fn ($u) => is_string($u) && str_contains($u, '@'))
            ->unique()
            ->values();

        $namesByEmail = $emails->isEmpty()
            ? collect()
            : User::query()->whereIn('email', $emails)->pluck('name', 'email');

        return $rows->map(function (array $row) use ($namesByEmail) {
            $row['usuario'] = $this->displayNameForUsuario($row['usuario'] ?? '—', $namesByEmail);

            return $row;
        });
    }

    private function displayNameForUsuario(string $raw, Collection $namesByEmail): string
    {
        if ($raw === '' || $raw === '—') {
            return '—';
        }

        if (strtolower($raw) === 'system') {
            return 'Sistema';
        }

        if (isset($namesByEmail[$raw])) {
            return (string) $namesByEmail[$raw];
        }

        if (str_contains($raw, '@')) {
            return (string) str($raw)->before('@')->replace(['.', '_'], ' ')->title();
        }

        return $raw;
    }

    private function listSqlite(array $filters): Collection
    {
        $query = StockMovement::query()->orderByDesc('created_at');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where('cod_centro', 'like', "%{$q}%");
        }

        if ($filters['sede'] !== '') {
            $sede = $filters['sede'];
            $query->where(function ($sub) use ($sede) {
                $sub->where('sede_origen', $sede)->orWhere('sede_destino', $sede);
            });
        }

        if ($filters['tipo'] !== '') {
            $query->where('tipo', strtolower($filters['tipo']));
        }

        if ($filters['desde'] !== '') {
            $query->whereDate('created_at', '>=', $filters['desde']);
        }

        if ($filters['hasta'] !== '') {
            $query->whereDate('created_at', '<=', $filters['hasta']);
        }

        $movimientos = $query->limit(500)->get()->map(fn (StockMovement $m) => [
            'id' => $m->id,
            'codigo' => $m->cod_centro,
            'producto' => '—',
            'origen' => $m->sede_origen,
            'destino' => $m->sede_destino,
            'tipo' => strtoupper($m->tipo),
            'cantidad' => $m->cantidad,
            'usuario' => '—',
            'created_at' => $m->created_at?->format('d/m/Y H:i'),
            'created_at_ts' => $m->created_at?->getTimestamp() ?? 0,
        ]);

        return $movimientos
            ->concat($this->listPendingManualRequisitions($filters))
            ->sortByDesc('created_at_ts')
            ->values();
    }
}
