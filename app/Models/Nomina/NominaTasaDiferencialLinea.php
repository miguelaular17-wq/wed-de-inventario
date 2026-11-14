<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaTasaDiferencialLinea extends Model
{
    protected $table = 'nomina_tasa_diferencial_lineas';

    protected $guarded = [];

    protected $casts = [
        'no_pagado' => 'boolean',
        'total_usd' => 'decimal:2',
        'bs_pagado' => 'decimal:2',
        'bs_nuevo' => 'decimal:2',
        'diferencia_bs' => 'decimal:2',
    ];

    /** Lo que entra al TXT: la diferencia solo si aún no se le canceló. */
    public function aPagarBs(): float
    {
        return $this->no_pagado ? round((float) $this->diferencia_bs, 2) : 0.0;
    }

    public function diferencial(): BelongsTo
    {
        return $this->belongsTo(NominaTasaDiferencial::class, 'diferencial_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }
}
