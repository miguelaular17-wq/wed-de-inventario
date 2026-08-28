<?php

namespace App\Services;

use Illuminate\Support\Collection;

class GerencialAbcService
{
    public const A_HASTA = 80.0;

    public const B_HASTA = 95.0;

    /**
     * @param  Collection<int, object|array<string, mixed>>  $items
     * @return Collection<int, object>
     */
    public function clasificar(Collection $items, string $metric): Collection
    {
        $sorted = $items
            ->map(fn ($row) => is_array($row) ? (object) $row : $row)
            ->sortByDesc(fn ($row) => max(0.0, $this->valor($row, $metric)))
            ->values();

        $total = $sorted->sum(fn ($row) => max(0.0, $this->valor($row, $metric)));
        $acum = 0.0;

        return $sorted->map(function (object $row) use ($metric, $total, &$acum) {
            $val = max(0.0, $this->valor($row, $metric));
            $acumAntes = $acum;
            $acum += $val;
            $abc = 'C';
            if ($val > 0 && $total > 0) {
                $pctAntes = ($acumAntes / $total) * 100;
                $abc = $pctAntes < self::A_HASTA ? 'A' : ($pctAntes < self::B_HASTA ? 'B' : 'C');
            }
            $row->abc = $abc;
            $row->pct = $total > 0 ? round($val / $total * 100, 1) : 0.0;
            $row->pct_acum = $total > 0 ? round($acum / $total * 100, 1) : 0.0;

            return $row;
        });
    }

    /**
     * @param  Collection<int, object>  $clasificados
     * @return array<string, array{productos:int,pct_items:float,pct_valor:float}>
     */
    public function resumen(Collection $clasificados): array
    {
        $n = $clasificados->count();
        $out = [];
        foreach (['A', 'B', 'C'] as $clase) {
            $g = $clasificados->where('abc', $clase);
            $out[$clase] = [
                'productos' => $g->count(),
                'pct_items' => $n > 0 ? round($g->count() / $n * 100, 1) : 0.0,
                'pct_valor' => round((float) $g->sum('pct'), 1),
            ];
        }

        return $out;
    }

    private function valor(object $row, string $metric): float
    {
        return (float) ($row->{$metric} ?? 0);
    }
}
