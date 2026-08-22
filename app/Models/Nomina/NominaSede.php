<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NominaSede extends Model
{
    protected $table = 'nomina_sedes';

    protected $fillable = [
        'nombre',
        'codigo',
        'direccion',
        'estado',
        'tipo',
        'excluir_comision',
    ];

    protected $casts = [
        'excluir_comision' => 'boolean',
    ];

    public function empleados(): HasMany
    {
        return $this->hasMany(NominaEmpleado::class, 'sede_id');
    }

    public function isActiva(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function isArea(): bool
    {
        return $this->tipo === 'AREA';
    }

    public function etiquetaTipo(): string
    {
        return $this->isArea() ? 'Área' : 'Sede';
    }

    public function scopeOrdenCatalogo($query)
    {
        return $query->orderByRaw("CASE WHEN tipo = 'AREA' THEN 1 ELSE 0 END")->orderBy('nombre');
    }
}
