<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cobranza extends Model
{
    protected $fillable = [
        'sede_nombre', 'codigo', 'cliente', 'saldo_bs', 'saldo_usd', 'fecha_emision', 'meses_antiguedad', 'estatus'
    ];
}
