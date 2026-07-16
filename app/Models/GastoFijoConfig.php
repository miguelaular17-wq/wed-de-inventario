<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastoFijoConfig extends Model
{
    protected $table = 'gasto_fijo_config';

    protected $fillable = ['tabla_idx', 'fila_idx', 'fecha', 'costo'];
}
