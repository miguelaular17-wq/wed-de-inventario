<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PayrollBankFileService
{
    /**
     * @return Collection<int, object{empresa:?NominaEmpresa,empleados:int,usd:float,personas:Collection<int, object{id:int,nombre:string,cedula:string}>}>
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
                    'personas' => $this->personasDelGrupo($grupo->map(fn ($r) => $r->empleado)->filter()),
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

    /**
     * @return Collection<int, object{empresa:?NominaEmpresa,empleados:int,usd:float,personas:Collection<int, object{id:int,nombre:string,cedula:string}>}>
     */
    public function resumenComisionesPorEmpresa(NominaPeriodo $periodo): Collection
    {
        $liquidaciones = $periodo->relationLoaded('liquidacionesComision')
            ? $periodo->liquidacionesComision
            : NominaLiquidacionComision::query()
                ->where('periodo_id', $periodo->id)
                ->with(['empleado.empresa', 'empleado.cliente'])
                ->get();

        return $liquidaciones
            ->filter(fn ($liq) => $liq->empleado && $liq->empleado->generaComision() && $liq->empleado->isActivo())
            ->groupBy(fn ($liq) => (string) ($liq->empleado->empresa_id ?: '0'))
            ->map(function (Collection $grupo) {
                $empleado = $grupo->first()->empleado;

                return (object) [
                    'empresa' => $empleado?->empresa,
                    'empleados' => $grupo->count(),
                    'usd' => round((float) $grupo->sum('total_pagar'), 2),
                    'personas' => $this->personasDelGrupo($grupo->map(fn ($liq) => $liq->empleado)->filter()),
                ];
            })
            ->sortBy(fn ($fila) => $fila->empresa?->codigo ?? 'zzzz')
            ->values();
    }

    /**
     * @param  Collection<int, \App\Models\Nomina\NominaEmpleado>  $empleados
     * @return Collection<int, object{id:int,nombre:string,cedula:string}>
     */
    private function personasDelGrupo(Collection $empleados): Collection
    {
        return $empleados
            ->unique(fn ($empleado) => (int) $empleado->id)
            ->map(fn ($empleado) => (object) [
                'id' => (int) $empleado->id,
                'nombre' => $empleado->nombre(),
                'cedula' => $empleado->cedula(),
            ])
            ->sortBy(fn ($persona) => mb_strtolower($persona->nombre))
            ->values();
    }

    public function generarComisiones(NominaPeriodo $periodo, NominaEmpresa $empresa, float $tasaBcv, Carbon|string|null $fechaPago = null): string
    {
        if ($tasaBcv <= 0) {
            throw ValidationException::withMessages([
                'tasa_bcv' => 'No hay tasa BCV del día. Cárgala en Flujo de caja o reintenta más tarde.',
            ]);
        }

        $fecha = Carbon::parse($fechaPago ?? $periodo->fecha_pago_comision ?? now());
        $liquidaciones = NominaLiquidacionComision::query()
            ->where('periodo_id', $periodo->id)
            ->visibles()
            ->with('empleado.cliente')
            ->get();

        $lineas = [];
        foreach ($liquidaciones as $liq) {
            if ((int) ($liq->empleado?->empresa_id) !== (int) $empresa->id) {
                continue;
            }
            $usd = (float) $liq->total_pagar;
            if ($usd <= 0) {
                continue;
            }
            $cedula = $liq->empleado->cedula();
            if (preg_replace('/\D+/', '', $cedula) === '') {
                continue;
            }
            $bs = round($usd * $tasaBcv, 2);
            $lineas[] = self::formatearLinea($cedula, $bs, $fecha);
        }

        if ($lineas === []) {
            throw ValidationException::withMessages([
                'empresa' => 'Esa empresa no tiene comisiones a pagar en esta quincena.',
            ]);
        }

        return implode("\r\n", $lineas)."\r\n";
    }

    public function nombreArchivo(NominaPeriodo $periodo, NominaEmpresa $empresa, Carbon|string|null $fechaPago = null): string
    {
        $fecha = Carbon::parse($fechaPago ?? now())->format('Ymd');

        return 'nomina_'.$empresa->codigo.'_'.$periodo->id.'_'.$fecha.'.txt';
    }

    public function nombreArchivoComisiones(NominaPeriodo $periodo, NominaEmpresa $empresa, Carbon|string|null $fechaPago = null): string
    {
        $fecha = Carbon::parse($fechaPago ?? $periodo->fecha_pago_comision ?? now())->format('Ymd');

        return 'comision_'.$empresa->codigo.'_'.$periodo->id.'_'.$fecha.'.txt';
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
