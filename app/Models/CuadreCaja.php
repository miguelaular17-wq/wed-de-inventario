<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un día de una caja con una cajera y lo que cobró por forma de pago, subido por el sincronizador de la sede.
 * id_cierre es el cierre de Suite; es nulo mientras Suite no lo registra.
 *
 * Tesorería la deja pendiente, procesada (se puede reabrir) o cerrada (definitivo, con motivo).
 */
class CuadreCaja extends Model
{
    public const ESTADO_APROBADO = 2;

    public const PENDIENTE = 'pendiente';

    public const PROCESADO = 'procesado';

    public const CERRADO = 'cerrado';

    protected $table = 'cuadres_caja';

    protected $guarded = [];

    protected $casts = [
        'fecha_cierre' => 'date',
        'registrado_at' => 'datetime',
        'conciliado_at' => 'datetime',
        'reabierto_at' => 'datetime',
        'estado' => 'integer',
        'factor_cambio' => 'float',
    ];

    public function pagos(): HasMany
    {
        return $this->hasMany(CuadreCajaPago::class)->orderBy('id');
    }

    public function conteos(): HasMany
    {
        return $this->hasMany(CuadreCajaConteo::class)->orderBy('id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(CuadreCajaLote::class)->orderBy('id');
    }

    public function conciliador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conciliado_por');
    }

    public function reabridor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reabierto_por');
    }

    public function conciliado(): bool
    {
        return $this->conciliado_at !== null;
    }

    public function estadoTesoreria(): string
    {
        if ($this->estado_tesoreria === self::CERRADO || $this->estado_tesoreria === self::PROCESADO) {
            return $this->estado_tesoreria;
        }

        return $this->conciliado() ? self::PROCESADO : self::PENDIENTE;
    }

    public function pendiente(): bool
    {
        return $this->estadoTesoreria() === self::PENDIENTE;
    }

    public function procesado(): bool
    {
        return $this->estadoTesoreria() === self::PROCESADO;
    }

    public function cerrado(): bool
    {
        return $this->estadoTesoreria() === self::CERRADO;
    }

    public function motivoCierreTexto(): ?string
    {
        if (! $this->motivo_cierre) {
            return null;
        }

        return config('cuadre_caja.motivos_cierre')[$this->motivo_cierre] ?? $this->motivo_cierre;
    }
}
