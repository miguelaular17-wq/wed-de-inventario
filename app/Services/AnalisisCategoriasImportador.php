<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Marca como "realizadas" en Análisis de categorías las subcategorías de una lista por sede
 * (Excel de categorías realizadas). Los nombres se cruzan con el catálogo real porque la hoja
 * no siempre trae la categoría correcta (o no la trae).
 */
class AnalisisCategoriasImportador
{
    /** @var array<string, string>|null  clave normalizada categoría => nombre real */
    private ?array $categorias = null;

    /** @var array<string, list<array{0: string, 1: string}>>|null  sub normalizada => [[cat, sub], ...] */
    private ?array $porSub = null;

    /** @var array<string, list<string>>|null  cat normalizada => subs reales */
    private ?array $subsDeCat = null;

    /**
     * @param  array<string, list<array{categoria: ?string, subcategoria: ?string}>>  $porSede
     * @return array<string, array{marcas: list<array{0: string, 1: string}>, no_encontradas: list<string>}>
     */
    public function resolver(array $porSede): array
    {
        $this->cargarArbol();
        $out = [];

        foreach ($porSede as $sede => $entradas) {
            $marcas = [];
            $noEncontradas = [];
            foreach ($entradas as $e) {
                $pares = $this->resolverEntrada($e['categoria'] ?? null, $e['subcategoria'] ?? null);
                if ($pares === []) {
                    $noEncontradas[] = trim(($e['categoria'] ?? '') . ' › ' . ($e['subcategoria'] ?? '(toda la categoría)'), ' ›');
                }
                foreach ($pares as [$cat, $sub]) {
                    $marcas[mb_strtoupper($cat . '|' . $sub, 'UTF-8')] = [$cat, $sub];
                }
            }
            $out[mb_strtoupper(trim($sede), 'UTF-8')] = [
                'marcas' => array_values($marcas),
                'no_encontradas' => array_values(array_unique($noEncontradas)),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{marcas: list<array{0: string, 1: string}>}>  $resuelto
     * @return array<string, int>  sede => filas nuevas
     */
    public function guardar(array $resuelto, ?int $userId = null): array
    {
        $now = now();
        $nuevas = [];
        foreach ($resuelto as $sede => $r) {
            $rows = array_map(fn ($p) => [
                'sede' => $sede,
                'categoria' => $p[0],
                'subcategoria' => $p[1],
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $r['marcas']);

            $nuevas[$sede] = 0;
            foreach (array_chunk($rows, 200) as $chunk) {
                $nuevas[$sede] += DB::table('analisis_categoria_checks')->insertOrIgnore($chunk);
            }
        }

        return $nuevas;
    }

    /** @return list<array{0: string, 1: string}> */
    private function resolverEntrada(?string $categoria, ?string $sub): array
    {
        $cat = $categoria !== null ? $this->norm($categoria) : null;
        $subN = $sub !== null ? $this->norm($sub) : null;

        if ($subN === null) {
            return $cat !== null ? $this->todaLaCategoria($cat) : [];
        }

        $candidatos = $this->porSub[$subN] ?? [];
        if ($cat !== null) {
            $exactos = array_values(array_filter($candidatos, fn ($p) => $this->norm($p[0]) === $cat));
            if ($exactos !== []) {
                return $exactos;
            }
        }
        if (count($candidatos) > 1) {
            // Mismo nombre en varias categorías ("RELOJ AMAZFIT" en RELOJES INTELIGENTES y en AMAZFIT):
            // se queda con la categoría principal (la que tiene más subcategorías).
            $tam = fn ($p) => count($this->subsDeCat[$this->norm($p[0])] ?? []);
            $max = max(array_map($tam, $candidatos));
            $candidatos = array_values(array_filter($candidatos, fn ($p) => $tam($p) === $max));
        }
        if ($candidatos !== []) {
            return $candidatos;
        }

        // La hoja a veces pone una categoría completa en la lista de subcategorías.
        return $this->todaLaCategoria($subN);
    }

    /** @return list<array{0: string, 1: string}> */
    private function todaLaCategoria(string $catN): array
    {
        $real = $this->categorias[$catN] ?? null;
        if ($real === null) {
            return [];
        }

        return array_map(fn ($s) => [$real, $s], $this->subsDeCat[$catN] ?? []);
    }

    /**
     * Árbol del catálogo ya cargado (pruebas).
     *
     * @param  iterable<object{categoria: string, subcategoria: string}>  $rows
     */
    public function conArbol(iterable $rows): static
    {
        $this->indexar($rows);

        return $this;
    }

    private function cargarArbol(): void
    {
        if ($this->categorias !== null) {
            return;
        }

        $this->indexar(DB::connection('pgsql')->select("
            SELECT DISTINCT
                TRIM(categoria) AS categoria,
                TRIM(COALESCE(NULLIF(TRIM(subcategoria), ''), '(Sin subcategoría)')) AS subcategoria
            FROM inventario_v2.productos
            WHERE categoria IS NOT NULL AND TRIM(categoria) <> ''
        "));
    }

    /** @param  iterable<object>  $rows */
    private function indexar(iterable $rows): void
    {
        $this->categorias = [];
        $this->porSub = [];
        $this->subsDeCat = [];
        foreach ($rows as $r) {
            $cat = (string) $r->categoria;
            $sub = (string) $r->subcategoria;
            $catN = $this->norm($cat);
            $this->categorias[$catN] = $cat;
            $this->subsDeCat[$catN][] = $sub;
            $this->porSub[$this->norm($sub)][] = [$cat, $sub];
        }
    }

    /** Mayúsculas, sin acentos y sin espacios de más ("JUGUETERÍA  NIÑA " = "JUGUETERIA NIÑA"). */
    public function norm(string $v): string
    {
        $v = str_replace(['Ñ', 'ñ'], ['__N__', '__N__'], $v);
        $v = Str::ascii($v);
        $v = str_replace('__N__', 'Ñ', $v);
        $v = preg_replace('/\s*\/\s*/u', '/', $v) ?? $v;

        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $v) ?? $v), 'UTF-8');
    }
}
