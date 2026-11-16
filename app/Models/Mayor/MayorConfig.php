<?php

namespace App\Models\Mayor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class MayorConfig extends Model
{
    protected $table = 'mayor_config';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['clave', 'valor', 'updated_at'];

    public static function getDecimal(string $clave, float $default = 0): float
    {
        if (! Schema::hasTable('mayor_config')) {
            return $default;
        }
        $row = static::query()->find($clave);

        return $row !== null && $row->valor !== null && $row->valor !== ''
            ? round((float) $row->valor, 4)
            : $default;
    }

    public static function put(string $clave, string|float $valor): void
    {
        if (! Schema::hasTable('mayor_config')) {
            return;
        }
        static::query()->updateOrCreate(
            ['clave' => $clave],
            ['valor' => (string) $valor, 'updated_at' => now()]
        );
    }
}
