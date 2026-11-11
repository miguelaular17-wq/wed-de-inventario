<?php

namespace App\Services\Conciliacion;

use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use App\Services\BankReconciliationMatcher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cruza los pagos móviles que Tesorería cargó (reporte de Cloud: referencia corta) con los abonos de
 * pago móvil del extracto: mismo monto, referencia que coincide y el abono el mismo día o hasta
 * DIAS_ABONO días después (el pago del sábado lo abona el banco el lunes). Entre varios, el más cercano.
 */
class PagosMovilesTesoreriaConciliador
{
    public const DIAS_ABONO = 4;

    public function __construct(private BankReconciliationMatcher $matcher) {}

    /**
     * Pagos sin conciliar cuyo abono podría caer en el período del extracto.
     *
     * @param  (callable(ConciliacionLinea): bool)|null  $deLaCuenta  limita las líneas del banco (p. ej. a una cuenta)
     */
    public function conciliarPeriodo(string $desde, string $hasta, ?callable $deLaCuenta = null): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('tesoreria_ingresos')) {
            return 0;
        }
        $ingresos = TesoreriaIngreso::query()
            ->where('tipo', 'banco')
            ->where(fn ($q) => $q->where('es_conciliado', false)->orWhereNull('es_conciliado'))
            ->whereBetween('fecha', [Carbon::parse($desde)->subDays(self::DIAS_ABONO)->toDateString(), $hasta])
            ->get();

        return $this->conciliar($ingresos, $deLaCuenta);
    }

    /**
     * @param  Collection<int, TesoreriaIngreso>  $ingresos
     * @param  (callable(ConciliacionLinea): bool)|null  $deLaCuenta
     */
    public function conciliar(Collection $ingresos, ?callable $deLaCuenta = null): int
    {
        $ingresos = $ingresos->filter(fn ($i) => $i->fecha !== null && (float) $i->monto >= 0.01 && ! $i->es_conciliado)->values();
        if ($ingresos->isEmpty()) {
            return 0;
        }
        $fechas = $ingresos->map(fn ($i) => Carbon::parse($i->fecha)->toDateString());
        $clave = fn ($monto) => number_format(abs((float) $monto), 2, '.', '');

        $porMonto = ConciliacionLinea::query()
            ->where('estado', 'pendiente')
            ->whereNull('tesoreria_ingreso_id')
            ->whereNull('flujo_caja_id')
            ->whereBetween('fecha', [$fechas->min(), Carbon::parse($fechas->max())->addDays(self::DIAS_ABONO)->toDateString()])
            ->get()
            ->filter(fn ($l) => $l->esAbono() && $this->matcher->esPagoMovil($l->descripcion) && ($deLaCuenta === null || $deLaCuenta($l)))
            ->groupBy(fn ($l) => $clave($l->monto));

        $usadas = [];
        $conciliados = 0;
        foreach ($ingresos->sortBy('fecha') as $ingreso) {
            $dia = Carbon::parse($ingreso->fecha)->startOfDay();
            $linea = collect($porMonto->get($clave($ingreso->monto), []))
                ->filter(function ($l) use ($ingreso, $dia, $usadas) {
                    $diferencia = $dia->diffInDays($l->fecha->copy()->startOfDay(), false);

                    return ! isset($usadas[$l->id])
                        && $diferencia >= 0 && $diferencia <= self::DIAS_ABONO
                        && $this->matcher->mismosMontos($l->monto, $ingreso->monto)
                        && ($this->matcher->referenciasCruzan($l->referencia, $ingreso->lote_referencia)
                            || $this->matcher->referenciaPagoContenida($l->referencia, $ingreso->lote_referencia));
                })
                ->sortBy(fn ($l) => [$l->fecha->timestamp, $l->id])
                ->first();
            if (! $linea) {
                continue;
            }

            $ok = DB::transaction(function () use ($linea, $ingreso) {
                $n = ConciliacionLinea::query()
                    ->where('id', $linea->id)
                    ->where('estado', 'pendiente')
                    ->whereNull('tesoreria_ingreso_id')
                    ->update(['estado' => 'conciliado', 'tesoreria_ingreso_id' => $ingreso->id, 'flujo_caja_id' => null, 'updated_at' => now()]);
                if ($n === 1) {
                    $ingreso->forceFill(['es_conciliado' => true])->save();
                }

                return $n === 1;
            });
            if ($ok) {
                $usadas[$linea->id] = true;
                $conciliados++;
            }
        }

        return $conciliados;
    }
}
