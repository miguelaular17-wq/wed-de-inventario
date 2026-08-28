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
    ) {
    }

    public function aplicarAdelantosYAsistencia(int $periodoId, Carbon $inicio, Carbon $fin): void
    {
        $this->advances->aplicarAPeriodo($periodoId, $inicio, $fin);
        $this->attendance->aplicarAPeriodo($periodoId, $inicio, $fin);
    }

    /**
     * @return \Illuminate\Support\Collection<int, NominaPrestamoCuota>
     */
    public function cuotasPendientesDelRango(Carbon $inicio, Carbon $fin)
    {
        return NominaPrestamoCuota::query()
            ->with(['prestamo.empleado.cliente'])
            ->whereNull('nomina_periodo_id')
            ->whereIn('estado', ['PENDIENTE', 'VENCIDA', 'PARCIAL'])
            ->whereBetween('fecha_programada', [$inicio->toDateString(), $fin->toDateString()])
            ->whereHas('prestamo', fn ($q) => $q->whereIn('estado', ['PENDIENTE', 'ACTIVO']))
            ->orderBy('prestamo_id')
            ->orderBy('numero')
            ->get();
    }

    /**
     * @param  iterable<NominaPrestamoCuota>  $cuotas
     * @return list<NominaPrestamoAbono>
     */
    public function aplicarCuotas(
        iterable $cuotas,
        int $periodoId,
        ?int $usuarioId = null,
        string $observacion = 'Descuento de nómina'
    ): array {
        $abonos = [];

        DB::transaction(function () use ($cuotas, $periodoId, $usuarioId, $observacion, &$abonos) {
            foreach ($cuotas as $cuota) {
                $cuota->refresh();
                if (! $cuota->puedeDescontarseEnNomina()) {
                    continue;
                }

                $prestamo = NominaPrestamo::query()->lockForUpdate()->find($cuota->prestamo_id);
                if (! $prestamo || in_array($prestamo->estado, ['PAGADO', 'CANCELADO'], true)) {
                    continue;
                }

                $abono = $this->payments->registrarAbono($prestamo, [
                    'monto' => $cuota->saldo(),
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
    }
}
