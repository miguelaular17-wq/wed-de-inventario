<?php

namespace App\Models\Mayor;

use App\Models\Nomina\NominaEmpleado;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MayorFactura extends Model
{
    public const ESTADO_EMITIDA = 'emitida';
    public const ESTADO_ANULADA = 'anulada';

    public const CONTADO = 'contado';
    public const CREDITO = 'credito';

    protected $table = 'mayor_facturas';

    protected $fillable = [
        'numero', 'fecha', 'cliente_id', 'vendedor_empleado_id', 'vendedor_nombre', 'user_id',
        'tasa_bcv', 'total_usd', 'total_bs', 'estado', 'condicion', 'fecha_vencimiento', 'en_profit', 'observaciones', 'anulada_motivo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_vencimiento' => 'date',
        'tasa_bcv' => 'float',
        'total_usd' => 'float',
        'total_bs' => 'float',
        'en_profit' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(MayorCliente::class, 'cliente_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'vendedor_empleado_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MayorFacturaItem::class, 'factura_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(MayorFacturaPago::class, 'factura_id')->orderBy('id');
    }

    public function numeroFormateado(): string
    {
        return 'MAY-'.str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    public function estaAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    /** Aparta stock de JRZ mientras esté emitida y aún no se haya cargado en Profit. */
    public function apartaStock(): bool
    {
        return $this->estado === self::ESTADO_EMITIDA && ! $this->en_profit;
    }

    public function totalPagadoUsd(): float
    {
        return round((float) $this->pagos->sum('monto_usd'), 2);
    }

    public function saldoUsd(): float
    {
        return max(0, round($this->total_usd - $this->totalPagadoUsd(), 2));
    }

    public function esCredito(): bool
    {
        return $this->condicion === self::CREDITO;
    }

    /** pagada | parcial | pendiente */
    public function estadoPago(): string
    {
        $pagado = $this->totalPagadoUsd();

        return match (true) {
            $this->saldoUsd() < 0.01 => 'pagada',
            $pagado > 0 => 'parcial',
            default => 'pendiente',
        };
    }

    public function estaVencida(): bool
    {
        return ! $this->estaAnulada() && $this->esCredito() && $this->saldoUsd() >= 0.01
            && $this->fecha_vencimiento && $this->fecha_vencimiento->lt(today());
    }
}
