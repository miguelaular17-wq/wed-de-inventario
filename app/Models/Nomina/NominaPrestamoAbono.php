<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaPrestamoAbono extends Model
{
    public const TIPO_NOMINA = 'DESCUENTO_NOMINA';
    public const TIPO_EFECTIVO = 'EFECTIVO';
    public const TIPO_TRANSFERENCIA = 'TRANSFERENCIA';
    public const TIPO_EXTRAORDINARIO = 'EXTRAORDINARIO';
    public const TIPO_AJUSTE = 'AJUSTE';

    protected $table = 'nomina_prestamo_abonos';

    protected $fillable = [
        'prestamo_id',
        'cuota_id',
        'fecha',
        'monto',
        'tipo',
        'usuario_id',
        'observacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(NominaPrestamo::class, 'prestamo_id');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(NominaPrestamoCuota::class, 'cuota_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public static function tipos(): array
    {
        return [
            self::TIPO_NOMINA => 'Descuento por nómina',
            self::TIPO_EFECTIVO => 'Pago en efectivo',
            self::TIPO_TRANSFERENCIA => 'Transferencia',
            self::TIPO_EXTRAORDINARIO => 'Abono extraordinario',
            self::TIPO_AJUSTE => 'Ajuste',
        ];
    }
}
