<?php

namespace App\Services\Patrimonial;

use App\Models\Patrimonial\Alquiler;
use App\Models\Patrimonial\AlquilerPago;
use App\Models\Patrimonial\PatTransaccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AlquilerComisionSync
{
    public function syncPago(Alquiler $alquiler, AlquilerPago $pago, ?string $fecha = null): void
    {
        $monto = round((float) ($alquiler->comision ?? 0), 2);
        $existente = $this->existente($pago);
        $cuotaPagada = $pago->estado === 'pagado' || (float) $pago->monto_pagado >= (float) $pago->monto;

        if ($monto <= 0 || ! $cuotaPagada) {
            $existente?->delete();

            return;
        }

        $fechaC = Carbon::parse($fecha ?: ($pago->fecha_pago?->toDateString() ?: now()->toDateString()));

        $payload = [
            'propiedad_id' => $alquiler->propiedad_id,
            'tipo' => 'comision',
            'categoria' => 'Comisión plataforma',
            'descripcion' => 'Comisión alquiler '.$pago->periodo.' — '.$alquiler->inquilino_nombre,
            'monto' => $monto,
            'moneda' => 'usd',
            'fecha' => $fechaC->toDateString(),
            'mes' => $fechaC->month,
            'anio' => $fechaC->year,
            'observaciones' => 'Comisión del canon (el inquilino paga el total)',
        ];
        if (Schema::hasColumn('pat_transacciones', 'alquiler_id')) {
            $payload['alquiler_id'] = $alquiler->id;
        }
        if (Schema::hasColumn('pat_transacciones', 'alquiler_pago_id')) {
            $payload['alquiler_pago_id'] = $pago->id;
        }

        if ($existente) {
            $existente->update($payload);
        } else {
            PatTransaccion::create($payload);
        }
    }

    public function deleteDeAlquiler(Alquiler $alquiler): void
    {
        if (Schema::hasColumn('pat_transacciones', 'alquiler_id')) {
            PatTransaccion::query()
                ->where('alquiler_id', $alquiler->id)
                ->where('tipo', 'comision')
                ->delete();

            return;
        }

        PatTransaccion::query()
            ->where('propiedad_id', $alquiler->propiedad_id)
            ->where('tipo', 'comision')
            ->where('descripcion', 'like', 'Comisión alquiler % — '.$alquiler->inquilino_nombre)
            ->delete();
    }

    private function existente(AlquilerPago $pago): ?PatTransaccion
    {
        if (Schema::hasColumn('pat_transacciones', 'alquiler_pago_id')) {
            return PatTransaccion::query()
                ->where('alquiler_pago_id', $pago->id)
                ->where('tipo', 'comision')
                ->first();
        }

        return PatTransaccion::query()
            ->where('propiedad_id', $pago->alquiler?->propiedad_id)
            ->where('tipo', 'comision')
            ->where('descripcion', 'Comisión alquiler '.$pago->periodo.' — '.($pago->alquiler?->inquilino_nombre ?? ''))
            ->first();
    }
}
