<?php

namespace App\Services;

use App\Models\IncentivoCruceItem;
use App\Support\Sku;
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
            $producto = $this->buscarProductoPorCodigo($clave);
            if (! $producto) {
                throw ValidationException::withMessages(['clave' => 'Ese producto no existe.']);
            }
            $clave = mb_strtoupper(Sku::principal((string) $producto->codigo));
            $nombre = trim((string) $producto->nombre) ?: $clave;
        }

        return IncentivoCruceItem::query()->updateOrCreate(
            ['modulo' => $modulo, 'tipo' => $tipo, 'clave' => $clave],
            ['nombre' => $nombre, 'activo' => (bool) $data['activo']],
        );
    }

    /**
     * Activa el catálogo de productos de evaluación del módulo (p. ej. Arreglo).
     *
     * @return array{activados:int, faltantes:list<string>}
     */
    public function activarProductosCatalogo(string $modulo): array
    {
        $codigos = config("incentivos.modulos.{$modulo}.productos", []);
        if (! is_array($codigos) || $codigos === []) {
            return ['activados' => 0, 'faltantes' => []];
        }

        $activados = 0;
        $faltantes = [];
        $vistos = [];
        foreach ($codigos as $codigo) {
            $codigo = mb_strtoupper(trim((string) $codigo));
            if ($codigo === '') {
                continue;
            }
            $producto = $this->buscarProductoPorCodigo($codigo);
            if (! $producto) {
                $faltantes[] = $codigo;

                continue;
            }
            $clave = mb_strtoupper(Sku::principal((string) $producto->codigo));
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $this->guardar($modulo, [
                'tipo' => 'producto',
                'clave' => $clave,
                'nombre' => trim((string) $producto->nombre) ?: $clave,
                'activo' => true,
            ]);
            $activados++;
        }

        return ['activados' => $activados, 'faltantes' => $faltantes];
    }

    /**
     * Expande claves guardadas a todos los segmentos de código (principal + alternos) para filtrar ventas.
     *
     * @param  list<string>  $claves
     * @return list<string>
     */
    public function codigosParaFiltro(array $claves): array
    {
        $out = [];
        foreach ($claves as $clave) {
            $clave = mb_strtoupper(trim((string) $clave));
            if ($clave === '') {
                continue;
            }
            $out[$clave] = $clave;
            $producto = $this->buscarProductoPorCodigo($clave);
            if (! $producto) {
                continue;
            }
            foreach (preg_split('#(?:^|\s)/(?:\s|$)#', (string) $producto->codigo) as $parte) {
                $parte = mb_strtoupper(trim($parte));
                if ($parte !== '') {
                    $out[$parte] = $parte;
                }
            }
        }

        return array_values($out);
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
            $clave = mb_strtoupper(Sku::principal((string) $row->codigo));
            $out[] = [
                'clave' => $clave,
                'nombre' => trim((string) $row->nombre) ?: $clave,
                'categoria' => trim((string) $row->categoria),
                'activo' => (bool) ($guardados[$clave]['activo'] ?? false),
            ];
        }

        return $out;
    }

    private function buscarProductoPorCodigo(string $clave): ?object
    {
        if (! Schema::hasTable('productos') || $clave === '') {
            return null;
        }

        $exacto = DB::table('productos')
            ->whereRaw('UPPER(TRIM(codigo)) = ?', [$clave])
            ->first(['codigo', 'nombre', 'categoria']);
        if ($exacto) {
            return $exacto;
        }

        // Código alterno exacto dentro de "PRINCIPAL / ALTERNO1 / ALTERNO2" (no substring).
        $candidatos = DB::table('productos')
            ->whereRaw("UPPER(REPLACE(codigo, ' ', '')) LIKE ?", ['%'.str_replace(' ', '', $clave).'%'])
            ->get(['codigo', 'nombre', 'categoria']);
        foreach ($candidatos as $row) {
            foreach (preg_split('#(?:^|\s)/(?:\s|$)#', (string) $row->codigo) as $parte) {
                if (mb_strtoupper(trim($parte)) === $clave) {
                    return $row;
                }
            }
        }

        return null;
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
