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

    /** Ingreso de efectivo registrado en Cobranza (EFECTIVO DIVISA / EFECTIVO BS). */
    public const ORIGEN_COBRANZA = 'cobranza';

    public const DIVISA_USD = 'USD';

    public const DIVISA_EUR = 'EUR';

    /** Categoría interna al guardar (compat con reportes / histórico). */
    public const CATEGORIA_EGRESO_REALIZADO = 'egreso_realizado';

    public const CATEGORIA_INGRESOS = 'ingresos';

    /** UI: solo dos tipos. */
    public const TIPOS = [
        self::EGRESO => 'Egreso',
        self::INGRESO => 'Ingreso',
    ];

    /** @deprecated Las categorías detalladas ya no se eligen en el modal. */
    public const CATEGORIAS = [
        self::CATEGORIA_EGRESO_REALIZADO => 'Egreso',
        self::CATEGORIA_INGRESOS => 'Ingreso',
    ];

    protected $table = 'tesoreria_caja_movimientos';

    protected $fillable = [
        'caja_id', 'tipo', 'categoria', 'tipo_gasto', 'origen', 'fecha', 'monto', 'moneda_divisa', 'concepto', 'referencia', 'beneficiario', 'sede', 'placa_vehiculo', 'user_id', 'flujo_caja_id',
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

    public function flujoCaja(): BelongsTo
    {
        return $this->belongsTo(FlujoCaja::class, 'flujo_caja_id');
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
        return $categoria === self::CATEGORIA_INGRESOS || $categoria === self::INGRESO
            ? self::INGRESO
            : self::EGRESO;
    }

    public static function categoriaDesdeTipo(string $tipo): string
    {
        return $tipo === self::INGRESO ? self::CATEGORIA_INGRESOS : self::CATEGORIA_EGRESO_REALIZADO;
    }

    public function etiquetaCategoria(): string
    {
        return $this->esIngreso() ? 'Ingreso' : 'Egreso';
    }

    public function esDeCuadre(): bool
    {
        return $this->origen === self::ORIGEN_CUADRE_DIARIO;
    }

    public function esDeCobranza(): bool
    {
        return $this->origen === self::ORIGEN_COBRANZA;
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
