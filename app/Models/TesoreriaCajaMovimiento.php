<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TesoreriaCajaMovimiento extends Model
{
    public const INGRESO = 'ingreso';

    public const EGRESO = 'egreso';

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_CUADRE_DIARIO = 'cuadre_diario';

    public const DIVISA_USD = 'USD';

    public const DIVISA_EUR = 'EUR';

    protected $table = 'tesoreria_caja_movimientos';

    protected $fillable = [
        'caja_id', 'tipo', 'origen', 'fecha', 'monto', 'moneda_divisa', 'concepto', 'referencia', 'sede', 'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'float',
    ];

    public function caja(): BelongsTo
    {
        return $this->belongsTo(TesoreriaCaja::class, 'caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TesoreriaCajaMovimientoDetalle::class, 'movimiento_id')->orderBy('sede')->orderBy('id');
    }

    public function esIngreso(): bool
    {
        return $this->tipo === self::INGRESO;
    }

    public function esDeCuadre(): bool
    {
        return $this->origen === self::ORIGEN_CUADRE_DIARIO;
    }

    public function signo(): float
    {
        return $this->esIngreso() ? 1.0 : -1.0;
    }

    public function monedaDivisa(): string
    {
        $m = strtoupper(trim((string) $this->moneda_divisa));

        return in_array($m, [self::DIVISA_USD, self::DIVISA_EUR], true) ? $m : self::DIVISA_USD;
    }

    public function formatoMonto(): string
    {
        $n = number_format((float) $this->monto, 2, ',', '.');
        if ($this->caja?->esBolivares()) {
            return 'Bs. '.$n;
        }

        return $this->monedaDivisa() === self::DIVISA_EUR ? '€'.$n : '$'.$n;
    }
}
