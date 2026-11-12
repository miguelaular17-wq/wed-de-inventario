<?php

namespace App\Models\Mayor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MayorFacturaItem extends Model
{
    protected $table = 'mayor_factura_items';

    protected $fillable = ['factura_id', 'producto_id', 'codigo', 'descripcion', 'cantidad', 'precio_usd', 'total_usd'];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_usd' => 'float',
        'total_usd' => 'float',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(MayorFactura::class, 'factura_id');
    }
}
