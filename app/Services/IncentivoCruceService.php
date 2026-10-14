<?php

namespace App\Services;

use App\Models\IncentivoCruceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class IncentivoCruceService
{
    /**
     * @return array{activo:bool, resumen:string, categorias:list<array{clave:string, nombre:string, activo:bool}>, productos:list<array{clave:string, nombre:string, activo:bool}>}
     */
    public function panel(string $modulo): array
    {
        $guardados = $this->guardados($modulo);
        $categorias = [];
        foreach ($this->catalogoCategorias() as $cat) {
            $clave = $cat['clave'];
            $categorias[] = [
                'clave' => $clave,
                'nombre' => $cat['nombre'],
                'activo' => (bool) ($guardados['categoria'][$clave]['activo'] ?? false),
            ];
        }

        $productos = [];
        foreach ($guardados['producto'] ?? [] as $item) {
            $productos[] = [
                'clave' => $item['clave'],
                'nombre' => $item['nombre'],
                'activo' => $item['activo'],
            ];
        }

        $activos = $this->activos($modulo);

        return [
            'activo' => $activos['categorias'] !== [] || $activos['productos'] !== [],
            'resumen' => $this->resumen($activos, $modulo),
            'categorias' => $categorias,
            'productos' => $productos,
        ];
    }

    /**
     * @return array{categorias:list<string>, productos:list<string>}
     */
    public function activos(string $modulo): array
    {
        $guardados = $this->guardados($modulo);
        $categorias = [];
        $productos = [];
        foreach ($guardados['categoria'] ?? [] as $item) {
            if ($item['activo']) {
                $categorias[] = $item['clave'];
            }
        }
        foreach ($guardados['producto'] ?? [] as $item) {
            if ($item['activo']) {
                $productos[] = $item['clave'];
            }
        }

        return ['categorias' => $categorias, 'productos' => $productos];
    }

    /**
     * Categorías y productos activos, en el orden en que salen las columnas.
     *
     * @return list<array{tipo:string, clave:string, nombre:string}>
     */
    public function activosDetalle(string $modulo): array
    {
        $guardados = $this->guardados($modulo);
        $out = [];
        foreach (['categoria', 'producto'] as $tipo) {
            $items = array_values(array_filter($guardados[$tipo] ?? [], fn (array $item) => $item['activo']));
            usort($items, fn (array $a, array $b) => strcasecmp($a['nombre'], $b['nombre']));
            foreach ($items as $item) {
                $out[] = [
                    'tipo' => $tipo,
                    'clave' => $item['clave'],
                    'nombre' => $item['nombre'],
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array{tipo:string, clave:string, nombre:?string, activo:bool}  $data
     */
    public function guardar(string $modulo, array $data): IncentivoCruceItem
    {
        $tipo = $data['tipo'];
        $clave = mb_strtoupper(trim($data['clave']));
        if ($clave === '') {
            throw ValidationException::withMessages(['clave' => 'Elige una categoría o un producto.']);
        }

        if ($tipo === 'categoria') {
            $catalogo = collect($this->catalogoCategorias())->keyBy('clave');
            if (! $catalogo->has($clave)) {
                throw ValidationException::withMessages(['clave' => 'Esa categoría no existe.']);
            }
            $nombre = $catalogo[$clave]['nombre'];
        } else {
            $producto = DB::table('productos')
                ->whereRaw('UPPER(TRIM(codigo)) = ?', [$clave])
                ->first(['codigo', 'nombre']);
            if (! $producto) {
                throw ValidationException::withMessages(['clave' => 'Ese producto no existe.']);
            }
            $clave = mb_strtoupper(trim((string) $producto->codigo));
            $nombre = trim((string) $producto->nombre) ?: $clave;
        }

        return IncentivoCruceItem::query()->updateOrCreate(
            ['modulo' => $modulo, 'tipo' => $tipo, 'clave' => $clave],
            ['nombre' => $nombre, 'activo' => (bool) $data['activo']],
        );
    }

    /**
     * @return list<array{clave:string, nombre:string, categoria:string, activo:bool}>
     */
    public function buscarProductos(string $modulo, string $termino): array
    {
        $termino = trim($termino);
        $guardados = $this->guardados($modulo)['producto'] ?? [];
        if ($termino === '') {
            return array_map(fn (array $item) => [
                'clave' => $item['clave'],
                'nombre' => $item['nombre'],
                'categoria' => '',
                'activo' => $item['activo'],
            ], array_values($guardados));
        }

        $like = '%'.addcslashes($termino, '%_\\').'%';
        $rows = DB::table('productos')
            ->where(function ($query) use ($like) {
                $query->where('codigo', 'ilike', $like)
                    ->orWhere('nombre', 'ilike', $like);
            })
            ->orderBy('nombre')
            ->limit(30)
            ->get(['codigo', 'nombre', 'categoria']);

        $out = [];
        foreach ($rows as $row) {
            $clave = mb_strtoupper(trim((string) $row->codigo));
            $out[] = [
                'clave' => $clave,
                'nombre' => trim((string) $row->nombre) ?: $clave,
                'categoria' => trim((string) $row->categoria),
                'activo' => (bool) ($guardados[$clave]['activo'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * @return array{categoria: array<string, array{clave:string, nombre:string, activo:bool}>, producto: array<string, array{clave:string, nombre:string, activo:bool}>}
     */
    private function guardados(string $modulo): array
    {
        $out = ['categoria' => [], 'producto' => []];
        if (! Schema::hasTable('incentivo_cruce_items')) {
            return $out;
        }

        $rows = IncentivoCruceItem::query()->where('modulo', $modulo)->get();
        foreach ($rows as $row) {
            $tipo = $row->tipo === 'producto' ? 'producto' : 'categoria';
            $out[$tipo][$row->clave] = [
                'clave' => $row->clave,
                'nombre' => $row->nombre,
                'activo' => (bool) $row->activo,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{clave:string, nombre:string}>
     */
    private function catalogoCategorias(): array
    {
        if (! Schema::hasTable('productos')) {
            return [];
        }

        return DB::table('productos')
            ->whereRaw("NULLIF(TRIM(categoria), '') IS NOT NULL")
            ->selectRaw('UPPER(TRIM(categoria)) as clave')
            ->selectRaw('MIN(TRIM(categoria)) as nombre')
            ->groupByRaw('UPPER(TRIM(categoria))')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($row) => ['clave' => (string) $row->clave, 'nombre' => (string) $row->nombre])
            ->all();
    }

    /**
     * @param  array{categorias:list<string>, productos:list<string>}  $activos
     */
    private function resumen(array $activos, string $modulo): string
    {
        $nCat = count($activos['categorias']);
        $nProd = count($activos['productos']);
        $efecto = $modulo === 'arreglos'
            ? 'Venta, productos y clientes solo suman esa selección.'
            : 'Cada categoría activa tiene su cruce, estado y porcentaje.';
        if ($nCat === 0 && $nProd === 0) {
            return $modulo === 'arreglos'
                ? 'Nada seleccionado. Venta, productos y clientes quedan en blanco.'
                : 'Nada seleccionado. El cruce queda desactivado.';
        }

        $partes = [];
        if ($nCat > 0) {
            $partes[] = $nCat === 1 ? '1 categoría' : $nCat.' categorías';
        }
        if ($nProd > 0) {
            $partes[] = $nProd === 1 ? '1 producto' : $nProd.' productos';
        }

        return 'Activo: '.implode(' y ', $partes).'. '.$efecto;
    }
}
