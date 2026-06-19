<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StockMovementService
{
    public function __construct(
        private InventarioV2Repository $v2,
    ) {}

    public function applyRequisition(Collection $lines, string $sedeOrigen, string $sedeDestino, ?string $usuario = null): int
    {
        if ($sedeOrigen === $sedeDestino) {
            throw new \InvalidArgumentException('Origen y destino deben ser distintos.');
        }

        $sedeLabel = config('inventario.display')[$sedeOrigen] ?? $sedeOrigen;
        $userEmail = $usuario ?? auth()->user()?->email ?? $sedeLabel;

        if (config('database.default') === 'pgsql') {
            return $this->v2->applyRequisition(
                $lines,
                $sedeOrigen,
                $sedeDestino,
                $userEmail,
            );
        }

        return $this->applySqlite($lines, $sedeOrigen, $sedeDestino, $userEmail);
    }

    private function applySqlite(Collection $lines, string $sedeOrigen, string $sedeDestino, ?string $usuario = null): int
    {
        $applied = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($lines, $sedeOrigen, $sedeDestino, &$applied) {
            foreach ($lines as $line) {
                $cod = (string) ($line['codigo'] ?? '');
                $qty = (int) ($line['cantidad'] ?? 0);
                if ($cod === '' || $qty <= 0) {
                    continue;
                }

                $product = \App\Models\Product::query()->where('cod_centro', $cod)->first();
                if (! $product) {
                    continue;
                }

                $this->adjustMetric($product->id, $sedeOrigen, -$qty);
                $this->adjustMetric($product->id, $sedeDestino, $qty);

                \App\Models\StockMovement::create([
                    'cod_centro' => $cod,
                    'sede_origen' => $sedeOrigen,
                    'sede_destino' => $sedeDestino,
                    'cantidad' => $qty,
                    'tipo' => 'requisicion',
                    'usuario' => $usuario ?? auth()->user()?->email ?? 'system',
                ]);

                $applied++;
            }
        });

        return $applied;
    }

    private function adjustMetric(int $productId, string $sede, int $delta): void
    {
        $metric = \App\Models\ProductSedeMetric::query()
            ->where('product_id', $productId)
            ->where('sede', $sede)
            ->first();

        if (! $metric) {
            \App\Models\ProductSedeMetric::create([
                'product_id' => $productId,
                'sede' => $sede,
                'existencia' => max(0, $delta),
            ]);

            return;
        }

        $metric->existencia = max(0, $metric->existencia + $delta);
        $metric->save();
    }
}
