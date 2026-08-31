<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaEmpleadoAjuste extends Model
{
    public const TIPO_DEDUCCION = 'DEDUCCION';
    public const TIPO_BONIFICACION = 'BONIFICACION';

    public const DESTINO_NOMINA = 'NOMINA';
    public const DESTINO_COMISION = 'COMISION';

    public const PENDIENTE = 'PENDIENTE';
    public const APLICADO = 'APLICADO';
    public const CANCELADO = 'CANCELADO';

    protected $table = 'nomina_empleado_ajustes';

    protected $fillable = [
        'empleado_id',
        'fecha',
        'tipo',
        'destino',
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
        return $this->estado === self::PENDIENTE;
    }

    public function esBonificacion(): bool
    {
        return $this->tipo === self::TIPO_BONIFICACION;
    }

    public function etiquetaTipo(): string
    {
        return $this->esBonificacion() ? 'Bonificación' : 'Deducción';
    }

    public function etiquetaDestino(): string
    {
        return $this->destino === self::DESTINO_COMISION ? 'Comisión' : 'Nómina';
    }
}
