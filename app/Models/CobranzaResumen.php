<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CobranzaResumen extends Model
{
    protected $table = 'cobranza_resumenes';
    
    protected $fillable = [
        'fecha_registro', 'sede_nombre', 'total_clientes', 'total_saldo',
        'critico_clientes', 'critico_saldo',
        'moroso_clientes', 'moroso_saldo',
        'reciente_clientes', 'reciente_saldo',
        'apartado_clientes', 'apartado_saldo'
    ];
}

