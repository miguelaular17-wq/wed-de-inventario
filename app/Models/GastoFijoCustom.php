<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastoFijoCustom extends Model
{
    protected $table = 'gasto_fijo_custom';
    protected $fillable = ['tabla_idx', 'sede', 'servicio', 'fecha', 'empresa', 'costo'];

    protected $casts = [
        'costo' => 'decimal:2',
    ];
}
