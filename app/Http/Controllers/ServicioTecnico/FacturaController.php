<?php

namespace App\Http\Controllers\ServicioTecnico;

use App\Http\Controllers\Controller;
use App\Models\StFactura;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacturaController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = StFactura::query()->visiblePara($user)->orderByDesc('fecha')->orderByDesc('numero');

        if (! $user->scopesServicioToOwnSede() && $request->filled('sede')) {
            $query->where('sede', strtoupper((string) $request->query('sede')));
        }
        if ($request->filled('estado_pago')) {
            $query->where('estado_pago', $request->query('estado_pago'));
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->query('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->query('hasta'));
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($inner) use ($q, $like) {
                $inner->where('cliente_nombre', $like, '%'.$q.'%')
                    ->orWhere('descripcion', $like, '%'.$q.'%');
                if (ctype_digit($q)) {
                    $inner->orWhere('numero', (int) $q);
                }
            });
        }

        return view('servicio.facturas.index', array_merge($this->formData(), [
            'facturas' => $query->paginate(30)->withQueryString(),
            'filtroSede' => $user->scopesServicioToOwnSede() ? strtoupper((string) $user->sede) : $request->query('sede'),
            'puedeFiltrarSede' => ! $user->scopesServicioToOwnSede(),
        ]));
    }

    public function create(): View
    {
        return view('servicio.facturas.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request, $user);
        $factura = StFactura::crearEnSede($data, $user);

        return redirect()
            ->route('servicio.facturas.show', $factura)
            ->with('status', 'Factura '.$factura->codigo().' registrada.');
    }

    public function show(Request $request, StFactura $factura): View
    {
        $this->authorizeRecord($request->user(), $factura);

        return view('servicio.facturas.show', array_merge($this->formData(), [
            'factura' => $factura->load(['tecnico', 'creador']),
            'puedeEliminar' => ! $request->user()->isTecnico(),
        ]));
    }

    public function edit(Request $request, StFactura $factura): View
    {
        $this->authorizeRecord($request->user(), $factura);

        return view('servicio.facturas.edit', array_merge($this->formData(), [
            'factura' => $factura,
        ]));
    }

    public function update(Request $request, StFactura $factura): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRecord($user, $factura);
        $data = $this->validated($request, $user, $factura);
        unset($data['sede'], $data['numero']);
        $data['updated_by'] = $user->id;
        $data['total'] = (float) ($data['costo_mano_obra'] ?? $factura->costo_mano_obra ?? 0)
            + (float) ($data['costo_refacciones'] ?? $factura->costo_refacciones ?? 0);
        if (isset($data['total']) && $request->filled('total') && (float) $request->input('total') > 0) {
            $data['total'] = (float) $request->input('total');
        }
        $factura->update($data);

        return redirect()
            ->route('servicio.facturas.show', $factura)
            ->with('status', 'Factura actualizada.');
    }

    public function destroy(Request $request, StFactura $factura): RedirectResponse
    {
        if ($request->user()->isTecnico()) {
            abort(403, 'Los técnicos no pueden eliminar facturas.');
        }
        $this->authorizeRecord($request->user(), $factura);
        $codigo = $factura->codigo();
        $factura->delete();

        return redirect()
            ->route('servicio.facturas.index')
            ->with('status', 'Factura '.$codigo.' eliminada.');
    }

    private function formData(): array
    {
        return [
            'estadosPago' => config('servicio_tecnico.estados_pago_factura'),
            'sedes' => config('inventario.sedes_locales'),
        ];
    }

    private function validated(Request $request, User $user, ?StFactura $factura = null): array
    {
        if ($factura) {
            $sedeRule = ['nullable', 'string'];
        } elseif ($user->scopesServicioToOwnSede()) {
            $sedeRule = ['nullable', 'string'];
        } else {
            $sedeRule = ['required', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))];
        }

        $data = $request->validate([
            'sede' => $sedeRule,
            'cliente_nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'presupuesto' => ['nullable', 'numeric', 'min:0'],
            'costo_mano_obra' => ['nullable', 'numeric', 'min:0'],
            'costo_refacciones' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'estado_pago' => ['required', 'string', 'in:'.implode(',', array_keys(config('servicio_tecnico.estados_pago_factura')))],
            'fecha' => ['nullable', 'date'],
        ]);

        if ($user->scopesServicioToOwnSede()) {
            $data['sede'] = strtoupper((string) $user->sede);
        } elseif (isset($data['sede'])) {
            $data['sede'] = strtoupper((string) $data['sede']);
        }

        $mo = (float) ($data['costo_mano_obra'] ?? 0);
        $rf = (float) ($data['costo_refacciones'] ?? 0);
        $data['total'] = $data['total'] ?? ($mo + $rf);

        if ($data['total'] <= 0) {
            abort(422, 'El total de la factura debe ser mayor a cero.');
        }

        return $data;
    }

    private function authorizeRecord(User $user, StFactura $factura): void
    {
        if ($user->scopesServicioToOwnSede() && strtoupper((string) $factura->sede) !== strtoupper((string) $user->sede)) {
            abort(403, 'Esta factura pertenece a otra sede.');
        }
    }
}
