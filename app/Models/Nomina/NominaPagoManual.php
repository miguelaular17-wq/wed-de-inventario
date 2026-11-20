<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaPagoManual extends Model
{
    public const TIPO_NOMINA = 'NOMINA';

    public const TIPO_COMISION = 'COMISION';

    public const PENDIENTE = 'PENDIENTE';

    public const PAGADO = 'PAGADO';

    protected $table = 'nomina_pagos_manuales';

    protected $fillable = [
        'tipo',
        'periodo_id',
        'empleado_id',
        'monto_usd',
        'tasa_bcv',
        'estado',
        'enviado_by',
        'enviado_at',
        'pagado_by',
        'pagado_at',
        'flujo_caja_id',
    ];

    protected $casts = [
        'monto_usd' => 'decimal:2',
        'tasa_bcv' => 'decimal:4',
        'enviado_at' => 'datetime',
        'pagado_at' => 'datetime',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(NominaPeriodo::class, 'periodo_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_by');
    }

    public function isPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function montoBs(): float
    {
        $tasa = (float) ($this->tasa_bcv ?? 0);

        return $tasa > 0 ? round((float) $this->monto_usd * $tasa, 2) : 0.0;
    }

    public function etiquetaTipo(): string
    {
        return $this->tipo === self::TIPO_COMISION ? 'Comisión' : 'Nómina';
    }
}
