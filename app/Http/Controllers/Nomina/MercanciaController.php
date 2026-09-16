<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaDescuentoMercancia;
use App\Models\Nomina\NominaEmpleado;
use App\Services\Nomina\MerchandiseDeductionService;
use App\Services\Nomina\QuincenaMovimientosExcelService;
use App\Services\Nomina\SalaryAdvanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MercanciaController extends Controller
{
    public function __construct(
        private MerchandiseDeductionService $mercancia,
        private SalaryAdvanceService $quincenas,
        private QuincenaMovimientosExcelService $excelQuincena,
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

        $delDia = $this->mercancia->delDia($fecha);

        return view('nomina.mercancia.index', [
            'fecha' => $fecha->toDateString(),
            'q' => $q,
            'resultados' => $resultados,
            'delDia' => $delDia,
            'quincena' => $this->quincenas->quincenaDe($fecha),
            'kpis' => $this->mercancia->kpis($fecha),
        ]);
    }

    public function exportarExcel(Request $request): Response
    {
        $quincena = $this->excelQuincena->resolverDesdeRequest(
            $request->query('inicio'),
            $request->query('fecha', $this->fechaConsulta($request)->toDateString())
        );

        return $this->excelQuincena->descargar('mercancia', $quincena);
    }

    public function storeEscritorio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empleado_id' => ['required', 'integer', 'exists:nomina_empleados,id'],
            'fecha' => ['required', 'date'],
            'destino' => ['required', 'in:NOMINA,COMISION'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['required', 'string', 'max:500'],
            'q' => ['nullable', 'string'],
        ]);

        $empleado = NominaEmpleado::query()->findOrFail($data['empleado_id']);
        $this->mercancia->create($empleado, $data, auth()->id());

        $destino = $data['destino'] === NominaDescuentoMercancia::DESTINO_COMISION
            ? 'comisión'
            : 'nómina';

        return redirect()
            ->route('nomina.mercancia.index', [
                'fecha' => $data['fecha'],
                'q' => $data['q'] ?? '',
            ])
            ->with('status', 'Descuento de mercancía registrado a '.$empleado->nombre().'. Se aplica en '.$destino.' al calcular esa quincena.');
    }

    public function store(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'destino' => ['required', 'in:NOMINA,COMISION'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $this->mercancia->create($empleado, $data, auth()->id());

        $destino = $data['destino'] === NominaDescuentoMercancia::DESTINO_COMISION
            ? 'comisión'
            : 'nómina';

        return redirect()
            ->route('nomina.mercancia.index', [
                'fecha' => $data['fecha'],
                'q' => $empleado->nombre(),
            ])
            ->with('status', "Descuento de mercancía registrado. Se aplica en {$destino} al calcular esa quincena.");
    }

    public function cancelar(Request $request, NominaDescuentoMercancia $descuento): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $this->mercancia->cancelar($descuento, $data['motivo'] ?? null);

        return redirect()
            ->route('nomina.mercancia.index', [
                'fecha' => $descuento->fecha?->toDateString() ?: now()->toDateString(),
                'q' => $request->input('q'),
            ])
            ->with('status', 'Descuento de mercancía cancelado.');
    }

    private function fechaConsulta(Request $request): Carbon
    {
        $valor = $request->query('fecha', $request->input('fecha'));

        try {
            return $valor ? Carbon::parse($valor)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}
