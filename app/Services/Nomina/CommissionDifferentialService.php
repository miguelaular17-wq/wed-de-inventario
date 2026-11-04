<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaComisionDiferencial;
use App\Models\Nomina\NominaComisionRegistro;
use App\Models\Nomina\NominaConfig;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Diferencial de comisiones: recalcula la comisión de personas seleccionadas con
 * porcentajes ajustados, sobre las mismas bases guardadas en la liquidación de la
 * quincena (no vuelve a leer ventas ni toca la liquidación original).
 */
class CommissionDifferentialService
{
    /** @var array<string, array{0:string,1:float}> clave => [etiqueta, valor por defecto] */
    public const PORCENTAJES = [
        'comision_telefonia_pct' => ['Telefonía (%)', 0.20],
        'comision_otros_pct' => ['Resto de categorías (%)', 1],
        'comision_supervisor_pct' => ['Supervisor de sede (%)', 0.05],
        'comision_encargado_pct' => ['Encargado de tienda (%)', 0.0],
        'comision_marketing_pct' => ['Supervisor de equipo / Marketing (%)', 0.10],
        'comision_digital_pct' => ['Digital (%)', 0.30],
        'comision_nunes_pct' => ['Nunes (%)', 0.60],
        'comision_pcp_pct' => ['PCP (%)', 0.015],
        'comision_sambil_pct' => ['Sambil (%)', 0.20],
        'comision_colaborador_pct' => ['Colaborador (%)', 0.25],
        'comision_servicio_tecnico_pct' => ['Participación servicio técnico (%)', 50],
        'retencion_comision_pct' => ['Retención sobre comisión (%)', 10],
    ];

    /** Modos que pagan un porcentaje sobre una venta neta agregada (sede, tiendas o equipo). */
    private const PCT_MODO_AGREGADO = [
        NominaEmpleado::COMISION_SUPERVISOR_SEDE => 'comision_supervisor_pct',
        NominaEmpleado::COMISION_ENCARGADO => 'comision_encargado_pct',
        NominaEmpleado::COMISION_SUPERVISOR_EQUIPO => 'comision_marketing_pct',
        NominaEmpleado::COMISION_DIGITAL => 'comision_digital_pct',
        NominaEmpleado::COMISION_NUNES => 'comision_nunes_pct',
        NominaEmpleado::COMISION_PCP => 'comision_pcp_pct',
        NominaEmpleado::COMISION_SAMBIL => 'comision_sambil_pct',
        NominaEmpleado::COMISION_COLABORADOR => 'comision_colaborador_pct',
    ];

