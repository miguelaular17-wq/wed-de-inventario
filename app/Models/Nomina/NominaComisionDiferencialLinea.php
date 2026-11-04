<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaComisionDiferencialLinea extends Model
{
    protected $table = 'nomina_comision_diferencial_lineas';

    protected $guarded = [];

    protected $casts = [
        'no_pagado' => 'boolean',
        'comision_original' => 'decimal:2',
        'comision_ajustada' => 'decimal:2',
        'abonos' => 'decimal:2',
        'retencion_original' => 'decimal:2',
        'retencion_ajustada' => 'decimal:2',
        'descuentos' => 'decimal:2',
        'total_original' => 'decimal:2',
        'total_ajustado' => 'decimal:2',
        'pagado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'detalle' => 'array',
    ];

    public function diferencial(): BelongsTo
    {
        return $this->belongsTo(NominaComisionDiferencial::class, 'diferencial_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }
}
