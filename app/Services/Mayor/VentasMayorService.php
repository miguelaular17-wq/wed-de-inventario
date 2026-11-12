<?php

namespace App\Services\Mayor;

use App\Models\FinanzasResumen;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Ventas al mayor: se ve el stock de todas las sedes, pero la factura solo puede salir del de JRZ.
 * Lo facturado aquí y aún no cargado en Profit queda apartado (se resta del disponible de JRZ).
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
    public function productos(string $q = '', int $limite = 60, bool|string $soloConStock = false): Collection
    {
        $filtro = $soloConStock === true ? 'alguna' : ($soloConStock ?: 'todos');
        $query = DB::table('productos')->where('activo', true)
            ->where(fn ($w) => $w->where('oculto', false)->orWhereNull('oculto'));
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
            ->get(['id', 'codigo', 'nombre', 'categoria', 'precio_unidad', 'precio_mayor']);

        return $this->conStock($productos);
    }

    /** @return array{productos: int, unidades: int, apartadas: int, valor_mayor: float} Stock de JRZ en total. */
    public function resumenJrz(): array
    {
        $fila = DB::table('stock_actual as s')->join('productos as p', 'p.id', '=', 's.producto_id')
            ->where('p.activo', true)->whereRaw('UPPER(TRIM(s.sede)) = ?', [self::SEDE_FACTURA])->where('s.existencia', '>', 0)
            ->selectRaw('COUNT(DISTINCT s.producto_id) as productos, COALESCE(SUM(s.existencia), 0) as unidades, COALESCE(SUM(s.existencia * p.precio_mayor), 0) as valor')
            ->first();
        $apartadas = (int) DB::table('mayor_factura_items as i')->join('mayor_facturas as f', 'f.id', '=', 'i.factura_id')
            ->where('f.estado', MayorFactura::ESTADO_EMITIDA)->where('f.en_profit', false)->sum('i.cantidad');

        return [
            'productos' => (int) ($fila->productos ?? 0),
            'unidades' => (int) ($fila->unidades ?? 0),
            'apartadas' => $apartadas,
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

        return $productos->map(function ($p) use ($stock, $apartado) {
            $id = (int) $p->id;
            $p->stocks = [];
            foreach ($this->sedes() as $sede) {
                $p->stocks[$sede] = (int) ($stock[$id][$sede] ?? 0);
            }
            $p->stock_total = array_sum($p->stocks);
            $p->apartado_jrz = (int) ($apartado[$id] ?? 0);
            $p->disponible_jrz = max(0, $p->stocks[self::SEDE_FACTURA] - $p->apartado_jrz);
            $p->precio_mayor = round((float) ($p->precio_mayor ?? 0), 2);
            $p->precio_unidad = round((float) ($p->precio_unidad ?? 0), 2);

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
     * Unidades de JRZ apartadas por facturas al mayor emitidas y aún no cargadas en Profit.
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
            ->selectRaw('i.producto_id, SUM(i.cantidad) as unidades')
            ->pluck('unidades', 'producto_id')
            ->map(fn ($u) => (int) $u)
            ->all();
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
        if ($items->contains(fn ($i) => $i['precio_usd'] <= 0)) {
            throw ValidationException::withMessages(['items' => 'Cada producto necesita un precio mayor que cero.']);
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
            $productos = DB::table('productos')->whereIn('id', $items->pluck('producto_id'))->get(['id', 'codigo', 'nombre'])->keyBy('id');
            $stock = $this->stockPorSede($items->pluck('producto_id')->all());
            $apartado = $this->apartadoJrz($items->pluck('producto_id')->all());
            $faltan = [];
            foreach ($items as $i) {
                $p = $productos->get($i['producto_id']);
                if (! $p) {
                    throw ValidationException::withMessages(['items' => 'Un producto de la factura ya no existe.']);
                }
                $disponible = max(0, (int) ($stock[$i['producto_id']][self::SEDE_FACTURA] ?? 0) - (int) ($apartado[$i['producto_id']] ?? 0));
                if ($i['cantidad'] > $disponible) {
                    $faltan[] = "{$p->nombre}: pides {$i['cantidad']} y en JRZ hay {$disponible} disponibles";
                }
            }
            if ($faltan !== []) {
                throw ValidationException::withMessages(['items' => 'No alcanza el stock de JRZ. '.implode(' · ', $faltan).'.']);
            }

            return DB::transaction(function () use ($items, $productos, $tasa, $vendedor, $cliente, $datos, $user, $total) {
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
                foreach ($items as $i) {
                    $p = $productos->get($i['producto_id']);
                    $factura->items()->create([
                        'producto_id' => $i['producto_id'],
                        'codigo' => $p->codigo,
                        'descripcion' => mb_substr((string) $p->nombre, 0, 300),
                        'cantidad' => $i['cantidad'],
                        'precio_usd' => $i['precio_usd'],
                        'total_usd' => round($i['cantidad'] * $i['precio_usd'], 2),
                    ]);
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
