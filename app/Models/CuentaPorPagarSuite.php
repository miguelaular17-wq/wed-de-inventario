<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class CuentaPorPagarSuite extends Model
{
    public const ORIGEN_CUENTA = 'suite_jrz';

    protected $table = 'cuentas_por_pagar_suite';

    protected $fillable = [
        'sede',
        'tipo_documento',
        'numero_documento',
        'fecha',
        'codigo_proveedor',
        'proveedor',
        'total_usd',
        'total_bs',
        'saldo_usd',
        'saldo_bs',
        'estado_suite',
        'credito',
        'cuenta_por_pagar_id',
        'synced_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total_usd' => 'float',
        'total_bs' => 'float',
        'saldo_usd' => 'float',
        'saldo_bs' => 'float',
        'credito' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function cuentaPorPagar(): BelongsTo
    {
        return $this->belongsTo(CuentaPorPagar::class, 'cuenta_por_pagar_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CuentaPorPagarSuiteItem::class, 'cuenta_por_pagar_suite_id')
            ->orderBy('item_numero');
    }

    public function pagosSuite(): HasMany
    {
        return $this->hasMany(CuentaPorPagarSuitePago::class, 'cuenta_por_pagar_suite_id')
            ->orderBy('fecha')
            ->orderBy('id');
    }

    public function montoPagadoUsd(): float
    {
        return round(max(0, (float) $this->total_usd - (float) $this->saldo_usd), 2);
    }

    public function montoPagadoSuiteUsd(): float
    {
        return round((float) $this->pagosSuite->sum('monto_usd'), 2);
    }

    public function estaAbierta(): bool
    {
        if ($this->cuentaPorPagar) {
            return $this->cuentaPorPagar->estaAbierta();
        }

        return abs((float) $this->saldo_usd) > 0.01 || abs((float) $this->saldo_bs) > 0.01;
    }

    public function saldoPorPagarUsd(): float
    {
        if ($this->cuentaPorPagar) {
            return round((float) $this->cuentaPorPagar->saldo, 2);
        }

        return round((float) $this->saldo_usd, 2);
    }

    public function situacion(): string
    {
        if (! $this->estaAbierta()) {
            return 'pagada';
        }

        $pagadoWeb = $this->cuentaPorPagar
            ? (float) $this->cuentaPorPagar->monto_pagado
            : 0.0;

        if ($pagadoWeb > 0.01) {
            return 'parcial';
        }

        $total = (float) $this->total_usd;
        $saldo = (float) $this->saldo_usd;
        if ($total > 0.01 && $saldo < ($total - 0.01)) {
            return 'parcial';
        }

        return 'debe';
    }

    /**
     * Crea o reutiliza la cuenta de Finanzas para pagar esta FAC desde la web.
     */
    public function ensureCuentaPorPagar(?int $userId = null): CuentaPorPagar
    {
        if ($this->cuenta_por_pagar_id) {
            $existente = CuentaPorPagar::query()->find($this->cuenta_por_pagar_id);
            if ($existente) {
                return $existente;
            }
        }

        $referencia = (string) $this->numero_documento;
        $cuenta = null;
        if (Schema::hasColumn('cuentas_por_pagar', 'origen') && Schema::hasColumn('cuentas_por_pagar', 'referencia')) {
            $cuenta = CuentaPorPagar::query()
                ->where('origen', self::ORIGEN_CUENTA)
                ->where('referencia', $referencia)
                ->first();
        }

        $deuda = round(max((float) $this->saldo_usd, 0), 2);
        if ($deuda <= 0.01) {
            $deuda = round(max((float) $this->total_usd, 0), 2);
        }

        if (! $cuenta) {
            $attrs = [
                'fecha' => $this->fecha?->toDateString() ?? now()->toDateString(),
                'beneficiario' => $this->proveedor ?: ($this->codigo_proveedor ?: 'Proveedor Suite'),
                'tipo_gasto' => '087 - PAGO PROVEEDORES',
                'motivo' => 'FAC '.$referencia.' Suite JRZ',
                'sede' => $this->sede ?: 'JRZ',
                'moneda' => 'USD',
                'monto_total' => $deuda,
                'monto_pagado' => 0,
                'saldo' => $deuda,
                'estado' => 'abierta',
                'created_by' => $userId,
            ];
            if (Schema::hasColumn('cuentas_por_pagar', 'origen')) {
                $attrs['origen'] = self::ORIGEN_CUENTA;
            }
            if (Schema::hasColumn('cuentas_por_pagar', 'referencia')) {
                $attrs['referencia'] = $referencia;
            }
            $cuenta = CuentaPorPagar::query()->create($attrs);
        }

        if (Schema::hasColumn('cuentas_por_pagar_suite', 'cuenta_por_pagar_id')) {
            $this->cuenta_por_pagar_id = $cuenta->id;
            $this->save();
        }

        return $cuenta;
    }
}
