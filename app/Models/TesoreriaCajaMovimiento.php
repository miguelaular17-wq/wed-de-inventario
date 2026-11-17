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

    /** Misma nomenclatura que Flujo de Caja (Finanzas). */
    public const CATEGORIA_EGRESO_REALIZADO = 'egreso_realizado';

    public const CATEGORIA_EGRESO_DIVISAS = 'egreso_divisas';

    public const CATEGORIA_COMPRA_DIVISAS = 'compra_divisas';

    public const CATEGORIA_OTROS_EGRESOS = 'otros_egresos';

    public const CATEGORIA_TRASLADOS = 'traslados';

    public const CATEGORIA_INGRESOS = 'ingresos';

    public const CATEGORIAS = [
        self::CATEGORIA_EGRESO_REALIZADO => 'EGRESOS REALIZADOS',
        self::CATEGORIA_EGRESO_DIVISAS => 'EGRESOS EN DIVISAS',
        self::CATEGORIA_COMPRA_DIVISAS => 'COMPRA DE DIVISAS',
        self::CATEGORIA_OTROS_EGRESOS => 'OTROS EGRESOS (AVANCES Y CAMBIOS)',
        self::CATEGORIA_TRASLADOS => 'TRASLADOS',
        self::CATEGORIA_INGRESOS => 'INGRESOS',
    ];

    protected $table = 'tesoreria_caja_movimientos';

    protected $fillable = [
        'caja_id', 'tipo', 'categoria', 'origen', 'fecha', 'monto', 'moneda_divisa', 'concepto', 'referencia', 'sede', 'user_id',
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

    public static function tipoDesdeCategoria(string $categoria): string
    {
        return $categoria === self::CATEGORIA_INGRESOS ? self::INGRESO : self::EGRESO;
    }

    public function etiquetaCategoria(): string
    {
        $clave = (string) ($this->categoria ?: ($this->esIngreso() ? self::CATEGORIA_INGRESOS : self::CATEGORIA_EGRESO_REALIZADO));

        return self::CATEGORIAS[$clave] ?? strtoupper($clave);
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
