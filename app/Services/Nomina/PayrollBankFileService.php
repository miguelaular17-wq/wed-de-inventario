<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaPeriodo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PayrollBankFileService
{
    /**
     * @return Collection<int, object{empresa:?NominaEmpresa,empleados:int,usd:float}>
     */
    public function resumenPorEmpresa(NominaPeriodo $periodo): Collection
    {
        return $periodo->registros
            ->groupBy(fn ($r) => (string) ($r->empleado?->empresa_id ?: '0'))
            ->map(function (Collection $grupo) {
                $empleado = $grupo->first()->empleado;

                return (object) [
                    'empresa' => $empleado?->empresa,
                    'empleados' => $grupo->count(),
                    'usd' => round((float) $grupo->sum('total_pagar'), 2),
                ];
            })
            ->sortBy(fn ($fila) => $fila->empresa?->codigo ?? 'zzzz')
            ->values();
    }

    public function generar(NominaPeriodo $periodo, NominaEmpresa $empresa, float $tasaBcv, Carbon|string|null $fechaPago = null): string
    {
        if ($tasaBcv <= 0) {
            throw ValidationException::withMessages([
                'tasa_bcv' => 'No hay tasa BCV del día. Cárgala en Flujo de caja o reintenta más tarde.',
            ]);
        }

        $fecha = Carbon::parse($fechaPago ?? now())->format('d/m/Y');
        $lineas = [];

        foreach ($periodo->registros as $registro) {
            if ((int) ($registro->empleado?->empresa_id) !== (int) $empresa->id) {
                continue;
            }
            $usd = (float) $registro->total_pagar;
            if ($usd <= 0) {
                continue;
            }
            $cedula = preg_replace('/\D+/', '', $registro->empleado->cedula()) ?: '';
            if ($cedula === '') {
                continue;
            }
            $bs = number_format(round($usd * $tasaBcv, 2), 2, '.', '');
            $lineas[] = $cedula.';'.$bs.';'.$fecha;
        }

        if ($lineas === []) {
            throw ValidationException::withMessages([
                'empresa' => 'Esa empresa no tiene montos a pagar en esta quincena.',
            ]);
        }

        return implode("\r\n", $lineas)."\r\n";
    }

    public function nombreArchivo(NominaPeriodo $periodo, NominaEmpresa $empresa, Carbon|string|null $fechaPago = null): string
    {
        $fecha = Carbon::parse($fechaPago ?? now())->format('Ymd');

        return 'nomina_'.$empresa->codigo.'_'.$periodo->id.'_'.$fecha.'.txt';
    }
}
