<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaInasistencia extends Model
{
    protected $table = 'nomina_inasistencias';

    protected $fillable = [
        'empleado_id',
        'fecha',
        'cantidad',
        'valor_unitario',
        'monto',
        'quincena_inicio',
        'quincena_fin',
        'etiqueta',
        'estado',
        'nomina_periodo_id',
        'motivo',
        'created_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'quincena_inicio' => 'date',
        'quincena_fin' => 'date',
        'cantidad' => 'decimal:2',
        'valor_unitario' => 'decimal:2',
        'monto' => 'decimal:2',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPendiente(): bool
    {
        return $this->estado === 'PENDIENTE';
    }
}
