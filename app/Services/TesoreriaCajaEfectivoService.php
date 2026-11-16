<?php

namespace App\Services;

use App\Models\CuadreCaja;
use App\Models\TesoreriaCaja;
use App\Models\TesoreriaCajaMovimiento;
use App\Models\TesoreriaCajaMovimientoDetalle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Al procesar/cerrar un cuadre, el efectivo contado (Bs y $) entra a Caja de Tesorería
 * como un ingreso diario (uno por moneda) con detalle por sede/caja.
 */
class TesoreriaCajaEfectivoService
{
    public function sincronizarDesdeCuadre(CuadreCaja $cuadre, User $user): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos') || ! Schema::hasTable('tesoreria_caja_movimiento_detalles')) {
            return;
        }

        $this->asegurarCajas();
        $cuadre->loadMissing('conteos');

        $montos = [
            'VES' => $this->montoConteo($cuadre, '01', false),
            'USD' => $this->montoConteo($cuadre, '02', true),
        ];

        DB::transaction(function () use ($cuadre, $user, $montos) {
            foreach ($montos as $moneda => $monto) {
                $this->upsertDetalle($cuadre, $moneda, $monto, $user);
            }
        });
    }

    public function quitarDesdeCuadre(CuadreCaja $cuadre): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimiento_detalles')) {
            return;
        }

        DB::transaction(function () use ($cuadre) {
            $detalles = TesoreriaCajaMovimientoDetalle::query()
                ->where('cuadre_caja_id', $cuadre->id)
                ->get();
            foreach ($detalles as $detalle) {
                $this->eliminarDetalle($detalle);
            }
        });
    }

    private function montoConteo(CuadreCaja $cuadre, string $codigoMetodo, bool $enDolares): float
    {
        $conteo = $cuadre->conteos->first(function ($c) use ($codigoMetodo, $enDolares) {
            return (string) $c->codigo_metodo === $codigoMetodo
                && (string) ($c->codigo_cuenta ?? '') === ''
                && (bool) $c->en_dolares === $enDolares;
        });

        return $conteo ? round((float) $conteo->monto, 2) : 0.0;
    }

    private function upsertDetalle(CuadreCaja $cuadre, string $moneda, float $monto, User $user): void
    {
        $caja = TesoreriaCaja::query()
            ->where('codigo', $moneda === 'USD' ? TesoreriaCaja::CODIGO_USD : TesoreriaCaja::CODIGO_BS)
            ->lockForUpdate()
            ->firstOrFail();

        $detalle = TesoreriaCajaMovimientoDetalle::query()
            ->where('cuadre_caja_id', $cuadre->id)
            ->where('moneda', $moneda)
            ->first();

        if ($monto < 0.01) {
            if ($detalle) {
                $this->eliminarDetalle($detalle);
            }

            return;
        }

        $fecha = $cuadre->fecha_cierre?->toDateString() ?? now()->toDateString();
        $padre = $this->padreDelDia($caja, $fecha, $user);

        if ($detalle) {
            $detalle->update([
                'movimiento_id' => $padre->id,
                'sede' => strtoupper(trim((string) $cuadre->sede)),
                'codigo_caja' => $cuadre->codigo_caja ?: $cuadre->descripcion_caja,
                'monto' => $monto,
            ]);
        } else {
            TesoreriaCajaMovimientoDetalle::query()->create([
                'movimiento_id' => $padre->id,
                'cuadre_caja_id' => $cuadre->id,
                'sede' => strtoupper(trim((string) $cuadre->sede)),
                'codigo_caja' => $cuadre->codigo_caja ?: $cuadre->descripcion_caja,
                'moneda' => $moneda,
                'monto' => $monto,
            ]);
        }

        $this->recalcularPadre($padre->fresh(), $caja);
    }

    private function eliminarDetalle(TesoreriaCajaMovimientoDetalle $detalle): void
    {
        $padre = TesoreriaCajaMovimiento::query()->lockForUpdate()->find($detalle->movimiento_id);
        $caja = $padre
            ? TesoreriaCaja::query()->lockForUpdate()->find($padre->caja_id)
            : null;
        $detalle->delete();
        if ($padre && $caja) {
            $this->recalcularPadre($padre->fresh(), $caja);
        }
    }

    private function padreDelDia(TesoreriaCaja $caja, string $fecha, User $user): TesoreriaCajaMovimiento
    {
        $ref = 'CUADRE_DIA:'.$fecha.':'.$caja->codigo;
        $padre = TesoreriaCajaMovimiento::query()
            ->where('caja_id', $caja->id)
            ->where('origen', TesoreriaCajaMovimiento::ORIGEN_CUADRE_DIARIO)
            ->whereDate('fecha', $fecha)
            ->lockForUpdate()
            ->first();

        if ($padre) {
            return $padre;
        }

        return TesoreriaCajaMovimiento::query()->create([
            'caja_id' => $caja->id,
            'tipo' => TesoreriaCajaMovimiento::INGRESO,
            'origen' => TesoreriaCajaMovimiento::ORIGEN_CUADRE_DIARIO,
            'fecha' => $fecha,
            'monto' => 0,
            'moneda_divisa' => $caja->esBolivares() ? null : TesoreriaCajaMovimiento::DIVISA_USD,
            'concepto' => 'Efectivo contado en cuadres del día',
            'referencia' => $ref,
            'sede' => null,
            'user_id' => $user->id,
        ]);
    }

    private function recalcularPadre(TesoreriaCajaMovimiento $padre, TesoreriaCaja $caja): void
    {
        $suma = round((float) TesoreriaCajaMovimientoDetalle::query()
            ->where('movimiento_id', $padre->id)
            ->sum('monto'), 2);
        $antes = round((float) $padre->monto, 2);
        $delta = round($suma - $antes, 2);

        if ($suma < 0.01) {
            if (abs($antes) >= 0.01) {
                $caja->update(['saldo' => round((float) $caja->saldo - $antes, 2)]);
            }
            $padre->delete();

            return;
        }

        $padre->update(['monto' => $suma]);
        if (abs($delta) >= 0.01) {
            $caja->update(['saldo' => round((float) $caja->saldo + $delta, 2)]);
        }
    }

    private function asegurarCajas(): void
    {
        foreach ([
            ['codigo' => TesoreriaCaja::CODIGO_BS, 'nombre' => 'Caja Principal Bolívares', 'moneda' => 'VES', 'orden' => 1],
            ['codigo' => TesoreriaCaja::CODIGO_USD, 'nombre' => 'Caja Principal Divisas', 'moneda' => 'USD', 'orden' => 2],
        ] as $def) {
            TesoreriaCaja::query()->firstOrCreate(
                ['codigo' => $def['codigo']],
                $def + ['saldo' => 0]
            );
        }
    }
}
