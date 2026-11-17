<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Egreso 058 de flujo de caja que no se descuenta de la comisión ST en esa quincena. */
class NominaStEgresoExclusion extends Model
{
    protected $table = 'nomina_st_egreso_exclusiones';

    protected $fillable = ['periodo_id', 'empleado_id', 'flujo_caja_id', 'created_by'];

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
