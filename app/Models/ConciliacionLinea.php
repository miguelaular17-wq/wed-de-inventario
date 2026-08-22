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

    public function esCargo(): bool
    {
        if ($this->tipo !== null && trim((string) $this->tipo) !== '') {
            return strtolower(trim((string) $this->tipo)) === 'cargo';
        }

        return (float) $this->monto < 0;
    }

    public function esAbono(): bool
    {
        return ! $this->esCargo();
    }
}
