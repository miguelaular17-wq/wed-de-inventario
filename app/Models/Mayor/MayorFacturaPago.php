<?php

namespace App\Models\Mayor;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Comprobante de pago de una factura al mayor. */
class MayorFacturaPago extends Model
{
    protected $table = 'mayor_factura_pagos';

    protected $fillable = ['factura_id', 'fecha', 'url', 'nombre_archivo', 'forma_pago', 'referencia', 'monto_usd', 'user_id'];

    protected $casts = [
        'fecha' => 'date',
        'monto_usd' => 'float',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(MayorFactura::class, 'factura_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function esImagen(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/i', (string) $this->url);
    }
}
