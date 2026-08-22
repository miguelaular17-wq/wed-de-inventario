<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaEmpleadoVendedor extends Model
{
    protected $table = 'nomina_empleado_vendedores';

    protected $fillable = [
        'empleado_id',
        'nombre_vendedor',
        'nombre_normalizado',
        'codigo_profit',
        'sede',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NominaEmpleado::class, 'empleado_id');
    }

    public static function normalizar(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? '');

        return mb_strtoupper($nombre, 'UTF-8');
    }
}
