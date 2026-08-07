<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConciliacionLinea extends Model
{
    protected $fillable = [
        'banco', 'titular',
        'fecha', 'descripcion', 'referencia', 'monto', 'estado', 'flujo_caja_id',
        'session_id', 'tipo',
        'tesoreria_ingreso_id'
    ];

    public function flujoCaja()
    {
        return $this->belongsTo(FlujoCaja::class);
    }

    public function tesoreriaIngreso()
    {
        return $this->belongsTo(TesoreriaIngreso::class, 'tesoreria_ingreso_id');
    }
}
