<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CobranzaLlamada extends Model
{
    protected $table = 'cobranza_llamadas';

    protected $fillable = [
        'codigo_cliente', 'descripcion', 'fecha_llamada', 'user_id'
    ];

    protected $casts = [
        'fecha_llamada' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
