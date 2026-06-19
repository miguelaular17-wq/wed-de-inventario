<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaHistorica extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'ventas_historicas';

    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'producto_id',
        'sede',
        'venta_promedio',
        'ventas_60d',
        'ultima_venta',
    ];

    protected $casts = [
        'venta_promedio' => 'float',
        'ventas_60d' => 'float',
        'ultima_venta' => 'date',
        'updated_at' => 'datetime',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
