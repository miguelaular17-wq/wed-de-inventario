<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NominaPeriodo extends Model
{
    public const ABIERTO = 'ABIERTO';
    public const CALCULADO = 'CALCULADO';
    public const APROBADO = 'APROBADO';
    public const PAGADO = 'PAGADO';
    public const CERRADO = 'CERRADO';

    protected $table = 'nomina_periodos';

    protected $fillable = [
        'fecha_inicio',
        'fecha_fin',
        'fecha_pago_comision',
        'etiqueta',
        'estado',
        'calculado_at',
        'calculado_por',
        'aprobado_at',
        'aprobado_por',
        'pagado_at',
        'pagado_por',
        'cerrado_at',
        'cerrado_por',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_pago_comision' => 'date',
        'calculado_at' => 'datetime',
        'aprobado_at' => 'datetime',
        'pagado_at' => 'datetime',
        'cerrado_at' => 'datetime',
    ];

    public function liquidacionesComision(): HasMany
    {
        return $this->hasMany(NominaLiquidacionComision::class, 'periodo_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(NominaRegistro::class, 'periodo_id');
    }

    public function calculadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculado_por');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function pagadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pagado_por');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public static function estados(): array
    {
        return [
            self::ABIERTO,
            self::CALCULADO,
            self::APROBADO,
            self::PAGADO,
            self::CERRADO,
        ];
    }
}
