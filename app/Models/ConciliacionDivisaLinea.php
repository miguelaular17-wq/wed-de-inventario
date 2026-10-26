<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConciliacionDivisaLinea extends Model
{
    protected $table = 'conciliacion_divisas_lineas';

    protected $fillable = [
        'banco', 'titular', 'fecha', 'hora', 'descripcion', 'referencia', 'monto', 'tipo', 'estado',
        'flujo_caja_id', 'huella', 'archivo', 'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'float',
    ];

    public function flujoCaja(): BelongsTo
    {
        return $this->belongsTo(FlujoCaja::class);
    }

    public function pago(): HasOne
    {
        return $this->hasOne(ConciliacionDivisaPago::class, 'linea_id');
    }

    public function esCargo(): bool
    {
        return $this->tipo === 'cargo';
    }
}
