<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NominaPrestamo extends Model
{
    protected $table = 'nomina_prestamos';

    protected $fillable = [
        'empleado_id',
        'fecha',
        'monto_original',
        'numero_cuotas',
        'valor_cuota',
        'frecuencia',
        'fecha_inicio',
        'fecha_fin_estimada',
        'saldo_pendiente',
        'estado',
        'motivo',
        'created_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_inicio' => 'date',
        'fecha_fin_estimada' => 'date',
        'monto_original' => 'decimal:2',
        'valor_cuota' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(NominaPrestamoCuota::class, 'prestamo_id')->orderBy('numero');
    }

    public function abonos(): HasMany
    {
        return $this->hasMany(NominaPrestamoAbono::class, 'prestamo_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    public function totalPagado(): float
    {
        return round((float) $this->monto_original - (float) $this->saldo_pendiente, 2);
    }

    public function proximaCuota(): ?NominaPrestamoCuota
    {
        return $this->cuotas
            ->first(fn (NominaPrestamoCuota $cuota) => in_array($cuota->estado, ['PENDIENTE', 'VENCIDA', 'PARCIAL'], true));
    }
}
