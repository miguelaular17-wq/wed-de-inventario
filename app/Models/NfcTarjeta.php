<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NfcTarjeta extends Model
{
    public const ACTIVA = 'ACTIVA';

    public const INACTIVA = 'INACTIVA';

    protected $table = 'nfc_tarjetas';

    protected $fillable = [
        'token',
        'uid',
        'cliente_nombre',
        'cliente_cedula',
        'cliente_telefono',
        'cliente_email',
        'notas',
        'saldo',
        'puntos',
        'estado',
        'asignada_at',
        'asignada_por',
        'ultimo_acceso_at',
    ];

    protected $casts = [
        'asignada_at' => 'datetime',
        'ultimo_acceso_at' => 'datetime',
        'saldo' => 'decimal:2',
        'puntos' => 'integer',
    ];

    public function asignador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignada_por');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(NfcMovimiento::class, 'nfc_tarjeta_id')->orderByDesc('id');
    }

    public function isActiva(): bool
    {
        return $this->estado === self::ACTIVA;
    }

    public function urlPublica(): string
    {
        return route('nfc.acceso', $this->token);
    }

    public static function generarToken(): string
    {
        do {
            $token = Str::lower(Str::random(12));
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }

    public function etiquetaEstado(): string
    {
        return $this->estado === self::ACTIVA ? 'Activa' : 'Inactiva';
    }
}
