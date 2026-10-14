<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncentivoCruceItem extends Model
{
    protected $table = 'incentivo_cruce_items';

    protected $fillable = [
        'modulo',
        'tipo',
        'clave',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
