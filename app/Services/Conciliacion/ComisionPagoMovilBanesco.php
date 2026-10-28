<?php

namespace App\Services\Conciliacion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Banesco cobra cada pago móvil recibido con un cargo aparte: misma fecha, misma referencia,
 * descripción "Banesco Pago Movil" y un monto pequeño (1,5 % del abono). Ese cargo es comisión.
 */
class ComisionPagoMovilBanesco
{
    /** El cargo debe ser como mucho este porcentaje del abono para tomarlo como comisión. */
    private const PCT_MAX = 0.05;

    /** En pagos móviles pequeños Banesco cobra una comisión mínima fija (más del 5 % del abono). */
    private const MINIMO_BS = 14.00;

    public function marcar(?string $desde = null, ?string $hasta = null): int
    {
        if (! Schema::hasTable('conciliacion_lineas') || ! Schema::hasColumn('conciliacion_lineas', 'forzar_comision')) {
            return 0;
        }

        $base = fn () => DB::table('conciliacion_lineas')
            ->whereRaw('UPPER(TRIM(banco)) IN (?, ?)', ['BANESCO', 'BANCO BANESCO'])
            ->whereRaw("LOWER(REPLACE(descripcion, 'ó', 'o')) LIKE ?", ['%pago movil%'])
            ->whereNotNull('referencia')->where('referencia', '!=', '')
            ->when($desde, fn ($q) => $q->where('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta));

        $cargos = $base()
            ->where('tipo', 'cargo')
            ->where('estado', 'pendiente')
            ->where(fn ($q) => $q->where('forzar_comision', false)->orWhereNull('forzar_comision'))
            ->whereNull('flujo_caja_id')->whereNull('tesoreria_ingreso_id')
            ->when(Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id'), fn ($q) => $q->whereNull('compra_divisa_id'))
            ->when(Schema::hasColumn('conciliacion_lineas', 'devolucion_linea_id'), fn ($q) => $q->whereNull('devolucion_linea_id'))
            ->get(['id', 'titular', 'fecha', 'referencia', 'monto']);
        if ($cargos->isEmpty()) {
            return 0;
        }

        $clave = fn ($l) => strtoupper(trim((string) $l->titular)).'|'.substr((string) $l->fecha, 0, 10).'|'.trim((string) $l->referencia);
        $abonos = $base()
            ->where('tipo', 'abono')
            ->whereIn('referencia', $cargos->pluck('referencia')->unique()->values()->all())
            ->get(['titular', 'fecha', 'referencia', 'monto'])
            ->groupBy($clave);

        $ids = [];
        foreach ($cargos as $c) {
            $cargo = abs((float) $c->monto);
            foreach ($abonos->get($clave($c), collect()) as $a) {
                $abono = abs((float) $a->monto);
                if ($abono > 0 && $cargo < $abono && ($cargo <= $abono * self::PCT_MAX || $cargo <= self::MINIMO_BS + 0.005)) {
                    $ids[] = $c->id;
                    break;
                }
            }
        }

        $marcadas = 0;
        foreach (array_chunk($ids, 1000) as $lote) {
            $marcadas += DB::table('conciliacion_lineas')->whereIn('id', $lote)->where('estado', 'pendiente')
                ->update(['forzar_comision' => true, 'updated_at' => now()]);
        }

        return $marcadas;
    }
}
