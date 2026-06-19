<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSedeMetric extends Model
{
    protected $connection = 'sqlite';

    protected $fillable = [
        'product_id',
        'sede',
        'existencia',
        'ventas_60d',
        'ultima_venta',
        'promedio_15d',
    ];

    protected $casts = [
        'ultima_venta' => 'date',
        'ventas_60d' => 'float',
        'existencia' => 'integer',
        'promedio_15d' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
