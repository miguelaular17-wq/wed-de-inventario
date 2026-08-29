<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Contrato extends Model
{
    protected $fillable = [
        'numero_contrato', 'cliente', 'garantia', 'garantia_documento', 'garantia_aumento', 'contacto', 'telefono',
        'sede', 'capital', 'interes_porcentaje', 'cuota_fija', 'total_a_pagar',
        'fecha_inicio', 'frecuencia', 'responsable_id', 'observaciones', 'activo',
        'estado', 'liquidado_en_contrato_id',
    ];

    protected $casts = [
        'fecha_inicio'       => 'date',
        'capital'            => 'decimal:2',
        'interes_porcentaje' => 'decimal:4',
        'cuota_fija'         => 'decimal:2',
        'total_a_pagar'      => 'decimal:2',
        'activo'             => 'boolean',
    ];

    public function cuotas(): HasMany
    {
        return $this->hasMany(ContratoCuota::class)->orderBy('numero_cuota');
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(ContratoSeguimiento::class)->orderByDesc('fecha_hora');
    }

    public function contratoRenovado(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'liquidado_en_contrato_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    // Cuotas pendientes o vencidas
    public function cuotasActivas(): HasMany
    {
        return $this->hasMany(ContratoCuota::class)
            ->whereIn('estatus', ['pendiente', 'vencido', 'parcial'])
            ->orderBy('fecha_vencimiento');
    }

    public function proximaCuota(): ?ContratoCuota
    {
        return $this->cuotas()
            ->whereIn('estatus', ['pendiente', 'parcial'])
            ->orderBy('fecha_vencimiento')
            ->first();
    }

    public function saldoPendiente(): float
    {
        return (float) $this->cuotas()
            ->whereIn('estatus', ['pendiente', 'vencido', 'parcial'])
            ->sum('saldo');
    }

    public function totalDeuda(): float
    {
        return (float) $this->getRawOriginal('total_a_pagar') + $this->saldoPendiente();
    }

    public function diasAtraso(): int
    {
        $primera = $this->cuotas()
            ->where('estatus', 'vencido')
            ->orderBy('fecha_vencimiento')
            ->first();
        if (!$primera) return 0;
        return (int) Carbon::parse($primera->fecha_vencimiento)->diffInDays(now(), false);
    }

    public function estatusGeneral(): string
    {
        if ($this->esLiquidado()) return 'LIQUIDADO';

        $vencidas = $this->cuotas()->where('estatus', 'vencido')->where('acumulada', false)->count();
        $pendientes = $this->cuotas()->whereIn('estatus', ['pendiente', 'parcial'])->count();
        if ($vencidas > 0) return 'VENCIDO';
        if ($pendientes > 0) return 'ACTIVO';
        // Si tiene saldo de capital pendiente aunque cuotas estén pagadas, sigue siendo deudor
        if ((float) $this->attributes['total_a_pagar'] > 0) return 'ACTIVO';
        return 'PAGADO';
    }

    public function esLiquidado(): bool
    {
        return $this->estado === 'liquidado' || ! $this->activo;
    }

    public function esSinComision(): bool
    {
        return (float) $this->interes_porcentaje == 0.0;
    }

    public function scopeVigente($query)
    {
        return $query->where('activo', true)
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', '!=', 'liquidado');
            });
    }

    public function getTotalAPagarAttribute($value): float
    {
        // total_a_pagar representa el capital restante del contrato.
        // Ya no se acumula con saldos de cuotas vencidas — cada cuota
        // refleja su propio saldo individualmente.
        return (float) $value;
    }
}
