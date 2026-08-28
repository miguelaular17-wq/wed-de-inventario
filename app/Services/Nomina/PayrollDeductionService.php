<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaPrestamo;
use App\Models\Nomina\NominaPrestamoAbono;
use App\Models\Nomina\NominaPrestamoCuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollDeductionService
{
    public function __construct(
        private LoanPaymentService $payments,
        private SalaryAdvanceService $advances,
        private AttendanceService $attendance,
        private MerchandiseDeductionService $mercancia,
    ) {
    }

    public function aplicarAdelantosYAsistencia(int $periodoId, Carbon $inicio, Carbon $fin): void
    {
        $this->advances->aplicarAPeriodo($periodoId, $inicio, $fin);
        $this->attendance->aplicarAPeriodo($periodoId, $inicio, $fin);
        $this->mercancia->aplicarAPeriodo($periodoId, $inicio, $fin);
    }

    /**
     * @return \Illuminate\Support\Collection<int, NominaPrestamoCuota>
     */
    public function cuotasPendientesDelRango(Carbon $inicio, Carbon $fin)
    {
        $inicioStr = $inicio->toDateString();
        $finStr = $fin->toDateString();

        return NominaPrestamoCuota::query()
            ->with(['prestamo.empleado.cliente'])
            ->whereIn('estado', ['PENDIENTE', 'VENCIDA', 'PARCIAL'])
            ->where(function ($query) use ($inicioStr, $finStr) {
                $query->where(function ($rango) use ($inicioStr, $finStr) {
                    $rango->whereNull('nomina_periodo_id')
                        ->whereBetween('fecha_programada', [$inicioStr, $finStr]);
                })->orWhere(function ($resto) use ($finStr) {
                    $resto->where('estado', 'PARCIAL')
                        ->whereDate('fecha_programada', '<=', $finStr);
                });
            })
            ->whereHas('prestamo', fn ($q) => $q->whereIn('estado', ['PENDIENTE', 'ACTIVO']))
            ->orderBy('prestamo_id')
            ->orderBy('numero')
            ->get()
            ->filter(fn (NominaPrestamoCuota $cuota) => $cuota->saldo() > 0)
            ->values();
    }

    /**
     * @param  iterable<NominaPrestamoCuota>  $cuotas
     * @param  array<int, float>  $montosPorCuotaId  Si falta una cuota, se descuenta el saldo completo.
     * @return list<NominaPrestamoAbono>
     */
    public function aplicarCuotas(
        iterable $cuotas,
        int $periodoId,
        ?int $usuarioId = null,
        string $observacion = 'Descuento de nómina',
        array $montosPorCuotaId = [],
    ): array {
        $abonos = [];

        DB::transaction(function () use ($cuotas, $periodoId, $usuarioId, $observacion, $montosPorCuotaId, &$abonos) {
            foreach ($cuotas as $cuota) {
                $cuota->refresh();
                if ((int) $cuota->nomina_periodo_id === $periodoId) {
                    continue;
                }
                if (! $cuota->puedeDescontarseEnNomina()) {
                    continue;
                }

                $prestamo = NominaPrestamo::query()->lockForUpdate()->find($cuota->prestamo_id);
                if (! $prestamo || in_array($prestamo->estado, ['PAGADO', 'CANCELADO'], true)) {
                    continue;
                }

                $saldo = $cuota->saldo();
                $monto = array_key_exists((int) $cuota->id, $montosPorCuotaId)
                    ? round((float) $montosPorCuotaId[(int) $cuota->id], 2)
                    : $saldo;
                $monto = min($monto, $saldo);
                if ($monto <= 0) {
                    continue;
                }

                $abono = $this->payments->registrarAbono($prestamo, [
                    'monto' => $monto,
                    'tipo' => NominaPrestamoAbono::TIPO_NOMINA,
                    'fecha' => $cuota->fecha_programada->toDateString(),
                    'cuota_id' => $cuota->id,
                    'observacion' => $observacion.' período #'.$periodoId,
                ], $usuarioId);

                $cuota->refresh();
                $cuota->nomina_periodo_id = $periodoId;
                $cuota->save();

                $abonos[] = $abono;
            }
        });

        return $abonos;
    }

    /**
     * Aplica cuotas pendientes al período de nómina indicado.
     * Nunca descuenta una cuota que ya tenga nomina_periodo_id.
     *
     * @return list<NominaPrestamoAbono>
     */
    public function aplicarCuotasDelPeriodo(int $periodoId, Carbon $inicio, Carbon $fin, ?int $usuarioId = null): array
    {
        $abonos = $this->aplicarCuotas(
            $this->cuotasPendientesDelRango($inicio, $fin),
            $periodoId,
            $usuarioId
        );
        $this->aplicarAdelantosYAsistencia($periodoId, $inicio, $fin);

        return $abonos;
    }

    public function deshacerPeriodo(int $periodoId): void
    {
        $this->payments->revertirAbonosDelPeriodo($periodoId);
        $this->advances->deshacerPeriodo($periodoId);
        $this->attendance->deshacerPeriodo($periodoId);
        $this->mercancia->deshacerPeriodo($periodoId);
    }
}
