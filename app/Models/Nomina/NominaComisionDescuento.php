<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaComisionDescuento extends Model
{
    public const TIPOS = ['FALTANTE', 'PERDIDA', 'DANO', 'PRESTAMO', 'OTRO'];

    public const DECISION_PENDIENTE = 'PENDIENTE';

    public const DECISION_DESCONTAR = 'DESCONTAR';

    public const DECISION_NO_DESCONTAR = 'NO_DESCONTAR';

    public const DESTINO_NOMINA = 'NOMINA';

    public const DESTINO_COMISION = 'COMISION';

    protected $table = 'nomina_comision_descuentos';

    protected $fillable = [
        'empleado_id', 'fecha', 'tipo', 'monto', 'motivo', 'estado', 'decision', 'destino', 'periodo_id', 'created_by',
    ];

    protected $casts = [
        'fecha' => 'date',
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

    public function etiquetaDecision(): string
    {
        if ($this->decision === self::DECISION_DESCONTAR) {
            return match ($this->destino) {
                self::DESTINO_NOMINA => 'Descontar nómina',
                self::DESTINO_COMISION => 'Descontar comisión',
                default => 'Descontar',
            };
        }

        return match ($this->decision) {
            self::DECISION_NO_DESCONTAR => 'No descontar',
            self::DECISION_PENDIENTE => 'Por decidir',
            default => '—',
        };
    }

    public function tieneDestino(): bool
    {
        return in_array($this->destino, [self::DESTINO_NOMINA, self::DESTINO_COMISION], true);
    }

    public function puedeDecidir(): bool
    {
        return $this->tipo === 'FALTANTE'
            && $this->estado === 'PENDIENTE'
            && in_array($this->decision, [self::DECISION_PENDIENTE, self::DECISION_DESCONTAR, self::DECISION_NO_DESCONTAR, null], true);
    }
}
