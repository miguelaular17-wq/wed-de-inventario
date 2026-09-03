<?php

namespace App\Models;

use App\Models\Nomina\NominaEmpleado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaQuincenaProducto extends Model
{
    protected $table = 'meta_quincena_productos';

    protected $fillable = [
        'producto_id',
        'sede',
        'quincena_inicio',
        'quincena_fin',
        'cantidad_inicial',
        'responsable_empleado_id',
        'creado_por_user_id',
    ];

    protected $casts = [
        'quincena_inicio' => 'date',
        'quincena_fin' => 'date',
        'cantidad_inicial' => 'decimal:4',
    ];

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'responsable_empleado_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }
}
