<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ingreso o egreso del flujo de caja de Cobranza (cobranza, contratos o patrimonial).
 * Si la cuenta es un banco real, tiene una copia oculta en flujo_cajas para la conciliación bancaria.
 */
class CobranzaMovimiento extends Model
{
    public const TIPO_INGRESO = 'ingreso';

    public const TIPO_EGRESO = 'egreso';

    public const AMBITOS = [
        'cobranza' => 'Cobranza',
        'contratos' => 'Contratos',
        'patrimonial' => 'Patrimonial',
        'general' => 'General',
    ];

    /** Cuentas de cruce: el dinero no pasa por un banco real. Banco Cruzado es de los avances de Finanzas. */
    public const BANCOS_CRUCE = ['BANCO CRUZADO', 'BANCO CRUCE COBRANZA'];

    /** La cuenta de cruce de Cobranza (Banco Cruzado es de avances y no se usa aquí). */
    public const BANCO_CRUCE_POR_DEFECTO = 'BANCO CRUCE COBRANZA';

    public const BANCO_CRUCE_AVANCES = 'BANCO CRUZADO';

    /** Dinero que no pasa por un banco (no va a la conciliación). */
    public const CUENTA_POR_IDENTIFICAR = 'POR IDENTIFICAR';

    public const CUENTA_DESCUENTO_DIVISAS = 'DESCUENTO EN DIVISAS';

    public const CUENTAS_SIN_BANCO = ['EFECTIVO', 'REGALÍA', self::CUENTA_DESCUENTO_DIVISAS, self::CUENTA_POR_IDENTIFICAR];

    public const FORMAS_PAGO = ['ZELLE', 'EFECTIVO', 'TRANSFERENCIA_DIVISAS', 'TRANSFERENCIA_BCV', 'PAGO_MOVIL', 'DEPOSITO', 'BINANCE', 'CRUCE', 'DESCUENTO_DIVISAS'];

    public const GRUPO_SIN_BANCO = 'SIN BANCO';

    public const VINCULO_CUOTA = 'contrato_cuota';

    public const VINCULO_ALQUILER = 'alquiler_pago';

    public const VINCULO_RESERVA = 'reserva';

    /** Ingreso o gasto de una propiedad (mantenimiento, servicios…): crea su transacción en Patrimonial. */
    public const VINCULO_PAT_TRANSACCION = 'pat_transaccion';

    /** Gasto o ingreso dividido entre varias propiedades: una transacción de Patrimonial por propiedad. */
    public const VINCULO_PAT_REPARTO = 'pat_reparto';

    protected $table = 'cobranza_movimientos';

    protected $fillable = [
        'fecha', 'tipo', 'ambito', 'banco', 'titular', 'categoria_cuenta',
        'monto_usd', 'tasa_cambio', 'monto_bs', 'referencia', 'concepto', 'contraparte', 'observaciones', 'forma_pago', 'tipo_gasto', 'comprobantes',
        'cruce_id', 'vinculo_tipo', 'vinculo_id', 'flujo_caja_id', 'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_usd' => 'decimal:2',
        'tasa_cambio' => 'decimal:4',
        'monto_bs' => 'decimal:2',
        'comprobantes' => 'array',
    ];

    public static function esBancoCruce(?string $banco): bool
    {
        return in_array(mb_strtoupper(trim((string) $banco), 'UTF-8'), self::BANCOS_CRUCE, true);
    }

    public static function esCruceCobranza(?string $banco): bool
    {
        return mb_strtoupper(trim((string) $banco), 'UTF-8') === self::BANCO_CRUCE_POR_DEFECTO;
    }

    public function esIngreso(): bool
    {
        return $this->tipo === self::TIPO_INGRESO;
    }

    public function esCruce(): bool
    {
        return (string) $this->cruce_id !== '';
    }

    public function nombreAmbito(): string
    {
        return self::AMBITOS[$this->ambito] ?? (string) $this->ambito;
    }

    public function flujoCaja(): BelongsTo
    {
        return $this->belongsTo(FlujoCaja::class, 'flujo_caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** La otra mitad del cruce (el egreso de un ingreso, o al revés). */
    public function contraparteCruce(): ?self
    {
        if (! $this->esCruce()) {
            return null;
        }

        return self::query()->where('cruce_id', $this->cruce_id)->where('id', '!=', $this->id)->first();
    }

    public function estaConciliado(): bool
    {
        return (bool) ($this->flujoCaja?->es_conciliado);
    }

    /** «Efectivo», «Transferencia $ · Banesco Grupo JRZ» o «Cruce · se fue en: Pago proveedor X (Proveedor X)». */
    public function comoSePago(): string
    {
        $formas = [
            'ZELLE' => 'Zelle', 'EFECTIVO' => 'Efectivo', 'TRANSFERENCIA_DIVISAS' => 'Transferencia $', 'TRANSFERENCIA_BCV' => 'Transferencia Bs',
            'PAGO_MOVIL' => 'Pago móvil', 'DEPOSITO' => 'Depósito', 'BINANCE' => 'Binance', 'CRUCE' => 'Cruce', 'DESCUENTO_DIVISAS' => 'Descuento en divisas',
        ];
        $banco = trim((string) $this->banco);
        $bancoUpper = mb_strtoupper($banco, 'UTF-8');

        if ($this->esCruce() || self::esBancoCruce($banco) || $this->forma_pago === 'CRUCE') {
            $otra = $this->contraparteCruce();
            if (! $otra) {
                return 'Cruce';
            }
            $destino = trim((string) $otra->concepto);
            $quien = trim((string) $otra->contraparte);

            return 'Cruce · se fue en: '.($destino !== '' ? $destino : 'sin detalle').($quien !== '' && $quien !== $destino ? ' ('.$quien.')' : '');
        }

        $forma = $formas[strtoupper((string) $this->forma_pago)] ?? match (true) {
            $bancoUpper === 'EFECTIVO' => 'Efectivo',
            in_array($bancoUpper, self::CUENTAS_SIN_BANCO, true) => mb_convert_case($banco, MB_CASE_TITLE, 'UTF-8'),
            default => (float) $this->monto_bs > 0 ? 'Transferencia Bs' : 'Transferencia $',
        };
        $cuenta = in_array($bancoUpper, self::CUENTAS_SIN_BANCO, true) ? '' : trim($banco.' '.trim((string) $this->titular));

        return $cuenta !== '' && $forma !== 'Efectivo' ? $forma.' · '.$cuenta : $forma;
    }

    /** A qué factura, cuota, alquiler, reserva o propiedad pertenece, en una línea. */
    public function descripcionVinculo(): ?string
    {
        $d = app(\App\Services\Cobranza\CobranzaVinculos::class)->describir(collect([$this]))[$this->id] ?? null;

        return $d ? $d['titulo'].' · '.$d['detalle'] : null;
    }
}
