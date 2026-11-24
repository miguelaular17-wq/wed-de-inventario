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
        'cantidad_arrastre',
        'arrastra_de_meta_id',
        'valor_unitario',
        'responsable_empleado_id',
        'creado_por_user_id',
    ];

    protected $casts = [
        'quincena_inicio' => 'date',
        'quincena_fin' => 'date',
        'cantidad_inicial' => 'decimal:4',
        'cantidad_arrastre' => 'decimal:4',
        'valor_unitario' => 'decimal:2',
    ];

    /** Meta = lo nuevo de la quincena + lo que quedó pendiente de la anterior. */
    public function cantidadEfectiva(): float
    {
        return round((float) $this->cantidad_inicial + (float) ($this->cantidad_arrastre ?? 0), 2);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'responsable_empleado_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }
}
