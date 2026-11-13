<?php

namespace App\Services\Mayor;

use App\Models\FinanzasResumen;
use App\Models\Mayor\MayorCatalogo;
use App\Models\Mayor\MayorCliente;
use App\Models\Mayor\MayorFactura;
use App\Models\Mayor\MayorFacturaPago;
use App\Models\Nomina\NominaEmpleado;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Ventas al mayor: se ve el stock de todas las sedes, pero la factura solo puede salir del de JRZ.
 * Lo facturado aquí y aún no cargado en Profit se resta del stock de JRZ (al cargarlo, Profit ya lo descuenta).
 */
class VentasMayorService
{
    public const SEDE_FACTURA = 'JRZ';

    /** @return list<string> JRZ primero y luego el resto de sedes con stock. */
    public function sedes(): array
    {
        $sedes = array_map(fn ($s) => mb_strtoupper(trim((string) $s), 'UTF-8'), config('inventario.sedes_stock', []));

        return array_values(array_unique(array_merge([self::SEDE_FACTURA], $sedes)));
    }

    /**
     * Productos activos con su stock por sede, lo apartado y lo disponible en JRZ.
     *
     * @return Collection<int, object>
     */
    public function productos(string $q = '', int $limite = 60, bool|string $soloConStock = false, ?string $categoria = null): Collection
    {
        $filtro = $soloConStock === true ? 'alguna' : ($soloConStock ?: 'todos');
        $query = DB::table('productos')->where('activo', true)
            ->where(fn ($w) => $w->where('oculto', false)->orWhereNull('oculto'))
            ->when($categoria !== null && $categoria !== '', fn ($w) => $w->where('categoria', $categoria));
        $q = trim($q);
        if ($q !== '') {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            foreach (preg_split('/\s+/', $q) ?: [] as $palabra) {
                $query->where(fn ($w) => $w->where('nombre', $like, '%'.$palabra.'%')->orWhere('codigo', $like, '%'.$palabra.'%'));
            }
        }
        if ($filtro !== 'todos') {
            $query->whereExists(fn ($e) => $e->selectRaw('1')->from('stock_actual')
                ->whereColumn('stock_actual.producto_id', 'productos.id')->where('existencia', '>', 0)
                ->when($filtro === 'jrz', fn ($s) => $s->whereRaw('UPPER(TRIM(sede)) = ?', [self::SEDE_FACTURA])));
        }
        // Lo que más hay en JRZ primero; luego por nombre.
        $productos = $query
            ->orderByRaw('(SELECT COALESCE(SUM(s.existencia), 0) FROM stock_actual s WHERE s.producto_id = productos.id AND UPPER(TRIM(s.sede)) = ?) DESC', [self::SEDE_FACTURA])
            ->orderBy('nombre')->limit($limite)
            ->get($this->columnasFicha());

        return $this->conStock($productos);
    }

    /**
     * Catálogo para clientes al mayor: lo que hay en JRZ con su presentación y precio al mayor (sin costo).
     *
     * @return Collection<int, object>
     */
    public function catalogoClientes(string $q = '', ?string $categoria = null, int $limite = 200): Collection
    {
        return $this->productos($q, $limite, 'jrz', $categoria)
            ->filter(fn ($p) => $p->disponible_presentaciones > 0 && $p->precio_presentacion > 0)
            ->values();
    }

    /** @return list<string> Categorías con stock en JRZ. */
    public function categoriasJrz(): array
    {
        return DB::table('productos as p')->join('stock_actual as s', 's.producto_id', '=', 'p.id')
            ->where('p.activo', true)->whereRaw('UPPER(TRIM(s.sede)) = ?', [self::SEDE_FACTURA])->where('s.existencia', '>', 0)
            ->whereNotNull('p.categoria')->where('p.categoria', '!=', '')
            ->distinct()->orderBy('p.categoria')->pluck('p.categoria')->all();
    }

    /** Columnas de la ficha del producto que existan en esta base. */
    private function columnasFicha(): array
    {
        $deseadas = ['id', 'codigo', 'nombre', 'categoria', 'subcategoria', 'proveedor', 'precio_unidad', 'precio_mayor',
            'ultima_cantidad_compra', 'ultimo_costo_compra'];

        return array_values(array_intersect($deseadas, Schema::getColumnListing('productos')));
    }

