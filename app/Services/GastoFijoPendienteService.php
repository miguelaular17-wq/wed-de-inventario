<?php

namespace App\Services;

use App\Models\GastoFijoPago;
use Carbon\CarbonInterface;

class GastoFijoPendienteService
{
    /** @var list<string> */
    public const NOMBRES_MESES = [
        'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
        'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE',
    ];

    /**
     * @return list<int>  Día del mes, o [-1] si es semanal.
     */
    public function parseDiasPago(string $fecha): array
    {
        $f = strtolower(trim($fecha));
        if ($f === '') {
            return [];
        }

        if (preg_match('/^(\d+)\s+de\s+cada/i', $f, $m)) {
            return [(int) $m[1]];
        }

        if (preg_match('/^(\d+)\s*-\s*(\d+)\s+de\s+cada/i', $f, $m)) {
            return [(int) $m[1]];
        }

        if (preg_match('/^(\d+)\s+al\s+(\d+)/i', $f, $m)) {
            return [(int) $m[1]];
        }

        if (preg_match('/^(\d+)\s*-\s*(\d+)/i', $f, $m)) {
            return [(int) $m[1]];
        }

        if (preg_match('/^(\d+)$/', $f, $m)) {
            return [(int) $m[1]];
        }

        if (preg_match('/^1ero/i', $f)) {
            return [1];
        }

        if (preg_match('/^(\d+)\s+d\/c/i', $f, $m)) {
            return [(int) $m[1]];
        }

        if (preg_match('/sabado|viernes|lunes/i', $f)) {
            return [-1];
        }

        return [];
    }

    /**
     * Periodos del año que siguen pendientes (el gasto no sale de la lista hasta pagarse).
     *
     * @param  iterable<GastoFijoPago>  $pagosDelAnio
     * @return list<array{mes_idx: int, tipo: string, dia: int|null, urgente: bool, mes_nombre: string}>
     */
    public function periodosPendientes(string $fecha, float $costo, iterable $pagosDelAnio, ?CarbonInterface $now = null): array
    {
        if ($costo <= 0 || trim($fecha) === '') {
            return [];
        }

        $now = $now ?? now();
        $mesActual = (int) $now->month;
        $diaActual = (int) $now->day;
        $diasPago = $this->parseDiasPago($fecha);
        if ($diasPago === []) {
            return [];
        }

        $isWeekly = in_array(-1, $diasPago, true);
        $pagos = collect($pagosDelAnio)->keyBy('mes_idx');
        $pendientes = [];
        $mesDesde = $isWeekly ? $mesActual - 1 : max(0, $mesActual - 2);

        for ($mesIdx = $mesDesde; $mesIdx < $mesActual; $mesIdx++) {
            $pago = $pagos->get($mesIdx);
            if ($this->estaPagadoEnPeriodo($pago, $isWeekly, $now, $mesIdx === $mesActual - 1)) {
                continue;
            }

            if ($isWeekly) {
                if ($mesIdx !== $mesActual - 1) {
                    continue;
                }
                $pendientes[] = [
                    'mes_idx' => $mesIdx,
                    'tipo' => 'semanal',
                    'dia' => null,
                    'urgente' => false,
                    'mes_nombre' => self::NOMBRES_MESES[$mesIdx],
                ];
                continue;
            }

            $dia = $diasPago[0];
            $esMesActual = $mesIdx === $mesActual - 1;

            if ($esMesActual) {
                $diff = $dia - $diaActual;
                if ($diff > 7) {
                    continue;
                }
                if ($diff > 0) {
                    $tipo = 'proximo';
                    $urgente = $diff <= 2;
                } elseif ($diff === 0) {
                    $tipo = 'hoy';
                    $urgente = true;
                } else {
                    $tipo = 'vencido';
                    $urgente = true;
                }
            } else {
                $tipo = 'vencido';
                $urgente = true;
            }

            $pendientes[] = [
                'mes_idx' => $mesIdx,
                'tipo' => $tipo,
                'dia' => $dia,
                'urgente' => $urgente,
                'mes_nombre' => self::NOMBRES_MESES[$mesIdx],
            ];
        }

        return $pendientes;
    }

    private function estaPagadoEnPeriodo(mixed $pago, bool $isWeekly, CarbonInterface $now, bool $esMesActual): bool
    {
        if (! $pago instanceof GastoFijoPago || ! $pago->pagado) {
            return false;
        }

        if ($isWeekly && $esMesActual) {
            return $pago->pagado_at && $pago->pagado_at->diffInDays($now) < 7;
        }

        return (bool) $pago->pagado;
    }
}
