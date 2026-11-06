<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un lote de punto de venta contado por Tesorería en el cuadre, enlazado a su lote POS de Tesorería. */
class CuadreCajaLote extends Model
{
    protected $table = 'cuadre_caja_lotes';

    protected $guarded = [];

    protected $casts = [
        'monto' => 'float',
    ];

    public function cuadre(): BelongsTo
    {
        return $this->belongsTo(CuadreCaja::class, 'cuadre_caja_id');
    }

    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(TesoreriaIngreso::class, 'tesoreria_ingreso_id');
    }
}
