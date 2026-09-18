<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NfcMovimiento extends Model
{
    public const RECARGA = 'RECARGA';

    public const GASTO = 'GASTO';

    public const PUNTOS = 'PUNTOS';

    public const RESTAR_PUNTOS = 'RESTAR_PUNTOS';

    public const CANJE = 'CANJE';

    protected $table = 'nfc_movimientos';

    protected $fillable = [
        'nfc_tarjeta_id',
        'nfc_recompensa_id',
        'tipo',
        'monto',
        'puntos',
        'saldo_despues',
        'puntos_despues',
        'concepto',
        'registrado_por',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_despues' => 'decimal:2',
        'puntos' => 'integer',
        'puntos_despues' => 'integer',
    ];

    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(NfcTarjeta::class, 'nfc_tarjeta_id');
    }

    public function recompensa(): BelongsTo
    {
        return $this->belongsTo(NfcRecompensa::class, 'nfc_recompensa_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function etiquetaTipo(): string
    {
        return match ($this->tipo) {
            self::RECARGA => 'Recarga',
            self::GASTO => 'Gasto',
            self::PUNTOS => 'Puntos +',
            self::RESTAR_PUNTOS => 'Puntos −',
            self::CANJE => 'Canje',
            default => $this->tipo,
        };
    }
}
