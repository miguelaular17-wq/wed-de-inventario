<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaEmpleado;
use App\Services\BcvRateService;
use App\Services\Nomina\SalaryAdvanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbonoSueldoController extends Controller
{
    public function __construct(
        private SalaryAdvanceService $advances,
        private BcvRateService $bcv,
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

        $delDia = $this->advances->delDia($fecha);
        $quincena = $this->advances->quincenaDe($fecha);

        return view('nomina.adelantos.index', [
            'fecha' => $fecha->toDateString(),
            'q' => $q,
            'resultados' => $resultados,
            'delDia' => $delDia,
            'totalDia' => round((float) $delDia->sum('monto'), 2),
            'quincena' => $quincena,
            'kpis' => $this->advances->kpis($fecha),
            'tasaBcv' => $this->bcv->getRateForToday(),
        ]);
    }

    public function storeEscritorio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empleado_id' => ['required', 'integer', 'exists:nomina_empleados,id'],
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string'],
            'q' => ['nullable', 'string'],
        ]);

        $empleado = NominaEmpleado::query()->findOrFail($data['empleado_id']);
        $this->advances->create($empleado, $data, auth()->id());

        return redirect()
            ->route('nomina.adelantos.index', [
                'fecha' => $data['fecha'],
                'q' => $data['q'] ?? '',
            ])
            ->with('status', 'Adelanto registrado a '.$empleado->nombre().'. Se descontará del sueldo en esa quincena.');
    }

    public function exportarTxt(Request $request): StreamedResponse|RedirectResponse
    {
        $fecha = $this->fechaConsulta($request);
        $tasa = $this->bcv->getRateForToday();
        $contenido = $this->advances->generarTxtDelDia($fecha, $tasa);
        $nombre = $this->advances->nombreArchivoDelDia($fecha);

        return response()->streamDownload(function () use ($contenido) {
            echo $contenido;
        }, $nombre, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function store(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string'],
        ]);

        $this->advances->create($empleado, $data, auth()->id());

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'abonos'])
            ->with('status', 'Adelanto registrado. Se descontará del sueldo en esa quincena.');
    }

    public function cancelar(Request $request, NominaAbonoSueldo $abono): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['nullable', 'string'],
        ]);

        $this->advances->cancelar($abono, $data['motivo'] ?? null);

        if ($request->input('origen') === 'adelantos') {
            return redirect()
                ->route('nomina.adelantos.index', [
                    'fecha' => $abono->fecha?->toDateString(),
                    'q' => $request->input('q'),
                ])
                ->with('status', 'Adelanto cancelado. El historial se conserva.');
        }

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $abono->empleado_id, 'tab' => 'abonos'])
            ->with('status', 'Adelanto cancelado. El historial se conserva.');
    }

    private function fechaConsulta(Request $request): Carbon
    {
        $valor = $request->query('fecha', $request->input('fecha'));

        return $valor ? Carbon::parse($valor)->startOfDay() : now()->startOfDay();
    }
}
