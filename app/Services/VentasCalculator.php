<?php

namespace App\Services;

use Illuminate\Support\Collection;

class VentasCalculator
{
    private const STOCK_ORDER = ['JRZ', 'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL'];

    public function calcular(Collection $products, string $sedeLocal, float $tiempoPronostico): Collection
    {
        $tv = (float) config('inventario.tiempo_venta_sede', 15);
        $tp = max($tiempoPronostico, 1.0);
        $localIndex = array_search($sedeLocal, self::STOCK_ORDER, true);

        return $products
            ->map(function (array $row) use ($sedeLocal, $tv, $tp, $localIndex) {
                $stocks = $row['stocks'];
                $ventas = $row['ventas_internas'];
                $exist = (int) $row['existencia'];
                $ventaLocal = (float) $row['ventas_60d'];

                $demanda = ($ventaLocal / 60) * $tp;
                $exc = [];
                $puede = [];

                foreach (self::STOCK_ORDER as $i => $sede) {
                    $stk = (int) ($stocks[$sede] ?? 0);
                    $v = (float) ($ventas[$sede] ?? 0);
                    $dem = ($v / $tv) * $tp;
                    $excedente = max(0, (int) floor($stk - $dem));
                    $exc[$sede] = $excedente;
                    $puede[$sede] = $excedente > 0 && ($localIndex === false || $i !== $localIndex);
                }

                if ($ventaLocal <= 0) {
                    return null;
                }

                if (! $this->tieneActividad($row, $stocks, $ventaLocal)) {
                    return null;
                }

                $haySurt = collect($puede)->contains(true);
                $necesita = $exist < $demanda - 1e-9;

                if ($demanda <= 1e-9) {
                    $accion = 'SIN VENTA';
                } elseif ($necesita && $haySurt) {
                    $accion = 'HACER REQUISICION';
                } elseif ($necesita) {
                    $accion = 'NO TIENE EXISTENCIA';
                } else {
                    $accion = 'TIENE EXISTENCIA';
                }

                $sugeridoNec = $accion === 'HACER REQUISICION'
                    ? max(0, (int) round($demanda - $exist))
                    : 0;

                [$op1, $op2] = $this->opcionesDesdeExcedente($puede, $exc, $sugeridoNec);

                $sugerido = $this->sugeridoVisible($accion, $sugeridoNec, $op1, $op2, $exc);
                $tag = $this->reqTag($accion, $sugeridoNec, $op1, $op2, $exc);

                return array_merge($row, [
                    'accion' => $accion,
                    'demanda' => (int) round($demanda),
                    'opc' => $this->etiquetaOpc($op1, $op2),
                    'op1' => $op1,
                    'op2' => $op2,
                    'sugerido_nec' => $sugeridoNec,
                    'sugerido' => $sugerido,
                    'req_tag' => $tag,
                    'excedentes' => $exc,
                ]);
            })
            ->filter()
            ->filter(fn (array $row) => $row['accion'] === 'TIENE EXISTENCIA' || (int) $row['sugerido'] >= config('inventario.minimo_sugerido_ventas', 3))
            ->values();
    }

    public function calcularInventario(Collection $products, string $sedeLocal): Collection
    {
        return $products
            ->filter(fn (array $row) => ($row['existencia'] ?? 0) > 0 || ($row['venta'] ?? 0) > 0)
            ->map(function (array $row) use ($sedeLocal) {
                $exist = (int) $row['existencia'];
                $venta = (float) $row['venta'];
                $tv = (float) config('inventario.tiempo_venta_sede', 15);
                $tp = (float) config('inventario.tiempo_pronostico_default', 15);
                $demanda = ($venta / $tv) * $tp;

                if ($venta <= 0) {
                    $accion = 'SIN VENTA';
                } elseif ($exist >= $demanda) {
                    $accion = 'OK';
                } elseif ($exist > 0) {
                    $accion = 'BAJO';
                } else {
                    $accion = 'SIN EXISTENCIA';
                }

                return array_merge($row, ['accion_inventario' => $accion]);
            })
            ->values();
    }

    private function opcionesDesdeExcedente(array $puede, array $exc, int $need = 0): array
    {
        $candidates = [];
        foreach (self::STOCK_ORDER as $sede) {
            if (! empty($puede[$sede])) {
                $candidates[] = ['sede' => $sede, 'exc' => $exc[$sede] ?? 0];
            }
        }

        if ($candidates === []) {
            return ['', ''];
        }

        usort($candidates, function ($a, $b) {
            if ($a['sede'] === 'JRZ' && $a['exc'] > 0) {
                return -1;
            }
            if ($b['sede'] === 'JRZ' && $b['exc'] > 0) {
                return 1;
            }

            return $b['exc'] <=> $a['exc'];
        });

        if ($candidates === []) {
            return ['', ''];
        }

        $first = $candidates[0];
        $op1 = config('inventario.display.'.$first['sede'], $first['sede']);

        // If need is zero (not requisition) or first covers the need, only return op1.
        if ($need <= 0 || $first['exc'] >= $need) {
            return [$op1, ''];
        }

        // Otherwise, return second option only if it exists and has excedente > 0
        if (isset($candidates[1]) && ($candidates[1]['exc'] ?? 0) > 0) {
            $op2 = config('inventario.display.'.$candidates[1]['sede'], $candidates[1]['sede']);
            return [$op1, $op2];
        }

        return [$op1, ''];
    }

    private function sedeFromDisplay(string $display): ?string
    {
        foreach (config('inventario.display', []) as $key => $label) {
            if (strcasecmp($label, $display) === 0 || strcasecmp($key, $display) === 0) {
                return $key;
            }
        }

        return null;
    }

    private function excedenteOpc(array $exc, string $display): int
    {
        $sede = $this->sedeFromDisplay($display);

        return $sede ? (int) ($exc[$sede] ?? 0) : 0;
    }

    private function reqTag(string $accion, int $nec, string $op1, string $op2, array $exc): string
    {
        if ($accion !== 'HACER REQUISICION' || $nec <= 0) {
            return '';
        }
        $e1 = $this->excedenteOpc($exc, $op1);
        $e2 = $this->excedenteOpc($exc, $op2);
        if ($e1 >= $nec) {
            return 'req_ok';
        }
        if ($e1 + $e2 >= $nec) {
            return 'req_parcial';
        }

        return 'req_insuf';
    }

    private function sugeridoVisible(string $accion, int $nec, string $op1, string $op2, array $exc): int
    {
        if ($accion !== 'HACER REQUISICION') {
            return 0;
        }
        $tag = $this->reqTag($accion, $nec, $op1, $op2, $exc);
        $e1 = $this->excedenteOpc($exc, $op1);
        $e2 = $this->excedenteOpc($exc, $op2);
        if ($tag === 'req_ok') {
            return $nec;
        }
        if (in_array($tag, ['req_parcial', 'req_insuf'], true)) {
            return max($e1, $e2);
        }

        return 0;
    }

    private function etiquetaOpc(string $op1, string $op2): string
    {
        if ($op1 && $op2) {
            return $op1.'+'.$op2;
        }

        return $op1 ?: $op2;
    }

    private function tieneActividad(array $row, array $stocks, float $ventaLocal): bool
    {
        $totalStock = array_sum($stocks);
        if ($totalStock > 0) {
            return true;
        }

        foreach ($row['ventas_internas'] ?? [] as $v) {
            if ((float) $v > 0) {
                return true;
            }
        }

        return $ventaLocal > 0;
    }
}
