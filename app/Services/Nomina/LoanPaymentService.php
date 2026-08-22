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
}
