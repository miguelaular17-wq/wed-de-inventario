<?php

namespace App\Services;

use App\Models\ProductoEdicion;
use App\Models\V2\Producto;
use Illuminate\Support\Collection;

class ProductoEdicionService
{
    /**
     * Fusiona datos del sincronizador con la edición manual.
     * Prioridad: edición (si el campo tiene valor) → sincronizador.
     *
     * @return array<string, mixed>
     */
    public function efectivo(mixed $producto, ?ProductoEdicion $edicion = null): array
    {
        if (is_array($producto)) {
            $base = $producto;
        } elseif ($producto instanceof Producto) {
            $base = $producto->getAttributes();
        } else {
            $base = (array) $producto;
        }
        $id = (int) ($base['id'] ?? $base['producto_id'] ?? 0);
        $codigo = (string) ($base['codigo'] ?? '');

        if ($edicion === null && $id > 0) {
            $edicion = ProductoEdicion::query()->where('producto_id', $id)->first();
        }

        $nombreSync = (string) ($base['nombre'] ?? $base['producto'] ?? $base['descripcion'] ?? '');
        $efectivo = [
            'id' => $id,
            'codigo' => $codigo,
            'nombre' => $nombreSync,
            'categoria' => (string) ($base['categoria'] ?? ''),
            'subcategoria' => (string) ($base['subcategoria'] ?? ''),
            'proveedor' => (string) ($base['proveedor'] ?? ''),
            'precio_unidad' => isset($base['precio_unidad']) ? (float) $base['precio_unidad'] : null,
            'precio_mayor' => isset($base['precio_mayor']) ? (float) $base['precio_mayor'] : null,
            'lote_inicial' => isset($base['lote_inicial']) ? (int) $base['lote_inicial'] : null,
            'url_imagen' => (string) ($base['url_imagen'] ?? ''),
            'descripcion_ampliada' => '',
            'activo' => (bool) ($base['activo'] ?? true),
            'oculto' => (bool) ($base['oculto'] ?? false),
            'sync' => [
                'nombre' => $nombreSync,
                'categoria' => (string) ($base['categoria'] ?? ''),
                'subcategoria' => (string) ($base['subcategoria'] ?? ''),
                'proveedor' => (string) ($base['proveedor'] ?? ''),
                'precio_unidad' => isset($base['precio_unidad']) ? (float) $base['precio_unidad'] : null,
                'precio_mayor' => isset($base['precio_mayor']) ? (float) $base['precio_mayor'] : null,
                'lote_inicial' => null,
                'url_imagen' => (string) ($base['url_imagen'] ?? ''),
            ],
            'editado' => false,
            'campos_editados' => [],
            'edicion' => $edicion,
        ];

        if ($edicion) {
            foreach (ProductoEdicion::CAMPOS as $campo) {
                $valor = $edicion->$campo;
                if ($valor === null || $valor === '') {
                    continue;
                }
                $efectivo[$campo] = match (true) {
                    in_array($campo, ['precio_unidad', 'precio_mayor'], true) => (float) $valor,
                    $campo === 'lote_inicial' => (int) $valor,
                    default => (string) $valor,
                };
                $efectivo['campos_editados'][] = $campo;
            }
            $efectivo['editado'] = $efectivo['campos_editados'] !== [];
        }

        return $efectivo;
    }

