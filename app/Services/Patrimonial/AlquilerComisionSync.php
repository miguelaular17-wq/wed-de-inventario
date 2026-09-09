<?php

namespace App\Services\Patrimonial;

use App\Models\Patrimonial\Alquiler;
use App\Models\Patrimonial\AlquilerPago;
use App\Models\Patrimonial\PatTransaccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AlquilerComisionSync
{
    public function syncPago(Alquiler $alquiler, AlquilerPago $pago, ?string $fecha = null, float $comisionEsteAbono = 0): void
    {
        $contrato = round((float) ($alquiler->comision ?? 0), 2);
        if ($contrato <= 0) {
            return;
        }

        $ya = round((float) ($pago->comision_pagada ?? 0), 2);
        $restante = round(max(0, $contrato - $ya), 2);
        if ($restante <= 0) {
            return;
        }

        $cuotaPagada = $pago->estado === 'pagado' || (float) $pago->monto_pagado >= (float) $pago->monto;
        $pedido = round(max(0, $comisionEsteAbono), 2);

        $aRegistrar = $cuotaPagada
            ? $restante
            : min($pedido, $restante);

        if ($aRegistrar <= 0) {
            return;
        }

        $this->crearTransaccion($alquiler, $pago, $aRegistrar, $fecha);
        $pago->comision_pagada = round($ya + $aRegistrar, 2);
        if (Schema::hasColumn('pat_alquiler_pagos', 'comision_pagada')) {
            $pago->save();
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

    private function crearTransaccion(Alquiler $alquiler, AlquilerPago $pago, float $monto, ?string $fecha): void
    {
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

        PatTransaccion::create($payload);
    }
}
