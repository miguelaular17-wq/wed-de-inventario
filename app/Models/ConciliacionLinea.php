<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConciliacionLinea extends Model
{
    protected $fillable = [
        'banco', 'titular',
        'fecha', 'descripcion', 'referencia', 'monto', 'estado', 'flujo_caja_id',
        'session_id', 'tipo',
    ];

    public function flujoCaja()
    {
        return $this->belongsTo(FlujoCaja::class);
    }
}
