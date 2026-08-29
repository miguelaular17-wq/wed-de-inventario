<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaLiquidacionComision extends Model
{
    protected $table = 'nomina_liquidaciones_comision';

    protected $guarded = [];

    protected $casts = [
        'base_total' => 'decimal:2',
        'base_telefonia' => 'decimal:2',
        'base_otros' => 'decimal:2',
        'pct_telefonia' => 'decimal:4',
        'pct_otros' => 'decimal:4',
        'comision_telefonia' => 'decimal:2',
        'comision_otros' => 'decimal:2',
        'comision_total' => 'decimal:2',
        'abonos' => 'decimal:2',
        'retencion_pct' => 'decimal:4',
        'retencion' => 'decimal:2',
        'descuentos' => 'decimal:2',
        'prestamos' => 'decimal:2',
        'total_pagar' => 'decimal:2',
        'fecha_pago' => 'date',
        'snapshot' => 'array',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(NominaPeriodo::class, 'periodo_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }

    public function scopeVisibles($query)
    {
        return $query
            ->where('modo', '!=', NominaEmpleado::COMISION_NINGUNA)
            ->whereHas('empleado', fn ($q) => $q->comisionables());
    }

    public function totalVentas(): float
    {
        $total = (float) $this->base_total;
        if ($total > 0) {
            return $total;
        }

        return round((float) $this->base_telefonia + (float) $this->base_otros, 2);
    }
}
