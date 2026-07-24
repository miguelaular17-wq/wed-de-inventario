<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Contrato extends Model
{
    protected $fillable = [
        'numero_contrato', 'cliente', 'garantia', 'garantia_documento', 'contacto', 'telefono',
        'sede', 'capital', 'interes_porcentaje', 'cuota_fija', 'total_a_pagar',
        'fecha_inicio', 'frecuencia', 'responsable_id', 'observaciones', 'activo',
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
        $vencidas = $this->cuotas()->where('estatus', 'vencido')->count();
        $pendientes = $this->cuotas()->whereIn('estatus', ['pendiente', 'parcial'])->count();
        if ($vencidas > 0) return 'VENCIDO';
        if ($pendientes > 0) return 'ACTIVO';
        return 'PAGADO';
    }

    public function getTotalAPagarAttribute($value): float
    {
        if ((float) $this->interes_porcentaje == 0) {
            return (float) $value;
        }

        $cuotasAtrasadas = $this->cuotas()->where('estatus', 'vencido')->sum('saldo');
        return (float) $value + (float) $cuotasAtrasadas;
    }
}
