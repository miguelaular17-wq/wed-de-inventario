<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NominaRegistro extends Model
{
    protected $table = 'nomina_registros';

    protected $fillable = [
        'periodo_id',
        'empleado_id',
        'salario_base',
        'total_comisiones',
        'total_bonificaciones',
        'total_otros_ingresos',
        'total_deducciones',
        'total_ajustes',
        'total_pagar',
        'observaciones',
    ];

    protected $casts = [
        'salario_base' => 'decimal:2',
        'total_comisiones' => 'decimal:2',
        'total_bonificaciones' => 'decimal:2',
        'total_otros_ingresos' => 'decimal:2',
        'total_deducciones' => 'decimal:2',
        'total_ajustes' => 'decimal:2',
        'total_pagar' => 'decimal:2',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(NominaPeriodo::class, 'periodo_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }

    public function ajustes(): HasMany
    {
        return $this->hasMany(NominaAjuste::class, 'nomina_registro_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function desglose(): array
    {
        return json_decode($this->observaciones ?: '{}', true) ?: [];
    }

    public function montoBonificaciones(): float
    {
        $desglose = $this->desglose();

        return round((float) ($desglose['bonificaciones_nomina'] ?? $this->total_bonificaciones ?? 0), 2);
    }

    public function montoDeduccionesAjuste(): float
    {
        $desglose = $this->desglose();

        return round(
            (float) ($desglose['deducciones_ajuste_nomina'] ?? 0)
            + (float) ($desglose['otras_deducciones'] ?? 0)
            + (float) ($desglose['mercancia'] ?? 0),
            2
        );
    }
}
