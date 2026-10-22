<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Productos para etiquetas de precio (hablador de anaquel): búsqueda por nombre o código
 * escaneado y armado de las fichas que se imprimen.
 */
class EtiquetasPrecioService
{
    public const MAX_ETIQUETAS = 600;

    public const TAMANOS = [
        'normal' => ['columnas' => 2, 'filas' => 5, 'ancho' => 97, 'alto' => 50],
        'pequena' => ['columnas' => 3, 'filas' => 7, 'ancho' => 64, 'alto' => 35],
    ];

    /** Tipos de ficha. "locura" y "pc" solo existen en tamaño normal. */
    public const TIPOS = [
        'clasica' => 'Clásica (1 pza / mayor)',
        'locura' => 'Precios de locura',
        'pc' => 'PC / Laptop (ficha técnica)',
    ];

    public const WEB = 'www.elpalaciodelosdetalles.com';

    public const SPECS_PC = ['marca', 'modelo', 'procesador', 'grafica', 'pantalla', 'ram', 'almacenamiento', 'sistema'];

    /** Logos en public/img/marcas/{clave}.png */
    public const LOGOS_MARCA = [
        'acer', 'apple', 'asus', 'dell', 'hp', 'lenovo', 'victus',
        'samsung', 'xiaomi', 'poco', 'tecno', 'infinix', 'honor', 'blu', 'cubot', 'cubitt',
    ];

    private const MARCAS_PC = [
        'DELL', 'HP', 'LENOVO', 'ASUS', 'ACER', 'APPLE', 'MSI', 'HUAWEI', 'SAMSUNG', 'TOSHIBA',
        'GATEWAY', 'CHUWI', 'BLACKVIEW', 'SIRAGON', 'VIT', 'ALCATEL', 'XIAOMI', 'MICROSOFT', 'GIGABYTE', 'ITEL',
        'REDMI', 'POCO', 'TECNO', 'INFINIX', 'HONOR', 'BLU', 'CUBOT', 'CUBITT', 'MOTOROLA', 'ZTE', 'OPPO', 'REALME', 'VIVO', 'NOKIA',
    ];

    /**
     * @return array{exacto: ?array<string, mixed>, resultados: list<array<string, mixed>>}
     */
    public function buscar(string $q, int $limite = 25): array
    {
        $q = trim($q);
        if ($q === '') {
            return ['exacto' => null, 'resultados' => []];
        }

        $qLower = mb_strtolower($q, 'UTF-8');
        $sinNumeral = ltrim($qLower, '#');
        $palabras = array_values(array_filter(preg_split('/\s+/u', $qLower) ?: [], fn ($p) => $p !== ''));

        $rows = $this->baseQuery()
            ->where(function ($w) use ($sinNumeral, $palabras) {
                $w->whereRaw('LOWER(codigo) LIKE ?', ['%' . $this->escapeLike($sinNumeral) . '%'])
                    ->orWhere(function ($n) use ($palabras) {
                        foreach ($palabras as $p) {
                            $n->whereRaw('LOWER(nombre) LIKE ?', ['%' . $this->escapeLike($p) . '%']);
                        }
                    });
            })
            ->orderBy('nombre')
            ->limit(200)
            ->get();

        $items = $rows->map(fn ($r) => $this->mapear($r) + ['specs' => $this->sugerirSpecsPc((string) $r->nombre)]);

        $exactos = $items->filter(fn ($p) => $this->coincideCodigo($p['codigo'], $sinNumeral))->values();

        $ordenados = $exactos
            ->concat($items->reject(fn ($p) => $this->coincideCodigo($p['codigo'], $sinNumeral)))
            ->take($limite)
            ->values()
            ->all();

        return [
            'exacto' => $exactos->count() === 1 ? $exactos->first() : null,
            'resultados' => $ordenados,
        ];
    }

