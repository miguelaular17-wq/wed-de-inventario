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
        'justificado_at' => 'datetime',
    ];

    public function linea(): BelongsTo
    {
        return $this->belongsTo(ConciliacionDivisaLinea::class, 'linea_id');
    }

    public function justificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'justificado_por');
    }

    public function estaJustificado(): bool
    {
        return $this->justificado_at !== null;
    }
}
