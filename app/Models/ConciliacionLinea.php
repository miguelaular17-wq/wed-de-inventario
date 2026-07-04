<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConciliacionLinea extends Model
{
    protected $fillable = ['fecha', 'descripcion', 'referencia', 'monto', 'estado', 'flujo_caja_id'];
}
