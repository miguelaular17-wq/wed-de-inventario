<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaPorPagarSuiteItem extends Model
{
    protected $table = 'cuentas_por_pagar_suite_items';

    protected $fillable = [
        'cuenta_por_pagar_suite_id',
        'sede',
        'tipo_documento',
        'numero_documento',
        'item_numero',
        'articulo',
        'descripcion',
        'cantidad',
        'costo_usd',
        'costo_bs',
        'total_usd',
        'total_bs',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'costo_usd' => 'float',
        'costo_bs' => 'float',
        'total_usd' => 'float',
        'total_bs' => 'float',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(CuentaPorPagarSuite::class, 'cuenta_por_pagar_suite_id');
    }
}
