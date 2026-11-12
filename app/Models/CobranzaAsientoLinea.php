<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CobranzaAsientoLinea extends Model
{
    protected $table = 'cobranza_asiento_lineas';

    protected $fillable = ['asiento_id', 'cuenta', 'detalle', 'debe_usd', 'haber_usd', 'debe_bs', 'haber_bs'];

    protected $casts = [
        'debe_usd' => 'decimal:2',
        'haber_usd' => 'decimal:2',
        'debe_bs' => 'decimal:2',
        'haber_bs' => 'decimal:2',
    ];
}
