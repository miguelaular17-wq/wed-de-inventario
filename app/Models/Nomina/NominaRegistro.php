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

    public function lineasDescuentoColumna(): array
    {
        return app(\App\Services\Nomina\NominaDescuentoComentarios::class)->lineasNominaColumnaDeducciones($this);
    }

    public function lineasDescuentoTotal(): array
    {
        return app(\App\Services\Nomina\NominaDescuentoComentarios::class)->lineasNomina($this);
    }

    public function montoDeduccionesAjuste(): float
    {
        $desglose = $this->desglose();

        // Columna "Deducciones" del Excel/PDF = total − ausencias − adelantos − préstamos.
        // Así no se duplican ajustes aunque un snapshot viejo haya guardado
        // el mismo monto en otras_deducciones y deducciones_ajuste_nomina.
        $resto = (float) $this->total_deducciones
            - (float) ($desglose['inasistencias'] ?? 0)
            - (float) ($desglose['abonos_sueldo'] ?? 0)
            - (float) ($desglose['prestamos'] ?? 0);

        return round(max(0, $resto), 2);
    }
}
