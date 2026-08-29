<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaDeduccion;
use App\Models\Nomina\NominaDescuentoMercancia;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaHoraExtra;
use App\Models\Nomina\NominaInasistencia;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaPrestamoCuota;
use App\Models\Nomina\NominaPrestamoPlan;
use App\Models\Nomina\NominaRegistro;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollPeriodService
{
    public function __construct(
        private SalaryAdvanceService $quincenas,
        private PayrollDeductionService $deductions,
        private CommissionCalculationService $commissions,
        private CommissionSettlementService $settlements,
        private LoanDiscountPlanService $loanPlans,
    ) {
    }

    public function abrir(Carbon|string $fecha, ?int $usuarioId = null): NominaPeriodo
    {
        $quincena = $this->quincenas->quincenaDe($fecha);

        return DB::transaction(function () use ($quincena, $usuarioId) {
            $solapado = NominaPeriodo::query()
                ->whereDate('fecha_inicio', '<=', $quincena['fin']->toDateString())
                ->whereDate('fecha_fin', '>=', $quincena['inicio']->toDateString())
                ->exists();

            if ($solapado) {
                throw ValidationException::withMessages([
                    'fecha' => 'Ya existe un período para esa quincena.',
                ]);
            }

            $periodo = NominaPeriodo::create([
                'fecha_inicio' => $quincena['inicio']->toDateString(),
                'fecha_fin' => $quincena['fin']->toDateString(),
                'fecha_pago_comision' => $quincena['fin']->copy()->addDays(3)->toDateString(),
                'etiqueta' => $quincena['etiqueta'],
                'estado' => NominaPeriodo::ABIERTO,
            ]);

            NominaAuditLog::registrar('PERIODO_ABRIR', 'periodo', $periodo->id, null, [
                'etiqueta' => $periodo->etiqueta,
                'estado' => $periodo->estado,
                'usuario_id' => $usuarioId,
            ]);

            return $periodo;
        });
    }

    /**
     * @param  list<int>  $descontarEmpleadoIds  Atajo: descuenta todas las cuotas pendientes de esos empleados.
     * @param  list<array{cuota_id:int, monto?:float|null, destino?:string|null}>  $descuentosCuotas
     */
    public function calcular(
        NominaPeriodo $periodo,
        ?int $usuarioId = null,
        array $descontarEmpleadoIds = [],
        array $descuentosCuotas = [],
        bool $formularioPresente = false
    ): NominaPeriodo {
        $descontarEmpleadoIds = array_values(array_unique(array_map('intval', $descontarEmpleadoIds)));

        return DB::transaction(function () use ($periodo, $usuarioId, $descontarEmpleadoIds, $descuentosCuotas, $formularioPresente) {
            $periodo = NominaPeriodo::query()->lockForUpdate()->findOrFail($periodo->id);
            $this->exigirEstado($periodo, NominaPeriodo::ABIERTO, 'calcular');

            $empleados = NominaEmpleado::query()
                ->with(['cliente', 'sedeCatalogo', 'vendedores'])
                ->where('estado', 'ACTIVO')
                ->where(function ($query) use ($periodo) {
                    $query->whereNull('fecha_ingreso')
                        ->orWhereDate('fecha_ingreso', '<=', $periodo->fecha_fin->toDateString());
                })
                ->orderBy('id')
                ->get();

            if ($empleados->isEmpty()) {
                throw ValidationException::withMessages([
                    'periodo' => 'No hay empleados activos para calcular esta quincena.',
                ]);
            }

            $this->deductions->aplicarAdelantosYAsistencia(
                $periodo->id,
                $periodo->fecha_inicio,
                $periodo->fecha_fin
            );
            $this->commissions->limpiarPeriodo($periodo);
            $this->settlements->limpiarPeriodo($periodo);

            $planes = $this->loanPlans->paraCalcular($periodo);
            if ($descuentosCuotas === [] && $descontarEmpleadoIds === [] && ! $formularioPresente) {
                $descuentosCuotas = $planes;
            } else {
                $descuentosCuotas = $this->loanPlans->completarDestinos($descuentosCuotas, $planes);
            }

            $cuotasPendientes = $this->anexarCuotas(
                $this->deductions->cuotasPendientesDelRango($periodo->fecha_inicio, $periodo->fecha_fin),
                $descuentosCuotas
            );
            $porEmpleado = $this->resolverDescuentosPrestamo(
                $cuotasPendientes,
                $descontarEmpleadoIds,
                $descuentosCuotas
            );

            foreach ($empleados as $empleado) {
                $comisionable = $empleado->generaComision();
                $comision = $comisionable
                    ? $this->commissions->calcular($periodo, $empleado)
                    : [
                        'total' => 0.0,
                        'modo' => $empleado->modo_comision ?: NominaEmpleado::COMISION_NINGUNA,
                        'base' => 0.0,
                        'gastos' => 0.0,
                        'lineas' => 0,
                        'base_telefonia' => 0.0,
                        'base_otros' => 0.0,
                        'comision_telefonia' => 0.0,
                        'comision_otros' => 0.0,
                        'pct_telefonia' => 0.0,
                        'pct_otros' => 0.0,
                    ];
                $seleccion = $porEmpleado[$empleado->id] ?? ['cuotas' => collect(), 'montos' => [], 'destinos' => []];
                [$prestamosNomina, $prestamosComision] = $this->aplicarPrestamosSeleccion(
                    $empleado,
                    $seleccion,
                    $comision,
                    $periodo,
                    $usuarioId
                );

                $liquidacion = $comisionable
                    ? $this->settlements->liquidar($periodo, $empleado, $comision, $prestamosComision)
                    : null;
                $desglose = $this->desglose($periodo, $empleado);
                $desglose['comision'] = $comision;
                $desglose['liquidacion'] = $liquidacion ? [
                    'comision_total' => (float) $liquidacion->comision_total,
                    'abonos' => (float) $liquidacion->abonos,
                    'retencion' => (float) $liquidacion->retencion,
                    'descuentos' => (float) $liquidacion->descuentos,
                    'prestamos' => (float) $liquidacion->prestamos,
                    'total_pagar' => (float) $liquidacion->total_pagar,
                    'fecha_pago' => $liquidacion->fecha_pago?->toDateString(),
                ] : [
                    'comision_total' => 0.0,
                    'abonos' => 0.0,
                    'retencion' => 0.0,
                    'descuentos' => 0.0,
                    'prestamos' => 0.0,
                    'total_pagar' => 0.0,
                    'fecha_pago' => null,
                ];
                $desglose['prestamos'] = $prestamosNomina;
                $desglose['prestamos_comision'] = $prestamosComision;

                $salario = $this->salarioDelPeriodo($empleado);
                $otrosIngresos = $desglose['horas_extras'];
                $totalDeducciones = $desglose['abonos_sueldo']
                    + $desglose['inasistencias']
                    + $desglose['mercancia']
                    + $desglose['otras_deducciones']
                    + $prestamosNomina;
                $totalPagar = $salario + $otrosIngresos - $totalDeducciones;

                NominaRegistro::create([
                    'periodo_id' => $periodo->id,
                    'empleado_id' => $empleado->id,
                    'salario_base' => $salario,
                    'total_comisiones' => $liquidacion?->total_pagar ?? 0,
                    'total_bonificaciones' => 0,
                    'total_otros_ingresos' => $otrosIngresos,
                    'total_deducciones' => $totalDeducciones,
                    'total_ajustes' => 0,
                    'total_pagar' => $totalPagar,
                    'observaciones' => json_encode($desglose, JSON_UNESCAPED_UNICODE),
                ]);
            }

            $periodo->update([
                'estado' => NominaPeriodo::CALCULADO,
                'calculado_at' => now(),
                'calculado_por' => $usuarioId,
            ]);

            $this->auditarTransicion($periodo, NominaPeriodo::ABIERTO, NominaPeriodo::CALCULADO);

            return $periodo->fresh('registros');
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, NominaPrestamoCuota>  $cuotasPendientes
     * @param  list<int>  $descontarEmpleadoIds
     * @param  list<array{cuota_id:int, monto?:float|null, destino?:string|null}>  $descuentosCuotas
     * @return array<int, array{cuotas: \Illuminate\Support\Collection<int, NominaPrestamoCuota>, montos: array<int, float>, destinos: array<int, string|null>}>
     */
    private function resolverDescuentosPrestamo($cuotasPendientes, array $descontarEmpleadoIds, array $descuentosCuotas): array
    {
        $porId = $cuotasPendientes->keyBy('id');
        $resultado = [];

        $filas = array_values(array_filter($descuentosCuotas, function ($fila) {
            return is_array($fila) && (int) ($fila['cuota_id'] ?? 0) > 0;
        }));

        if ($filas !== []) {
            foreach ($filas as $fila) {
                $cuota = $porId->get((int) $fila['cuota_id']);
                if (! $cuota || ! $cuota->prestamo?->empleado) {
                    continue;
                }
                $empleadoId = (int) $cuota->prestamo->empleado_id;
                $saldo = $cuota->saldo();
                $monto = array_key_exists('monto', $fila) && $fila['monto'] !== null && $fila['monto'] !== ''
                    ? round((float) $fila['monto'], 2)
                    : $saldo;
                $monto = min(max($monto, 0), $saldo);
                if ($monto <= 0) {
                    continue;
                }
                if (! isset($resultado[$empleadoId])) {
                    $resultado[$empleadoId] = ['cuotas' => collect(), 'montos' => [], 'destinos' => []];
                }
                $resultado[$empleadoId]['cuotas']->push($cuota);
                $resultado[$empleadoId]['montos'][(int) $cuota->id] = $monto;
                $destino = $fila['destino'] ?? null;
                $resultado[$empleadoId]['destinos'][(int) $cuota->id] = in_array($destino, [
                    NominaPrestamoPlan::DESTINO_NOMINA,
                    NominaPrestamoPlan::DESTINO_COMISION,
                ], true) ? $destino : null;
            }

            return $resultado;
        }

        $cuotasPorEmpleado = $cuotasPendientes->groupBy(
            fn (NominaPrestamoCuota $cuota) => (int) $cuota->prestamo->empleado_id
        );
        foreach ($descontarEmpleadoIds as $empleadoId) {
            $grupo = $cuotasPorEmpleado->get($empleadoId, collect());
            if ($grupo->isEmpty()) {
                continue;
            }
            $resultado[$empleadoId] = [
                'cuotas' => $grupo->values(),
                'montos' => $grupo->mapWithKeys(fn (NominaPrestamoCuota $cuota) => [(int) $cuota->id => $cuota->saldo()])->all(),
                'destinos' => [],
            ];
        }

        return $resultado;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, NominaPrestamoCuota>  $cuotas
     * @param  list<array{cuota_id:int}>  $descuentosCuotas
     * @return \Illuminate\Support\Collection<int, NominaPrestamoCuota>
     */
    private function anexarCuotas($cuotas, array $descuentosCuotas)
    {
        $ids = collect($descuentosCuotas)
            ->map(fn ($fila) => (int) ($fila['cuota_id'] ?? 0))
            ->filter()
            ->unique()
            ->values();
        $faltan = $ids->diff($cuotas->pluck('id'));
        if ($faltan->isEmpty()) {
            return $cuotas;
        }

        $extra = NominaPrestamoCuota::query()
            ->with(['prestamo.empleado.cliente'])
            ->whereIn('id', $faltan->all())
            ->get();

        return $cuotas->concat($extra)->unique('id')->values();
    }

    /**
     * @param  array{cuotas: \Illuminate\Support\Collection<int, NominaPrestamoCuota>, montos: array<int, float>, destinos: array<int, string|null>}  $seleccion
     * @param  array<string, mixed>  $comision
     * @return array{0:float,1:float}
     */
    private function aplicarPrestamosSeleccion(
        NominaEmpleado $empleado,
        array $seleccion,
        array $comision,
        NominaPeriodo $periodo,
        ?int $usuarioId
    ): array {
        $cuotasNomina = collect();
        $cuotasComision = collect();
        $montosNomina = [];
        $montosComision = [];

        foreach ($seleccion['cuotas'] as $cuota) {
            $id = (int) $cuota->id;
            $destino = $seleccion['destinos'][$id]
                ?? $this->destinoAutomatico($empleado, $comision);
            if ($destino === NominaPrestamoPlan::DESTINO_COMISION && $empleado->generaComision()) {
                $cuotasComision->push($cuota);
                $montosComision[$id] = $seleccion['montos'][$id] ?? $cuota->saldo();
            } else {
                $cuotasNomina->push($cuota);
                $montosNomina[$id] = $seleccion['montos'][$id] ?? $cuota->saldo();
            }
        }

        $prestamosNomina = 0.0;
        $prestamosComision = 0.0;
        $aplicadas = [];

        if ($cuotasNomina->isNotEmpty()) {
            $abonos = $this->deductions->aplicarCuotas(
                $cuotasNomina,
                $periodo->id,
                $usuarioId,
                'Descuento de nómina',
                $montosNomina
            );
            $prestamosNomina = round(collect($abonos)->sum('monto'), 2);
            $aplicadas = array_merge($aplicadas, $cuotasNomina->pluck('id')->all());
        }

        if ($cuotasComision->isNotEmpty()) {
            $abonos = $this->deductions->aplicarCuotas(
                $cuotasComision,
                $periodo->id,
                $usuarioId,
                'Descuento de comisión',
                $montosComision
            );
            $prestamosComision = round(collect($abonos)->sum('monto'), 2);
            $this->registrarDescuentoPrestamoComision($periodo, $empleado, $prestamosComision, $usuarioId);
            $aplicadas = array_merge($aplicadas, $cuotasComision->pluck('id')->all());
        }

        $this->loanPlans->marcarAplicados($periodo, array_map('intval', $aplicadas));

        return [$prestamosNomina, $prestamosComision];
    }

    /**
     * @param  array<string, mixed>  $comision
     */
    private function destinoAutomatico(NominaEmpleado $empleado, array $comision): string
    {
        if ($empleado->generaComision() && (float) ($comision['total'] ?? 0) > 0) {
            return NominaPrestamoPlan::DESTINO_COMISION;
        }

        return NominaPrestamoPlan::DESTINO_NOMINA;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{empleado: NominaEmpleado, cuotas: \Illuminate\Support\Collection<int, NominaPrestamoCuota>, total: float}>
     */
    public function empleadosConCuotasPendientes(NominaPeriodo $periodo)
    {
        $cuotas = $this->anexarCuotas(
            $this->deductions->cuotasPendientesDelRango($periodo->fecha_inicio, $periodo->fecha_fin),
            $this->loanPlans->paraCalcular($periodo)
        );

        return $cuotas
            ->filter(fn (NominaPrestamoCuota $cuota) => $cuota->prestamo?->empleado)
            ->groupBy(fn (NominaPrestamoCuota $cuota) => (int) $cuota->prestamo->empleado_id)
            ->map(function ($grupo) {
                return [
                    'empleado' => $grupo->first()->prestamo->empleado,
                    'cuotas' => $grupo->values(),
                    'total' => round($grupo->sum(fn (NominaPrestamoCuota $cuota) => $cuota->saldo()), 2),
                ];
            })
            ->sortBy(fn (array $fila) => mb_strtolower($fila['empleado']->nombre()))
            ->values();
    }

    public function revertirCalculo(NominaPeriodo $periodo, ?int $usuarioId = null): NominaPeriodo
    {
        return DB::transaction(function () use ($periodo, $usuarioId) {
            $periodo = NominaPeriodo::query()->lockForUpdate()->findOrFail($periodo->id);

            if (! in_array($periodo->estado, [NominaPeriodo::CALCULADO, NominaPeriodo::APROBADO], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo se puede deshacer un cálculo en estado CALCULADO o APROBADO. Este período está '.$periodo->estado.'.',
                ]);
            }

            $desde = $periodo->estado;

            $this->deductions->deshacerPeriodo($periodo->id);
            $this->loanPlans->deshacerPeriodo($periodo->id);
            $this->commissions->limpiarPeriodo($periodo);
            $this->settlements->limpiarPeriodo($periodo);
            NominaRegistro::query()->where('periodo_id', $periodo->id)->delete();

            $periodo->update([
                'estado' => NominaPeriodo::ABIERTO,
                'calculado_at' => null,
                'calculado_por' => null,
                'aprobado_at' => null,
                'aprobado_por' => null,
            ]);

            NominaAuditLog::registrar('PERIODO_REVERTIR_CALCULO', 'periodo', $periodo->id, [
                'estado' => $desde,
                'usuario_id' => $usuarioId,
            ], [
                'estado' => NominaPeriodo::ABIERTO,
            ]);

            return $periodo->fresh();
        });
    }

    public function aprobar(NominaPeriodo $periodo, ?int $usuarioId = null): NominaPeriodo
    {
        if (! $periodo->registros()->exists()) {
            throw ValidationException::withMessages([
                'periodo' => 'El período no contiene recibos calculados.',
            ]);
        }

        return $this->transicionar(
            $periodo,
            NominaPeriodo::CALCULADO,
            NominaPeriodo::APROBADO,
            'aprobado_at',
            'aprobado_por',
            $usuarioId
        );
    }

    public function pagar(NominaPeriodo $periodo, ?int $usuarioId = null): NominaPeriodo
    {
        return $this->transicionar(
            $periodo,
            NominaPeriodo::APROBADO,
            NominaPeriodo::PAGADO,
            'pagado_at',
            'pagado_por',
            $usuarioId
        );
    }

    public function cerrar(NominaPeriodo $periodo, ?int $usuarioId = null): NominaPeriodo
    {
        return $this->transicionar(
            $periodo,
            NominaPeriodo::PAGADO,
            NominaPeriodo::CERRADO,
            'cerrado_at',
            'cerrado_por',
            $usuarioId
        );
    }

    /**
     * @return array{horas_extras:float,inasistencias:float,abonos_sueldo:float,mercancia:float,prestamos:float}
     */
    private function desglose(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $prestamos = NominaPrestamoCuota::query()
            ->where('nomina_periodo_id', $periodo->id)
            ->whereHas('prestamo', fn ($query) => $query->where('empleado_id', $empleado->id))
            ->with('abono')
            ->get()
            ->sum(fn (NominaPrestamoCuota $cuota) => (float) ($cuota->abono?->monto ?? 0));

        return [
            'horas_extras' => round((float) NominaHoraExtra::query()
                ->where('empleado_id', $empleado->id)
                ->where('nomina_periodo_id', $periodo->id)
                ->sum('monto'), 2),
            'inasistencias' => round((float) NominaInasistencia::query()
                ->where('empleado_id', $empleado->id)
                ->where('nomina_periodo_id', $periodo->id)
                ->sum('monto'), 2),
            'abonos_sueldo' => round((float) NominaAbonoSueldo::query()
                ->where('empleado_id', $empleado->id)
                ->where('nomina_periodo_id', $periodo->id)
                ->sum('monto'), 2),
            'mercancia' => round((float) NominaDescuentoMercancia::query()
                ->where('empleado_id', $empleado->id)
                ->where('nomina_periodo_id', $periodo->id)
                ->sum('monto'), 2),
            'otras_deducciones' => Schema::hasTable('nomina_deducciones')
                ? round((float) NominaDeduccion::query()
                    ->where('empleado_id', $empleado->id)
                    ->where('nomina_periodo_id', $periodo->id)
                    ->sum('monto'), 2)
                : 0.0,
            'prestamos' => round((float) $prestamos, 2),
        ];
    }

    private function salarioDelPeriodo(NominaEmpleado $empleado): float
    {
        $salario = (float) $empleado->salario_base;

        return match ($empleado->tipo_salario) {
            'MENSUAL' => round($salario / 2, 2),
            'SOLO_COMISION' => 0.0,
            default => round($salario, 2),
        };
    }

    private function transicionar(
        NominaPeriodo $periodo,
        string $desde,
        string $hacia,
        string $fechaCampo,
        string $usuarioCampo,
        ?int $usuarioId
    ): NominaPeriodo {
        return DB::transaction(function () use (
            $periodo,
            $desde,
            $hacia,
            $fechaCampo,
            $usuarioCampo,
            $usuarioId
        ) {
            $periodo = NominaPeriodo::query()->lockForUpdate()->findOrFail($periodo->id);
            $this->exigirEstado($periodo, $desde, strtolower($hacia));

            $periodo->update([
                'estado' => $hacia,
                $fechaCampo => now(),
                $usuarioCampo => $usuarioId,
            ]);

            $this->auditarTransicion($periodo, $desde, $hacia);

            return $periodo->fresh();
        });
    }

    private function exigirEstado(NominaPeriodo $periodo, string $esperado, string $accion): void
    {
        if ($periodo->estado !== $esperado) {
            throw ValidationException::withMessages([
                'estado' => "No se puede {$accion} un período en estado {$periodo->estado}.",
            ]);
        }
    }

    private function registrarDescuentoPrestamoComision(
        NominaPeriodo $periodo,
        NominaEmpleado $empleado,
        float $monto,
        ?int $usuarioId
    ): void {
        if ($monto <= 0 || ! Schema::hasTable('nomina_comision_descuentos')) {
            return;
        }

        NominaComisionDescuento::create([
            'empleado_id' => $empleado->id,
            'fecha' => $periodo->fecha_fin->toDateString(),
            'tipo' => 'PRESTAMO',
            'monto' => $monto,
            'motivo' => 'Cuota de préstamo descontada de la comisión',
            'estado' => 'APLICADO',
            'periodo_id' => $periodo->id,
            'created_by' => $usuarioId,
        ]);
    }

    private function auditarTransicion(NominaPeriodo $periodo, string $desde, string $hacia): void
    {
        NominaAuditLog::registrar('PERIODO_'.$hacia, 'periodo', $periodo->id, [
            'estado' => $desde,
        ], [
            'estado' => $hacia,
        ]);
    }
}
