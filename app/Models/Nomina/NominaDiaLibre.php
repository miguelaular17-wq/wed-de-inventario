<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaDiaLibre extends Model
{
    public const PENDIENTE = 'PENDIENTE';
    public const APROBADO = 'APROBADO';
    public const RECHAZADO = 'RECHAZADO';

    protected $table = 'nomina_dias_libres';

    protected $fillable = [
        'empleado_id',
        'fecha',
        'estado',
        'creado_por',
        'aprobado_por',
        'aprobado_at',
        'nota',
    ];

    protected $casts = [
        'fecha' => 'date',
        'aprobado_at' => 'datetime',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function esPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function esAprobado(): bool
    {
        return $this->estado === self::APROBADO;
    }
}
