<?php

namespace App\Http\Controllers;

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
        // Desde el stock: «Facturar» abre la factura con ese producto ya cargado.
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $request->query('productos', '')))));
        $iniciales = $ids === [] ? collect() : $this->servicio->conStock(
            \Illuminate\Support\Facades\DB::table('productos')->whereIn('id', array_slice($ids, 0, 50))->get(['id', 'codigo', 'nombre', 'categoria', 'precio_unidad', 'precio_mayor'])
        )->filter(fn ($p) => $p->disponible_presentaciones > 0)->map(fn ($p) => $this->paraFactura($p))->values();

        return view('ventas_mayor.create', [
            'vendedoras' => $this->servicio->vendedoras(),
            'tasa' => $this->servicio->tasaBcv(),
            'iniciales' => $iniciales,
            'clienteInicial' => $request->filled('cliente_id') ? \App\Models\Mayor\MayorCliente::query()->find((int) $request->query('cliente_id')) : null,
        ]);
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

    /** Catálogo para clientes al mayor: lo que hay en JRZ con la presentación y el precio que puso la regente. */
    public function catalogo(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $categoria = trim((string) $request->query('categoria', ''));
        $limite = 240;

        return view('ventas_mayor.catalogo', [
            'productos' => $this->servicio->catalogoClientes($q, $categoria, $limite),
            'categorias' => $this->servicio->categoriasJrz(),
            'q' => $q,
            'categoria' => $categoria,
            'limite' => $limite,
        ]);
    }

    public function catalogoPdf(Request $request): Response
    {
        ini_set('memory_limit', '768M');
        set_time_limit(300);
        $q = trim((string) $request->query('q', ''));
        $categoria = trim((string) $request->query('categoria', ''));
        $productos = $this->servicio->catalogoClientes($q, $categoria, 300);
        $fotos = \App\Support\FotoProducto::embebidas($productos->mapWithKeys(fn ($p) => [(int) $p->id => (string) $p->codigo])->all());
        $logo = is_file(public_path('logo.png'))
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('logo.png')))
            : null;

        return Pdf::loadView('ventas_mayor.catalogo_pdf', [
            'productos' => $productos,
            'fotos' => $fotos,
            'logo' => $logo,
            'categoria' => $categoria,
            'q' => $q,
        ])->setPaper('letter')
            ->stream('catalogo_al_mayor_'.now()->format('Ymd').'.pdf');
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
        ]);
        $this->servicio->guardarCatalogo($producto, $data, $request->user()?->id);

        return back()->with('success', 'Se guardó cómo se vende al mayor.');
    }

    public function buscarClientes(Request $request): JsonResponse
    {
        return response()->json(['clientes' => $this->servicio->buscarClientes((string) $request->query('q', ''))
            ->map(fn ($c) => $c->only(['id', 'documento', 'nombre', 'telefono', 'email', 'direccion']))->values()]);
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

        $factura = $this->servicio->emitir($cliente, [
            'fecha' => $data['fecha'],
            'tasa_bcv' => (float) $data['tasa_bcv'],
            'vendedor_empleado_id' => (int) $data['vendedor_empleado_id'],
            'observaciones' => $data['observaciones'] ?? null,
            'condicion' => $data['condicion'],
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            'items' => array_values($data['items']),
        ], $user, $abono);

        // De contado se paga todo aunque no escriban el monto.
        if ($factura->condicion === MayorFactura::CONTADO && $abono === null) {
            $abono = $factura->total_usd;
        }
        $archivos = $request->file('comprobantes', []);
        if (($abono !== null && $abono > 0) || $archivos !== []) {
            $this->servicio->registrarPago($factura, $abono, $archivos, [
                'fecha' => $data['fecha'],
                'forma_pago' => $data['pago_forma'] ?? null,
                'referencia' => $data['pago_referencia'] ?? null,
            ], $user->id);
        }

        $factura->load('pagos');
        $saldo = $factura->saldoUsd();

        return redirect()->route('ventas_mayor.show', $factura)->with('success', 'Factura '.$factura->numeroFormateado().' emitida'
            .($saldo >= 0.01 ? ' a crédito: queda por cobrar $'.number_format($saldo, 2).'.' : ' y pagada.')
            .' Las unidades se restaron del stock de JRZ.');
    }

    public function show(MayorFactura $factura): View
    {
        $factura->load(['cliente', 'items', 'pagos.usuario', 'usuario', 'vendedor.cliente']);

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
        $this->servicio->registrarPago($factura, $monto, $request->file('comprobantes', []), [
            'fecha' => $data['fecha'] ?? null,
            'forma_pago' => $data['forma_pago'] ?? null,
            'referencia' => $data['referencia'] ?? null,
        ], $request->user()->id);

        $factura->load('pagos');

        return back()->with('success', $factura->saldoUsd() < 0.01
            ? 'Pago registrado: la factura quedó pagada.'
            : 'Pago registrado. Queda por cobrar $'.number_format($factura->saldoUsd(), 2).'.');
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
