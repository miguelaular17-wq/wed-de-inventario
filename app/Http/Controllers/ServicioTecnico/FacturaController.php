<?php

namespace App\Http\Controllers\ServicioTecnico;

use App\Http\Controllers\Controller;
use App\Models\StFactura;
use App\Models\User;
use App\Services\Nomina\SalaryAdvanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacturaController extends Controller
{
    public function __construct(private SalaryAdvanceService $quincenas)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $soloSus = $user->veSoloSusFacturasTaller();
        $quincena = $this->quincenas->quincenaDe(now());

        $desde = $request->query('desde');
        $hasta = $request->query('hasta');
        if ($soloSus && ! $request->filled('desde') && ! $request->filled('hasta')) {
            $desde = $quincena['inicio']->toDateString();
            $hasta = $quincena['fin']->toDateString();
        }

        $query = StFactura::query()->visiblePara($user)->orderByDesc('fecha')->orderByDesc('numero');

        if (! $user->scopesServicioToOwnSede() && $request->filled('sede')) {
            $query->where('sede', strtoupper((string) $request->query('sede')));
        }
        if ($request->filled('estado_pago')) {
            $query->where('estado_pago', $request->query('estado_pago'));
        }
        if ($desde) {
            $query->whereDate('fecha', '>=', $desde);
        }
        if ($hasta) {
            $query->whereDate('fecha', '<=', $hasta);
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

        return view('servicio.facturas.index', array_merge($this->formData($user), [
            'facturas' => $query->paginate(30)->withQueryString(),
            'filtroSede' => $user->scopesServicioToOwnSede() ? strtoupper((string) $user->sede) : $request->query('sede'),
            'puedeFiltrarSede' => ! $user->scopesServicioToOwnSede(),
            'soloSusFacturas' => $soloSus,
            'quincena' => $quincena,
            'filtroDesde' => $desde,
            'filtroHasta' => $hasta,
        ]));
    }

    public function create(): RedirectResponse
    {
        return redirect()
            ->route('servicio.facturas.index')
            ->withErrors(['factura' => 'Ya no se pueden crear facturas de taller desde aquí.']);
    }

    public function store(): RedirectResponse
    {
        abort(403, 'Ya no se pueden crear facturas de taller.');
    }

    public function show(Request $request, StFactura $factura): View
    {
        $user = $request->user();
        $this->authorizeRecord($user, $factura);
        $puedeEditar = $this->puedeGestionar($user);

        return view('servicio.facturas.show', array_merge($this->formData($user), [
            'factura' => $factura->load(['tecnico', 'creador']),
            'puedeEliminar' => $puedeEditar,
            'puedeEditar' => $puedeEditar,
        ]));
    }

    public function edit(Request $request, StFactura $factura): View|RedirectResponse
    {
        $user = $request->user();
        if (! $this->puedeGestionar($user)) {
            abort(403, 'No puedes editar facturas de taller.');
        }
        $this->authorizeRecord($user, $factura);

        return view('servicio.facturas.edit', array_merge($this->formData($user), [
            'factura' => $factura,
        ]));
    }

    public function update(Request $request, StFactura $factura): RedirectResponse
    {
        $user = $request->user();
        if (! $this->puedeGestionar($user)) {
            abort(403, 'No puedes editar facturas de taller.');
        }
        $this->authorizeRecord($user, $factura);
        $data = $this->validated($request, $user, $factura);
        unset($data['sede'], $data['numero']);
        $data['updated_by'] = $user->id;
        $data['total'] = (float) ($data['costo_mano_obra'] ?? $factura->costo_mano_obra ?? 0)
            + (float) ($data['costo_refacciones'] ?? $factura->costo_refacciones ?? 0);
        if ($request->filled('total') && (float) $request->input('total') > 0) {
            $data['total'] = (float) $request->input('total');
        }
        $factura->update($data);

        return redirect()
            ->route('servicio.facturas.show', $factura)
            ->with('status', 'Factura actualizada.');
    }

    public function destroy(Request $request, StFactura $factura): RedirectResponse
    {
        $user = $request->user();
        if (! $this->puedeGestionar($user)) {
            abort(403, 'No puedes eliminar facturas de taller.');
        }
        $this->authorizeRecord($user, $factura);
        $codigo = $factura->codigo();
        $factura->delete();

        return redirect()
            ->route('servicio.facturas.index')
            ->with('status', 'Factura '.$codigo.' eliminada.');
    }

    private function formData(User $user): array
    {
        return [
            'estadosPago' => config('servicio_tecnico.estados_pago_factura'),
            'sedes' => config('inventario.sedes_locales'),
            'puedeCrearFactura' => false,
            'puedeGestionarFacturas' => $this->puedeGestionar($user),
        ];
    }

    private function puedeGestionar(User $user): bool
    {
        return $user->isAdmin() || $user->isGerente();
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
        if ($user->veSoloSusFacturasTaller() && (int) $factura->tecnico_id !== (int) $user->id) {
            abort(403, 'Solo puedes ver tus propias facturas.');
        }
        if ($user->scopesServicioToOwnSede() && strtoupper((string) $factura->sede) !== strtoupper((string) $user->sede)) {
            abort(403, 'Esta factura pertenece a otra sede.');
        }
    }
}
