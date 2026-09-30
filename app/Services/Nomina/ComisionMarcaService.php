<?php

namespace App\Services\Nomina;

use App\Models\FlujoCaja;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ComisionMarcaService
{
    public function tasaDelDia(string $fecha): ?float
    {
        $tasas = FlujoCaja::query()
            ->where('tipo', 'egreso')
            ->where(function ($query) {
                $query->where('oculto', false)->orWhereNull('oculto');
            })
            ->where('tasa_cambio', '>', 0)
            ->whereDate('fecha', $fecha)
            ->pluck('tasa_cambio');

        return $this->promedio($tasas);
    }

    public function promedio(Collection $tasas): ?float
    {
        $validas = $tasas
            ->map(fn ($tasa) => (float) $tasa)
            ->filter(fn (float $tasa) => $tasa > 0)
            ->values();

        if ($validas->isEmpty()) {
            return null;
        }

        return round($validas->avg(), 4);
    }

    public function aUsd(float $montoBs, ?float $tasa): ?float
    {
        if ($tasa === null || $tasa <= 0) {
            return null;
        }

        return round($montoBs / $tasa, 2);
    }

    /**
     * @param  Collection<int, \App\Models\Nomina\NominaComisionMarca>  $registros
     * @return array{bs: float, usd: float, por_marca: array<string, array{bs: float, usd: float, n: int}>}
     */
    public function totales(Collection $registros): array
    {
        $porMarca = [];
        foreach (['SAMSUNG', 'HONOR'] as $marca) {
            $grupo = $registros->where('marca', $marca);
            $porMarca[$marca] = [
                'bs' => round((float) $grupo->sum('monto_bs'), 2),
                'usd' => round((float) $grupo->sum('monto_usd'), 2),
                'n' => $grupo->count(),
            ];
        }

        return [
            'bs' => round((float) $registros->sum('monto_bs'), 2),
            'usd' => round((float) $registros->sum('monto_usd'), 2),
            'por_marca' => $porMarca,
        ];
    }

    public function etiquetaFecha(string $fecha): string
    {
        return Carbon::parse($fecha)->format('d/m/Y');
    }
}
