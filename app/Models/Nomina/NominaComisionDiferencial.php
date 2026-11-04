<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pago complementario de comisiones de una quincena, recalculado con porcentajes ajustados. */
class NominaComisionDiferencial extends Model
{
    protected $table = 'nomina_comision_diferenciales';

    protected $fillable = ['periodo_id', 'motivo', 'config', 'fecha_pago', 'tasa_bcv', 'created_by'];

    protected $casts = [
        'config' => 'array',
        'fecha_pago' => 'date',
        'tasa_bcv' => 'decimal:4',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(NominaPeriodo::class, 'periodo_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(NominaComisionDiferencialLinea::class, 'diferencial_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
