<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaPorPagarSuitePago extends Model
{
    protected $table = 'cuentas_por_pagar_suite_pagos';

    protected $fillable = [
        'cuenta_por_pagar_suite_id',
        'sede',
        'tipo_documento',
        'numero_documento',
        'numero_pago',
        'fecha',
        'monto_usd',
        'monto_bs',
        'metodo_pago',
        'codigo_metodo',
        'referencia',
        'codigo_cuenta',
        'tasa_cambio',
        'descripcion',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_usd' => 'float',
        'monto_bs' => 'float',
        'tasa_cambio' => 'float',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(CuentaPorPagarSuite::class, 'cuenta_por_pagar_suite_id');
    }
}
