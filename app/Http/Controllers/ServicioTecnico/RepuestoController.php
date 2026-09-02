<?php

namespace App\Http\Controllers\ServicioTecnico;

use App\Http\Controllers\Controller;
use App\Models\StMovimientoRepuesto;
use App\Models\StRepuesto;
use App\Services\ServicioTecnico\StRepuestoImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RepuestoController extends Controller
{
    public function __construct(
        private readonly StRepuestoImportService $importService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = StRepuesto::query()->visiblePara($user)->orderBy('sede')->orderBy('nombre');

        if (! $user->scopesServicioToOwnSede() && $request->filled('sede')) {
            $query->where('sede', strtoupper((string) $request->query('sede')));
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($inner) use ($q, $like) {
                $inner->where('nombre', $like, '%'.$q.'%')
                    ->orWhere('codigo', $like, '%'.$q.'%')
                    ->orWhere('categoria', $like, '%'.$q.'%');
            });
        }

        if ($request->boolean('bajo_stock')) {
            $query->whereColumn('stock', '<=', 'stock_min')->where('stock_min', '>', 0);
        }

        return view('servicio.repuestos.index', [
            'repuestos' => $query->paginate(40)->withQueryString(),
            'sedes' => config('inventario.sedes_locales'),
            'filtroSede' => $user->scopesServicioToOwnSede() ? strtoupper((string) $user->sede) : $request->query('sede'),
            'puedeFiltrarSede' => ! $user->scopesServicioToOwnSede(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('servicio.repuestos.create', [
            'sedes' => config('inventario.sedes_locales'),
            'sedeDefault' => $request->user()->scopesServicioToOwnSede()
                ? strtoupper((string) $request->user()->sede)
                : session('sede_local'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request, $user);

        $repuesto = StRepuesto::create($data);

        if ($repuesto->stock > 0) {
            StMovimientoRepuesto::create([
                'repuesto_id' => $repuesto->id,
                'tipo' => StMovimientoRepuesto::TIPO_ENTRADA,
                'cantidad' => $repuesto->stock,
                'stock_antes' => 0,
                'stock_despues' => $repuesto->stock,
                'motivo' => 'Stock inicial',
                'user_id' => $user->id,
                'created_at' => now(),
            ]);
        }

        return redirect()
            ->route('servicio.repuestos.index')
            ->with('status', 'Repuesto «'.$repuesto->nombre.'» registrado.');
    }

    public function edit(Request $request, StRepuesto $repuesto): View
    {
        $this->authorizeRepuesto($request->user(), $repuesto);

        return view('servicio.repuestos.edit', [
            'repuesto' => $repuesto,
            'sedes' => config('inventario.sedes_locales'),
            'movimientos' => $repuesto->movimientos()->with('usuario')->orderByDesc('created_at')->limit(20)->get(),
        ]);
    }

    public function update(Request $request, StRepuesto $repuesto): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRepuesto($user, $repuesto);
        $data = $this->validated($request, $user, $repuesto);

        $repuesto->update($data);

        return redirect()
            ->route('servicio.repuestos.edit', $repuesto)
            ->with('status', 'Repuesto actualizado.');
    }

    public function ajustarStock(Request $request, StRepuesto $repuesto): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRepuesto($user, $repuesto);

        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'not_in:0'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($repuesto, $data, $user) {
            $locked = StRepuesto::query()->lockForUpdate()->findOrFail($repuesto->id);
            $antes = $locked->stock;
            $despues = $antes + (int) $data['cantidad'];

            if ($despues < 0) {
                abort(422, 'El stock no puede quedar negativo.');
            }

            $locked->stock = $despues;
            $locked->save();

            StMovimientoRepuesto::create([
                'repuesto_id' => $locked->id,
                'tipo' => StMovimientoRepuesto::TIPO_AJUSTE,
                'cantidad' => (int) $data['cantidad'],
                'stock_antes' => $antes,
                'stock_despues' => $despues,
                'motivo' => $data['motivo'],
                'user_id' => $user->id,
                'created_at' => now(),
            ]);
        });

        return back()->with('status', 'Stock ajustado.');
    }

    public function importForm(Request $request): View
    {
        return view('servicio.repuestos.import', [
            'sedes' => config('inventario.sedes_locales'),
            'sedeDefault' => $request->user()->scopesServicioToOwnSede()
                ? strtoupper((string) $request->user()->sede)
                : session('sede_local'),
        ]);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'sede' => ['nullable', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))],
        ]);

        $result = $this->importService->importarCsv(
            $data['archivo'],
            $user,
            $data['sede'] ?? null,
        );

        return redirect()
            ->route('servicio.repuestos.index')
            ->with('status', "Importados {$result['importados']} repuesto(s). Omitidos: {$result['omitidos']}.");
    }

    public function plantillaCsv(): StreamedResponse
    {
        $csv = "nombre;codigo;categoria;stock;stock_minimo;costo;venta\nPantalla OLED;REP-001;telefonia;5;1;40;65\n";

        return response()->streamDownload(function () use ($csv) {
            echo "\xEF\xBB\xBF".$csv;
        }, 'plantilla_repuestos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validated(Request $request, $user, ?StRepuesto $repuesto = null): array
    {
        $sedeRule = $user->scopesServicioToOwnSede()
            ? ['nullable', 'string']
            : ['required', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))];

        $data = $request->validate([
            'sede' => $sedeRule,
            'codigo' => ['nullable', 'string', 'max:64'],
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('servicio_tecnico.categorias_reparacion', [])))],
            'stock' => ['nullable', 'integer', 'min:0'],
            'stock_min' => ['nullable', 'integer', 'min:0'],
            'costo' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);

        if ($user->scopesServicioToOwnSede()) {
            $data['sede'] = strtoupper((string) $user->sede);
        } else {
            $data['sede'] = strtoupper((string) $data['sede']);
        }

        if (! $repuesto) {
            $data['stock'] = (int) ($data['stock'] ?? 0);
        } else {
            unset($data['stock']);
        }

        $data['stock_min'] = (int) ($data['stock_min'] ?? 0);
        $data['activo'] = $request->boolean('activo', true);

        return $data;
    }

    private function authorizeRepuesto($user, StRepuesto $repuesto): void
    {
        if ($user->scopesServicioToOwnSede() && strtoupper((string) $repuesto->sede) !== strtoupper((string) $user->sede)) {
            abort(403, 'Este repuesto pertenece a otra sede.');
        }
    }
}
