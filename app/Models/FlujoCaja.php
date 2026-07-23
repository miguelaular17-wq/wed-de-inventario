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
        'comision', 'motivo', 'categoria_egreso', 'tipo_gasto', 'sede', 'placa_vehiculo', 'oculto', 'comprobante_url',
        'desglose', 'comprobantes'
    ];

    protected $casts = [
        'desglose' => 'array',
        'comprobantes' => 'array',
    ];
}
