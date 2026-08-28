<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaPrestamoCuota extends Model
{
    protected $table = 'nomina_prestamo_cuotas';

    protected $fillable = [
        'prestamo_id',
        'numero',
        'fecha_programada',
        'monto',
        'monto_pagado',
        'estado',
        'fecha_pago',
        'nomina_periodo_id',
        'abono_id',
    ];

    protected $casts = [
        'fecha_programada' => 'date',
        'fecha_pago' => 'date',
        'monto' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
    ];

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(NominaPrestamo::class, 'prestamo_id');
    }

    public function abono(): BelongsTo
    {
        return $this->belongsTo(NominaPrestamoAbono::class, 'abono_id');
    }

    public function saldo(): float
    {
        return round(max(0, (float) $this->monto - (float) $this->monto_pagado), 2);
    }

    public function puedeDescontarseEnNomina(): bool
    {
        if ($this->saldo() <= 0 || $this->estado === 'PAGADA') {
            return false;
        }

        if ($this->nomina_periodo_id && $this->estado !== 'PARCIAL') {
            return false;
        }

        return in_array($this->estado, ['PENDIENTE', 'VENCIDA', 'PARCIAL'], true);
    }
}
