<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cierre de caja de Suite con sus ingresos por forma de pago, subido por el sincronizador de la sede. */
class CuadreCaja extends Model
{
    public const ESTADO_APROBADO = 2;

    protected $table = 'cuadres_caja';

    protected $guarded = [];

    protected $casts = [
        'fecha_cierre' => 'date',
        'registrado_at' => 'datetime',
        'conciliado_at' => 'datetime',
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

    public function conciliador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conciliado_por');
    }

    public function conciliado(): bool
    {
        return $this->conciliado_at !== null;
    }
}
