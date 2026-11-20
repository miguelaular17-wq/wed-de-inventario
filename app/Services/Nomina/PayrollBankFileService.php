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
     * @return Collection<int, object{empresa:?NominaEmpresa,empleados:int,usd:float,personas:Collection<int, object{id:int,nombre:string,cedula:string,usd:float}>}>
     */
    public function resumenPorEmpresa(NominaPeriodo $periodo): Collection
    {
        return $periodo->registros
            ->groupBy(fn ($r) => (string) ($r->empleado?->empresa_id ?: '0'))
            ->map(function (Collection $grupo) {
                $empleado = $grupo->first()->empleado;
                $porEmpleado = $grupo
                    ->groupBy(fn ($r) => (string) (int) ($r->empleado_id ?: 0))
                    ->map(fn (Collection $regs) => round((float) $regs->sum('total_pagar'), 2));

                return (object) [
                    'empresa' => $empleado?->empresa,
                    'empleados' => $grupo->count(),
                    'usd' => round((float) $grupo->sum('total_pagar'), 2),
                    'personas' => $this->personasDelGrupo(
                        $grupo->map(fn ($r) => $r->empleado)->filter(),
                        $porEmpleado
                    ),
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
     * Personas con nómina > 0 para armar el TXT (incluye sede/cargo para filtros).
     *
     * @return Collection<int, object{id:int,nombre:string,cedula:string,usd:float,sede:string,cargo:string,empresa_id:int,empresa:string,descargable:bool}>
     */
    public function personasNominaTxt(NominaPeriodo $periodo): Collection
    {
        return $this->resumenPorEmpresa($periodo)
            ->flatMap(function ($fila) {
                $empresa = $fila->empresa;
                $empresaId = (int) ($empresa?->id ?: ($fila->personas?->first()?->empresa_id ?? 0));

                return ($fila->personas ?? collect())->map(function ($persona) use ($empresa, $empresaId) {
                    $cedulaOk = preg_replace('/\D+/', '', (string) $persona->cedula) !== '';

                    return (object) [
                        'id' => (int) $persona->id,
                        'nombre' => (string) $persona->nombre,
                        'cedula' => (string) $persona->cedula,
                        'usd' => round((float) $persona->usd, 2),
                        'sede' => (string) ($persona->sede ?? '—'),
                        'cargo' => (string) ($persona->cargo ?? '—'),
                        'empresa_id' => (int) ($persona->empresa_id ?? $empresaId),
                        'empresa' => $empresa ? ($empresa->codigo.' — '.$empresa->nombre) : '',
                        // Sin empresa → pago manual (Finanzas), no TXT bancario.
                        'descargable' => $cedulaOk && (float) $persona->usd > 0 && $empresaId > 0,
                    ];
                });
            })
            ->filter(fn ($p) => $p->usd > 0)
            ->sortBy(fn ($p) => mb_strtolower($p->nombre))
            ->values();
    }

    /**
     * Un solo TXT de nómina con las personas seleccionadas (cualquier empresa).
     *
     * @param  list<int>  $empleadoIds
     */
    public function generarSeleccion(
        NominaPeriodo $periodo,
        float $tasaBcv,
        array $empleadoIds,
        Carbon|string|null $fechaPago = null
    ): string {
        if ($tasaBcv <= 0) {
            throw ValidationException::withMessages([
                'tasa_bcv' => 'No hay tasa BCV del día. Cárgala en Flujo de caja o reintenta más tarde.',
            ]);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $empleadoIds))));
        if ($ids === []) {
            throw ValidationException::withMessages([
                'empleados' => 'Selecciona al menos una persona para el TXT.',
            ]);
        }

        $fecha = Carbon::parse($fechaPago ?? now());
        $registros = $periodo->relationLoaded('registros')
            ? $periodo->registros
            : $periodo->registros()->with(['empleado.cliente'])->get();

        $porEmpleado = $registros
            ->whereIn('empleado_id', $ids)
            ->groupBy(fn ($r) => (int) $r->empleado_id)
            ->map(fn (Collection $regs) => round((float) $regs->sum('total_pagar'), 2));

        $lineas = [];
        foreach ($ids as $id) {
            $usd = (float) ($porEmpleado->get($id, 0));
            if ($usd <= 0) {
                continue;
            }
            $empleado = $registros->firstWhere('empleado_id', $id)?->empleado;
            if (! $empleado || ! $empleado->empresa_id) {
                continue;
            }
            $cedula = $empleado->cedula();
            if (preg_replace('/\D+/', '', $cedula) === '') {
                continue;
            }
            $lineas[] = self::formatearLinea($cedula, round($usd * $tasaBcv, 2), $fecha);
        }

        if ($lineas === []) {
            throw ValidationException::withMessages([
                'empleados' => 'Ninguna de las personas seleccionadas tiene monto descargable (empresa, cédula y nómina > 0).',
            ]);
        }

        return implode("\r\n", $lineas)."\r\n";
    }

    public function nombreArchivoSeleccion(NominaPeriodo $periodo, Carbon|string|null $fechaPago = null): string
    {
        $fecha = Carbon::parse($fechaPago ?? now())->format('Ymd');

        return 'nomina_todas_'.$periodo->id.'_'.$fecha.'.txt';
    }

    /**
     * @return Collection<int, object{empresa:?NominaEmpresa,empleados:int,usd:float,personas:Collection<int, object{id:int,nombre:string,cedula:string,usd:float}>}>
     */
    public function resumenComisionesPorEmpresa(NominaPeriodo $periodo): Collection
    {
        $liquidaciones = $periodo->relationLoaded('liquidacionesComision')
            ? $periodo->liquidacionesComision
            : NominaLiquidacionComision::query()
                ->where('periodo_id', $periodo->id)
                ->with(['empleado.empresa', 'empleado.cliente', 'empleado.sedeCatalogo', 'empleado.cargoCatalogo'])
                ->get();

        return $liquidaciones
            ->filter(fn ($liq) => $liq->empleado && $liq->empleado->generaComision() && $liq->empleado->isActivo())
            ->groupBy(fn ($liq) => (string) ($liq->empleado->empresa_id ?: '0'))
            ->map(function (Collection $grupo) {
                $empleado = $grupo->first()->empleado;
                $porEmpleado = $grupo
                    ->groupBy(fn ($liq) => (string) (int) ($liq->empleado_id ?: 0))
                    ->map(fn (Collection $liqs) => round((float) $liqs->sum('total_pagar'), 2));

                return (object) [
                    'empresa' => $empleado?->empresa,
                    'empleados' => $grupo->count(),
                    'usd' => round((float) $grupo->sum('total_pagar'), 2),
                    'personas' => $this->personasDelGrupo(
                        $grupo->map(fn ($liq) => $liq->empleado)->filter(),
                        $porEmpleado
                    ),
                ];
            })
            ->sortBy(fn ($fila) => $fila->empresa?->codigo ?? 'zzzz')
            ->values();
    }

    /**
     * @param  Collection<int, \App\Models\Nomina\NominaEmpleado>  $empleados
     * @param  Collection<string, float>  $montosPorEmpleado
     * @return Collection<int, object{id:int,nombre:string,cedula:string,usd:float,sede:string,cargo:string,empresa_id:int}>
     */
    private function personasDelGrupo(Collection $empleados, Collection $montosPorEmpleado): Collection
    {
        return $empleados
            ->unique(fn ($empleado) => (int) $empleado->id)
            ->map(fn ($empleado) => (object) [
                'id' => (int) $empleado->id,
                'nombre' => $empleado->nombre(),
                'cedula' => $empleado->cedula(),
                'usd' => round((float) ($montosPorEmpleado->get((string) (int) $empleado->id, 0)), 2),
                'sede' => $empleado->nombreSede(),
                'cargo' => $empleado->nombreCargo(),
                'empresa_id' => (int) ($empleado->empresa_id ?: 0),
            ])
            ->sortBy(fn ($persona) => mb_strtolower($persona->nombre))
            ->values();
    }

    /**
     * Personas con comisión > 0 para armar el TXT (incluye sede/cargo para filtros).
     *
     * @return Collection<int, object{id:int,nombre:string,cedula:string,usd:float,sede:string,cargo:string,empresa_id:int,empresa:?string,descargable:bool}>
     */
    public function personasComisionesTxt(NominaPeriodo $periodo): Collection
    {
        return $this->resumenComisionesPorEmpresa($periodo)
            ->flatMap(function ($fila) {
                $empresa = $fila->empresa;
                $empresaId = (int) ($empresa?->id ?: ($fila->personas?->first()?->empresa_id ?? 0));

                return ($fila->personas ?? collect())->map(function ($persona) use ($empresa, $empresaId) {
                    $cedulaOk = preg_replace('/\D+/', '', (string) $persona->cedula) !== '';

                    return (object) [
                        'id' => (int) $persona->id,
                        'nombre' => (string) $persona->nombre,
                        'cedula' => (string) $persona->cedula,
                        'usd' => round((float) $persona->usd, 2),
                        'sede' => (string) ($persona->sede ?? '—'),
                        'cargo' => (string) ($persona->cargo ?? '—'),
                        'empresa_id' => (int) ($persona->empresa_id ?? $empresaId),
                        'empresa' => $empresa ? ($empresa->codigo.' — '.$empresa->nombre) : '',
                        // Sin empresa → pago manual (Finanzas), no TXT bancario.
                        'descargable' => $cedulaOk && (float) $persona->usd > 0 && $empresaId > 0,
                    ];
                });
            })
            ->filter(fn ($p) => $p->usd > 0)
            ->sortBy(fn ($p) => mb_strtolower($p->nombre))
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

    /**
     * Un solo TXT con las personas seleccionadas (cualquier empresa).
     *
     * @param  list<int>  $empleadoIds
     */
    public function generarComisionesSeleccion(
        NominaPeriodo $periodo,
        float $tasaBcv,
        array $empleadoIds,
        Carbon|string|null $fechaPago = null
    ): string {
        if ($tasaBcv <= 0) {
            throw ValidationException::withMessages([
                'tasa_bcv' => 'No hay tasa BCV del día. Cárgala en Flujo de caja o reintenta más tarde.',
            ]);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $empleadoIds))));
        if ($ids === []) {
            throw ValidationException::withMessages([
                'empleados' => 'Selecciona al menos una persona para el TXT.',
            ]);
        }

        $fecha = Carbon::parse($fechaPago ?? $periodo->fecha_pago_comision ?? now());
        $liquidaciones = NominaLiquidacionComision::query()
            ->where('periodo_id', $periodo->id)
            ->whereIn('empleado_id', $ids)
            ->visibles()
            ->with(['empleado.cliente'])
            ->get()
            ->filter(fn ($liq) => $liq->empleado && $liq->empleado->generaComision() && $liq->empleado->isActivo());

        $porEmpleado = $liquidaciones
            ->groupBy(fn ($liq) => (int) $liq->empleado_id)
            ->map(fn (Collection $liqs) => round((float) $liqs->sum('total_pagar'), 2));

        $lineas = [];
        foreach ($ids as $id) {
            $usd = (float) ($porEmpleado->get($id, 0));
            if ($usd <= 0) {
                continue;
            }
            $empleado = $liquidaciones->firstWhere('empleado_id', $id)?->empleado;
            if (! $empleado || ! $empleado->empresa_id) {
                continue;
            }
            $cedula = $empleado->cedula();
            if (preg_replace('/\D+/', '', $cedula) === '') {
                continue;
            }
            $lineas[] = self::formatearLinea($cedula, round($usd * $tasaBcv, 2), $fecha);
        }

        if ($lineas === []) {
            throw ValidationException::withMessages([
                'empleados' => 'Ninguna de las personas seleccionadas tiene monto descargable (empresa, cédula y comisión > 0).',
            ]);
        }

        return implode("\r\n", $lineas)."\r\n";
    }

    public function nombreArchivoComisionesSeleccion(NominaPeriodo $periodo, Carbon|string|null $fechaPago = null): string
    {
        $fecha = Carbon::parse($fechaPago ?? $periodo->fecha_pago_comision ?? now())->format('Ymd');

        return 'comision_todas_'.$periodo->id.'_'.$fecha.'.txt';
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
