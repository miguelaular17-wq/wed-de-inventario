<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraDivisa extends Model
{
    protected $table = 'compra_divisas';

    protected $fillable = [
        'fecha',
        'banco',
        'titular',
        'categoria_cuenta',
        'referencia',
        'concepto',
        'motivo',
        'monto_bs',
        'monto_usd',
        'tasa_cambio',
        'es_conciliado',
        'comprobante_url',
        'comprobantes',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_bs' => 'decimal:2',
        'monto_usd' => 'decimal:4',
        'tasa_cambio' => 'decimal:6',
        'es_conciliado' => 'boolean',
        'comprobantes' => 'array',
    ];
}
