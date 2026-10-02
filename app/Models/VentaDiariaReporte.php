<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VentaDiariaReporte extends Model
{
    protected $table = 'venta_diaria_reportes';

    protected $fillable = [
        'sede',
        'fecha',
        'tasa',
        'divisas_efectivo',
        'efectivo_bs',
        'punto_venta_bs',
        'transf_pm_bs',
        'zelle_binance',
        'cashea',
        'abonos',
        'iphone',
        'gift_card',
        'total_creditos',
        'z_fiscal_bs',
        'productos_vendidos',
        'deliverys_pendientes',
        'fondo_bs',
        'fondo_divisas',
        'observaciones',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'tasa' => 'decimal:4',
        'divisas_efectivo' => 'decimal:2',
        'efectivo_bs' => 'decimal:2',
        'punto_venta_bs' => 'decimal:2',
        'transf_pm_bs' => 'decimal:2',
        'zelle_binance' => 'decimal:2',
        'cashea' => 'decimal:2',
        'abonos' => 'decimal:2',
        'iphone' => 'decimal:2',
        'gift_card' => 'decimal:2',
        'total_creditos' => 'decimal:2',
        'z_fiscal_bs' => 'decimal:2',
        'productos_vendidos' => 'decimal:2',
        'deliverys_pendientes' => 'decimal:2',
        'fondo_bs' => 'decimal:2',
        'fondo_divisas' => 'decimal:2',
    ];

    public function cajas(): HasMany
    {
        return $this->hasMany(VentaDiariaCaja::class, 'reporte_id')->orderBy('orden')->orderBy('id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
