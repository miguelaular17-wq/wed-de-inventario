<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialCobranza extends Model
{
    use HasFactory;

    protected $table = 'historial_cobranzas';
    
    protected $connection = 'pgsql';

    protected $guarded = [];

    public function scopeCuentasOperativas($query)
    {
        return $query->whereRaw("UPPER(BTRIM(COALESCE(codigo_cliente, ''))) NOT LIKE 'EXP%'");
    }

    public function scopeCabeceras($query)
    {
        return $query->where('monto_neto', '>', 0);
    }
}
