<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Monto manual que se suma (o resta) a los egresos 058 de servicio técnico en una quincena. */
class NominaStEgresoAjuste extends Model
{
    protected $table = 'nomina_st_egreso_ajustes';

    protected $fillable = ['periodo_id', 'empleado_id', 'monto', 'motivo', 'created_by'];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(NominaPeriodo::class, 'periodo_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
