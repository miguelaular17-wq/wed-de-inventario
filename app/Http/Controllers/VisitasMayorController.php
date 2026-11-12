<?php

namespace App\Http\Controllers;

use App\Models\Mayor\MayorCliente;
use App\Models\Mayor\MayorVisita;
use App\Services\Mayor\VentasMayorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Hoja de ruta: visitas para captar clientes al mayor. Cada quien ve las suyas; admin y gerente ven todas. */
class VisitasMayorController extends Controller
{
    public function __construct(private VentasMayorService $servicio) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $desde = $request->query('desde') ?: now()->startOfMonth()->toDateString();
        $hasta = $request->query('hasta') ?: now()->toDateString();
        $resultado = array_key_exists((string) $request->query('resultado'), MayorVisita::RESULTADOS) ? $request->query('resultado') : null;
        $usuarioId = $user->isAdmin() ? (int) $request->query('usuario') : (int) $user->id;

        $base = MayorVisita::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($usuarioId, fn ($q) => $q->where('user_id', $usuarioId));
        $porResultado = (clone $base)->selectRaw('resultado, COUNT(*) as n')->groupBy('resultado')->pluck('n', 'resultado');

        return view('ventas_mayor.visitas.index', [
            'visitas' => (clone $base)->when($resultado, fn ($q) => $q->where('resultado', $resultado))
                ->with(['usuario', 'cliente'])->orderByDesc('fecha')->orderByDesc('id')->paginate(30)->withQueryString(),
            'porResultado' => $porResultado,
            'proximas' => MayorVisita::query()->when($usuarioId, fn ($q) => $q->where('user_id', $usuarioId))
                ->whereNotNull('proxima_visita')->whereBetween('proxima_visita', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->orderBy('proxima_visita')->limit(10)->get(),
            'usuarios' => $user->isAdmin()
                ? \App\Models\User::query()->whereIn('id', MayorVisita::query()->distinct()->pluck('user_id'))->orderBy('name')->get(['id', 'name'])
                : collect(),
            'filtro' => ['desde' => $desde, 'hasta' => $hasta, 'resultado' => $resultado, 'usuario' => $usuarioId],
        ]);
    }

    public function create(): View
    {
        return view('ventas_mayor.visitas.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'cliente_id' => ['nullable', 'integer', 'exists:mayor_clientes,id'],
            'negocio_nombre' => ['required', 'string', 'max:200'],
            'negocio_documento' => ['nullable', 'string', 'max:40'],
            'negocio_telefono' => ['nullable', 'string', 'max:60'],
            'negocio_direccion' => ['nullable', 'string', 'max:300'],
            'zona' => ['nullable', 'string', 'max:120'],
            'atendio_nombre' => ['required', 'string', 'max:200'],
            'atendio_cargo' => ['nullable', 'string', 'max:120'],
            'atendio_documento' => ['nullable', 'string', 'max:40'],
            'atendio_telefono' => ['nullable', 'string', 'max:60'],
            'resultado' => ['required', Rule::in(array_keys(MayorVisita::RESULTADOS))],
            'proxima_visita' => ['nullable', 'date', 'after_or_equal:fecha'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'firma' => ['required', 'string'],
            'evidencias' => ['required', 'array', 'min:1', 'max:6'],
            'evidencias.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:10240'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'firma.required' => 'Falta la firma de la persona que atendió.',
            'evidencias.required' => 'Sube al menos una foto de evidencia de la visita.',
            'evidencias.min' => 'Sube al menos una foto de evidencia de la visita.',
        ]);

        // La firma llega como imagen PNG dibujada en pantalla (data URL).
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $data['firma'], $m)
            || ($png = base64_decode($m[1], true)) === false || strlen($png) < 400) {
            throw ValidationException::withMessages(['firma' => 'Falta la firma de la persona que atendió.']);
        }

        $user = $request->user();
        $firmaUrl = $this->servicio->subirContenido($png, 'image/png', 'png', 'visita_firma');
        $evidencias = [];
        foreach ($request->file('evidencias', []) as $archivo) {
            $evidencias[] = $this->servicio->subirContenido(
                (string) file_get_contents($archivo->getRealPath()),
                (string) $archivo->getClientMimeType(),
                strtolower($archivo->getClientOriginalExtension() ?: 'jpg'),
                'visita_evidencia',
            );
        }

        $documento = MayorCliente::normalizarDocumento($data['negocio_documento'] ?? '');
        $clienteId = $data['cliente_id'] ?? ($documento !== '' ? MayorCliente::query()->where('documento', $documento)->value('id') : null);

        $visita = MayorVisita::create([
            'fecha' => $data['fecha'],
            'user_id' => $user->id,
            'cliente_id' => $clienteId,
            'negocio_nombre' => $data['negocio_nombre'],
            'negocio_documento' => $documento ?: null,
            'negocio_telefono' => $data['negocio_telefono'] ?? null,
            'negocio_direccion' => $data['negocio_direccion'] ?? null,
            'zona' => $data['zona'] ?? null,
            'atendio_nombre' => $data['atendio_nombre'],
            'atendio_cargo' => $data['atendio_cargo'] ?? null,
            'atendio_documento' => $data['atendio_documento'] ?? null,
            'atendio_telefono' => $data['atendio_telefono'] ?? null,
            'resultado' => $data['resultado'],
            'proxima_visita' => $data['proxima_visita'] ?? null,
            'observaciones' => $data['observaciones'] ?? null,
            'firma_url' => $firmaUrl,
            'evidencias' => $evidencias,
            'latitud' => $data['latitud'] ?? null,
            'longitud' => $data['longitud'] ?? null,
        ]);

        return redirect()->route('ventas_mayor.visitas.show', $visita)->with('success', 'Visita registrada en la hoja de ruta.');
    }

    public function show(Request $request, MayorVisita $visita): View
    {
        $this->autorizar($request, $visita);
        $visita->load(['usuario', 'cliente']);

        return view('ventas_mayor.visitas.show', [
            'visita' => $visita,
            'anteriores' => MayorVisita::query()->where('id', '!=', $visita->id)
                ->where(fn ($q) => $q->when($visita->cliente_id, fn ($w) => $w->where('cliente_id', $visita->cliente_id))
                    ->orWhere('negocio_nombre', $visita->negocio_nombre))
                ->orderByDesc('fecha')->limit(10)->get(),
        ]);
    }

    /** El negocio visitado pasa a ser cliente al mayor (para facturarle). */
    public function registrarCliente(Request $request, MayorVisita $visita): RedirectResponse
    {
        $this->autorizar($request, $visita);
        if (! $visita->cliente_id) {
            $cliente = $this->servicio->resolverCliente([
                'documento' => $visita->negocio_documento ?: $request->input('documento'),
                'nombre' => $visita->negocio_nombre,
                'telefono' => $visita->negocio_telefono ?: $visita->atendio_telefono,
                'direccion' => $visita->negocio_direccion,
            ], $request->user()->id);
            $visita->update(['cliente_id' => $cliente->id, 'negocio_documento' => $cliente->documento]);
        }

        return redirect()->route('ventas_mayor.create', ['cliente_id' => $visita->cliente_id])
            ->with('success', 'Cliente registrado. Ya le puedes hacer la factura.');
    }

    private function autorizar(Request $request, MayorVisita $visita): void
    {
        abort_unless($request->user()->isAdmin() || (int) $visita->user_id === (int) $request->user()->id, 403);
    }
}
