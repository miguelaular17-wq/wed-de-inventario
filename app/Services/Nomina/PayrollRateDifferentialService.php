<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaRegistro;
use App\Models\Nomina\NominaTasaDiferencial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Diferencial de nómina por tasa: los montos en USD de la quincena no cambian; se paga en Bs la diferencia
 * entre la tasa con que se pagó y la tasa nueva. Solo entra al TXT a quien aún no se le canceló la diferencia
 * (no_pagado = true).
 */
class PayrollRateDifferentialService
{
    /**
     * Personas que cobran en la quincena (neto en USD mayor a cero).
     *
     * @return Collection<int, object{empleado_id:int,total_usd:float,empleado:\App\Models\Nomina\NominaEmpleado}>
     */
    public function candidatos(NominaPeriodo $periodo): Collection
    {
        return NominaRegistro::query()
            ->where('periodo_id', $periodo->id)
            ->with(['empleado.cliente', 'empleado.empresa', 'empleado.sedeCatalogo', 'empleado.cargoCatalogo'])
            ->get()
            ->filter(fn ($registro) => $registro->empleado)
            ->groupBy('empleado_id')
            ->map(fn (Collection $registros) => (object) [
                'empleado_id' => (int) $registros->first()->empleado_id,
                'total_usd' => round((float) $registros->sum('total_pagar'), 2),
                'empleado' => $registros->first()->empleado,
            ])
            ->filter(fn ($candidato) => $candidato->total_usd > 0)
            ->sortBy(fn ($candidato) => mb_strtolower($candidato->empleado->nombre()))
            ->values();
    }

    /**
     * @param  array<int, bool>|null  $seleccion  empleado_id => no_pagado (diferencia sin cancelar); null = todos sin cancelar
     * @return Collection<int, array{empleado_id:int,no_pagado:bool,total_usd:float,bs_pagado:float,bs_nuevo:float,diferencia_bs:float,empleado:\App\Models\Nomina\NominaEmpleado}>
     */
    public function calcular(NominaPeriodo $periodo, float $tasaPagada, float $tasaNueva, ?array $seleccion = null): Collection
    {
        return $this->candidatos($periodo)
            ->filter(fn ($c) => $seleccion === null || array_key_exists($c->empleado_id, $seleccion))
            ->map(function ($c) use ($tasaPagada, $tasaNueva, $seleccion) {
                $noPagado = (bool) ($seleccion[$c->empleado_id] ?? true);
                $pagado = round($c->total_usd * $tasaPagada, 2);
                $nuevo = round($c->total_usd * $tasaNueva, 2);

                return [
                    'empleado_id' => $c->empleado_id,
                    'no_pagado' => $noPagado,
                    'total_usd' => $c->total_usd,
                    'bs_pagado' => $pagado,
                    'bs_nuevo' => $nuevo,
                    'diferencia_bs' => round($nuevo - $pagado, 2),
                    'empleado' => $c->empleado,
                ];
            })
            ->values();
    }

    /**
     * @param  array<int, bool>  $seleccion  empleado_id => no_pagado
     */
    public function crear(
        NominaPeriodo $periodo,
        string $motivo,
        string $fechaPago,
        float $tasaPagada,
        float $tasaNueva,
        array $seleccion,
        ?int $userId
    ): NominaTasaDiferencial {
        $this->validarTasas($tasaPagada, $tasaNueva);

        $lineas = $this->calcular($periodo, $tasaPagada, $tasaNueva, $seleccion);
        if ($lineas->isEmpty()) {
            throw ValidationException::withMessages([
                'seleccion' => 'Selecciona al menos una persona que cobre en esta quincena.',
            ]);
        }
        if (! $lineas->contains(fn ($linea) => $linea['no_pagado'])) {
            throw ValidationException::withMessages([
                'seleccion' => 'Todas las personas marcadas ya tienen la diferencia cancelada; no hay nada que pagar.',
            ]);
        }

        return DB::transaction(function () use ($periodo, $motivo, $fechaPago, $tasaPagada, $tasaNueva, $lineas, $userId) {
            $diferencial = NominaTasaDiferencial::create([
                'periodo_id' => $periodo->id,
                'motivo' => $motivo,
                'fecha_pago' => $fechaPago,
                'tasa_pagada' => $tasaPagada,
                'tasa_nueva' => $tasaNueva,
                'created_by' => $userId,
            ]);

            foreach ($lineas as $linea) {
                unset($linea['empleado']);
                $diferencial->lineas()->create($linea);
            }

            return $diferencial;
        });
    }

    public function validarTasas(float $tasaPagada, float $tasaNueva): void
    {
        if ($tasaNueva <= $tasaPagada) {
            throw ValidationException::withMessages([
                'tasa_nueva' => 'La tasa nueva debe ser mayor a la tasa con que se pagó la quincena.',
            ]);
        }
    }

    /**
     * @return Collection<int, object{empresa:?NominaEmpresa,empleados:int,usd:float,bs:float,personas:Collection<int, object{id:int,nombre:string,cedula:string,bs:float}>}>
     */
    public function resumenPorEmpresa(NominaTasaDiferencial $diferencial): Collection
    {
        return $diferencial->lineas
            ->filter(fn ($linea) => $linea->empleado && $linea->aPagarBs() > 0)
            ->groupBy(fn ($linea) => (string) ($linea->empleado->empresa_id ?: '0'))
            ->map(fn (Collection $grupo) => (object) [
                'empresa' => $grupo->first()->empleado->empresa,
                'empleados' => $grupo->count(),
                'usd' => round((float) $grupo->sum('total_usd'), 2),
                'bs' => round((float) $grupo->sum('diferencia_bs'), 2),
                'personas' => $grupo
                    ->map(fn ($linea) => (object) [
                        'id' => (int) $linea->empleado_id,
                        'nombre' => $linea->empleado->nombre(),
                        'cedula' => $linea->empleado->cedula(),
                        'bs' => round((float) $linea->diferencia_bs, 2),
                    ])
                    ->sortBy(fn ($persona) => mb_strtolower($persona->nombre))
                    ->values(),
            ])
            ->sortBy(fn ($fila) => $fila->empresa?->codigo ?? 'zzzz')
            ->values();
    }

    public function generarTxt(NominaTasaDiferencial $diferencial, NominaEmpresa $empresa): string
    {
        $lineas = [];
        foreach ($diferencial->lineas as $linea) {
            if ((int) ($linea->empleado?->empresa_id) !== (int) $empresa->id) {
                continue;
            }
            $bs = $linea->aPagarBs();
            if ($bs <= 0) {
                continue;
            }
            $cedula = $linea->empleado->cedula();
            if (preg_replace('/\D+/', '', $cedula) === '') {
                continue;
            }
            $lineas[] = PayrollBankFileService::formatearLinea($cedula, $bs, $diferencial->fecha_pago);
        }

        if ($lineas === []) {
            throw ValidationException::withMessages([
                'empresa' => 'Esa empresa no tiene diferencias a pagar en este diferencial.',
            ]);
        }

        return implode("\r\n", $lineas)."\r\n";
    }

    public function nombreArchivo(NominaTasaDiferencial $diferencial, NominaEmpresa $empresa): string
    {
        return 'nomina_diferencial_tasa_'.$empresa->codigo.'_'.$diferencial->periodo_id.'_'.$diferencial->id
            .'_'.$diferencial->fecha_pago->format('Ymd').'.txt';
    }
}
