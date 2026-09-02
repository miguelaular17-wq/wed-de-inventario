<?php

namespace App\Http\Controllers\ServicioTecnico;

use App\Http\Controllers\Controller;
use App\Models\StReparacion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReparacionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = StReparacion::query()->visiblePara($user)->orderByDesc('created_at');

        if (! $user->scopesServicioToOwnSede() && $request->filled('sede')) {
            $query->where('sede', strtoupper((string) $request->query('sede')));
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->query('tipo'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->query('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->query('hasta'));
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($inner) use ($q, $like) {
                $inner->where('cliente_nombre', $like, '%'.$q.'%')
                    ->orWhere('producto', $like, '%'.$q.'%')
                    ->orWhere('comprobante_venta', $like, '%'.$q.'%');
            });
        }

        return view('servicio.reparaciones.index', array_merge($this->formData(), [
            'reparaciones' => $query->paginate(30)->withQueryString(),
            'sedes' => config('inventario.sedes_locales'),
            'filtroSede' => $user->scopesServicioToOwnSede() ? strtoupper((string) $user->sede) : $request->query('sede'),
            'puedeFiltrarSede' => ! $user->scopesServicioToOwnSede(),
        ]));
    }

    public function create(): View
    {
        return view('servicio.reparaciones.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request, $user);
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;
        $data['tecnico_id'] = $user->isTecnico() ? $user->id : null;

        $reparacion = StReparacion::create($data);

        return redirect()
            ->route('servicio.reparaciones.show', $reparacion)
            ->with('status', 'Registro guardado.');
    }

    public function show(Request $request, StReparacion $reparacion): View
    {
        $this->authorizeRecord($request->user(), $reparacion);

        return view('servicio.reparaciones.show', array_merge($this->formData(), [
            'reparacion' => $reparacion->load(['tecnico', 'creador']),
            'puedeEliminar' => ! $request->user()->isTecnico(),
        ]));
    }

    public function edit(Request $request, StReparacion $reparacion): View
    {
        $this->authorizeRecord($request->user(), $reparacion);

        return view('servicio.reparaciones.edit', array_merge($this->formData(), [
            'reparacion' => $reparacion,
        ]));
    }

    public function update(Request $request, StReparacion $reparacion): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRecord($user, $reparacion);
        $data = $this->validated($request, $user, $reparacion);
        $data['updated_by'] = $user->id;
        $reparacion->update($data);

        return redirect()
            ->route('servicio.reparaciones.show', $reparacion)
            ->with('status', 'Registro actualizado.');
    }

    public function destroy(Request $request, StReparacion $reparacion): RedirectResponse
    {
        if ($request->user()->isTecnico()) {
            abort(403, 'Los técnicos no pueden eliminar registros.');
        }
        $this->authorizeRecord($request->user(), $reparacion);
        $reparacion->delete();

        return redirect()
            ->route('servicio.reparaciones.index')
            ->with('status', 'Registro eliminado.');
    }

    private function formData(): array
    {
        return [
            'tipos' => config('servicio_tecnico.tipos_reparacion'),
            'categorias' => config('servicio_tecnico.categorias_reparacion'),
            'acciones' => config('servicio_tecnico.acciones_reparacion'),
            'estados' => config('servicio_tecnico.estados_reparacion'),
            'sedes' => config('inventario.sedes_locales'),
        ];
    }

    private function validated(Request $request, User $user, ?StReparacion $reparacion = null): array
    {
        $sedeRule = $user->scopesServicioToOwnSede()
            ? ['nullable', 'string']
            : ['required', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))];

        $data = $request->validate([
            'sede' => $sedeRule,
            'tipo' => ['required', 'string', 'in:'.implode(',', array_keys(config('servicio_tecnico.tipos_reparacion')))],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'cliente_telefono' => ['nullable', 'string', 'max:40'],
            'producto' => ['required', 'string', 'max:255'],
            'categoria' => ['required', 'string', 'in:'.implode(',', array_keys(config('servicio_tecnico.categorias_reparacion')))],
            'comprobante_venta' => ['nullable', 'string', 'max:64'],
            'falla' => ['nullable', 'string'],
            'accion' => ['required', 'string', 'in:'.implode(',', array_keys(config('servicio_tecnico.acciones_reparacion')))],
            'repuestos_texto' => ['nullable', 'string', 'max:255'],
            'costo_interno' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'string', 'in:'.implode(',', array_keys(config('servicio_tecnico.estados_reparacion')))],
            'observaciones' => ['nullable', 'string'],
        ]);

        if ($user->scopesServicioToOwnSede()) {
            $data['sede'] = strtoupper((string) $user->sede);
        } else {
            $data['sede'] = strtoupper((string) $data['sede']);
        }

        return $data;
    }

    private function authorizeRecord(User $user, StReparacion $reparacion): void
    {
        if ($user->scopesServicioToOwnSede() && strtoupper((string) $reparacion->sede) !== strtoupper((string) $user->sede)) {
            abort(403, 'Este registro pertenece a otra sede.');
        }
    }
}
