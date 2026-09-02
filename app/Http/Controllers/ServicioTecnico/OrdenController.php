<?php

namespace App\Http\Controllers\ServicioTecnico;

use App\Http\Controllers\Controller;
use App\Models\StOrden;
use App\Models\StRepuesto;
use App\Models\User;
use App\Services\ServicioTecnico\StOrdenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrdenController extends Controller
{
    public function __construct(
        private readonly StOrdenService $ordenService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = StOrden::query()->visiblePara($user)->orderByDesc('fecha_ingreso')->orderByDesc('numero');

        if (! $user->scopesServicioToOwnSede() && $request->filled('sede')) {
            $query->where('sede', strtoupper((string) $request->query('sede')));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($inner) use ($q, $like) {
                $inner->where('cliente_nombre', $like, '%'.$q.'%')
                    ->orWhere('cliente_telefono', $like, '%'.$q.'%')
                    ->orWhere('equipo', $like, '%'.$q.'%')
                    ->orWhere('serial', $like, '%'.$q.'%');

                if (ctype_digit($q)) {
                    $inner->orWhere('numero', (int) $q);
                }
            });
        }

        return view('servicio.ordenes.index', [
            'ordenes' => $query->paginate(30)->withQueryString(),
            'estados' => StOrden::ESTADOS,
            'sedes' => config('inventario.sedes_locales'),
            'filtroSede' => $user->scopesServicioToOwnSede() ? strtoupper((string) $user->sede) : $request->query('sede'),
            'puedeFiltrarSede' => ! $user->scopesServicioToOwnSede(),
        ]);
    }

    public function create(): View
    {
        return view('servicio.ordenes.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request, $user);

        $orden = StOrden::crearEnSede($data, $user);

        return redirect()
            ->route('servicio.ordenes.show', $orden)
            ->with('status', 'Orden '.$orden->codigo().' registrada.');
    }

    public function show(Request $request, StOrden $orden): View
    {
        $this->authorizeOrden($request->user(), $orden);

        return view('servicio.ordenes.show', [
            'orden' => $orden->load(['creador', 'editor', 'tecnico', 'repuestosLineas.repuesto', 'eventos.usuario']),
            'estados' => StOrden::ESTADOS,
        ]);
    }

    public function edit(Request $request, StOrden $orden): View
    {
        $user = $request->user();
        $this->authorizeOrden($user, $orden);

        $repuestosDisponibles = StRepuesto::query()
            ->visiblePara($user)
            ->where('sede', strtoupper((string) $orden->sede))
            ->activos()
            ->orderBy('nombre')
            ->get();

        return view('servicio.ordenes.edit', array_merge($this->formData(), [
            'orden' => $orden->load('repuestosLineas.repuesto'),
            'repuestosDisponibles' => $repuestosDisponibles,
            'puedeTransferir' => ! $user->scopesServicioToOwnSede(),
        ]));
    }

    public function update(Request $request, StOrden $orden): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeOrden($user, $orden);
        $data = $this->validated($request, $user, $orden);

        $nuevoEstado = $data['estado'] ?? $orden->estado;
        $proyeccion = $orden->replicate();
        $proyeccion->fill($data);
        if ($nuevoEstado === StOrden::ESTADO_ENTREGADO && $proyeccion->excedePresupuesto() && ! $request->boolean('confirmar_exceso')) {
            throw ValidationException::withMessages([
                'estado' => 'Los costos superan el presupuesto. Marca la casilla de confirmación para entregar.',
            ]);
        }

        unset($data['sede']);
        if ($request->filled('sede_destino') && ! $user->scopesServicioToOwnSede()) {
            $data['sede'] = strtoupper((string) $request->input('sede_destino'));
        }

        $lineas = $this->parseRepuestosInput($request);

        $this->ordenService->actualizarOrden($orden, $data, $user, $lineas);

        return redirect()
            ->route('servicio.ordenes.show', $orden)
            ->with('status', 'Orden '.$orden->fresh()->codigo().' actualizada.');
    }

    public function confirmarRecepcion(Request $request, StOrden $orden): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeOrden($user, $orden);

        if (! $orden->puedeConfirmarRecepcion($user)) {
            abort(403, 'No puedes confirmar la recepción de esta orden.');
        }

        $this->ordenService->confirmarRecepcion($orden, $user);

        return redirect()
            ->route('servicio.ordenes.show', $orden)
            ->with('status', 'Recepción de '.$orden->codigo().' confirmada.');
    }

    public function destroy(Request $request, StOrden $orden): RedirectResponse
    {
        $this->authorizeOrden($request->user(), $orden);

        if ($orden->repuestos_descontados_at) {
            return back()->withErrors(['error' => 'No se puede eliminar una orden con repuestos ya descontados.']);
        }

        $codigo = $orden->codigo();
        $orden->delete();

        return redirect()
            ->route('servicio.ordenes.index')
            ->with('status', 'Orden '.$codigo.' eliminada.');
    }

    private function formData(): array
    {
        return [
            'estados' => StOrden::ESTADOS,
            'prioridades' => StOrden::PRIORIDADES,
            'sedes' => config('inventario.sedes_locales'),
        ];
    }

    /**
     * @return list<array{repuesto_id:int,cantidad:int}>
     */
    private function parseRepuestosInput(Request $request): array
    {
        $lineas = [];
        $raw = $request->input('repuestos', []);

        if (! is_array($raw)) {
            return [];
        }

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = (int) ($item['repuesto_id'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 0);
            if ($id > 0 && $cantidad > 0) {
                $lineas[] = ['repuesto_id' => $id, 'cantidad' => $cantidad];
            }
        }

        return $lineas;
    }

    private function validated(Request $request, User $user, ?StOrden $orden = null): array
    {
        if ($orden) {
            $sedeRule = ['nullable', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))];
        } elseif ($user->scopesServicioToOwnSede()) {
            $sedeRule = ['nullable', 'string'];
        } else {
            $sedeRule = ['required', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))];
        }

        $data = $request->validate([
            'sede' => $sedeRule,
            'cliente_nombre' => ['required', 'string', 'max:255'],
            'cliente_telefono' => ['nullable', 'string', 'max:40'],
            'cliente_cedula' => ['nullable', 'string', 'max:40'],
            'equipo' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:255'],
            'falla' => ['nullable', 'string'],
            'accesorios' => ['nullable', 'string', 'max:255'],
            'diagnostico' => ['nullable', 'string'],
            'estado' => ['nullable', 'string', 'in:'.implode(',', array_keys(StOrden::ESTADOS))],
            'prioridad' => ['required', 'string', 'in:'.implode(',', array_keys(StOrden::PRIORIDADES))],
            'fecha_prometida' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string'],
            'presupuesto' => ['nullable', 'numeric', 'min:0'],
            'costo_mano_obra' => ['nullable', 'numeric', 'min:0'],
            'costo_refacciones' => ['nullable', 'numeric', 'min:0'],
            'sede_destino' => ['nullable', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))],
        ]);

        if ($user->scopesServicioToOwnSede()) {
            $data['sede'] = strtoupper((string) $user->sede);
        }

        if (! $orden) {
            $data['fecha_ingreso'] = now()->toDateString();
            $data['estado'] = $data['estado'] ?? StOrden::ESTADO_PENDIENTE;
        } else {
            $data['estado'] = $data['estado'] ?? $orden->estado;
        }

        return $data;
    }

    private function authorizeOrden(User $user, StOrden $orden): void
    {
        if (! $user->scopesServicioToOwnSede()) {
            return;
        }

        $sede = strtoupper((string) $user->sede);
        $enSede = strtoupper((string) $orden->sede) === $sede;
        $origenTransfer = strtoupper((string) ($orden->sede_origen_transfer ?? '')) === $sede;

        if (! $enSede && ! $origenTransfer) {
            abort(403, 'Esta orden pertenece a otra sede.');
        }
    }
}
