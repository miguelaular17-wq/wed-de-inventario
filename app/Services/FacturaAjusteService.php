<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Ajuste de facturas (ventas_documentos + ventas_detalle) por el administrador. Lo editado queda ajuste_manual
 * y con historial en factura_ajustes; en Postgres un trigger impide que el sincronizador de la sede lo borre o lo pise.
 */
class FacturaAjusteService
{
    public const DOCUMENTOS = 'ventas_documentos';

    public const DETALLE = 'ventas_detalle';

    /** Columnas que no se editan a mano. */
    private const FIJAS = ['id', 'created_at', 'updated_at', 'anulado_at', 'ajuste_manual'];

    /** El renglón pertenece a la factura por estas columnas: siguen al encabezado. */
    private const CLAVE_DOCUMENTO = ['sede', 'tipo_documento', 'numero_documento'];

    /** @var array<string, array<string, array{tipo: string, nulo: bool}>> */
    private array $columnas = [];

    /**
     * @param  array{q?: ?string, producto?: ?string, sede?: ?string, tipo?: ?string, desde?: ?string, hasta?: ?string, ajustadas?: bool}  $f
     * @return array{facturas: LengthAwarePaginator, renglones: Collection, desde: ?string, hasta: ?string}
     */
    public function buscar(array $f, int $porPagina = 50): array
    {
        $q = trim((string) ($f['q'] ?? ''));
        $producto = trim((string) ($f['producto'] ?? ''));
        [$desde, $hasta] = $this->rango($f, $q !== '', $producto !== '');

        $docs = DB::table(self::DOCUMENTOS.' as d')
            ->when($desde, fn ($w) => $w->where('d.fecha', '>=', $desde))
            ->when($hasta, fn ($w) => $w->where('d.fecha', '<=', $hasta))
            ->when(! empty($f['sede']), fn ($w) => $w->where('d.sede', $f['sede']))
            ->when(! empty($f['tipo']), fn ($w) => $w->where('d.tipo_documento', $f['tipo']))
            ->when(! empty($f['ajustadas']) && $this->hayAjustes(), fn ($w) => $w->where(function ($a) {
                $a->where('d.ajuste_manual', true)->orWhereExists(fn ($e) => $e->selectRaw('1')->from(self::DETALLE.' as a')
                    ->whereColumn('a.sede', 'd.sede')->whereColumn('a.tipo_documento', 'd.tipo_documento')
                    ->whereColumn('a.numero_documento', 'd.numero_documento')->where('a.ajuste_manual', true));
            }))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.mb_strtolower($q).'%';
                $w->where(function ($o) use ($q, $like) {
                    $o->where('d.numero_documento', $q)
                        ->orWhereRaw('LOWER(d.numero_documento) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(d.cliente) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(d.vendedor) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(d.cajera) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(d.factura_origen) LIKE ?', [$like]);
                });
            })
            ->when($producto !== '', fn ($w) => $w->whereExists(fn ($e) => $this->filtroProducto(
                $e->selectRaw('1')->from(self::DETALLE.' as p')
                    ->whereColumn('p.sede', 'd.sede')->whereColumn('p.tipo_documento', 'd.tipo_documento')
                    ->whereColumn('p.numero_documento', 'd.numero_documento'),
                $producto, 'p'
            )))
            ->select('d.*')
            ->orderByDesc('d.fecha')->orderByDesc('d.id')
            ->paginate($porPagina)
            ->withQueryString();

        // Con búsqueda por producto se muestran los renglones que coinciden debajo de cada factura.
        $renglones = collect();
        if ($producto !== '' && $docs->isNotEmpty()) {
            $renglones = $this->filtroProducto(DB::table(self::DETALLE.' as p'), $producto, 'p')
                ->where(function ($w) use ($docs) {
                    foreach ($docs as $d) {
                        $w->orWhere(fn ($x) => $x->where('p.sede', $d->sede)->where('p.tipo_documento', $d->tipo_documento)
                            ->where('p.numero_documento', $d->numero_documento));
                    }
                })
                ->orderBy('p.item_numero')
                ->get(['p.sede', 'p.tipo_documento', 'p.numero_documento', 'p.item_numero', 'p.codigo_producto', 'p.nombre_producto', 'p.cantidad', 'p.precio_neto', 'p.precio_venta'])
                ->groupBy(fn ($r) => $r->sede.'|'.$r->tipo_documento.'|'.$r->numero_documento);
        }

        return ['facturas' => $docs, 'renglones' => $renglones, 'desde' => $desde, 'hasta' => $hasta];
    }

    /** @return array{documento: object, renglones: Collection, historial: Collection} */
    public function factura(int $id): array
    {
        $doc = DB::table(self::DOCUMENTOS)->where('id', $id)->first();
        abort_if(! $doc, 404, 'Esa factura no existe.');

        return [
            'documento' => $doc,
            'renglones' => $this->renglones($doc),
            'historial' => Schema::hasTable('factura_ajustes') ? DB::table('factura_ajustes as h')
                ->leftJoin('users as u', 'u.id', '=', 'h.user_id')
                ->where(function ($w) use ($doc) {
                    $w->where(fn ($x) => $x->where('h.tabla', 'documento')->where('h.registro_id', $doc->id))
                        ->orWhere(fn ($x) => $x->where('h.tabla', 'detalle')->where('h.sede', $doc->sede)
                            ->where('h.tipo_documento', $doc->tipo_documento)->where('h.numero_documento', $doc->numero_documento));
                })
                ->orderByDesc('h.id')->limit(100)
                ->get(['h.*', 'u.name as usuario']) : collect(),
        ];
    }

    /** @return array<string, array{tipo: string, nulo: bool}> columna => tipo (texto|numero|entero|fecha|bool) */
    public function editables(string $tabla): array
    {
        $cols = array_diff_key($this->columnas($tabla), array_flip(self::FIJAS));
        if ($tabla === self::DETALLE) {
            $cols = array_diff_key($cols, array_flip(self::CLAVE_DOCUMENTO));
        }

        return $cols;
    }

    /**
     * Guarda lo que cambió del encabezado y de los renglones.
     *
     * @param  array<string, mixed>  $encabezado
     * @param  array<int|string, array<string, mixed>>  $renglones  id => campos
     * @return int campos cambiados
     */
    public function guardar(int $documentoId, array $encabezado, array $renglones, ?int $userId): int
    {
        $doc = DB::table(self::DOCUMENTOS)->where('id', $documentoId)->first();
        abort_if(! $doc, 404, 'Esa factura no existe.');
        $lineas = $this->renglones($doc)->keyBy('id');

        $cambiosDoc = $this->diferencias(self::DOCUMENTOS, $doc, $encabezado, 'encabezado');
        $cambiosLineas = [];
        foreach ($renglones as $id => $valores) {
            $linea = $lineas->get((int) $id);
            if (! $linea) {
                throw ValidationException::withMessages(['renglones' => 'Un renglón ya no es de esta factura: recarga la página.']);
            }
            $c = $this->diferencias(self::DETALLE, $linea, (array) $valores, 'renglón '.$linea->item_numero);
            if ($c !== []) {
                $cambiosLineas[(int) $id] = $c;
            }
        }
        if ($cambiosDoc === [] && $cambiosLineas === []) {
            return 0;
        }

        $claveNueva = array_merge(array_intersect_key((array) $doc, array_flip(self::CLAVE_DOCUMENTO)),
            array_map(fn ($c) => $c[1], array_intersect_key($cambiosDoc, array_flip(self::CLAVE_DOCUMENTO))));
        $cambiaClave = array_intersect_key($cambiosDoc, array_flip(self::CLAVE_DOCUMENTO)) !== [];
        if ($cambiaClave && DB::table(self::DOCUMENTOS)->where($claveNueva)->where('id', '!=', $doc->id)->exists()) {
            throw ValidationException::withMessages(['encabezado' => 'Ya existe otra factura '.$claveNueva['tipo_documento'].' '.$claveNueva['numero_documento'].' en '.$claveNueva['sede'].'.']);
        }
        // El renglón con otra fecha o número de renglón no puede chocar con otro de la misma factura.
        foreach ($cambiosLineas as $id => $c) {
            if (isset($c['fecha']) || isset($c['item_numero'])) {
                $l = $lineas[$id];
                $choca = DB::table(self::DETALLE)->where($claveNueva)
                    ->where('item_numero', $c['item_numero'][1] ?? $l->item_numero)
                    ->where('fecha', $c['fecha'][1] ?? $this->dia($l->fecha))
                    ->where('id', '!=', $id)->exists();
                if ($choca) {
                    throw ValidationException::withMessages(['renglones' => 'El renglón '.$l->item_numero.' quedaría repetido (mismo número y fecha que otro de la factura).']);
                }
            }
        }

        $n = 0;
        DB::transaction(function () use ($doc, $lineas, $cambiosDoc, $cambiosLineas, $cambiaClave, $claveNueva, $userId, &$n) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SET LOCAL app.ajuste_factura = '1'");
            }
            $ahora = now();
            if ($cambiosDoc !== []) {
                DB::table(self::DOCUMENTOS)->where('id', $doc->id)->update(
                    array_map(fn ($c) => $c[1], $cambiosDoc) + ['ajuste_manual' => true, 'updated_at' => $ahora]
                );
                $this->registrar('documento', $doc, $cambiosDoc, $cambiaClave, $userId);
                $n += count($cambiosDoc);
            }
            foreach ($lineas as $id => $linea) {
                $c = $cambiosLineas[$id] ?? [];
                // Si cambió el número, la sede o el tipo de la factura, sus renglones se van con ella.
                if ($cambiaClave) {
                    foreach (self::CLAVE_DOCUMENTO as $k) {
                        if ((string) $linea->{$k} !== (string) $claveNueva[$k]) {
                            $c[$k] = [$linea->{$k}, $claveNueva[$k]];
                        }
                    }
                }
                if ($c === []) {
                    continue;
                }
                DB::table(self::DETALLE)->where('id', $id)->update(array_map(fn ($x) => $x[1], $c) + ['ajuste_manual' => true]);
                $claveLinea = array_intersect_key($c, array_flip([...self::CLAVE_DOCUMENTO, 'item_numero', 'fecha'])) !== [];
                $this->registrar('detalle', $linea, $c, $claveLinea, $userId);
                $n += count($cambiosLineas[$id] ?? []);
            }
        });

        return $n;
    }

    /**
     * La factura vuelve a quedar como la tenga la sede: la próxima sincronización de ese día la reemplaza.
     */
    public function liberar(int $documentoId): void
    {
        $doc = DB::table(self::DOCUMENTOS)->where('id', $documentoId)->first();
        abort_if(! $doc, 404, 'Esa factura no existe.');
        DB::transaction(function () use ($doc) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SET LOCAL app.ajuste_factura = '1'");
            }
            DB::table(self::DOCUMENTOS)->where('id', $doc->id)->update(['ajuste_manual' => false]);
            $clave = array_intersect_key((array) $doc, array_flip(self::CLAVE_DOCUMENTO));
            DB::table(self::DETALLE)->where($clave)->update(['ajuste_manual' => false]);
            DB::table('factura_ajustes')->where('clave_cambiada', true)
                ->where(fn ($w) => $w->where(fn ($x) => $x->where('tabla', 'documento')->where('registro_id', $doc->id))
                    ->orWhere(fn ($x) => $x->where('tabla', 'detalle')->whereIn('registro_id', DB::table(self::DETALLE)->where($clave)->select('id'))))
                ->update(['clave_cambiada' => false, 'updated_at' => now()]);
        });
    }

    public function hayAjustes(): bool
    {
        return Schema::hasColumn(self::DOCUMENTOS, 'ajuste_manual') && Schema::hasTable('factura_ajustes');
    }

    private function renglones(object $doc): Collection
    {
        return DB::table(self::DETALLE)
            ->where('sede', $doc->sede)->where('tipo_documento', $doc->tipo_documento)->where('numero_documento', $doc->numero_documento)
            ->orderBy('fecha')->orderBy('item_numero')->orderBy('id')
            ->get();
    }

    private function filtroProducto($query, string $producto, string $alias)
    {
        $like = '%'.mb_strtolower($producto).'%';

        return $query->where(fn ($o) => $o->where("{$alias}.codigo_producto", $producto)
            ->orWhereRaw("LOWER({$alias}.codigo_producto) LIKE ?", [$like])
            ->orWhereRaw("LOWER({$alias}.nombre_producto) LIKE ?", [$like]));
    }

    /**
     * Sin fechas: buscando un número/cliente se mira todo; buscando producto, los últimos 90 días; si no, los últimos 7.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function rango(array $f, bool $conTexto, bool $conProducto): array
    {
        $desde = ! empty($f['desde']) ? Carbon::parse($f['desde'])->toDateString() : null;
        $hasta = ! empty($f['hasta']) ? Carbon::parse($f['hasta'])->toDateString() : null;
        if ($desde || $hasta || $conTexto) {
            return [$desde, $hasta];
        }

        return [now()->subDays($conProducto ? 90 : 7)->toDateString(), null];
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed}> campo => [antes, después]
     */
    private function diferencias(string $tabla, object $actual, array $nuevos, string $donde): array
    {
        $out = [];
        $errores = [];
        foreach ($this->editables($tabla) as $campo => $meta) {
            if ($meta['tipo'] === 'bool') {
                $nuevo = ! empty($nuevos[$campo]);
                if ((bool) $actual->{$campo} !== $nuevo) {
                    $out[$campo] = [(bool) $actual->{$campo}, $nuevo];
                }

                continue;
            }
            if (! array_key_exists($campo, $nuevos)) {
                continue;
            }
            $texto = trim((string) $nuevos[$campo]);
            $antes = $actual->{$campo};
            try {
                $nuevo = $this->normalizar($texto, $meta);
            } catch (\InvalidArgumentException $e) {
                $errores[] = ucfirst($donde).' · '.str_replace('_', ' ', $campo).': '.$e->getMessage();

                continue;
            }
            if (! $this->iguales($antes, $nuevo, $meta['tipo'])) {
                $out[$campo] = [$antes, $nuevo];
            }
        }
        if ($errores !== []) {
            throw ValidationException::withMessages(['campos' => implode(' · ', $errores)]);
        }

        return $out;
    }

    private function normalizar(string $texto, array $meta): mixed
    {
        if ($texto === '') {
            if (! $meta['nulo']) {
                throw new \InvalidArgumentException('no puede quedar vacío');
            }

            return null;
        }

        return match ($meta['tipo']) {
            'numero' => is_numeric($t = str_replace(',', '.', $texto)) ? (string) $t : throw new \InvalidArgumentException('debe ser un número (usa punto para decimales)'),
            'entero' => preg_match('/^-?\d+$/', $texto) === 1 ? (int) $texto : throw new \InvalidArgumentException('debe ser un número entero'),
            'fecha' => $this->fechaValida($texto),
            default => $texto,
        };
    }

    private function fechaValida(string $texto): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', substr($texto, 0, 10))->toDateString();
        } catch (\Throwable) {
            throw new \InvalidArgumentException('fecha no válida');
        }
    }

    private function iguales(mixed $antes, mixed $nuevo, string $tipo): bool
    {
        if ($antes === null || $nuevo === null) {
            return $antes === null && $nuevo === null;
        }

        return match ($tipo) {
            'numero' => abs((float) $antes - (float) $nuevo) < 0.0000001,
            'entero' => (int) $antes === (int) $nuevo,
            'fecha' => $this->dia($antes) === $nuevo,
            default => (string) $antes === (string) $nuevo,
        };
    }

    private function registrar(string $tabla, object $fila, array $cambios, bool $claveCambiada, ?int $userId): void
    {
        if (! Schema::hasTable('factura_ajustes')) {
            return;
        }
        DB::table('factura_ajustes')->insert([
            'tabla' => $tabla,
            'registro_id' => $fila->id,
            'sede' => $fila->sede,
            'tipo_documento' => $fila->tipo_documento,
            'numero_documento' => $fila->numero_documento,
            'item_numero' => $tabla === 'detalle' ? $fila->item_numero : null,
            'fecha' => $this->dia($fila->fecha),
            'clave_cambiada' => $claveCambiada,
            'cambios' => json_encode($cambios, JSON_UNESCAPED_UNICODE),
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, array{tipo: string, nulo: bool}> */
    private function columnas(string $tabla): array
    {
        return $this->columnas[$tabla] ??= collect(Schema::getColumns($tabla))->mapWithKeys(function ($c) {
            $t = strtolower((string) ($c['type_name'] ?? $c['type'] ?? ''));
            $tipo = match (true) {
                str_contains($t, 'bool') => 'bool',
                $t === 'date' => 'fecha',
                str_contains($t, 'timestamp') || str_contains($t, 'datetime') => 'texto',
                in_array($t, ['int2', 'int4', 'int8', 'integer', 'bigint', 'smallint', 'int'], true) => 'entero',
                str_contains($t, 'numeric') || str_contains($t, 'decimal') || str_contains($t, 'float') || str_contains($t, 'double') || $t === 'real' => 'numero',
                default => 'texto',
            };

            return [$c['name'] => ['tipo' => $tipo, 'nulo' => (bool) $c['nullable']]];
        })->all();
    }

    private function dia(mixed $fecha): ?string
    {
        return $fecha === null ? null : substr((string) $fecha, 0, 10);
    }
}
