<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NominaEmpresa extends Model
{
    protected $table = 'nomina_empresas';

    protected $fillable = [
        'codigo',
        'nombre',
        'estado',
    ];

    public function empleados(): HasMany
    {
        return $this->hasMany(NominaEmpleado::class, 'empresa_id');
    }

    public function isActiva(): bool
    {
        return $this->estado === 'ACTIVO';
    }
}
