<?php

namespace App\Models\Patrimonial;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ReservaPago extends Model
{
    protected $table = 'pat_reserva_pagos';

    protected $fillable = [
        'reserva_id', 'monto_pagado', 'forma_pago', 'fecha_pago', 
        'tasa_cambio', 'banco_origen', 'banco_destino', 
        'referencia', 'comentario', 'user_id'
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'monto_pagado' => 'decimal:2',
        'tasa_cambio' => 'decimal:4',
    ];

    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
