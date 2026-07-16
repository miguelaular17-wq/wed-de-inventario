<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastoFijoOculto extends Model
{
    protected $table = 'gasto_fijo_oculto';
    protected $fillable = ['tabla_idx', 'fila_idx'];
}
