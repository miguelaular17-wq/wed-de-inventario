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
    ];

    protected $casts = [
        'costo' => 'float',
        'visible' => 'boolean',
        'orden' => 'integer',
        'grupo_id' => 'integer',
    ];

    public function pagos()
    {
        return $this->hasMany(GastoFijoPago::class, 'gasto_fijo_id');
    }
}
