<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $connection = 'sqlite';

    protected $fillable = [
        'cod_centro',
        'sede_origen',
        'sede_destino',
        'cantidad',
        'tipo',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];
}
