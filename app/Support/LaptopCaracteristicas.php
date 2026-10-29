<?php

namespace App\Support;

/**
 * Extrae las características de una laptop/PC a partir del nombre del producto
 * (la base de datos no guarda la ficha técnica en columnas separadas).
 */
class LaptopCaracteristicas
{
    private const RAM_VALIDAS = [2, 4, 6, 8, 12, 16, 18, 24, 32, 36, 48, 64];

    /**
     * @return array<string, string> etiqueta => valor; vacío si el producto no es un equipo.
     */
    public static function extraer(?string $nombre, ?string $subcategoria = null): array
    {
        $texto = self::normalizar((string) $nombre);

        $esEquipo = preg_match('/LAPTO|COMPUTADOR|NOTEBOOK/', strtoupper((string) $subcategoria))
            || preg_match('/LAPTOP|LAPTO\b|LPATOP|MACBOOK|\bPC\b|ALL-IN-ONE|CHROMEBOOK/', $texto);
        if (! $esEquipo) {
            return [];
        }

        $procesador = self::procesador($texto);
        [$ram, $almacenamiento] = self::memoria($texto);

        // Bolsos, bases, monitores, etc. comparten subcategoría pero no tienen procesador ni RAM.
        if ($procesador === null && $ram === null) {
            return [];
        }

        return array_filter([
            'Procesador' => $procesador,
            'RAM' => $ram,
            'Almacenamiento' => $almacenamiento,
            'Pantalla' => self::pantalla($texto),
            'Gráficos' => self::graficos($texto),
        ], fn ($v) => $v !== null && $v !== '');
    }

    private static function normalizar(string $nombre): string
    {
        $t = mb_strtoupper($nombre, 'UTF-8');
        $t = str_replace(['”', '“', '″', "''"], '"', $t);
        $t = preg_replace('/\b(1\d),(\d)\b/', '$1.$2', $t);

        return preg_replace('/\s+/', ' ', $t);
    }

    private static function procesador(string $t): ?string
    {
        if (preg_match('/MACBOOK|APPLE/', $t) && preg_match('/\b(M[1-9]|A1\d)( (?:PRO|MAX))?\b/', $t, $m)) {
            return 'Apple '.$m[1].(isset($m[2]) ? ' '.ucfirst(strtolower(trim($m[2]))) : '');
        }

        if (preg_match('/CORE ULTRA ?I?([3579])(?:[ -]+(\d{3}(?! ?[GT]B)[A-Z]{0,2})\b)?/', $t, $m)) {
            return trim('Intel Core Ultra '.$m[1].' '.($m[2] ?? ''));
        }

        if (preg_match('/\bI([3579])(?:[ -]*(\d{4,5}[A-Z]{0,2}|N\d{3})\b|-(\d{1,2})TH\b)?\b/', $t, $m)) {
            $cpu = 'Intel Core i'.$m[1];
            if (! empty($m[2])) {
                $cpu .= '-'.$m[2];
            } elseif (! empty($m[3])) {
                $cpu .= ' ('.$m[3].'ª gen)';
            }

            return $cpu;
        }

        if (preg_match('/\bCORE ([3579])(?: (\d{3}(?! ?[GT]B)[A-Z]{0,2}))?\b/', $t, $m)) {
            return trim('Intel Core '.$m[1].' '.($m[2] ?? ''));
        }

        if (preg_match('/RYZEN (AI )?([3579])(?:[ -]+(\d{3,4}(?! ?[GT]B)[A-Z]{0,2})\b)?/', $t, $m)) {
            return trim('AMD Ryzen '.($m[1] !== '' ? 'AI ' : '').$m[2].' '.($m[3] ?? ''));
        }

        if (preg_match('/\b(CELERON )?(N\d{3,4})\b/', $t, $m)) {
            return 'Intel '.($m[1] !== '' ? 'Celeron ' : '').$m[2];
        }

        if (str_contains($t, 'CELERON')) {
            return 'Intel Celeron';
        }

        if (str_contains($t, 'PENTIUM')) {
            return 'Intel Pentium';
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string} [RAM, almacenamiento]
     */
    private static function memoria(string $t): array
    {
        $ram = null;
        $disco = null;

        // Formatos "16/512GB", "8GB/512GB", "4+128GB", "8/256", "16/1TB", "16/512G".
        if (preg_match('/\b(\d{1,2}) ?(?:GB)? ?[\/+] ?(\d{1,4}) ?(GB|TB|G)?\b/', $t, $m)
            && in_array((int) $m[1], self::RAM_VALIDAS, true)
            && ($discoPar = self::formatoDisco((int) $m[2], $m[3] ?? '')) !== null) {
            $ram = $m[1].' GB';
            $disco = $discoPar;
        }

        if ($ram === null || $disco === null) {
            preg_match_all('/\b(\d{1,4}) ?(GB|TB)\b/', $t, $todos, PREG_SET_ORDER);
            foreach ($todos as $m) {
                $n = (int) $m[1];
                if ($m[2] === 'GB' && in_array($n, self::RAM_VALIDAS, true)) {
                    $ram ??= $n.' GB';
                } else {
                    $disco ??= self::formatoDisco($n, $m[2]);
                }
            }
        }

        if ($disco === null && preg_match('/\b(\d{3,4}) ?(?:SSD|UFS|EMMC|HDD)\b/', $t, $m)) {
            $disco = self::formatoDisco((int) $m[1], 'GB');
        }

        if ($disco !== null && preg_match('/\b(SSD|UFS|EMMC|HDD)\b/', $t, $m)) {
            $disco .= ' '.($m[1] === 'EMMC' ? 'eMMC' : $m[1]);
        }

        return [$ram, $disco];
    }

    private static function formatoDisco(int $n, string $unidad): ?string
    {
        if ($unidad === 'TB') {
            return $n.' TB';
        }

        return $n >= 64 ? $n.' GB' : null;
    }

    private static function pantalla(string $t): ?string
    {
        $partes = [];

        if (preg_match('/(?<![\d.])(1[0-8](?:\.\d)?) ?(?:"|IN\b|IM\b|INCH|PULG)/', $t, $m)
            || preg_match('/(?<![\d.])(1[0-8]\.\d)(?![\d.])/', $t, $m)) {
            $partes[] = $m[1].'"';
        }

        if (preg_match('/\bFHD\b|FULL HD/', $t)) {
            $partes[] = 'Full HD';
        } elseif (preg_match('/\b(2K|QHD|WUXGA|OLED)\b/', $t, $m)) {
            $partes[] = $m[1];
        } elseif (preg_match('/\bHD\b/', $t)) {
            $partes[] = 'HD';
        }

        if (preg_match('/(\d{2,3}) ?HZ\b/', $t, $m)) {
            $partes[] = $m[1].' Hz';
        }

        if (preg_match('/TOUCH|TACTIL|TÁCTIL/', $t)) {
            $partes[] = 'táctil';
        }

        if (preg_match('/X360|2-?IN-?1|2EN1|\bFLIP\b/', $t)) {
            $partes[] = '2 en 1';
        }

        return $partes === [] ? null : implode(' · ', $partes);
    }

    private static function graficos(string $t): ?string
    {
        if (preg_match('/\b(RTX|GTX) ?(\d{4}(?: ?TI)?)?/', $t, $m)) {
            return trim('NVIDIA '.$m[1].' '.($m[2] ?? ''));
        }

        if (str_contains($t, 'IRIS XE')) {
            return 'Intel Iris Xe';
        }

        if (preg_match('/UHD GRAPHICS/', $t)) {
            return 'Intel UHD Graphics';
        }

        return null;
    }
}
