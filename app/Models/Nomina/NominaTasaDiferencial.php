<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pago complementario en Bs de la nómina de una quincena que se pagó con una tasa menor a la que correspondía. */
class NominaTasaDiferencial extends Model
{
    protected $table = 'nomina_tasa_diferenciales';

    protected $fillable = ['periodo_id', 'motivo', 'fecha_pago', 'tasa_pagada', 'tasa_nueva', 'created_by'];

    protected $casts = [
        'fecha_pago' => 'date',
        'tasa_pagada' => 'decimal:4',
        'tasa_nueva' => 'decimal:4',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(NominaPeriodo::class, 'periodo_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(NominaTasaDiferencialLinea::class, 'diferencial_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
