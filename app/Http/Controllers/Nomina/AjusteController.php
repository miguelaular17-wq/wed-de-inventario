<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpleadoAjuste;
use App\Models\Nomina\NominaEmpleado;
use App\Services\Nomina\AjusteService;
use App\Services\Nomina\SalaryAdvanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AjusteController extends Controller
{
    public function __construct(
        private AjusteService $ajustes,
        private SalaryAdvanceService $quincenas,
    ) {
    }

    public function index(Request $request): View
    {
        $fecha = $this->fechaConsulta($request);
        $q = trim((string) $request->query('q', ''));
        $resultados = collect();

        if ($q !== '') {
            $resultados = NominaEmpleado::query()
                ->activos()
                ->buscar($q)
                ->with(['cliente', 'sedeCatalogo', 'empresa', 'cargoCatalogo'])
                ->join('clientes', 'clientes.id', '=', 'nomina_empleados.cliente_id')
                ->select('nomina_empleados.*')
                ->orderBy('clientes.nombre')
                ->limit(20)
                ->get();
        }

        $delDia = $this->ajustes->delDia($fecha);

        return view('nomina.ajustes.index', [
            'fecha' => $fecha->toDateString(),
            'q' => $q,
            'resultados' => $resultados,
            'delDia' => $delDia,
            'totalDia' => round((float) $delDia->sum('monto'), 2),
            'quincena' => $this->quincenas->quincenaDe($fecha),
            'kpis' => $this->ajustes->kpis($fecha),
        ]);
    }

    public function storeEscritorio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empleado_id' => ['required', 'integer', 'exists:nomina_empleados,id'],
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'in:DEDUCCION,BONIFICACION'],
            'destino' => ['required', 'in:NOMINA,COMISION'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['required', 'string', 'max:500'],
            'q' => ['nullable', 'string'],
        ]);

        $empleado = NominaEmpleado::query()->findOrFail($data['empleado_id']);
        $this->ajustes->create($empleado, $data, auth()->id());

        $esBono = $data['tipo'] === NominaEmpleadoAjuste::TIPO_BONIFICACION;

        return redirect()
            ->route('nomina.ajustes.index', [
                'fecha' => $data['fecha'],
                'q' => $data['q'] ?? '',
            ])
            ->with('status', ($esBono ? 'Bonificación' : 'Deducción').' registrada a '.$empleado->nombre().'.');
    }

    public function store(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'in:DEDUCCION,BONIFICACION'],
            'destino' => ['required', 'in:NOMINA,COMISION'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $this->ajustes->create($empleado, $data, auth()->id());

        $esBono = $data['tipo'] === NominaEmpleadoAjuste::TIPO_BONIFICACION;
        $destino = $data['destino'] === NominaEmpleadoAjuste::DESTINO_COMISION ? 'comisión' : 'nómina';

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'ajustes'])
            ->with('status', ($esBono ? 'Bonificación' : 'Deducción')." registrada. Se aplica en {$destino} al calcular esa quincena.");
    }

    public function cancelar(Request $request, NominaEmpleadoAjuste $ajuste): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['nullable', 'string'],
        ]);

        $this->ajustes->cancelar($ajuste, $data['motivo'] ?? null);

        if ($request->input('origen') === 'ajustes') {
            return redirect()
                ->route('nomina.ajustes.index', [
                    'fecha' => $ajuste->fecha?->toDateString(),
                    'q' => $request->input('q'),
                ])
                ->with('status', 'Ajuste cancelado. El historial se conserva.');
        }

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $ajuste->empleado_id, 'tab' => 'ajustes'])
            ->with('status', 'Ajuste cancelado. El historial se conserva.');
    }

    private function fechaConsulta(Request $request): Carbon
    {
        $valor = $request->query('fecha', $request->input('fecha'));

        return $valor ? Carbon::parse($valor)->startOfDay() : now()->startOfDay();
    }
}
