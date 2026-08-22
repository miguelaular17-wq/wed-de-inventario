<?php

namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;

class NominaConfig extends Model
{
    protected $table = 'nomina_config';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'clave',
        'valor',
        'updated_at',
    ];

    public static function getDecimal(string $clave, float $default = 0): float
    {
        $row = static::query()->find($clave);

        return $row ? round((float) $row->valor, 2) : $default;
    }

    public static function put(string $clave, string|float $valor): void
    {
        static::query()->updateOrCreate(
            ['clave' => $clave],
            [
                'valor' => (string) $valor,
                'updated_at' => now(),
            ]
        );
    }
}
