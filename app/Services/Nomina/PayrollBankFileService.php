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

        $fecha = Carbon::parse($fechaPago ?? now());
        $lineas = [];

        foreach ($periodo->registros as $registro) {
            if ((int) ($registro->empleado?->empresa_id) !== (int) $empresa->id) {
                continue;
            }
            $usd = (float) $registro->total_pagar;
            if ($usd <= 0) {
                continue;
            }
            $cedula = $registro->empleado->cedula();
            if (preg_replace('/\D+/', '', $cedula) === '') {
                continue;
            }
            $bs = round($usd * $tasaBcv, 2);
            $lineas[] = self::formatearLinea($cedula, $bs, $fecha);
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

    /**
     * Formato banco: V + cédula (9 dígitos) + 2 espacios + monto Bs en céntimos (21 dígitos) + fecha ddmmyyyy.
     * Ejemplo: V031475493  00000000000000502151015082026
     */
    public static function formatearLinea(string $cedula, float $montoBs, Carbon|string $fecha): string
    {
        $digitos = preg_replace('/\D+/', '', $cedula) ?: '0';
        $digitos = substr($digitos, -9);
        $identificacion = 'V'.str_pad($digitos, 9, '0', STR_PAD_LEFT);

        $centimos = (int) round(round($montoBs, 2) * 100);
        if ($centimos < 0) {
            $centimos = 0;
        }
        $monto = str_pad((string) $centimos, 21, '0', STR_PAD_LEFT);

        $dia = Carbon::parse($fecha)->format('dmY');

        return $identificacion.'  '.$monto.$dia;
    }
}
