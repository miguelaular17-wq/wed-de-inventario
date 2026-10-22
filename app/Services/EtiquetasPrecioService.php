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

        $items = $rows->map(fn ($r) => $this->mapear($r));

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
     * @param  list<array{id: int, copias: int}>  $items
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
        $unidad = (float) $producto['precio_unidad'];
        $mayor = (float) $producto['precio_mayor'];
        $conMayor = $mayor > 0 && ($unidad <= 0 || $mayor < $unidad);
        $ahorro = $conMayor && $unidad > 0 ? (int) round((1 - $mayor / $unidad) * 100) : 0;

        return $producto + [
            'con_mayor' => $conMayor,
            'cantidad_mayor' => $cantidadMayor,
            'ahorro_pct' => $ahorro,
            'unidad_fmt' => $this->formatoPrecio($unidad),
            'mayor_fmt' => $this->formatoPrecio($mayor),
        ];
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
    public function ajustarNombre(string $nombre, callable $anchoPt, callable $altoLineaPt, float $anchoMm, float $altoMm, array $tamanosPt, int $maxLineas = 3): array
    {
        $anchoMaxPt = $anchoMm * 72 / 25.4;
        $altoMaxPt = $altoMm * 72 / 25.4;
        $texto = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $nombre) ?? $nombre), 'UTF-8');

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
            'precio_unidad' => round((float) ($r->precio_unidad ?? 0), 2),
            'precio_mayor' => round((float) ($r->precio_mayor ?? 0), 2),
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
