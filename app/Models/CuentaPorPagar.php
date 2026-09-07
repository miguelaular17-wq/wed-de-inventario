<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaPorPagar extends Model
{
    protected $table = 'cuentas_por_pagar';

    protected $fillable = [
        'fecha',
        'beneficiario',
        'tipo_gasto',
        'motivo',
        'sede',
        'moneda',
        'monto_total',
        'monto_pagado',
        'saldo',
        'estado',
        'created_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_total' => 'float',
        'monto_pagado' => 'float',
        'saldo' => 'float',
    ];

    public function pagos(): HasMany
    {
        return $this->hasMany(FlujoCaja::class, 'cuenta_por_pagar_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estaAbierta(): bool
    {
        return $this->estado !== 'pagada' && (float) $this->saldo > 0.01;
    }

    public function montoDelPago(FlujoCaja $mov): float
    {
        return strtoupper((string) $this->moneda) === 'BS'
            ? (float) $mov->monto_bs
            : (float) $mov->monto_usd;
    }

    public function recalcular(): self
    {
        $this->load('pagos');
        $pagado = round($this->pagos->sum(fn (FlujoCaja $mov) => $this->montoDelPago($mov)), 2);
        $this->monto_pagado = $pagado;
        $this->saldo = round(max(0, (float) $this->monto_total - $pagado), 2);
        $this->estado = $this->saldo <= 0.01 ? 'pagada' : 'abierta';
        $this->save();

        return $this;
    }
}