    /** @return array{productos: int, unidades: int, valor_mayor: float} Stock de JRZ en total, ya sin lo facturado. */
    public function resumenJrz(): array
    {
        $facturado = DB::table('mayor_factura_items as i')->join('mayor_facturas as f', 'f.id', '=', 'i.factura_id')
            ->where('f.estado', MayorFactura::ESTADO_EMITIDA)->where('f.en_profit', false)
            ->groupBy('i.producto_id')->selectRaw('i.producto_id, SUM('.$this->unidadesSql().') as unidades');
        $restante = 'CASE WHEN s.existencia - COALESCE(fa.unidades, 0) > 0 THEN s.existencia - COALESCE(fa.unidades, 0) ELSE 0 END';
        $fila = DB::table('stock_actual as s')->join('productos as p', 'p.id', '=', 's.producto_id')
            ->leftJoinSub($facturado, 'fa', 'fa.producto_id', '=', 's.producto_id')
            ->where('p.activo', true)->whereRaw('UPPER(TRIM(s.sede)) = ?', [self::SEDE_FACTURA])
            ->whereRaw("{$restante} > 0")
            ->selectRaw("COUNT(DISTINCT s.producto_id) as productos, COALESCE(SUM({$restante}), 0) as unidades, COALESCE(SUM({$restante} * p.precio_mayor), 0) as valor")
            ->first();

        return [
            'productos' => (int) ($fila->productos ?? 0),
            'unidades' => (int) ($fila->unidades ?? 0),
            'valor_mayor' => round((float) ($fila->valor ?? 0), 2),
        ];
    }

    /**
     * @param  Collection<int, object>  $productos
     * @return Collection<int, object>
     */
    public function conStock(Collection $productos): Collection
    {
        $ids = $productos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $stock = $this->stockPorSede($ids);
        $apartado = $this->apartadoJrz($ids);
        $catalogo = $this->catalogo($ids);

        return $productos->map(function ($p) use ($stock, $apartado, $catalogo) {
            $id = (int) $p->id;
            $p->stocks = [];
            foreach ($this->sedes() as $sede) {
                $p->stocks[$sede] = (int) ($stock[$id][$sede] ?? 0);
            }
            $p->stocks[self::SEDE_FACTURA] = max(0, $p->stocks[self::SEDE_FACTURA] - (int) ($apartado[$id] ?? 0));
            $p->stock_total = array_sum($p->stocks);
            $p->disponible_jrz = $p->stocks[self::SEDE_FACTURA];
            $p->precio_mayor = round((float) ($p->precio_mayor ?? 0), 2);
            $p->precio_unidad = round((float) ($p->precio_unidad ?? 0), 2);
            $c = $catalogo[$id] ?? ['presentacion' => 'unidad', 'unidades' => 1, 'precio' => $p->precio_mayor, 'costo_unidad' => 0.0, 'costo_suite' => 0.0, 'precio_editado' => false, 'costo_editado' => false];
            $p->costo_suite = $c['costo_suite'];
            $p->presentacion = $c['presentacion'];
            $p->unidades_por = $c['unidades'];
            $p->presentacion_texto = MayorCatalogo::etiqueta($c['presentacion'], $c['unidades']);
            $p->precio_presentacion = $c['precio'];
            $p->precio_editado = $c['precio_editado'];
            $p->costo_unidad = $c['costo_unidad'];
            $p->costo_presentacion = round($c['costo_unidad'] * $c['unidades'], 2);
            $p->costo_editado = $c['costo_editado'];
            $p->ganancia_pct = $p->costo_presentacion > 0 ? round(($p->precio_presentacion - $p->costo_presentacion) / $p->costo_presentacion * 100, 1) : null;
            $p->disponible_presentaciones = intdiv($p->disponible_jrz, max(1, $c['unidades']));

            return $p;
        })->values();
    }

    /**
     * @param  list<int>  $productoIds
     * @return array<int, array<string, int>>
     */
    public function stockPorSede(array $productoIds): array
    {
        if ($productoIds === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk($productoIds, 500) as $lote) {
            $filas = DB::table('stock_actual')->whereIn('producto_id', $lote)
                ->selectRaw('producto_id, UPPER(TRIM(sede)) as sede, SUM(existencia) as existencia')
                ->groupBy('producto_id', DB::raw('UPPER(TRIM(sede))'))
                ->get();
            foreach ($filas as $f) {
                $out[(int) $f->producto_id][(string) $f->sede] = (int) $f->existencia;
            }
        }

        return $out;
    }

