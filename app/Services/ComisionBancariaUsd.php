<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ComisionBancariaUsd
{
    /**
     * @param  Collection<int, object>  $egresos
     * @return array<string, float>
     */
    public function tasasPorDia(Collection $egresos): array
    {
        $matcher = app(BankReconciliationMatcher::class);
        $cubetas = [];

        foreach ($egresos as $egreso) {
            $tasa = (float) ($egreso->tasa_cambio ?? 0);
            if ($tasa <= 0 || empty($egreso->fecha)) {
                continue;
            }
            $fecha = \Carbon\Carbon::parse($egreso->fecha)->toDateString();
            [$banco, $titular] = $matcher->partesCuenta($egreso->banco ?? '', $egreso->titular ?? '');
            $cubetas[$banco.'|'.$titular.'|'.$fecha][] = $tasa;
            $cubetas['*|'.$fecha][] = $tasa;
        }

        $tasas = [];
        foreach ($cubetas as $clave => $valores) {
            $tasas[$clave] = round(array_sum($valores) / count($valores), 4);
        }

        return $tasas;
    }

    /**
     * @param  Collection<int, object>  $lineas
     * @param  array<string, float>  $tasas
     * @return array{filas: Collection, total_usd: float}
     */
    public function convertir(Collection $lineas, array $tasas, string $banco, string $titular): array
    {
        $detalle = $lineas->map(function ($linea) use ($tasas, $banco, $titular) {
            $fecha = \Carbon\Carbon::parse($linea->fecha)->toDateString();
            $tasa = $tasas[$banco.'|'.$titular.'|'.$fecha] ?? $tasas['*|'.$fecha] ?? null;
            $monto = (float) $linea->monto;

            return [
                'fecha' => $linea->fecha,
                'descripcion' => $linea->descripcion,
                'referencia' => $linea->referencia,
                'monto' => $monto,
                'monto_usd' => ($tasa && $tasa > 0) ? round($monto / $tasa, 2) : null,
            ];
        });

        $filas = $detalle->groupBy('descripcion')->map(function ($grupo) {
            $primero = $grupo->first();
            $convertidos = $grupo->filter(fn (array $fila) => $fila['monto_usd'] !== null);

            return [
                'fecha' => $primero['fecha'],
                'descripcion' => $primero['descripcion'],
                'referencia' => $grupo->count() > 1 ? 'VARIAS ('.$grupo->count().')' : $primero['referencia'],
                'monto' => round($grupo->sum('monto'), 2),
                'monto_usd' => $convertidos->isEmpty() ? null : round($convertidos->sum('monto_usd'), 2),
            ];
        })->values();

        return [
            'filas' => $filas,
            'total_usd' => round((float) $filas->sum(fn (array $fila) => (float) ($fila['monto_usd'] ?? 0)), 2),
        ];
    }
}
