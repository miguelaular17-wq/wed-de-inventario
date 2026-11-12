<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Asiento contable del flujo de caja de Cobranza: líneas debe/haber que cuadran, sin mover dinero. */
class CobranzaAsiento extends Model
{
    /** Cuentas contables sugeridas además de las del catálogo de bancos. */
    public const CUENTAS_CONTABLES = [
        'CUENTAS POR COBRAR · COBRANZA',
        'CUENTAS POR COBRAR · CONTRATOS',
        'CUENTAS POR COBRAR · PATRIMONIAL',
        'INGRESOS · COBRANZA',
        'INGRESOS · CONTRATOS',
        'INGRESOS · PATRIMONIAL',
        'GASTOS · COBRANZA',
        'GASTOS · PATRIMONIAL',
        'CAPITAL PRESTADO · CONTRATOS',
        'AJUSTES Y DIFERENCIAS',
    ];

    protected $table = 'cobranza_asientos';

    protected $fillable = ['fecha', 'descripcion', 'ambito', 'user_id', 'movimiento_id'];

    protected $casts = ['fecha' => 'date'];

    public function lineas(): HasMany
    {
        return $this->hasMany(CobranzaAsientoLinea::class, 'asiento_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(CobranzaMovimiento::class, 'movimiento_id');
    }
}