    /** @return array<string, float> */
    public function configActual(): array
    {
        $config = [];
        foreach (self::PORCENTAJES as $clave => [, $default]) {
            $config[$clave] = NominaConfig::getDecimal($clave, $default);
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array<string, float>
     */
    public function normalizarConfig(array $entrada): array
    {
        $actual = $this->configActual();
        $config = [];
        foreach (array_keys(self::PORCENTAJES) as $clave) {
            $valor = $entrada[$clave] ?? null;
            $config[$clave] = ($valor === null || $valor === '')
                ? $actual[$clave]
                : round((float) $valor, 4);
        }

        return $config;
    }

    /** @return Collection<int, NominaLiquidacionComision> */
    public function liquidaciones(NominaPeriodo $periodo): Collection
    {
        return NominaLiquidacionComision::query()
            ->where('periodo_id', $periodo->id)
            ->visibles()
            ->with(['empleado.cliente', 'empleado.empresa', 'empleado.sedeCatalogo', 'empleado.cargoCatalogo'])
            ->get()
            ->sortBy(fn ($liq) => mb_strtolower($liq->empleado->nombre()))
            ->values();
    }

    /**
     * Porcentajes con los que realmente se liquidó la quincena (pueden diferir de la configuración actual).
     *
     * @param  Collection<int, NominaLiquidacionComision>  $liquidaciones
     * @return array<string, list<float>>
     */
    public function porcentajesQuincena(Collection $liquidaciones): array
    {
        $usados = array_fill_keys(array_keys(self::PORCENTAJES), []);
        $pctAgregados = $liquidaciones->isEmpty()
            ? collect()
            : NominaComisionRegistro::query()
                ->where('periodo_id', $liquidaciones->first()->periodo_id)
                ->where('tipo_documento', 'AJUSTE')
                ->whereIn('empleado_id', $liquidaciones->pluck('empleado_id'))
                ->pluck('porcentaje', 'empleado_id');

        foreach ($liquidaciones as $liq) {
            $modo = (string) $liq->modo;
            if (isset(self::PCT_MODO_AGREGADO[$modo])) {
                if ($pctAgregados->has($liq->empleado_id)) {
                    $usados[self::PCT_MODO_AGREGADO[$modo]][] = round((float) $pctAgregados[$liq->empleado_id], 4);
                }
            } else {
                if ((float) $liq->base_telefonia != 0.0) {
                    $usados['comision_telefonia_pct'][] = round((float) $liq->pct_telefonia, 4);
                }
                if ((float) $liq->base_otros != 0.0) {
                    $usados['comision_otros_pct'][] = round((float) $liq->pct_otros, 4);
                }
                if ($liq->esServicioTecnico() && isset(($liq->snapshot ?? [])['pct_st'])) {
                    $usados['comision_servicio_tecnico_pct'][] = round((float) $liq->snapshot['pct_st'], 4);
                }
            }
            if ((float) $liq->retencion_pct > 0) {
                $usados['retencion_comision_pct'][] = round((float) $liq->retencion_pct, 4);
            }
        }

        return array_map(function (array $valores) {
            $unicos = array_values(array_unique($valores, SORT_REGULAR));
            sort($unicos);

            return $unicos;
        }, $usados);
    }

    /**
     * @param  array<string, float>  $config
     * @return array<string, mixed>
     */
    public function calcularLinea(NominaLiquidacionComision $liq, array $config, bool $noPagado): array
    {
        $modo = (string) $liq->modo;
        $comisionTelefonia = (float) $liq->comision_telefonia;
        $comisionOtros = (float) $liq->comision_otros;
        $comisionSt = 0.0;

        if (isset(self::PCT_MODO_AGREGADO[$modo])) {
            $pct = $config[self::PCT_MODO_AGREGADO[$modo]];
            $comision = round((float) $liq->base_total * $pct / 100, 2);
        } else {
            $comisionTelefonia = $this->recalcular(
                (float) $liq->base_telefonia,
                (float) $liq->pct_telefonia,
                $config['comision_telefonia_pct'],
                $comisionTelefonia
            );
            $comisionOtros = $this->recalcular(
                (float) $liq->base_otros,
                (float) $liq->pct_otros,
                $config['comision_otros_pct'],
                $comisionOtros
            );
            if ($liq->esServicioTecnico()) {
                $pctStOriginal = (float) (($liq->snapshot ?? [])['pct_st'] ?? $config['comision_servicio_tecnico_pct']);
                $comisionSt = $this->recalcular(
                    $liq->baseStNeta(),
                    $pctStOriginal,
                    $config['comision_servicio_tecnico_pct'],
                    $liq->comisionSt()
                );
            }
            $comision = round($comisionTelefonia + $comisionOtros + $comisionSt, 2);
        }

        $abonos = (float) $liq->abonos;
        $bruto = round($comision + $abonos, 2);
        if ($liq->empleado?->exentoRetencionComision()) {
            $retencionPct = 0.0;
            $retenible = 0.0;
        } elseif ($liq->esServicioTecnico()) {
            $retencionPct = $config['retencion_comision_pct'];
            $retenible = round($comisionTelefonia + $comisionOtros + $abonos, 2);
        } else {
            $retencionPct = $config['retencion_comision_pct'];
            $retenible = $bruto;
        }
        $retencion = round($retenible * $retencionPct / 100, 2);
        $descuentos = round((float) $liq->descuentos + (float) $liq->prestamos, 2);
        $totalAjustado = round($bruto - $retencion - $descuentos, 2);
        $totalOriginal = (float) $liq->total_pagar;
        $pagado = $noPagado ? 0.0 : max(0.0, $totalOriginal);

        return [
            'empleado_id' => (int) $liq->empleado_id,
            'modo' => $modo,
            'no_pagado' => $noPagado,
            'comision_original' => (float) $liq->comision_total,
            'comision_ajustada' => $comision,
            'abonos' => $abonos,
            'retencion_original' => (float) $liq->retencion,
            'retencion_ajustada' => $retencion,
            'descuentos' => $descuentos,
            'total_original' => $totalOriginal,
            'total_ajustado' => $totalAjustado,
            'pagado' => $pagado,
            'diferencia' => round(max(0.0, $totalAjustado) - $pagado, 2),
            'detalle' => [
                'liquidacion_id' => $liq->id,
                'base_total' => (float) $liq->base_total,
                'base_telefonia' => (float) $liq->base_telefonia,
                'base_otros' => (float) $liq->base_otros,
                'comision_telefonia' => $comisionTelefonia,
                'comision_otros' => $comisionOtros,
                'comision_st' => $comisionSt,
                'retencion_pct' => $retencionPct,
            ],
        ];
    }

    /**
     * @param  array<string, float>  $config
     * @param  array<int, bool>  $seleccion  empleado_id => no_pagado
     * @return Collection<int, array<string, mixed>>
     */
    public function calcularSeleccion(NominaPeriodo $periodo, array $config, array $seleccion): Collection
    {
        return $this->liquidaciones($periodo)
            ->filter(fn ($liq) => array_key_exists((int) $liq->empleado_id, $seleccion))
            ->map(fn ($liq) => $this->calcularLinea($liq, $config, $seleccion[(int) $liq->empleado_id]) + [
                'empleado' => $liq->empleado,
            ])
            ->values();
    }

    /**
     * @param  array<string, float>  $config
     * @param  array<int, bool>  $seleccion  empleado_id => no_pagado
     */
    public function crear(
        NominaPeriodo $periodo,
        string $motivo,
        string $fechaPago,
        float $tasaBcv,
        array $config,
        array $seleccion,
        ?int $userId
    ): NominaComisionDiferencial {
        $lineas = $this->calcularSeleccion($periodo, $config, $seleccion);
        if ($lineas->isEmpty()) {
            throw ValidationException::withMessages([
                'seleccion' => 'Selecciona al menos una persona con comisión en esta quincena.',
            ]);
        }

        return DB::transaction(function () use ($periodo, $motivo, $fechaPago, $tasaBcv, $config, $lineas, $userId) {
            $diferencial = NominaComisionDiferencial::create([
                'periodo_id' => $periodo->id,
                'motivo' => $motivo,
                'config' => $config,
                'fecha_pago' => $fechaPago,
                'tasa_bcv' => $tasaBcv,
                'created_by' => $userId,
            ]);

            foreach ($lineas as $linea) {
                unset($linea['empleado']);
                $diferencial->lineas()->create($linea);
            }

            return $diferencial;
        });
    }

    /**
     * @return Collection<int, object{empresa:?NominaEmpresa,empleados:int,usd:float,personas:Collection<int, object{id:int,nombre:string,cedula:string,usd:float}>}>
     */
    public function resumenPorEmpresa(NominaComisionDiferencial $diferencial): Collection
    {
        return $diferencial->lineas
            ->filter(fn ($linea) => $linea->empleado && (float) $linea->diferencia > 0)
            ->groupBy(fn ($linea) => (string) ($linea->empleado->empresa_id ?: '0'))
            ->map(fn (Collection $grupo) => (object) [
                'empresa' => $grupo->first()->empleado->empresa,
                'empleados' => $grupo->count(),
                'usd' => round((float) $grupo->sum('diferencia'), 2),
                'personas' => $grupo
                    ->map(fn ($linea) => (object) [
                        'id' => (int) $linea->empleado_id,
                        'nombre' => $linea->empleado->nombre(),
                        'cedula' => $linea->empleado->cedula(),
                        'usd' => round((float) $linea->diferencia, 2),
                    ])
                    ->sortBy(fn ($persona) => mb_strtolower($persona->nombre))
                    ->values(),
            ])
            ->sortBy(fn ($fila) => $fila->empresa?->codigo ?? 'zzzz')
            ->values();
    }

    public function generarTxt(NominaComisionDiferencial $diferencial, NominaEmpresa $empresa): string
    {
        $tasa = (float) $diferencial->tasa_bcv;
        if ($tasa <= 0) {
            throw ValidationException::withMessages([
                'tasa_bcv' => 'El diferencial no tiene tasa BCV. Indica la tasa al crearlo.',
            ]);
        }

        $lineas = [];
        foreach ($diferencial->lineas as $linea) {
            if ((int) ($linea->empleado?->empresa_id) !== (int) $empresa->id) {
                continue;
            }
            $usd = (float) $linea->diferencia;
            if ($usd <= 0) {
                continue;
            }
            $cedula = $linea->empleado->cedula();
            if (preg_replace('/\D+/', '', $cedula) === '') {
                continue;
            }
            $lineas[] = PayrollBankFileService::formatearLinea($cedula, round($usd * $tasa, 2), $diferencial->fecha_pago);
        }

        if ($lineas === []) {
            throw ValidationException::withMessages([
                'empresa' => 'Esa empresa no tiene diferencias a pagar en este diferencial.',
            ]);
        }

        return implode("\r\n", $lineas)."\r\n";
    }

    public function nombreArchivo(NominaComisionDiferencial $diferencial, NominaEmpresa $empresa): string
    {
        return 'comision_diferencial_'.$empresa->codigo.'_'.$diferencial->periodo_id.'_'.$diferencial->id
            .'_'.$diferencial->fecha_pago->format('Ymd').'.txt';
    }

    /** Si el porcentaje no cambió se conserva la comisión ya liquidada (evita diferencias por redondeo por línea). */
    private function recalcular(float $base, float $pctOriginal, float $pctNuevo, float $comisionOriginal): float
    {
        if (abs($pctNuevo - $pctOriginal) < 0.00001) {
            return round($comisionOriginal, 2);
        }

        return round($base * $pctNuevo / 100, 2);
    }
}
