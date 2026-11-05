<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lo que entró por una forma de pago en un cierre de caja. */
class CuadreCajaPago extends Model
{
    public $timestamps = false;

    protected $table = 'cuadre_caja_pagos';

    protected $guarded = [];

    protected $casts = [
        'en_dolares' => 'boolean',
        'ingresos' => 'float',
        'ingresos_usd' => 'float',
    ];

    public function cuadre(): BelongsTo
    {
        return $this->belongsTo(CuadreCaja::class, 'cuadre_caja_id');
    }
}