    /**
     * Unidades de JRZ facturadas al mayor y aún no cargadas en Profit (se restan del stock de JRZ).
     *
     * @param  list<int>  $productoIds
     * @return array<int, int>
     */
    public function apartadoJrz(array $productoIds, ?int $exceptoFacturaId = null): array
    {
        if ($productoIds === []) {
            return [];
        }

        return DB::table('mayor_factura_items as i')
            ->join('mayor_facturas as f', 'f.id', '=', 'i.factura_id')
            ->where('f.estado', MayorFactura::ESTADO_EMITIDA)
            ->where('f.en_profit', false)
            ->when($exceptoFacturaId, fn ($q) => $q->where('f.id', '!=', $exceptoFacturaId))
            ->whereIn('i.producto_id', $productoIds)
            ->groupBy('i.producto_id')
            ->selectRaw('i.producto_id, SUM('.$this->unidadesSql().') as unidades')
            ->pluck('unidades', 'producto_id')
            ->map(fn ($u) => (int) $u)
            ->all();
    }

    /** Unidades que sacó cada renglón: las cajas o paquetes ya convertidos a unidades. */
    private function unidadesSql(): string
    {
        return Schema::hasColumn('mayor_factura_items', 'unidades') ? 'COALESCE(i.unidades, i.cantidad)' : 'i.cantidad';
    }

    /**
     * Cómo se vende cada producto al mayor. Sin configurar: por unidad, al precio mayor y con el costo de Suite.
     *
     * @param  list<int>  $productoIds
     * @return array<int, array{presentacion: string, unidades: int, precio: float, costo_unidad: float, costo_suite: float,
     *               precio_editado: bool, costo_editado: bool}>
     */
    public function catalogo(array $productoIds): array
    {
        if ($productoIds === []) {
            return [];
        }
        $productos = DB::table('productos')->whereIn('id', $productoIds)
            ->get(array_merge(['id', 'precio_mayor'], Schema::hasColumn('productos', 'costo_actual') ? ['costo_actual'] : []))
            ->keyBy('id');
        $config = Schema::hasTable('mayor_catalogo')
            ? MayorCatalogo::query()->whereIn('producto_id', $productoIds)->get()->keyBy('producto_id')
            : collect();

        $out = [];
        foreach ($productos as $id => $p) {
            $c = $config->get($id);
            $presentacion = $c && isset(MayorCatalogo::PRESENTACIONES[$c->presentacion]) ? $c->presentacion : 'unidad';
            $unidades = $presentacion === 'unidad' ? 1 : max(1, (int) ($c->unidades ?? 1));
            $out[(int) $id] = [
                'presentacion' => $presentacion,
                'unidades' => $unidades,
                'precio' => round($c?->precio_usd !== null ? (float) $c->precio_usd : (float) $p->precio_mayor * $unidades, 2),
                'costo_unidad' => round($c?->costo_usd !== null ? (float) $c->costo_usd : (float) ($p->costo_actual ?? 0), 4),
                'costo_suite' => round((float) ($p->costo_actual ?? 0), 4),
                'precio_editado' => $c?->precio_usd !== null,
                'costo_editado' => $c?->costo_usd !== null,
            ];
        }

        return $out;
    }

    /**
     * Guarda cómo se vende un producto al mayor. Precio o costo vacíos vuelven a los de Suite.
     *
     * @param  array{presentacion: string, unidades?: ?int, precio_usd?: ?float, costo_usd?: ?float}  $datos
     */
    public function guardarCatalogo(int $productoId, array $datos, ?int $userId): MayorCatalogo
    {
        $presentacion = isset(MayorCatalogo::PRESENTACIONES[$datos['presentacion']]) ? $datos['presentacion'] : 'unidad';
        $unidades = $presentacion === 'unidad' ? 1 : (int) ($datos['unidades'] ?? 0);
        if ($unidades < 1) {
            throw ValidationException::withMessages(['unidades' => 'Indica cuántas unidades trae cada '.mb_strtolower(MayorCatalogo::PRESENTACIONES[$presentacion]).'.']);
        }

        return MayorCatalogo::query()->updateOrCreate(['producto_id' => $productoId], [
            'presentacion' => $presentacion,
            'unidades' => $unidades,
            'precio_usd' => isset($datos['precio_usd']) && $datos['precio_usd'] !== '' ? round((float) $datos['precio_usd'], 2) : null,
            'costo_usd' => isset($datos['costo_usd']) && $datos['costo_usd'] !== '' ? round((float) $datos['costo_usd'], 4) : null,
            'user_id' => $userId,
        ]);
    }

