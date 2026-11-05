<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lo que Tesorería contó de una forma de pago de un cierre (en la moneda de esa forma de pago). */
class CuadreCajaConteo extends Model
{
    protected $table = 'cuadre_caja_conteos';

    protected $guarded = [];

    protected $casts = [
        'en_dolares' => 'boolean',
        'monto' => 'float',
    ];

    public function cuadre(): BelongsTo
    {
        return $this->belongsTo(CuadreCaja::class, 'cuadre_caja_id');
    }
}
