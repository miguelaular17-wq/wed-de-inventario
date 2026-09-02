<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StOrdenRepuesto extends Model
{
    protected $table = 'st_orden_repuestos';

    protected $fillable = [
        'orden_id',
        'repuesto_id',
        'cantidad',
        'precio_unitario',
        'costo_unitario',
        'descontado',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'descontado' => 'boolean',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(StOrden::class, 'orden_id');
    }

    public function repuesto(): BelongsTo
    {
        return $this->belongsTo(StRepuesto::class, 'repuesto_id');
    }

    public function subtotalVenta(): float
    {
        return (float) $this->precio_unitario * (int) $this->cantidad;
    }
}