    /** Tasa BCV del día (o la última registrada en Finanzas). */
    public function tasaBcv(?string $fecha = null): float
    {
        $fecha = $fecha ?: now()->toDateString();
        $tasa = FinanzasResumen::query()->whereDate('fecha', '<=', $fecha)->where('tasa_bcv_usd', '>', 1)
            ->orderByDesc('fecha')->value('tasa_bcv_usd');

        return round((float) $tasa, 4);
    }

    /** @return Collection<int, NominaEmpleado> */
    public function vendedoras(): Collection
    {
        return NominaEmpleado::query()->activos()
            ->whereNotNull('codigo_vendedor')->where('codigo_vendedor', '!=', '')
            ->with('cliente')
            ->get()
            ->sortBy(fn ($e) => $e->nombre())
            ->values();
    }

    /** @return Collection<int, MayorCliente> */
    public function buscarClientes(string $q, int $limite = 15): Collection
    {
        $q = trim($q);
        if ($q === '') {
            return collect();
        }
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $doc = MayorCliente::normalizarDocumento($q);

        return MayorCliente::query()
            ->where(function ($w) use ($q, $doc, $like) {
                $w->where('nombre', $like, '%'.$q.'%');
                if ($doc !== '') {
                    $w->orWhere('documento', $like, '%'.$doc.'%');
                }
            })
            ->orderBy('nombre')->limit($limite)->get();
    }

    /**
     * Cliente elegido o nuevo. Si el documento ya existe se usa ese y se completan sus datos.
     *
     * @param  array{cliente_id?: ?int, documento?: ?string, nombre?: ?string, telefono?: ?string, email?: ?string, direccion?: ?string}  $datos
     */
    public function resolverCliente(array $datos, ?int $userId): MayorCliente
    {
        if (! empty($datos['cliente_id'])) {
            $cliente = MayorCliente::query()->find((int) $datos['cliente_id']);
            if ($cliente) {
                return $cliente;
            }
        }
        $documento = MayorCliente::normalizarDocumento($datos['documento'] ?? '');
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($documento === '' || $nombre === '') {
            throw ValidationException::withMessages(['cliente' => 'Elige el cliente o registra uno nuevo con su RIF/cédula y nombre.']);
        }
        $extra = array_filter([
            'telefono' => trim((string) ($datos['telefono'] ?? '')),
            'email' => trim((string) ($datos['email'] ?? '')),
            'direccion' => trim((string) ($datos['direccion'] ?? '')),
        ], fn ($v) => $v !== '');

        $cliente = MayorCliente::query()->firstOrNew(['documento' => $documento]);
        if (! $cliente->exists) {
            $cliente->fill(['nombre' => mb_substr($nombre, 0, 200), 'user_id' => $userId] + $extra)->save();
        } elseif ($extra !== []) {
            $cliente->fill(array_filter($extra, fn ($v, $k) => empty($cliente->{$k}), ARRAY_FILTER_USE_BOTH))->save();
        }

        return $cliente;
    }

    /**
     * Emite la factura. Cada cantidad tiene que caber en lo disponible de JRZ (stock − apartado).
     *
     * Contado: se paga todo al emitir. Crédito: queda por cobrar todo o una parte, con fecha de vencimiento.
     *
     * @param  array{fecha: string, tasa_bcv: float, vendedor_empleado_id: ?int, observaciones: ?string,
     *               condicion?: string, fecha_vencimiento?: ?string,
     *               items: list<array{producto_id: int, cantidad: int, precio_usd: float}>}  $datos
     */
    public function emitir(MayorCliente $cliente, array $datos, User $user, ?float $abonoInicial = null): MayorFactura
    {
        $items = collect($datos['items'])
            ->filter(fn ($i) => (int) ($i['cantidad'] ?? 0) > 0)
            ->groupBy(fn ($i) => (int) $i['producto_id'])
            ->map(fn ($g) => ['producto_id' => (int) $g->first()['producto_id'], 'cantidad' => (int) $g->sum('cantidad'), 'precio_usd' => round((float) $g->first()['precio_usd'], 2)])
            ->values();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un producto con su cantidad.']);
        }
        // El precio se pone en la ficha del producto: la factura usa el del catálogo al mayor aunque el formulario traiga otro.
        $precios = $this->catalogo($items->pluck('producto_id')->all());
        $items = $items->map(fn ($i) => ['precio_usd' => (float) ($precios[$i['producto_id']]['precio'] ?? 0)] + $i)->values();
        if ($items->contains(fn ($i) => $i['precio_usd'] <= 0)) {
            throw ValidationException::withMessages(['items' => 'Hay productos sin precio al mayor: hay que ponerle precio en su ficha.']);
        }
        $tasa = round((float) $datos['tasa_bcv'], 4);
        if ($tasa <= 0) {
            throw ValidationException::withMessages(['tasa_bcv' => 'Indica la tasa BCV del día.']);
        }

