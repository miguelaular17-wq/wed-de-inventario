<?php
namespace App\Models\Patrimonial;

use Illuminate\Database\Eloquent\Model;

class AlquilerPago extends Model
{
    protected $table = 'pat_alquiler_pagos';

    protected $fillable = [
        'alquiler_id', 'periodo', 'fecha_vencimiento',
        'fecha_pago', 'monto', 'estado', 'observaciones',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'fecha_pago'        => 'date',
        'monto'             => 'decimal:2',
    ];

    public function alquiler()
    {
        return $this->belongsTo(Alquiler::class, 'alquiler_id');
    }
}
