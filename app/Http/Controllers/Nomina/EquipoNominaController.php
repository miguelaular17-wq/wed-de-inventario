<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaPeriodo;
use App\Services\Nomina\OrganizationService;
use Illuminate\View\View;

class EquipoNominaController extends Controller
{
    public function __construct(private OrganizationService $organization)
    {
    }

    public function index(): View
    {
        $ids = $this->organization->idsPersonalACargo(auth()->user());
        $filtro = $ids === [] ? [0] : $ids;

        $periodos = NominaPeriodo::query()
            ->whereIn('estado', [
                NominaPeriodo::CALCULADO,
                NominaPeriodo::APROBADO,
                NominaPeriodo::PAGADO,
                NominaPeriodo::CERRADO,
            ])
            ->withCount(['registros as equipo_count' => fn ($q) => $q->whereIn('empleado_id', $filtro)])
            ->withSum(['registros as equipo_total' => fn ($q) => $q->whereIn('empleado_id', $filtro)], 'total_pagar')
            ->orderByDesc('fecha_inicio')
            ->get();

        $ficha = $this->organization->empleadoDelUsuario(auth()->user());

        return view('nomina.equipo.index', [
            'periodos' => $periodos,
            'ficha' => $ficha,
            'tieneFicha' => (bool) $ficha,
            'esSupervisor' => (bool) ($ficha?->es_supervisor || ($ficha && $this->organization->esGerente($ficha))),
            'equipoCount' => count($ids),
        ]);
    }

    public function show(NominaPeriodo $periodo): View
    {
        abort_unless(in_array($periodo->estado, [
            NominaPeriodo::CALCULADO,
            NominaPeriodo::APROBADO,
            NominaPeriodo::PAGADO,
            NominaPeriodo::CERRADO,
        ], true), 404);

        $ids = $this->organization->idsPersonalACargo(auth()->user());
        $filtro = $ids === [] ? [0] : $ids;

        $registros = $periodo->registros()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo'])
            ->whereIn('empleado_id', $filtro)
            ->get();

        return view('nomina.equipo.show', [
            'periodo' => $periodo,
            'registros' => $registros,
        ]);
    }
}
