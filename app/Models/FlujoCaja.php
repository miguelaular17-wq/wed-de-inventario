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
        'desglose', 'comprobantes', 'cuenta_por_pagar_id',
        'anulado', 'anulado_motivo', 'anulado_at',
    ];

    /** Sin los anulados (duplicados, devoluciones): no cuentan en listados ni totales. */
    public function scopeVigentes($query)
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('flujo_cajas', 'anulado')) {
            $query->where(fn ($q) => $q->where('anulado', false)->orWhereNull('anulado'));
        }

        return $query;
    }

    public function cuentaPorPagar()
    {
        return $this->belongsTo(CuentaPorPagar::class, 'cuenta_por_pagar_id');
    }

    protected $casts = [
        'desglose' => 'array',
        'comprobantes' => 'array',
        'detalle_todoticket' => 'array',
        'es_todoticket' => 'boolean',
        'anulado' => 'boolean',
        'anulado_at' => 'datetime',
    ];
}
