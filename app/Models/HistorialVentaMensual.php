<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialVentaMensual extends Model
{
    use HasFactory;

    protected $table = 'inventario_v2.historial_ventas_mensuales';

    protected $fillable = [
        'producto_id',
        'sede',
        'anio_mes',
        'cantidad',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'cantidad' => 'integer',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }
}
