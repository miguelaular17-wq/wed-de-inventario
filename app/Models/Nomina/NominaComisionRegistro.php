<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaComisionRegistro extends Model
{
    protected $table = 'nomina_comision_registros';

    protected $guarded = [];

    protected $casts = [
        'fecha' => 'date',
        'regla_snapshot' => 'array',
        'base_monto' => 'decimal:2',
        'porcentaje' => 'decimal:4',
        'monto_comision' => 'decimal:2',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(NominaPeriodo::class, 'periodo_id');
    }

    public function regla(): BelongsTo
    {
        return $this->belongsTo(NominaReglaComision::class, 'regla_id');
    }
}