        $vendedor = ! empty($datos['vendedor_empleado_id'])
            ? NominaEmpleado::query()->with('cliente')->find((int) $datos['vendedor_empleado_id'])
            : null;
        if (! $vendedor) {
            throw ValidationException::withMessages(['vendedor_empleado_id' => 'Elige la vendedora.']);
        }

        $total = round($items->sum(fn ($i) => $i['cantidad'] * $i['precio_usd']), 2);
        $condicion = ($datos['condicion'] ?? MayorFactura::CONTADO) === MayorFactura::CREDITO ? MayorFactura::CREDITO : MayorFactura::CONTADO;
        if ($abonoInicial !== null && $abonoInicial > $total + 0.009) {
            throw ValidationException::withMessages(['pago_monto_usd' => 'Lo pagado ($'.number_format($abonoInicial, 2).') es más que el total de la factura ($'.number_format($total, 2).').']);
        }
        if ($condicion === MayorFactura::CONTADO && $abonoInicial !== null && $abonoInicial < $total - 0.009) {
            throw ValidationException::withMessages(['pago_monto_usd' => 'De contado se paga todo ($'.number_format($total, 2).'). Si queda una parte por pagar, márcala a crédito.']);
        }
        $vence = $datos['fecha_vencimiento'] ?? null;
        if ($condicion === MayorFactura::CREDITO && (! $vence || $vence < $datos['fecha'])) {
            throw ValidationException::withMessages(['fecha_vencimiento' => 'A crédito: indica hasta cuándo tiene para pagar (desde la fecha de la factura).']);
        }
        $datos['condicion'] = $condicion;
        $datos['fecha_vencimiento'] = $condicion === MayorFactura::CREDITO ? $vence : null;

