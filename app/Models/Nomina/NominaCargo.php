<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NominaCargo extends Model
{
    protected $table = 'nomina_cargos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
    ];

    public function empleados(): HasMany
    {
        return $this->hasMany(NominaEmpleado::class, 'cargo_id');
    }

    public function isActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }
}
