<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marcas de los equipos de servicio técnico: una sola forma de escribir cada marca
 * (mayúsculas, sin espacios de más y variantes unificadas) y la lista para elegir al registrar.
 */
class MarcasServicio
{
    public const OTRA = '__OTRA__';

    /** Marca escrita (en mayúsculas) => [marca correcta, texto que se antepone al modelo o null]. */
    public const MAPA = [
        'CUBOTT' => ['CUBOT', null],
        'IPHONE' => ['APPLE', 'IPHONE'],
        'IPHONE S/NUEVOS' => ['APPLE', 'IPHONE'],
        'TECNO SPARK' => ['TECNO', 'SPARK'],
        'ALEXA' => ['AMAZON', 'ALEXA'],
        'POWERBEATS PRO' => ['BEATS', 'POWERBEATS PRO'],
        'SAMSUNG Y INIFNIX' => ['SAMSUNG', null],
        'N/A' => ['GENERICO', null],
        'GENÉRICO' => ['GENERICO', null],
        'HEADSET' => ['GENERICO', 'HEADSET'],
        'SMARTWATCH' => ['GENERICO', 'SMARTWATCH'],
        'X10 ULTRA' => ['GENERICO', 'X10 ULTRA'],
        'M9' => ['GENERICO', 'M9'],
        'EAAYSMX' => ['GENERICO', 'EAAYSMX'],
    ];

    /**
     * @return array{0: ?string, 1: ?string} [marca, modelo]
     */
    public static function normalizar(?string $marca, ?string $modelo): array
    {
        $limpia = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $marca)), 'UTF-8');
        if ($limpia === '') {
            return [$marca, $modelo];
        }

        [$nueva, $prefijo] = self::MAPA[$limpia] ?? [$limpia, null];
        $modelo = trim((string) $modelo);
        if ($prefijo !== null && in_array(mb_strtoupper($modelo, 'UTF-8'), ['N/A', 'NA', '-'], true)) {
            $modelo = '';
        }
        if ($prefijo !== null && ! str_contains(mb_strtoupper($modelo, 'UTF-8'), $prefijo)) {
            $modelo = trim($prefijo.' '.$modelo);
        }

        return [$nueva, $modelo === '' ? null : $modelo];
    }

    /**
     * Marcas registradas en los equipos, ya normalizadas y en orden alfabético.
     *
     * @return list<string>
     */
    public static function disponibles(): array
    {
        if (! Schema::hasTable('st_equipos')) {
            return [];
        }

        return DB::table('st_equipos')
            ->whereNotNull('marca')
            ->where('marca', '!=', '')
            ->distinct()
            ->pluck('marca')
            ->map(fn ($m) => self::normalizar((string) $m, null)[0])
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL)
            ->values()
            ->all();
    }
}