    /**
     * @param  list<array{id: int, copias: int, specs?: array<string, ?string>}>  $items
     * @return list<array<string, mixed>>  Una entrada por etiqueta a imprimir, en el orden de la lista.
     */
    public function armarEtiquetas(array $items, int $cantidadMayor): array
    {
        $ids = array_values(array_unique(array_map(fn ($i) => (int) $i['id'], $items)));
        if ($ids === []) {
            return [];
        }

        $productos = $this->baseQuery()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->id => $this->mapear($r)]);

        $etiquetas = [];
        foreach ($items as $item) {
            $producto = $productos[(int) $item['id']] ?? null;
            if (! $producto) {
                continue;
            }
            $ficha = $this->ficha($producto, $cantidadMayor);
            $ficha['specs'] = $this->specsFinales($producto['nombre'], $item['specs'] ?? null);
            $ficha['logo_marca'] = $this->logoMarca($ficha['specs'], $producto['nombre']);
            $copias = max(1, min(100, (int) $item['copias']));
            for ($i = 0; $i < $copias && count($etiquetas) < self::MAX_ETIQUETAS; $i++) {
                $etiquetas[] = $ficha;
            }
        }

        return $etiquetas;
    }

    /**
     * @param  array<string, mixed>  $producto
     * @return array<string, mixed>
     */
    public function ficha(array $producto, int $cantidadMayor): array
    {
        $unidad = $this->redondearArriba((float) $producto['precio_unidad']);
        $mayor = $this->redondearArriba((float) $producto['precio_mayor']);
        // Solo mostrar mayor si, ya redondeados, sigue siendo más barato.
        $conMayor = $mayor > 0 && ($unidad <= 0 || $mayor < $unidad);
        $ahorro = $conMayor && $unidad > 0 ? (int) round((1 - $mayor / $unidad) * 100) : 0;

        return array_merge($producto, [
            'precio_unidad' => $unidad,
            'precio_mayor' => $mayor,
            'con_mayor' => $conMayor,
            'cantidad_mayor' => $cantidadMayor,
            'ahorro_pct' => $ahorro,
            'unidad_fmt' => $this->formatoPrecio($unidad),
            'mayor_fmt' => $this->formatoPrecio($mayor),
            'unidad_entero_fmt' => $this->formatoPrecioEntero($unidad),
        ]);
    }

    /** Precios de etiqueta: siempre hacia arriba al entero (26,66 → 27). */
    public function redondearArriba(float $valor): float
    {
        if ($valor <= 0) {
            return 0.0;
        }

        return (float) ceil(round($valor, 2));
    }

    /**
     * Elige el tamaño de letra más grande con el que el nombre cabe entero en la caja
     * y devuelve las líneas ya cortadas (Dompdf no reajusta el texto por su cuenta).
     *
     * @param  callable(string, float): float  $anchoPt  ancho en pt de un texto a un tamaño dado
     * @param  callable(float): float  $altoLineaPt  alto real de una línea en pt a un tamaño dado
     * @param  list<float>  $tamanosPt  de mayor a menor
     * @return array{fs: float, alto_linea: float, lineas: list<string>}
     */
    public function ajustarNombre(string $nombre, callable $anchoPt, callable $altoLineaPt, float $anchoMm, float $altoMm, array $tamanosPt, int $maxLineas = 3, bool $mayusculas = true): array
    {
        $anchoMaxPt = $anchoMm * 72 / 25.4;
        $altoMaxPt = $altoMm * 72 / 25.4;
        $texto = trim(preg_replace('/\s+/u', ' ', $nombre) ?? $nombre);
        if ($mayusculas) {
            $texto = mb_strtoupper($texto, 'UTF-8');
        }

        foreach ($tamanosPt as $fs) {
            $lineas = $this->partirEnLineas($texto, fn (string $t) => $anchoPt($t, $fs), $anchoMaxPt);
            $caben = (int) floor($altoMaxPt / $altoLineaPt($fs));
            if (count($lineas) <= min($maxLineas, max(1, $caben))) {
                return ['fs' => $fs, 'alto_linea' => $altoLineaPt($fs), 'lineas' => $lineas];
            }
        }

        $fs = end($tamanosPt);
        $medir = fn (string $t) => $anchoPt($t, $fs);
        $limite = min($maxLineas, max(1, (int) floor($altoMaxPt / $altoLineaPt($fs))));
        $lineas = array_slice($this->partirEnLineas($texto, $medir, $anchoMaxPt), 0, $limite);
        $ultima = rtrim($lineas[$limite - 1] ?? '');
        while ($ultima !== '' && $medir($ultima . '…') > $anchoMaxPt) {
            $ultima = rtrim(mb_substr($ultima, 0, -1));
        }
        $lineas[$limite - 1] = $ultima . '…';

        return ['fs' => $fs, 'alto_linea' => $altoLineaPt($fs), 'lineas' => $lineas];
    }

    /**
     * @param  callable(string): float  $medir
     * @return list<string>
     */
    private function partirEnLineas(string $texto, callable $medir, float $anchoMaxPt): array
    {
        $lineas = [];
        $actual = '';
        foreach (explode(' ', $texto) as $palabra) {
            while ($medir($palabra) > $anchoMaxPt && mb_strlen($palabra) > 1) {
                if ($actual !== '') {
                    $lineas[] = $actual;
                    $actual = '';
                }
                $corte = mb_strlen($palabra) - 1;
                while ($corte > 1 && $medir(mb_substr($palabra, 0, $corte)) > $anchoMaxPt) {
                    $corte--;
                }
                $lineas[] = mb_substr($palabra, 0, $corte);
                $palabra = mb_substr($palabra, $corte);
            }
            $prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
            if ($actual !== '' && $medir($prueba) > $anchoMaxPt) {
                $lineas[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $prueba;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }

        return $lineas;
    }

    /**
     * Lee del nombre del producto lo que se pueda de la ficha técnica de una PC/laptop.
     * Es solo una sugerencia: en la lista se corrige a mano antes de imprimir.
     *
     * @return array<string, string>
     */
    public function sugerirSpecsPc(string $nombre): array
    {
        $n = ' ' . mb_strtoupper(preg_replace('/\s+/u', ' ', $nombre) ?? $nombre, 'UTF-8') . ' ';
        $specs = array_fill_keys(self::SPECS_PC, '');
        $usado = [];

        $mejorPos = null;
        foreach (self::MARCAS_PC as $marca) {
            if (preg_match('/[\s\/(]' . preg_quote($marca, '/') . '[\s\/)]/u', $n, $m, PREG_OFFSET_CAPTURE)
                && ($mejorPos === null || $m[0][1] < $mejorPos)) {
                $mejorPos = $m[0][1];
                $specs['marca'] = $marca;
            }
        }
        if ($specs['marca'] !== '') {
            $usado[] = $specs['marca'];
        }
        if ($specs['marca'] === '' && preg_match('/\b(MACBOOK|IMAC)\b/u', $n)) {
            $specs['marca'] = 'APPLE';
        }

        if (preg_match('/\b(?:INTEL\s+)?(?:CORE\s+)?(?:(I[3579])(?:[\s-]?(\d{4,5}[A-Z]{0,2}))?|(ULTRA\s?[579])\s?(\d{3}[A-Z]{0,2})?|(CELERON|PENTIUM)\s?([A-Z]?\d{4}[A-Z]?)?|(N\d{3,4}))\b/u', $n, $m)) {
            $usado[] = $m[0];
            if (($m[1] ?? '') !== '' && ($m[2] ?? '') === '') {
                $specs['procesador'] = 'INTEL CORE ' . $m[1];
            } else {
                $specs['procesador'] = 'INTEL ' . trim(preg_replace('/^INTEL\s+|^CORE\s+/u', '', trim($m[0])) ?? '');
                $specs['procesador'] = preg_replace('/^INTEL (?:CORE )?(I[3579])\s?-?(\d)/u', 'INTEL $1-$2', $specs['procesador']) ?? $specs['procesador'];
            }
        } elseif (preg_match('/\b(?:AMD\s+)?RYZEN\s?[3579](?:\s?\d{4}[A-Z]{0,2})?\b|\bAPPLE\s+M[1-4](?:\s(?:PRO|MAX))?\b|\bM[1-4](?:\s(?:PRO|MAX))?\b(?=.*\b(?:MACBOOK|APPLE)\b)/u', $n, $m)) {
            $usado[] = $m[0];
            $specs['procesador'] = trim($m[0]);
        }

        if (preg_match('/\b(?:NVIDIA\s+)?(?:GEFORCE\s+)?(RTX|GTX|MX)\s?(\d{3,4}(?:\s?TI)?)\b|\b(?:AMD\s+)?RADEON\s+(?:RX\s?)?\d{3,4}[A-Z]{0,2}\b|\bINTEL\s+ARC(?:\s+[A-Z]?\d{3})?\b/u', $n, $m)) {
            $usado[] = $m[0];
            $specs['grafica'] = ($m[1] ?? '') !== '' ? 'NVIDIA ' . $m[1] . ' ' . $m[2] : trim($m[0]);
        }

        $esCelular = in_array($specs['marca'], ['REDMI', 'POCO', 'TECNO', 'INFINIX', 'HONOR', 'BLU', 'CUBOT', 'CUBITT', 'ITEL', 'MOTOROLA', 'ZTE', 'OPPO', 'REALME', 'VIVO', 'NOKIA', 'ALCATEL'], true)
            || (! preg_match('/\b(LAPTOP|NOTEBOOK|COMPUTADORA|PORTATIL|PORTÁTIL|CHROMEBOOK|CHROMEBASE|ALL[- ]IN[- ]ONE|AIO|MACBOOK|IMAC|DESKTOP)\b/u', $n)
                && preg_match('/\b(GALAXY|REDMI|SMARTPHONE|CELULAR|TELEFONO|TELÉFONO|5G|4G)\b/u', $n));

        if (preg_match('/(?<![\d.])(\d{1,2})\s?(?:GB)?\s?[+\/]\s?(\d{2,4})\s?(GB|TB)\b/u', $n, $m) && (int) $m[1] <= 32) {
            $usado[] = $m[0];
            $specs['ram'] = $m[1] . 'GB RAM';
            $specs['almacenamiento'] = $m[2] . $m[3] . ($esCelular ? '' : ' SSD');
        } elseif (preg_match('/(?<![\d+])(\d{1,2})\s?GB\s?(?:RAM\s?)?((?:LP)?DDR\d[X]?)?\b/u', $n, $m) && (int) $m[1] <= 64) {
            $usado[] = $m[0];
            $specs['ram'] = trim($m[1] . 'GB ' . ($m[2] ?? '')) ?: $m[1] . 'GB';
            if (($m[2] ?? '') === '') {
                $specs['ram'] .= ' RAM';
            }
        }

        if ($specs['almacenamiento'] === '' && preg_match_all('/(?<![\d+\/])(\d{2,4}\s?GB|\d\s?TB)\s?(SSD|HDD|NVME|EMMC|M\.2)?\b/u', $n, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $cap = (int) preg_replace('/\D/', '', $m[1]);
                if (str_contains($m[1], 'TB') || $cap >= 64) {
                    $usado[] = $m[0];
                    $tipoDisco = ($m[2] ?? '') !== '' ? $m[2] : ($esCelular ? '' : 'SSD');
                    $specs['almacenamiento'] = trim(str_replace(' ', '', $m[1]) . ' ' . $tipoDisco);
                    break;
                }
            }
        }

        if (preg_match_all('/(?<![\w.\-+])(1[0-8](?:[.,]\d)?)\s?("|”|\'\'|PULG(?:ADAS)?\b|IN\b)?\s?(FHD|FULL HD|QHD|OLED|IPS|TOUCH|HD)?(?![\w.])/u', $n, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $conUnidad = ($m[2] ?? '') !== '';
                $conTipo = ($m[3] ?? '') !== '';
                if (! $conUnidad && ! $conTipo && ! preg_match('/[.,]/', $m[1])) {
                    continue;
                }
                $usado[] = trim($m[0]);
                $specs['pantalla'] = 'Pantalla ' . str_replace(',', '.', $m[1]) . '"' . ($conTipo ? ' ' . str_replace('FULL HD', 'FHD', $m[3]) : '');
                break;
            }
        }
        if (preg_match('/\b(\d{2,3})\s?HZ\b/u', $n, $m)) {
            $usado[] = $m[0];
            if ($specs['pantalla'] !== '') {
                $specs['pantalla'] .= ' ' . $m[1] . 'Hz';
            }
        }

        if (preg_match('/\b(?:W|WIN|WINDOWS)\s?(10|11)(?:\s?(HOME|PRO))?\b/u', $n, $m)) {
            $usado[] = $m[0];
            $specs['sistema'] = 'Windows ' . $m[1] . (($m[2] ?? '') !== '' ? ' ' . ucfirst(strtolower($m[2])) : '');
        } elseif (preg_match('/\bCHROME\s?OS\b|\bFREE\s?DOS\b|\bMAC\s?OS\b|\bUBUNTU\b|\bLINUX\b/u', $n, $m)) {
            $usado[] = $m[0];
            $specs['sistema'] = trim($m[0]);
        }

        $resto = $n;
        foreach ($usado as $u) {
            $resto = str_replace($u, ' ', $resto);
        }
        $resto = preg_replace('/\b(LAPTOP|NOTEBOOK|COMPUTADORA|PORTATIL|PORTÁTIL|PC|MINI|ALL IN ONE|AIO|INTEL|AMD|CORE|GAMING|GAMER|MEMORY|MEMORIA|RAM|NVIDIA|GEFORCE|FULL|HD|FHD)\b/u', ' ', $resto) ?? $resto;
        $resto = preg_replace('/(^|\s)[-–|,·\/+]+(?=\s|$)/u', ' ', $resto) ?? $resto;
        $resto = trim(preg_replace('/\s+/u', ' ', $resto) ?? '');
        $specs['modelo'] = mb_substr(trim(($specs['marca'] !== '' ? $specs['marca'] . ' ' : '') . implode(' ', array_slice(explode(' ', $resto), 0, 3))), 0, 40);

        return $specs;
    }

    /**
     * @param  array<string, ?string>|null  $manual
     * @return array<string, string>
     */
    public function specsFinales(string $nombre, ?array $manual): array
    {
        $sugeridas = $this->sugerirSpecsPc($nombre);
        if ($manual === null) {
            return $sugeridas;
        }

        $out = [];
        foreach (self::SPECS_PC as $campo) {
            $out[$campo] = mb_substr(trim((string) ($manual[$campo] ?? '')), 0, 40);
        }

        return $out;
    }

    /**
     * Logo de la marca para la ficha de PC. Victus es la línea gamer de HP y tiene logo propio.
     *
     * @param  array<string, string>  $specs
     */
    public function logoMarca(array $specs, string $nombre): ?string
    {
        $texto = mb_strtoupper(($specs['marca'] ?? '') . ' ' . ($specs['modelo'] ?? '') . ' ' . $nombre, 'UTF-8');
        $marca = mb_strtoupper(trim($specs['marca'] ?? ''), 'UTF-8');

        $clave = match (true) {
            str_contains($texto, 'VICTUS') => 'victus',
            (bool) preg_match('/\bPOCO\b/u', $texto) => 'poco',
            $marca === 'REDMI' => 'xiaomi',
            $marca === 'APPLE' || ($marca === '' && preg_match('/\b(APPLE|MACBOOK|IMAC)\b/u', $texto)) => 'apple',
            in_array(mb_strtolower($marca), self::LOGOS_MARCA, true) => mb_strtolower($marca),
            default => null,
        };

        return $clave !== null && is_file(public_path("img/marcas/{$clave}.png")) ? $clave : null;
    }

    public function formatoPrecioEntero(float $valor): string
    {
        return $valor > 0 ? '$' . number_format($valor, 0, ',', '.') : '—';
    }

    public function formatoPrecio(float $valor): string
    {
        return $valor > 0 ? number_format($valor, 2, ',', '.') : '—';
    }

    /**
     * El escáner manda solo una parte del código compuesto ("#0707 / 0378789000952").
     */
    public function coincideCodigo(string $codigo, string $buscado): bool
    {
        $buscado = ltrim(mb_strtolower(trim($buscado), 'UTF-8'), '#');
        if ($buscado === '') {
            return false;
        }

        foreach (explode('/', $codigo) as $parte) {
            if (ltrim(mb_strtolower(trim($parte), 'UTF-8'), '#') === $buscado) {
                return true;
            }
        }

        return false;
    }

    private function baseQuery()
    {
        return DB::table('productos')
            ->where('activo', true)
            ->where(fn ($q) => $q->where('oculto', false)->orWhereNull('oculto'))
            ->select(['id', 'codigo', 'nombre', 'categoria', 'precio_unidad', 'precio_mayor']);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapear(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'codigo' => trim((string) $r->codigo),
            'nombre' => trim((string) $r->nombre),
            'categoria' => trim((string) ($r->categoria ?? '')),
            'precio_unidad' => $this->redondearArriba((float) ($r->precio_unidad ?? 0)),
            'precio_mayor' => $this->redondearArriba((float) ($r->precio_mayor ?? 0)),
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
