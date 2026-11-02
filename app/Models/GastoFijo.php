<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastoFijo extends Model
{
    protected $table = 'gastos_fijos';

    protected $fillable = [
        'grupo_id',
        'sede',
        'servicio',
        'fecha',
        'empresa',
        'costo',
        'orden',
        'visible',
        'activo',
        'meses_atraso',
    ];

    protected $attributes = [
        'activo' => true,
        'meses_atraso' => 0,
    ];

    protected $casts = [
        'costo' => 'float',
        'visible' => 'boolean',
        'activo' => 'boolean',
        'meses_atraso' => 'integer',
        'orden' => 'integer',
        'grupo_id' => 'integer',
    ];

    public function pagos()
    {
        return $this->hasMany(GastoFijoPago::class, 'gasto_fijo_id');
    }
}
