<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaEmpleado;
use App\Services\Nomina\FaltanteCajaService;
use App\Services\Nomina\SalaryAdvanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaltanteCajaController extends Controller
{
    public function __construct(
        private FaltanteCajaService $faltantes,
        private SalaryAdvanceService $quincenas,
    ) {
    }

    public function index(Request $request): View
    {
        $fecha = $this->fechaConsulta($request);
        $q = trim((string) $request->query('q', ''));
        $cajeras = $this->faltantes->cajeras($q !== '' ? $q : null);
        $pendientesPorEmpleado = $cajeras->mapWithKeys(
            fn (NominaEmpleado $empleado) => [$empleado->id => $this->faltantes->pendienteDe($empleado)]
        );
        $delDia = $this->faltantes->delDia($fecha);

        return view('nomina.faltante_caja.index', [
            'fecha' => $fecha->toDateString(),
            'q' => $q,
            'cajeras' => $cajeras,
            'pendientesPorEmpleado' => $pendientesPorEmpleado,
            'delDia' => $delDia,
            'quincena' => $this->quincenas->quincenaDe($fecha),
            'kpis' => $this->faltantes->kpis($fecha),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empleado_id' => ['required', 'integer', 'exists:nomina_empleados,id'],
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string'],
        ]);

        $empleado = NominaEmpleado::query()->findOrFail($data['empleado_id']);
        $this->faltantes->create($empleado, $data, auth()->id());

        return redirect()
            ->route('nomina.faltante_caja.index', [
                'fecha' => $data['fecha'],
                'q' => $data['q'] ?? '',
            ])
            ->with('status', 'Faltante de caja registrado a '.$empleado->nombre().'. Se descuenta de la comisión al liquidar la quincena.');
    }

    public function cancelar(Request $request, NominaComisionDescuento $descuento): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $this->faltantes->cancelar($descuento, $data['motivo'] ?? null);

        return redirect()
            ->route('nomina.faltante_caja.index', [
                'fecha' => $descuento->fecha?->toDateString() ?: now()->toDateString(),
                'q' => $request->input('q'),
            ])
            ->with('status', 'Faltante de caja cancelado.');
    }

    private function fechaConsulta(Request $request): Carbon
    {
        $raw = $request->query('fecha');
        try {
            return $raw ? Carbon::parse($raw)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}
