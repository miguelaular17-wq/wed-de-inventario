<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaDocumento extends Model
{
    protected $table = 'ventas_documentos';

    protected $guarded = [];

    protected $casts = [
        'fecha' => 'date',
        'total_neto_bs' => 'decimal:5',
        'total_neto_usd' => 'decimal:5',
        'factor_cambio' => 'decimal:6',
        'total_descuento_bs' => 'decimal:5',
        'total_descuento_usd' => 'decimal:5',
        'total_impuesto_bs' => 'decimal:5',
        'total_impuesto_usd' => 'decimal:5',
    ];
}
