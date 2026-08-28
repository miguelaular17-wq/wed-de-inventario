<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaPrestamo;
use App\Models\Nomina\NominaPrestamoAbono;
use App\Models\Nomina\NominaPrestamoCuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanPaymentService
{
    public function registrarAbono(NominaPrestamo $prestamo, array $data, ?int $usuarioId = null): NominaPrestamoAbono
    {
        if (in_array($prestamo->estado, ['PAGADO', 'CANCELADO'], true)) {
            throw ValidationException::withMessages([
                'prestamo' => 'No se pueden registrar abonos en un préstamo cerrado.',
            ]);
        }

        $monto = round((float) $data['monto'], 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto del abono debe ser mayor a cero.',
            ]);
        }

        if ($monto - (float) $prestamo->saldo_pendiente > 0.009) {
            throw ValidationException::withMessages([
                'monto' => 'El abono no puede ser mayor al saldo pendiente.',
            ]);
        }

        $cuota = null;
        if (! empty($data['cuota_id'])) {
            $cuota = NominaPrestamoCuota::query()
                ->where('prestamo_id', $prestamo->id)
                ->where('id', $data['cuota_id'])
                ->first();

            if (! $cuota) {
                throw ValidationException::withMessages([
                    'cuota_id' => 'La cuota no pertenece a este préstamo.',
                ]);
            }

            if ($data['tipo'] === NominaPrestamoAbono::TIPO_NOMINA && ! $cuota->puedeDescontarseEnNomina()) {
                throw ValidationException::withMessages([
                    'cuota_id' => 'Esa cuota ya fue descontada en nómina o está pagada.',
                ]);
            }
        }

        return DB::transaction(function () use ($prestamo, $data, $monto, $cuota, $usuarioId) {
            $abono = NominaPrestamoAbono::create([
                'prestamo_id' => $prestamo->id,
                'cuota_id' => $cuota?->id,
                'fecha' => $data['fecha'] ?? now()->toDateString(),
                'monto' => $monto,
                'tipo' => $data['tipo'],
                'usuario_id' => $usuarioId,
                'observacion' => $data['observacion'] ?? null,
            ]);

            $this->aplicarACuotas($prestamo, $abono, $cuota);

            $prestamo->saldo_pendiente = round((float) $prestamo->saldo_pendiente - $monto, 2);
            if ($prestamo->saldo_pendiente <= 0) {
                $prestamo->saldo_pendiente = 0;
                $prestamo->estado = 'PAGADO';
            } elseif ($prestamo->estado === 'PENDIENTE') {
                $prestamo->estado = 'ACTIVO';
            }
            $prestamo->save();

            NominaAuditLog::registrar('PRESTAMO_ABONO', 'prestamo', $prestamo->id, null, [
                'abono_id' => $abono->id,
                'monto' => $monto,
                'tipo' => $abono->tipo,
                'cuota_id' => $cuota?->id,
            ]);

            return $abono;
        });
    }

    public function aplicarACuotas(NominaPrestamo $prestamo, NominaPrestamoAbono $abono, ?NominaPrestamoCuota $preferida = null): void
    {
        $restante = (float) $abono->monto;
        $cuotas = $prestamo->cuotas()
            ->whereIn('estado', ['PENDIENTE', 'VENCIDA', 'PARCIAL'])
            ->orderBy('numero')
            ->get();

        if ($preferida) {
            $cuotas = collect([$preferida])->concat($cuotas->where('id', '!=', $preferida->id))->values();
        }

        foreach ($cuotas as $cuota) {
            if ($restante <= 0) {
                break;
            }

            $saldoCuota = $cuota->saldo();
            $aplica = min($saldoCuota, $restante);
            $cuota->monto_pagado = round((float) $cuota->monto_pagado + $aplica, 2);
            $cuota->abono_id = $abono->id;

            if ($abono->tipo === NominaPrestamoAbono::TIPO_NOMINA && ! empty($abono->getAttribute('nomina_periodo_id'))) {
                $cuota->nomina_periodo_id = $abono->getAttribute('nomina_periodo_id');
            }

            if ($cuota->saldo() <= 0) {
                $cuota->estado = 'PAGADA';
                $cuota->fecha_pago = $abono->fecha;
            } else {
                $cuota->estado = 'PARCIAL';
            }

            $cuota->save();
            $restante = round($restante - $aplica, 2);
        }
    }

    public function revertirAbonosDelPeriodo(int $periodoId): void
    {
        $marcador = 'período #'.$periodoId;
        $abonos = NominaPrestamoAbono::query()
            ->where('tipo', NominaPrestamoAbono::TIPO_NOMINA)
            ->where('observacion', 'like', '%'.$marcador.'%')
            ->orderByDesc('id')
            ->get();

        $cuotasDelPeriodo = NominaPrestamoCuota::query()
            ->where('nomina_periodo_id', $periodoId)
            ->get();

        $abonoIds = $abonos->pluck('id')
            ->merge($cuotasDelPeriodo->pluck('abono_id'))
            ->filter()
            ->unique()
            ->values();

        foreach ($abonoIds as $abonoId) {
            $abono = NominaPrestamoAbono::query()->find($abonoId);
            if (! $abono) {
                continue;
            }
            $this->revertirAbono($abono, $periodoId);
        }

        NominaPrestamoCuota::query()
            ->where('nomina_periodo_id', $periodoId)
            ->update(['nomina_periodo_id' => null]);
    }

    private function revertirAbono(NominaPrestamoAbono $abono, int $periodoId): void
    {
        DB::transaction(function () use ($abono, $periodoId) {
            $prestamo = NominaPrestamo::query()->lockForUpdate()->findOrFail($abono->prestamo_id);
            $cuotas = NominaPrestamoCuota::query()
                ->where('abono_id', $abono->id)
                ->orderByDesc('numero')
                ->lockForUpdate()
                ->get();

            $restante = (float) $abono->monto;
            foreach ($cuotas as $cuota) {
                if ($restante <= 0) {
                    break;
                }
                $quita = min((float) $cuota->monto_pagado, $restante);
                $cuota->monto_pagado = round((float) $cuota->monto_pagado - $quita, 2);
                if ($cuota->monto_pagado <= 0) {
                    $cuota->monto_pagado = 0;
                    $cuota->fecha_pago = null;
                    $cuota->estado = $cuota->fecha_programada->lt(now()->startOfDay())
                        ? 'VENCIDA'
                        : 'PENDIENTE';
                } else {
                    $cuota->estado = 'PARCIAL';
                    $cuota->fecha_pago = null;
                }
                $cuota->abono_id = null;
                if ((int) $cuota->nomina_periodo_id === $periodoId) {
                    $cuota->nomina_periodo_id = null;
                }
                $cuota->save();
                $restante = round($restante - $quita, 2);
            }

            $prestamo->saldo_pendiente = round((float) $prestamo->saldo_pendiente + (float) $abono->monto, 2);
            if ($prestamo->estado === 'PAGADO' && $prestamo->saldo_pendiente > 0) {
                $prestamo->estado = 'ACTIVO';
            }
            $prestamo->save();

            NominaAuditLog::registrar('PRESTAMO_ABONO_REVERTIR', 'prestamo', $prestamo->id, [
                'abono_id' => $abono->id,
                'monto' => (float) $abono->monto,
            ], [
                'periodo_id' => $periodoId,
            ]);

            $abono->delete();
        });
    }
}
