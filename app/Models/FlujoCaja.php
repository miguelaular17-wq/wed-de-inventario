<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlujoCaja extends Model
{
    protected $fillable = [
        'fecha', 'concepto', 'tipo', 'monto', 'cuenta', 'observaciones',
        'banco', 'titular', 'categoria_cuenta', 'referencia',
        'banco_receptor', 'titular_receptor',
        'monto_usd', 'tasa_cambio', 'diferencial_cambiario', 'monto_bs',
        'comision', 'motivo', 'categoria_egreso', 'tipo_gasto', 'nomina_empleado_id',
        'es_todoticket', 'detalle_todoticket',
        'sede', 'placa_vehiculo', 'oculto', 'comprobante_url',
        'desglose', 'asiento', 'comprobantes', 'cuenta_por_pagar_id',
        'anulado', 'anulado_motivo', 'anulado_at', 'conciliar_en', 'origen',
    ];

    /** Copia oculta de un movimiento del flujo de caja de Cobranza: solo existe para la conciliación. */
    public const ORIGEN_COBRANZA = 'cobranza';

    /** Sin los anulados (duplicados, devoluciones): no cuentan en listados ni totales. */
    public function scopeVigentes($query)
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('flujo_cajas', 'anulado')) {
            $query->where(fn ($q) => $q->where('anulado', false)->orWhereNull('anulado'));
        }

        return $query;
    }

    /**
     * Egresos que se concilian en el período: los de su fecha, salvo los pasados a otro mes,
     * más los de meses anteriores pasados a este.
     */
    public function scopeParaConciliarEntre($query, ?string $desde, ?string $hasta)
    {
        if (! self::tieneConciliarEn()) {
            return $query->when($desde, fn ($q) => $q->where('fecha', '>=', $desde))
                ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta));
        }

        return $query->where(function ($q) use ($desde, $hasta) {
            $q->where(function ($propios) use ($desde, $hasta) {
                $propios->whereNull('conciliar_en')
                    ->when($desde, fn ($x) => $x->where('fecha', '>=', $desde))
                    ->when($hasta, fn ($x) => $x->where('fecha', '<=', $hasta));
            })->orWhere(function ($pasados) use ($desde, $hasta) {
                $pasados->whereNotNull('conciliar_en')
                    ->when($desde, fn ($x) => $x->where('conciliar_en', '>=', $desde))
                    ->when($hasta, fn ($x) => $x->where('conciliar_en', '<=', $hasta));
            });
        });
    }

    public static function tieneConciliarEn(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn('flujo_cajas', 'conciliar_en');
    }

    public function cuentaPorPagar()
    {
        return $this->belongsTo(CuentaPorPagar::class, 'cuenta_por_pagar_id');
    }

    protected $casts = [
        'desglose' => 'array',
        'asiento' => 'array',
        'comprobantes' => 'array',
        'detalle_todoticket' => 'array',
        'es_todoticket' => 'boolean',
        'anulado' => 'boolean',
        'anulado_at' => 'datetime',
    ];
}
