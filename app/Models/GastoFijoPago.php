<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastoFijoPago extends Model
{
    protected $fillable = ['gasto_fijo_id', 'tabla_idx', 'fila_idx', 'mes_idx', 'anio', 'monto', 'pagado', 'pagado_at'];

    protected $casts = [
        'pagado' => 'boolean',
        'pagado_at' => 'datetime',
        'monto' => 'decimal:2',
    ];

    public function gastoFijo()
    {
        return $this->belongsTo(GastoFijo::class, 'gasto_fijo_id');
    }
}