    /**
     * Aplica ediciones a una colección de filas/productos (mutando nombre/descripcion/etc.).
     *
     * @param  Collection<int, mixed>  $items
     * @return Collection<int, mixed>
     */
    public function aplicarAColeccion(Collection $items, string $nombreKey = 'nombre'): Collection
    {
        $ids = $items->map(function ($item) {
            if (is_array($item)) {
                return (int) ($item['id'] ?? 0);
            }

            return (int) ($item->id ?? 0);
        })->filter()->unique()->values()->all();

        if ($ids === []) {
            return $items;
        }

        $ediciones = ProductoEdicion::query()
            ->whereIn('producto_id', $ids)
            ->get()
            ->keyBy('producto_id');

        return $items->map(function ($item) use ($ediciones, $nombreKey) {
            $id = is_array($item) ? (int) ($item['id'] ?? 0) : (int) ($item->id ?? 0);
            $edicion = $ediciones->get($id);
            if (! $edicion) {
                if (is_array($item)) {
                    $item['editado'] = false;

                    return $item;
                }
                $item->editado = false;

                return $item;
            }

            $efectivo = $this->efectivo($item, $edicion);
            if (is_array($item)) {
                $item['producto'] = $efectivo['nombre'];
                $item['nombre'] = $efectivo['nombre'];
                $item['categoria'] = $efectivo['categoria'];
                $item['subcategoria'] = $efectivo['subcategoria'];
                $item['proveedor'] = $efectivo['proveedor'];
                $item['precio_unidad'] = $efectivo['precio_unidad'];
                $item['precio_mayor'] = $efectivo['precio_mayor'];
                $item['lote_inicial'] = $efectivo['lote_inicial'];
                $item['url_imagen'] = $efectivo['url_imagen'];
                $item['editado'] = $efectivo['editado'];
                $item['campos_editados'] = $efectivo['campos_editados'];

                return $item;
            }

            $item->{$nombreKey} = $efectivo['nombre'];
            if (isset($item->descripcion) || $nombreKey === 'descripcion') {
                $item->descripcion = $efectivo['nombre'];
            }
            $item->nombre = $efectivo['nombre'];
            $item->categoria = $efectivo['categoria'];
            $item->subcategoria = $efectivo['subcategoria'];
            $item->proveedor = $efectivo['proveedor'];
            if ($efectivo['precio_unidad'] !== null) {
                $item->precio_unidad = $efectivo['precio_unidad'];
            }
            if ($efectivo['precio_mayor'] !== null) {
                $item->precio_mayor = $efectivo['precio_mayor'];
            }
            $item->lote_inicial = $efectivo['lote_inicial'];
            if ($efectivo['url_imagen'] !== '') {
                $item->url_imagen = $efectivo['url_imagen'];
            }
            $item->editado = $efectivo['editado'];
            $item->campos_editados = $efectivo['campos_editados'];

            return $item;
        });
    }

    /**
     * Guarda overrides: solo campos distintos al sync; null = volver al sincronizador.
     *
     * @param  array<string, mixed>  $data
     */
    public function guardar(Producto $producto, array $data, ?int $userId = null): ProductoEdicion
    {
        $edicion = ProductoEdicion::query()->firstOrNew(['producto_id' => $producto->id]);
        $edicion->codigo = (string) $producto->codigo;
        $edicion->editado_by = $userId;

        foreach (ProductoEdicion::CAMPOS as $campo) {
            if (! array_key_exists($campo, $data)) {
                continue;
            }
            $valor = $data[$campo];
            if ($valor === null || $valor === '') {
                $edicion->$campo = null;
                continue;
            }

            if (in_array($campo, ['precio_unidad', 'precio_mayor'], true)) {
                $num = round((float) $valor, 2);
                $sync = round((float) ($producto->$campo ?? 0), 2);
                $edicion->$campo = abs($num - $sync) < 0.001 ? null : $num;
                continue;
            }

            if ($campo === 'lote_inicial') {
                $num = (int) $valor;
                $edicion->$campo = $num > 0 ? $num : null;
                continue;
            }

            $texto = trim((string) $valor);
            $syncTexto = trim((string) ($producto->$campo ?? ''));
            // descripcion_ampliada no existe en sync
            if ($campo === 'descripcion_ampliada') {
                $edicion->$campo = $texto !== '' ? $texto : null;
                continue;
            }
            $edicion->$campo = $texto === $syncTexto ? null : $texto;
        }

        if (! $edicion->tieneAlgunaEdicion()) {
            if ($edicion->exists) {
                $edicion->delete();
            }

            return new ProductoEdicion(['producto_id' => $producto->id, 'codigo' => $producto->codigo]);
        }

        $edicion->save();

        return $edicion;
    }

    public function restaurar(Producto $producto): void
    {
        ProductoEdicion::query()->where('producto_id', $producto->id)->delete();
    }
}
