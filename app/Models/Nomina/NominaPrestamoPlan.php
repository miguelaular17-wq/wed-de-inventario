<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaPrestamoPlan extends Model
{
    public const DESTINO_NOMINA = 'NOMINA';
    public const DESTINO_COMISION = 'COMISION';

    public const PENDIENTE = 'PENDIENTE';
    public const APLICADO = 'APLICADO';
    public const CANCELADO = 'CANCELADO';

    protected $table = 'nomina_prestamo_planes';

    protected $fillable = [
        'empleado_id',
        'prestamo_id',
        'cuota_id',
        'quincena_inicio',
        'quincena_fin',
        'etiqueta',
        'monto',
        'destino',
        'estado',
        'nomina_periodo_id',
        'created_by',
    ];

    protected $casts = [
        'quincena_inicio' => 'date',
        'quincena_fin' => 'date',
        'monto' => 'decimal:2',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(NominaPrestamo::class, 'prestamo_id');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(NominaPrestamoCuota::class, 'cuota_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPendiente(): bool
    {
        return $this->estado === self::PENDIENTE;
    }

    public function etiquetaDestino(): string
    {
        return $this->destino === self::DESTINO_COMISION ? 'Comisión' : 'Nómina';
    }
}
