<?php

namespace App\Services\Patrimonial;

use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Reserva;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ReservaComisionSync
{
    public function sync(Reserva $reserva, ?string $fecha = null): void
    {
        $monto = round((float) ($reserva->comision ?? 0), 2);
        $fecha = $fecha ?: $this->fechaPreferida($reserva);
        $fechaC = Carbon::parse($fecha);

        $existente = $this->existente($reserva);

        if ($monto <= 0) {
            $existente?->delete();

            return;
        }

        $payload = [
            'propiedad_id' => $reserva->propiedad_id,
            'tipo' => 'comision',
            'categoria' => 'Comisión plataforma',
            'descripcion' => 'Comisión reserva — '.$reserva->cliente_nombre,
            'monto' => $monto,
            'moneda' => $reserva->moneda ?: 'usd',
            'fecha' => $fechaC->toDateString(),
            'mes' => $fechaC->month,
            'anio' => $fechaC->year,
            'observaciones' => 'Comisión de la reserva',
        ];
        if (Schema::hasColumn('pat_transacciones', 'reserva_id')) {
            $payload['reserva_id'] = $reserva->id;
        }

        if ($existente) {
            $existente->update($payload);
        } else {
            PatTransaccion::create($payload);
        }
    }

    public function delete(Reserva $reserva): void
    {
        $this->existente($reserva)?->delete();
    }

    public function asegurarDelMes(int $mes, int $anio): void
    {
        if (! Schema::hasTable('pat_reservas') || ! Schema::hasColumn('pat_reservas', 'comision')) {
            return;
        }

        $reservas = Reserva::query()->where('comision', '>', 0)->get();
        foreach ($reservas as $reserva) {
            $fecha = $this->fechaEnMes($reserva, $mes, $anio);
            if ($fecha) {
                $this->sync($reserva, $fecha);
            }
        }
    }

    private function fechaPreferida(Reserva $reserva): string
    {
        if (Schema::hasTable('pat_reserva_pagos')) {
            $pago = $reserva->pagos()->orderBy('fecha_pago')->first();
            if ($pago?->fecha_pago) {
                return Carbon::parse($pago->fecha_pago)->toDateString();
            }
        }

        return $reserva->fecha_entrada?->toDateString() ?: now()->toDateString();
    }

    private function fechaEnMes(Reserva $reserva, int $mes, int $anio): ?string
    {
        if (Schema::hasTable('pat_reserva_pagos')) {
            $pago = $reserva->pagos()
                ->whereMonth('fecha_pago', $mes)
                ->whereYear('fecha_pago', $anio)
                ->orderBy('fecha_pago')
                ->first();
            if ($pago?->fecha_pago) {
                return Carbon::parse($pago->fecha_pago)->toDateString();
            }
        }

        if ($reserva->fecha_entrada && (int) $reserva->fecha_entrada->month === $mes && (int) $reserva->fecha_entrada->year === $anio) {
            return $reserva->fecha_entrada->toDateString();
        }

        return null;
    }

    private function existente(Reserva $reserva): ?PatTransaccion
    {
        if (Schema::hasColumn('pat_transacciones', 'reserva_id')) {
            return PatTransaccion::query()
                ->where('reserva_id', $reserva->id)
                ->where('tipo', 'comision')
                ->first();
        }

        return PatTransaccion::query()
            ->where('propiedad_id', $reserva->propiedad_id)
            ->where('tipo', 'comision')
            ->where('descripcion', 'Comisión reserva — '.$reserva->cliente_nombre)
            ->first();
    }
}
