<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContratoCuota extends Model
{
    protected $table = 'contrato_cuotas';

    protected $fillable = [
        'contrato_id', 'numero_cuota', 'fecha_vencimiento', 'monto',
        'estatus', 'fecha_pago', 'forma_pago', 'monto_pagado', 'abono_capital', 'saldo',
        'notificaciones_enviadas',
    ];

    protected $casts = [
        'fecha_vencimiento'      => 'date',
        'fecha_pago'             => 'date',
        'monto'                  => 'decimal:2',
        'monto_pagado'           => 'decimal:2',
        'saldo'                  => 'decimal:2',
        'notificaciones_enviadas' => 'array',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(ContratoSeguimiento::class, 'cuota_id');
    }

    public function diasHastaVencimiento(): int
    {
        if (!$this->fecha_vencimiento) return 0;
        return (int) now()->startOfDay()->diffInDays($this->fecha_vencimiento->startOfDay(), false);
    }

    public function diasAtraso(): int
    {
        if (!$this->fecha_vencimiento) return 0;
        $dias = $this->fecha_vencimiento->diffInDays(now(), false);
        return $dias > 0 ? (int) $dias : 0;
    }

    public function yaNotificado(string $tipo): bool
    {
        $enviadas = $this->notificaciones_enviadas ?? [];
        return in_array($tipo, $enviadas);
    }

    public function marcarNotificado(string $tipo): void
    {
        $enviadas = $this->notificaciones_enviadas ?? [];
        if (!in_array($tipo, $enviadas)) {
            $enviadas[] = $tipo;
            $this->notificaciones_enviadas = $enviadas;
            $this->save();
        }
    }
}
