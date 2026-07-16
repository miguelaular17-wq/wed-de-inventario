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
}
