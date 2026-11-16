<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TesoreriaCajaMovimientoDetalle extends Model
{
    protected $table = 'tesoreria_caja_movimiento_detalles';

    protected $fillable = [
        'movimiento_id', 'cuadre_caja_id', 'sede', 'codigo_caja', 'moneda', 'monto',
    ];

    protected $casts = [
        'monto' => 'float',
    ];

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCajaMovimiento::class, 'movimiento_id');
    }

    public function cuadre(): BelongsTo
    {
        return $this->belongsTo(CuadreCaja::class, 'cuadre_caja_id');
    }
}