        // Una factura a la vez: dos personas no pueden apartar las mismas unidades de JRZ.
        return Cache::lock('ventas_mayor_emitir', 15)->block(10, function () use ($items, $tasa, $vendedor, $cliente, $datos, $user, $total) {
            $ids = $items->pluck('producto_id')->all();
            $productos = DB::table('productos')->whereIn('id', $ids)->get(['id', 'codigo', 'nombre'])->keyBy('id');
            $stock = $this->stockPorSede($ids);
            $apartado = $this->apartadoJrz($ids);
            $catalogo = $this->catalogo($ids);
            $faltan = [];
            foreach ($items as $i) {
                $p = $productos->get($i['producto_id']);
                if (! $p) {
                    throw ValidationException::withMessages(['items' => 'Un producto de la factura ya no existe.']);
                }
                $presentacion = $catalogo[$i['producto_id']]['presentacion'] ?? 'unidad';
                $unidadesPor = $catalogo[$i['producto_id']]['unidades'] ?? 1;
                $disponible = max(0, (int) ($stock[$i['producto_id']][self::SEDE_FACTURA] ?? 0) - (int) ($apartado[$i['producto_id']] ?? 0));
                if ($i['cantidad'] * $unidadesPor > $disponible) {
                    $faltan[] = $unidadesPor > 1
                        ? "{$p->nombre}: pides ".MayorCatalogo::cantidadTexto($i['cantidad'], $presentacion).' ('.($i['cantidad'] * $unidadesPor)." und.) y en JRZ hay {$disponible} und."
                        : "{$p->nombre}: pides {$i['cantidad']} y en JRZ hay {$disponible} disponibles";
                }
            }
            if ($faltan !== []) {
                throw ValidationException::withMessages(['items' => 'No alcanza el stock de JRZ. '.implode(' · ', $faltan).'.']);
            }

            return DB::transaction(function () use ($items, $productos, $catalogo, $tasa, $vendedor, $cliente, $datos, $user, $total) {
                $factura = MayorFactura::create([
                    'numero' => ((int) MayorFactura::query()->max('numero')) + 1,
                    'fecha' => $datos['fecha'],
                    'cliente_id' => $cliente->id,
                    'vendedor_empleado_id' => $vendedor->id,
                    'vendedor_nombre' => $vendedor->nombre(),
                    'user_id' => $user->id,
                    'tasa_bcv' => $tasa,
                    'total_usd' => $total,
                    'total_bs' => round($total * $tasa, 2),
                    'estado' => MayorFactura::ESTADO_EMITIDA,
                    'condicion' => $datos['condicion'],
                    'fecha_vencimiento' => $datos['fecha_vencimiento'],
                    'observaciones' => $datos['observaciones'] ?? null,
                ]);
                $conPresentacion = Schema::hasColumn('mayor_factura_items', 'unidades');
                foreach ($items as $i) {
                    $p = $productos->get($i['producto_id']);
                    $unidadesPor = $catalogo[$i['producto_id']]['unidades'] ?? 1;
                    $factura->items()->create([
                        'producto_id' => $i['producto_id'],
                        'codigo' => $p->codigo,
                        'descripcion' => mb_substr((string) $p->nombre, 0, 300),
                        'cantidad' => $i['cantidad'],
                        'precio_usd' => $i['precio_usd'],
                        'total_usd' => round($i['cantidad'] * $i['precio_usd'], 2),
                    ] + ($conPresentacion ? [
                        'presentacion' => $catalogo[$i['producto_id']]['presentacion'] ?? 'unidad',
                        'unidades_por' => $unidadesPor,
                        'unidades' => $i['cantidad'] * $unidadesPor,
                    ] : []));
                }

                return $factura;
            });
        });
    }

    /**
     * Pago o abono: el monto va en el primer comprobante; los demás comprobantes quedan como soporte.
     * Un abono en efectivo puede no tener comprobante.
     *
     * @param  list<UploadedFile>  $archivos
     * @param  array{fecha?: ?string, forma_pago?: ?string, referencia?: ?string}  $datos
     */
    public function registrarPago(MayorFactura $factura, ?float $monto, array $archivos, array $datos, ?int $userId): void
    {
        $monto = $monto !== null && $monto > 0 ? round($monto, 2) : null;
        if ($monto === null && $archivos === []) {
            throw ValidationException::withMessages(['monto_usd' => 'Indica el monto del pago o sube el comprobante.']);
        }
        $factura->loadMissing('pagos');
        if ($monto !== null && $monto > $factura->saldoUsd() + 0.009) {
            throw ValidationException::withMessages(['monto_usd' => 'El pago ($'.number_format($monto, 2).') es más que lo que se debe ($'.number_format($factura->saldoUsd(), 2).').']);
        }
        $base = [
            'fecha' => $datos['fecha'] ?? now()->toDateString(),
            'forma_pago' => $datos['forma_pago'] ?? null,
            'referencia' => $datos['referencia'] ?? null,
            'user_id' => $userId,
        ];
        if ($archivos === []) {
            $factura->pagos()->create($base + ['monto_usd' => $monto]);

            return;
        }
        foreach (array_values($archivos) as $k => $archivo) {
            $factura->pagos()->create($base + [
                'url' => $this->subirArchivo($archivo, $factura),
                'nombre_archivo' => mb_substr($archivo->getClientOriginalName(), 0, 200),
                'monto_usd' => $k === 0 ? $monto : null,
            ]);
        }
    }

    private function subirArchivo(UploadedFile $archivo, MayorFactura $factura): string
    {
        return $this->subirContenido(
            (string) file_get_contents($archivo->getRealPath()),
            (string) $archivo->getClientMimeType(),
            strtolower($archivo->getClientOriginalExtension() ?: 'bin'),
            'mayor_'.$factura->numero,
        );
    }

    public function subirContenido(string $contenido, string $mime, string $extension, string $prefijo): string
    {
        return app(\App\Services\ComprobantesStorage::class)->subirContenido($contenido, $mime, $extension, $prefijo);
    }
}
