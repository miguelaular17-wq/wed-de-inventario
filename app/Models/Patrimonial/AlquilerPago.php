<?php
namespace App\Models\Patrimonial;

use Illuminate\Database\Eloquent\Model;

class AlquilerPago extends Model
{
    protected $table = 'pat_alquiler_pagos';

    protected $fillable = [
        'alquiler_id', 'periodo', 'fecha_vencimiento',
        'fecha_pago', 'monto', 'monto_pagado', 'estado', 'observaciones',
        'forma_pago', 'tasa_cambio', 'banco_origen', 
        'banco_destino', 'referencia', 'comentario', 'user_id'
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'fecha_pago'        => 'date',
        'monto'             => 'decimal:2',
        'monto_pagado'      => 'decimal:2',
    ];

    public function alquiler()
    {
        return $this->belongsTo(Alquiler::class, 'alquiler_id');
    }

    public function getSaldo()
    {
        return max(0, $this->monto - $this->monto_pagado);
    }
}
