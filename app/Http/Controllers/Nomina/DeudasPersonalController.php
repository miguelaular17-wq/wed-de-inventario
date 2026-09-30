<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpleado;
use App\Services\Nomina\DeudasPersonalService;
use App\Services\Nomina\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeudasPersonalController extends Controller
{
    public function __construct(
        private DeudasPersonalService $deudas,
        private OrganizationService $organization,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->filtrosDesdeRequest($request);
        $ids = $this->idsVisibles();
        if ($ids !== null) {
            $filtros['empleado_ids'] = $ids;
        }
        $resultado = $this->deudas->listar($filtros);

        $empleadosQuery = NominaEmpleado::query()
            ->activos()
            ->with('cliente')
            ->orderBy('id');
        if ($ids !== null) {
            $empleadosQuery->whereIn('id', $ids !== [] ? $ids : [0]);
        }
        $empleadosOpciones = $empleadosQuery
            ->get()
            ->map(fn (NominaEmpleado $e) => [
                'id' => $e->id,
                'label' => trim($e->nombre().' · '.$e->cedula()),
            ]);

        return view('nomina.deudas.index', [
            'filas' => $resultado['filas'],
            'resumen' => $resultado['resumen'],
            'filtros' => $filtros,
            'tipos' => DeudasPersonalService::TIPOS,
            'estados' => DeudasPersonalService::ESTADOS,
            'sedes' => $this->deudas->sedesOpciones(),
            'empleadosOpciones' => $empleadosOpciones,
        ]);
    }

    public function show(Request $request, NominaEmpleado $empleado): View
    {
        $ids = $this->idsVisibles();
        if ($ids !== null && ! in_array((int) $empleado->id, $ids, true)) {
            abort(403);
        }

        $filtros = $this->filtrosDesdeRequest($request);
        $ficha = $this->deudas->fichaEmpleado($empleado, $filtros);

        return view('nomina.deudas.show', [
            'ficha' => $ficha,
            'empleado' => $empleado,
            'filtros' => $filtros,
            'tipos' => DeudasPersonalService::TIPOS,
            'estados' => DeudasPersonalService::ESTADOS,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        return [
            'sede' => $request->query('sede'),
            'empleado_id' => $request->query('empleado_id') ? (int) $request->query('empleado_id') : null,
            'q' => trim((string) $request->query('q', '')),
            'tipo' => $request->query('tipo'),
            'estado' => $request->query('estado'),
            'fecha_desde' => $request->query('fecha_desde'),
            'fecha_hasta' => $request->query('fecha_hasta'),
            'monto_min' => $request->query('monto_min'),
            'monto_max' => $request->query('monto_max'),
        ];
    }

    /**
     * null = ve a todo el personal (nómina / RRHH).
     * Un supervisor solo ve a su equipo y a los supervisores de su sede, incluido él.
     *
     * @return list<int>|null
     */
    private function idsVisibles(): ?array
    {
        $user = auth()->user();
        if (! $user || $user->canAccess('nomina')) {
            return null;
        }

        return $this->organization->idsPersonalACargo($user, true);
    }
}
