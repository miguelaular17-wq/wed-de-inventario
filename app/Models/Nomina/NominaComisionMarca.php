<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaComisionMarca extends Model
{
    public const MARCA_SAMSUNG = 'SAMSUNG';

    public const MARCA_HONOR = 'HONOR';

    public const MARCAS = [
        self::MARCA_SAMSUNG => 'Samsung',
        self::MARCA_HONOR => 'Honor',
    ];

    protected $table = 'nomina_comisiones_marca';

    protected $fillable = [
        'nomina_empleado_id',
        'marca',
        'fecha',
        'monto_bs',
        'tasa',
        'monto_usd',
        'nota',
        'user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_bs' => 'decimal:2',
        'tasa' => 'decimal:4',
        'monto_usd' => 'decimal:2',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'nomina_empleado_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function nombreMarca(): string
    {
        return self::MARCAS[$this->marca] ?? $this->marca;
    }
}
