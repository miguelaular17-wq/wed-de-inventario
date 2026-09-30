<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaDiariaCaja extends Model
{
    protected $table = 'venta_diaria_cajas';

    protected $fillable = [
        'reporte_id',
        'nombre',
        'orden',
        'efectivo_usd',
        'efectivo_bs',
        'punto_venta',
        'transf_pm',
        'zelle_binance',
        'cashea',
        'fact_credito',
        'abonos',
        'iphone',
        'gift_card',
    ];

    protected $casts = [
        'efectivo_usd' => 'decimal:2',
        'efectivo_bs' => 'decimal:2',
        'punto_venta' => 'decimal:2',
        'transf_pm' => 'decimal:2',
        'zelle_binance' => 'decimal:2',
        'cashea' => 'decimal:2',
        'fact_credito' => 'decimal:2',
        'abonos' => 'decimal:2',
        'iphone' => 'decimal:2',
        'gift_card' => 'decimal:2',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(VentaDiariaReporte::class, 'reporte_id');
    }
}
