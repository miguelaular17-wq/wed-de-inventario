<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NominaReglaComision extends Model
{
    protected $table = 'nomina_reglas_comision';

    protected $fillable = [
        'nombre',
        'nivel',
        'producto_id',
        'codigo_producto',
        'categoria',
        'subcategoria',
        'porcentaje',
        'base_comisionable',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:4',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activo' => 'boolean',
    ];

    public function registros(): HasMany
    {
        return $this->hasMany(NominaComisionRegistro::class, 'regla_id');
    }
}
