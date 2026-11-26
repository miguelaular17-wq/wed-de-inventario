<?php

namespace App\Http\Controllers;

use App\Models\Mayor\MayorConfig;
use App\Models\Mayor\MayorFactura;
use App\Models\Mayor\MayorFacturaPago;
use App\Services\Mayor\VentasMayorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class VentasMayorController extends Controller
{
    public function __construct(private VentasMayorService $servicio) {}

    /** Stock de todas las sedes, con JRZ (la que se factura) primero y resaltada. */
    public function stock(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $ver = in_array($request->query('ver'), ['jrz', 'alguna', 'todos'], true) ? $request->query('ver') : 'alguna';
        $limite = $q === '' ? 200 : 300;

        return view('ventas_mayor.stock', [
            'productos' => $this->servicio->productos($q, $limite, $ver),
            'sedes' => $this->servicio->sedes(),
            'resumen' => $this->servicio->resumenJrz(),
            'q' => $q,
            'ver' => $ver,
            'limite' => $limite,
            'puedeEditarCatalogo' => (bool) $request->user()?->canAccess('ventas_mayor.catalogo'),
        ]);
    }

    public function facturas(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $porCobrar = $request->boolean('por_cobrar');
        $pagado = '(SELECT COALESCE(SUM(p.monto_usd), 0) FROM mayor_factura_pagos p WHERE p.factura_id = mayor_facturas.id)';
        $facturas = MayorFactura::query()->with(['cliente', 'pagos'])
            ->when($porCobrar, fn ($query) => $query->where('estado', MayorFactura::ESTADO_EMITIDA)
                ->whereRaw("total_usd - {$pagado} >= 0.01"))
            ->when($q !== '', function ($query) use ($q) {
                $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $numero = (int) preg_replace('/\D/', '', $q);
                $query->where(function ($w) use ($q, $like, $numero) {
                    $w->whereHas('cliente', fn ($c) => $c->where('nombre', $like, '%'.$q.'%')->orWhere('documento', $like, '%'.$q.'%'))
                        ->orWhere('vendedor_nombre', $like, '%'.$q.'%');
                    if ($numero > 0) {
                        $w->orWhere('numero', $numero);
                    }
                });
            })
            ->orderByDesc('numero')
            ->paginate(30)->withQueryString();

        $porCobrarTotal = (float) MayorFactura::query()->where('estado', MayorFactura::ESTADO_EMITIDA)
            ->selectRaw("COALESCE(SUM(CASE WHEN total_usd - {$pagado} > 0 THEN total_usd - {$pagado} ELSE 0 END), 0) as saldo")
            ->value('saldo');

        return view('ventas_mayor.facturas', ['facturas' => $facturas, 'q' => $q, 'porCobrar' => $porCobrar, 'porCobrarTotal' => round($porCobrarTotal, 2)]);
    }

    public function create(Request $request): View
    {
        // Desde el catálogo/stock: «Facturar» abre la factura con esos productos (y cantidades) ya cargados.
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $request->query('productos', '')))));
        $ids = array_slice($ids, 0, 50);
        $cantsRaw = array_map('intval', explode(',', (string) $request->query('cants', '')));
        $cantPorId = [];
        foreach ($ids as $i => $id) {
            $cantPorId[$id] = max(1, (int) ($cantsRaw[$i] ?? 1));
        }
        $iniciales = $ids === [] ? collect() : $this->servicio->conStock(
            \Illuminate\Support\Facades\DB::table('productos')->whereIn('id', $ids)->get(['id', 'codigo', 'nombre', 'categoria', 'precio_unidad', 'precio_mayor'])
        )->filter(fn ($p) => $p->disponible_presentaciones > 0)
            ->sortBy(fn ($p) => array_search((int) $p->id, $ids, true))
            ->map(function ($p) use ($cantPorId) {
                $fila = $this->paraFactura($p);
                $cant = (int) ($cantPorId[(int) $p->id] ?? 1);
                $disp = (int) ($p->disponible_presentaciones ?? 0);
                $fila['cantidad_inicial'] = $disp > 0 ? min($cant, $disp) : max(1, $cant);

                return $fila;
            })->values();

        return view('ventas_mayor.create', [
            'vendedoras' => $this->servicio->vendedoras(),
            'tasa' => $this->servicio->tasaBcv(),
            'iniciales' => $iniciales,
            'clienteInicial' => $request->filled('cliente_id') ? \App\Models\Mayor\MayorCliente::query()->find((int) $request->query('cliente_id')) : null,
            'formas' => $this->servicio->formasPago(),
            'formasDivisa' => VentasMayorService::FORMAS_DIVISA,
            'descuentoDivisaPct' => $this->servicio->descuentoDivisaPct(),
        ]);
    }

    /** Solo gerente (o admin): descuento por pago en divisas. */
    public function configuracion(Request $request): View
    {
        abort_unless($request->user()?->isGerente() || $request->user()?->isAdmin(), 403);

        return view('ventas_mayor.configuracion', [
            'descuentoDivisaPct' => $this->servicio->descuentoDivisaPct(),
            'formasDivisa' => VentasMayorService::FORMAS_DIVISA,
        ]);
    }

    public function guardarConfiguracion(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isGerente() || $request->user()?->isAdmin(), 403);
        $data = $request->validate([
            'descuento_divisa_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        MayorConfig::put(VentasMayorService::CLAVE_DESCUENTO_DIVISA, round((float) $data['descuento_divisa_pct'], 4));

        return redirect()->route('ventas_mayor.configuracion')->with('success', 'Descuento en divisas guardado: '
            .number_format((float) $data['descuento_divisa_pct'], 2).'%.');
    }

    public function buscarProductos(Request $request): JsonResponse
    {
        $productos = $this->servicio->productos((string) $request->query('q', ''), 25);

        return response()->json(['productos' => $productos->map(fn ($p) => $this->paraFactura($p) + [
            'precio_unidad' => $p->precio_unidad,
            'otras_sedes' => collect($p->stocks)->except(VentasMayorService::SEDE_FACTURA)->filter()->all(),
        ])->values()]);
    }

    /** Lo que necesita la factura de un producto: se vende por su presentación (unidad, caja o paquete). */
    private function paraFactura(object $p): array
    {
        return [
            'id' => (int) $p->id,
            'codigo' => $p->codigo,
            'nombre' => $p->nombre,
            'precio_mayor' => $p->precio_presentacion,
            'disponible_jrz' => $p->disponible_jrz,
            'disponible_pres' => $p->disponible_presentaciones,
            'presentacion' => $p->presentacion,
            'presentacion_texto' => $p->presentacion_texto,
            'unidades_por' => $p->unidades_por,
        ];
    }

    /** Catálogo al mayor: mismos filtros que el catálogo normal. */
    public function catalogo(Request $request): View
    {
        $user = $request->user();
        $puedeEditar = (bool) $user?->canAccess('ventas_mayor.catalogo');
        // Ocultar/restaurar: regente (permiso de catálogo), gerente/admin, o el cargo Ventas al mayor.
        $puedeOcultar = $puedeEditar || (bool) $user?->isMayor();
        $filtros = $this->filtrosCatalogo($request, null, $puedeOcultar);
        $opciones = $this->servicio->opcionesFiltroCatalogo();
        $descuentoPct = $this->servicio->descuentoDivisaPct();

        return view('ventas_mayor.catalogo', [
            'productos' => $this->servicio->catalogoClientes($filtros),
            'categorias' => $opciones['categorias'],
            'subcategorias' => $opciones['subcategorias'],
            'subcategoriasElegidas' => $filtros['subcategorias'],
            'catSubcatMap' => $opciones['catSubcatMap'],
            'sedes' => $opciones['sedes'],
            'sedesExcluidas' => $filtros['excluir_sedes'],
            'filtros' => $filtros,
            'limite' => $filtros['limite'],
            'puedeEditarCatalogo' => $puedeEditar,
            'puedeOcultarCatalogo' => $puedeOcultar,
            'rangos' => [
                'stock' => ['titulo' => 'Stock JRZ (unidades)', 'pasos' => \App\Http\Controllers\CatalogoController::PASOS_STOCK, 'prefijo' => ''],
                'precio' => ['titulo' => 'Precio unidad', 'pasos' => \App\Http\Controllers\CatalogoController::PASOS_PRECIO, 'prefijo' => '$'],
            ],
            'descuentoDivisaPct' => $descuentoPct,
            'descuentoEtiqueta' => rtrim(rtrim(number_format($descuentoPct, 2, '.', ''), '0'), '.').'%',
            'descuentoFactor' => max(0, 1 - ($descuentoPct / 100)),
        ]);
    }

    public function catalogoPdf(Request $request): Response
    {
        ini_set('memory_limit', '768M');
        set_time_limit(300);
        // El PDF nunca incluye productos ocultos del catálogo al mayor.
        $filtros = $this->filtrosCatalogo($request, 300, false);
        $filtros['ver_ocultos'] = false;
        $productos = $this->servicio->catalogoClientes($filtros);
        $fotos = \App\Support\FotoProducto::embebidas($productos->mapWithKeys(fn ($p) => [(int) $p->id => (string) $p->codigo])->all());
        $logo = is_file(public_path('logo.png'))
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('logo.png')))
            : null;
        $descuentoPct = $this->servicio->descuentoDivisaPct();

        return Pdf::loadView('ventas_mayor.catalogo_pdf', [
            'productos' => $productos,
            'fotos' => $fotos,
            'logo' => $logo,
            'categoria' => $filtros['categoria'],
            'q' => $filtros['q'],
            'descuentoDivisaPct' => $descuentoPct,
            'descuentoEtiqueta' => rtrim(rtrim(number_format($descuentoPct, 2, '.', ''), '0'), '.').'%',
            'descuentoFactor' => max(0, 1 - ($descuentoPct / 100)),
        ])->setPaper('letter')
            ->download('catalogo_al_mayor_'.now()->format('Ymd').'.pdf');
    }

    /**
     * @return array{
     *   q: string,
     *   categoria: string,
     *   subcategorias: list<string>,
     *   sede: string,
     *   excluir_sedes: list<string>,
     *   solo_existencia: bool,
     *   stock_min: ?float,
     *   stock_max: ?float,
     *   precio_min: ?float,
     *   precio_max: ?float,
     *   limite: int,
     *   ver_ocultos: bool
     * }
     */
    private function filtrosCatalogo(Request $request, ?int $limiteForzado = null, bool $permitirVerOcultos = false): array
    {
        $sub = $request->input('subcategoria');
        $subLista = is_array($sub) ? $sub : [$sub];
        $excluir = $request->input('excluir_sede', []);
        $excluirLista = is_array($excluir) ? $excluir : [$excluir];
        $perPage = (int) $request->input('per_page', 24);
        if (! in_array($perPage, [24, 48, 72, 96], true)) {
            $perPage = 24;
        }
        $leer = function (string $campo) use ($request): ?float {
            $valor = $request->input($campo);

            return is_numeric($valor) && (float) $valor >= 0 ? (float) $valor : null;
        };

        return [
            'q' => trim((string) $request->query('q', $request->query('search', ''))),
            'categoria' => trim((string) $request->query('categoria', '')),
            'subcategorias' => array_values(array_unique(array_filter(
                array_map(fn ($s) => trim((string) $s), $subLista),
                fn ($s) => $s !== '' && strtolower($s) !== 'todas'
            ))),
            'sede' => trim((string) $request->query('sede', \App\Services\Mayor\VentasMayorService::SEDE_FACTURA)),
            'excluir_sedes' => array_values(array_unique(array_filter(
                array_map(fn ($s) => strtoupper(trim((string) $s)), $excluirLista)
            ))),
            'solo_existencia' => $request->boolean('solo_existencia'),
            'stock_min' => $leer('stock_min'),
            'stock_max' => $leer('stock_max'),
            'precio_min' => $leer('precio_min'),
            'precio_max' => $leer('precio_max'),
            'limite' => $limiteForzado ?? $perPage,
            'ver_ocultos' => $permitirVerOcultos && $request->boolean('ver_ocultos'),
        ];
    }

    /** Cómo se vende el producto al mayor: presentación, unidades por caja/paquete, precio y costo. */
    public function guardarCatalogo(Request $request, int $producto): RedirectResponse
    {
        abort_unless(\Illuminate\Support\Facades\DB::table('productos')->where('id', $producto)->exists(), 404);
        $data = $request->validate([
            'presentacion' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Mayor\MayorCatalogo::PRESENTACIONES))],
            'unidades' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'precio_usd' => ['nullable', 'numeric', 'min:0.01', 'max:10000000'],
            'costo_usd' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'oculto' => ['sometimes', 'boolean'],
        ]);
        if ($request->has('oculto')) {
            $data['oculto'] = $request->boolean('oculto');
        }
        $this->servicio->guardarCatalogo($producto, $data, $request->user()?->id);

        return back()->with('success', 'Se guardó cómo se vende al mayor.');
    }

    /** Oculta o restaura un producto solo del catálogo al mayor (listado y PDF). */
    public function toggleOcultoCatalogo(Request $request, int $producto): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->canAccess('ventas_mayor.catalogo') || $user->isMayor()),
            403,
            'Solo el gerente, la regente o Ventas al mayor pueden ocultar productos del catálogo.'
        );
        abort_unless(\Illuminate\Support\Facades\DB::table('productos')->where('id', $producto)->exists(), 404);
        $fila = $this->servicio->toggleOcultoCatalogo($producto, $user?->id);
        $label = $fila->oculto
            ? 'ocultado del catálogo al mayor'
            : 'restaurado en el catálogo al mayor';

        return back()->with('success', "Producto {$label}.");
    }

    public function clientes(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $filtroFotos = (string) $request->query('fotos', '');
        $like = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = \App\Models\Mayor\MayorCliente::query()->withCount('archivos')->withCount('facturas');
        if ($q !== '') {
            $doc = \App\Models\Mayor\MayorCliente::normalizarDocumento($q);
            $query->where(function ($w) use ($q, $doc, $like) {
                $w->where('nombre', $like, '%'.$q.'%');
                if ($doc !== '') {
                    $w->orWhere('documento', $like, '%'.$doc.'%');
                }
                $w->orWhere('telefono', $like, '%'.$q.'%')
                    ->orWhere('email', $like, '%'.$q.'%');
            });
        }
        if ($filtroFotos === 'con') {
            $query->has('archivos');
        } elseif ($filtroFotos === 'sin') {
            $query->doesntHave('archivos');
        }

        $clientes = $query->orderBy('nombre')->paginate(40)->withQueryString();

        return view('ventas_mayor.clientes.index', [
            'clientes' => $clientes,
            'q' => $q,
            'fotos' => $filtroFotos,
            'total' => \App\Models\Mayor\MayorCliente::query()->count(),
            'conFotos' => \App\Models\Mayor\MayorCliente::query()->has('archivos')->count(),
        ]);
    }

    public function clienteShow(int $cliente): View
    {
        $c = \App\Models\Mayor\MayorCliente::query()
            ->with(['archivos'])
            ->withCount('facturas')
            ->findOrFail($cliente);

        $facturas = \App\Models\Mayor\MayorFactura::query()
            ->where('cliente_id', $c->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('ventas_mayor.clientes.show', [
            'cliente' => $c,
            'facturas' => $facturas,
            'maxArchivos' => \App\Models\Mayor\MayorClienteArchivo::MAX_POR_CLIENTE,
        ]);
    }

    public function guardarCliente(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'documento' => ['required', 'string', 'max:40'],
            'nombre' => ['required', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:300'],
            'archivos' => ['nullable', 'array', 'max:4'],
            'archivos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        $cliente = $this->servicio->resolverCliente([
            'documento' => $data['documento'],
            'nombre' => $data['nombre'],
            'telefono' => $data['telefono'] ?? null,
            'email' => $data['email'] ?? null,
            'direccion' => $data['direccion'] ?? null,
        ], $request->user()?->id);

        // Si ya existía, actualizar datos visibles.
        $cliente->fill([
            'nombre' => mb_substr($data['nombre'], 0, 200),
            'telefono' => $data['telefono'] ?? $cliente->telefono,
            'email' => $data['email'] ?? $cliente->email,
            'direccion' => $data['direccion'] ?? $cliente->direccion,
        ])->save();

        $archivos = array_values(array_filter($request->file('archivos', []) ?: []));
        if ($archivos !== []) {
            $this->servicio->guardarArchivosCliente($cliente, $archivos, $request->user()?->id);
        }

        return redirect()->route('ventas_mayor.clientes.show', $cliente)
            ->with('success', 'Cliente guardado.');
    }

    public function actualizarCliente(Request $request, int $cliente): RedirectResponse
    {
        $c = \App\Models\Mayor\MayorCliente::query()->findOrFail($cliente);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:300'],
            'archivos' => ['nullable', 'array', 'max:4'],
            'archivos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        $c->fill([
            'nombre' => mb_substr($data['nombre'], 0, 200),
            'telefono' => $data['telefono'] ?? null,
            'email' => $data['email'] ?? null,
            'direccion' => $data['direccion'] ?? null,
        ])->save();

        $archivos = array_values(array_filter($request->file('archivos', []) ?: []));
        if ($archivos !== []) {
            $this->servicio->guardarArchivosCliente($c, $archivos, $request->user()?->id);
        }

        return redirect()->route('ventas_mayor.clientes.show', $c)
            ->with('success', 'Cliente actualizado.');
    }

    public function buscarClientes(Request $request): JsonResponse
    {
        return response()->json(['clientes' => $this->servicio->buscarClientes((string) $request->query('q', ''))
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'documento' => $c->documento,
                'nombre' => $c->nombre,
                'telefono' => $c->telefono,
                'email' => $c->email,
                'direccion' => $c->direccion,
                'archivos_count' => (int) ($c->archivos_count ?? 0),
                'tiene_fotos' => (int) ($c->archivos_count ?? 0) > 0,
            ])->values()]);
    }

    public function archivosCliente(int $cliente): JsonResponse
    {
        $c = \App\Models\Mayor\MayorCliente::query()->findOrFail($cliente);

        return response()->json([
            'cliente_id' => (int) $c->id,
            'archivos' => $this->servicio->archivosClienteParaApi($c),
            'max' => \App\Models\Mayor\MayorClienteArchivo::MAX_POR_CLIENTE,
        ]);
    }

    public function subirArchivosCliente(Request $request, int $cliente): JsonResponse|RedirectResponse
    {
        $c = \App\Models\Mayor\MayorCliente::query()->findOrFail($cliente);
        $data = $request->validate([
            'slot' => ['nullable', 'integer', 'min:1', 'max:4'],
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);
        $slot = isset($data['slot']) ? (int) $data['slot'] : null;
        if ($slot) {
            $this->servicio->guardarArchivoClienteEnSlot($c, $slot, $request->file('archivo'), $request->user()?->id);
        } else {
            $this->servicio->guardarArchivosCliente($c, [$request->file('archivo')], $request->user()?->id);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'archivos' => $this->servicio->archivosClienteParaApi($c->fresh()),
                'archivos_count' => $c->archivos()->count(),
            ]);
        }

        return redirect()->route('ventas_mayor.clientes.show', $c)->with('success', 'Archivo subido al bucket Clientes VM.');
    }

    public function borrarArchivoCliente(Request $request, int $cliente, int $archivo): JsonResponse|RedirectResponse
    {
        $c = \App\Models\Mayor\MayorCliente::query()->findOrFail($cliente);
        $this->servicio->borrarArchivoCliente($c, $archivo);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'archivos' => $this->servicio->archivosClienteParaApi($c->fresh()),
                'archivos_count' => $c->archivos()->count(),
            ]);
        }

        return redirect()->route('ventas_mayor.clientes.show', $c)->with('success', 'Archivo eliminado.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'tasa_bcv' => ['required', 'numeric', 'min:0.0001'],
            'vendedor_empleado_id' => ['required', 'integer', 'exists:nomina_empleados,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'cliente_id' => ['nullable', 'integer', 'exists:mayor_clientes,id'],
            'cliente_documento' => ['nullable', 'string', 'max:40'],
            'cliente_nombre' => ['nullable', 'string', 'max:200'],
            'cliente_telefono' => ['nullable', 'string', 'max:60'],
            'cliente_email' => ['nullable', 'email', 'max:120'],
            'cliente_direccion' => ['nullable', 'string', 'max:300'],
            'cliente_archivos' => ['nullable', 'array', 'max:4'],
            'cliente_archivos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.producto_id' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio_usd' => ['required', 'numeric', 'min:0.01'],
            'condicion' => ['required', 'in:contado,credito'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'comprobantes' => ['nullable', 'array', 'max:6'],
            'comprobantes.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'pago_forma' => ['nullable', 'string', 'max:60'],
            'pago_referencia' => ['nullable', 'string', 'max:120'],
            'pago_monto_usd' => ['nullable', 'numeric', 'min:0'],
        ]);
        $abono = isset($data['pago_monto_usd']) && $data['pago_monto_usd'] !== '' ? round((float) $data['pago_monto_usd'], 2) : null;
        $user = $request->user();

        $cliente = $this->servicio->resolverCliente([
            'cliente_id' => $data['cliente_id'] ?? null,
            'documento' => $data['cliente_documento'] ?? null,
            'nombre' => $data['cliente_nombre'] ?? null,
            'telefono' => $data['cliente_telefono'] ?? null,
            'email' => $data['cliente_email'] ?? null,
            'direccion' => $data['cliente_direccion'] ?? null,
        ], $user->id);

        $archivosCliente = array_values(array_filter($request->file('cliente_archivos', []) ?: []));
        if ($archivosCliente !== []) {
            $this->servicio->guardarArchivosCliente($cliente, $archivosCliente, $user->id);
        }

        $factura = $this->servicio->emitir($cliente, [
            'fecha' => $data['fecha'],
            'tasa_bcv' => (float) $data['tasa_bcv'],
            'vendedor_empleado_id' => (int) $data['vendedor_empleado_id'],
            'observaciones' => $data['observaciones'] ?? null,
            'condicion' => $data['condicion'],
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            'forma_pago' => $data['pago_forma'] ?? null,
            'items' => array_values($data['items']),
        ], $user, $abono);

        // De contado se paga todo aunque no escriban el monto.
        if ($factura->condicion === MayorFactura::CONTADO && $abono === null) {
            $abono = $factura->total_usd;
        }
        $archivos = $request->file('comprobantes', []);
        $pago = null;
        if (($abono !== null && $abono > 0) || $archivos !== []) {
            $pago = $this->servicio->registrarPago($factura, $abono, $archivos, [
                'fecha' => $data['fecha'],
                'forma_pago' => $data['pago_forma'] ?? null,
                'referencia' => $data['pago_referencia'] ?? null,
            ], $user->id);
        }

        $factura->load('pagos');
        $saldo = $factura->saldoUsd();
        $redir = redirect()->route('ventas_mayor.show', $factura)->with('success', 'Factura '.$factura->numeroFormateado().' emitida'
            .($saldo >= 0.01 ? ' a crédito: queda por cobrar $'.number_format($saldo, 2).'.' : ' y pagada.')
            .' Las unidades se restaron del stock de JRZ.');

        return $pago && ($pago->monto_usd ?? 0) > 0
            ? $redir->with('pago_recibo_id', $pago->id)
            : $redir;
    }

    public function show(MayorFactura $factura): View
    {
        $factura->load(['cliente.archivos', 'items', 'pagos.usuario', 'usuario', 'vendedor.cliente']);

        return view('ventas_mayor.show', ['factura' => $factura]);
    }

    public function pdf(MayorFactura $factura): Response
    {
        $factura->load(['cliente', 'items', 'pagos', 'usuario']);
        $logo = is_file(public_path('logo.png'))
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('logo.png')))
            : null;

        return Pdf::loadView('ventas_mayor.pdf', [
            'factura' => $factura,
            'logo' => $logo,
            'empresa' => config('recibos.empresas.'.VentasMayorService::SEDE_FACTURA, []),
        ])->setPaper('letter')
            ->stream('factura_'.$factura->numeroFormateado().'.pdf');
    }

    /** Factura/recibo de pago al abonar (cualquier crédito o contado). */
    public function pagoPdf(MayorFactura $factura, \App\Models\Mayor\MayorFacturaPago $pago): Response
    {
        abort_unless((int) $pago->factura_id === (int) $factura->id, 404);
        abort_unless(($pago->monto_usd ?? 0) > 0, 404, 'Este registro no tiene monto de pago.');
        $factura->load(['cliente', 'items', 'pagos', 'usuario', 'vendedor']);
        $pago->loadMissing('usuario');
        $logo = is_file(public_path('logo.png'))
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('logo.png')))
            : null;

        // Saldo después de este pago (pagos con id <= este, por orden de registro).
        $pagadoHasta = (float) $factura->pagos
            ->filter(fn ($p) => (int) $p->id <= (int) $pago->id)
            ->sum(fn ($p) => (float) ($p->monto_usd ?? 0));
        $saldoRestante = max(0, round((float) $factura->total_usd - $pagadoHasta, 2));
        $pagadoAntes = max(0, round($pagadoHasta - (float) ($pago->monto_usd ?? 0), 2));

        return Pdf::loadView('ventas_mayor.pago_pdf', [
            'factura' => $factura,
            'pago' => $pago,
            'saldoRestante' => $saldoRestante,
            'pagadoHasta' => $pagadoHasta,
            'pagadoAntes' => $pagadoAntes,
            'logo' => $logo,
            'empresa' => config('recibos.empresas.'.VentasMayorService::SEDE_FACTURA, []),
        ])->setPaper('letter')
            ->stream('pago_'.$factura->numeroFormateado().'_'.$pago->id.'.pdf');
    }

    /** Pago o abono (una factura a crédito se va pagando por partes), con o sin comprobante. */
    public function subirComprobante(Request $request, MayorFactura $factura): RedirectResponse
    {
        abort_if($factura->estaAnulada(), 422, 'La factura está anulada.');
        if ($factura->loadMissing('pagos')->saldoUsd() < 0.01) {
            return back()->withErrors(['monto_usd' => 'La factura ya está pagada: no hace falta otro comprobante.']);
        }
        $data = $request->validate([
            'fecha' => ['nullable', 'date', 'before_or_equal:today'],
            'comprobantes' => ['nullable', 'array', 'max:6'],
            'comprobantes.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'forma_pago' => ['nullable', 'string', 'max:60'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'monto_usd' => ['nullable', 'numeric', 'min:0'],
        ]);
        $monto = isset($data['monto_usd']) && $data['monto_usd'] !== '' ? (float) $data['monto_usd'] : null;
        $pago = $this->servicio->registrarPago($factura, $monto, $request->file('comprobantes', []), [
            'fecha' => $data['fecha'] ?? null,
            'forma_pago' => $data['forma_pago'] ?? null,
            'referencia' => $data['referencia'] ?? null,
        ], $request->user()->id);

        $factura->load('pagos');
        $redir = back()->with('success', $factura->saldoUsd() < 0.01
            ? 'Pago registrado: la factura quedó pagada. Se generó la factura de pago.'
            : 'Pago registrado. Queda por cobrar $'.number_format($factura->saldoUsd(), 2).'. Se generó la factura de pago.');

        return $pago && ($pago->monto_usd ?? 0) > 0
            ? $redir->with('pago_recibo_id', $pago->id)
            : $redir;
    }

    public function borrarComprobante(MayorFactura $factura, MayorFacturaPago $pago): RedirectResponse
    {
        abort_unless((int) $pago->factura_id === (int) $factura->id, 404);
        $pago->delete();

        return back()->with('success', 'Comprobante quitado.');
    }

    /** Ya se cargó en Profit: el stock de la sincronización la refleja y deja de apartarse aparte. */
    public function marcarProfit(Request $request, MayorFactura $factura): RedirectResponse
    {
        abort_if($factura->estaAnulada(), 422, 'La factura está anulada.');
        $factura->update(['en_profit' => ! $factura->en_profit]);

        return back()->with('success', $factura->en_profit
            ? 'Marcada como cargada en Profit: desde ahora Profit descuenta sus unidades del stock de JRZ.'
            : 'Marcada como no cargada en Profit: sus unidades se vuelven a restar del stock de JRZ.');
    }

    public function anular(Request $request, MayorFactura $factura): RedirectResponse
    {
        $data = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:300']]);
        if (! $factura->estaAnulada()) {
            $factura->update(['estado' => MayorFactura::ESTADO_ANULADA, 'anulada_motivo' => $data['motivo']]);
        }

        return back()->with('success', 'Factura anulada: sus unidades se liberaron del stock de JRZ.');
    }
}
