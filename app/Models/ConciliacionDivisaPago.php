<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cobro del reporte de pagos (Cloud) de una cuenta en divisas. */
class ConciliacionDivisaPago extends Model
{
    protected $table = 'conciliacion_divisas_pagos';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha' => 'date',
        'fecha_hora' => 'datetime',
        'monto' => 'float',
    ];

    public function linea(): BelongsTo
    {
        return $this->belongsTo(ConciliacionDivisaLinea::class, 'linea_id');
    }
}
