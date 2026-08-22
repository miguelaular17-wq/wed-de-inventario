<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaEmpleado;
use App\Services\Nomina\SalaryAdvanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AbonoSueldoController extends Controller
{
    public function __construct(private SalaryAdvanceService $advances)
    {
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
            ->with('status', 'Abono registrado. Se descontará del sueldo en esa quincena.');
    }

    public function cancelar(Request $request, NominaAbonoSueldo $abono): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['nullable', 'string'],
        ]);

        $this->advances->cancelar($abono, $data['motivo'] ?? null);

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $abono->empleado_id, 'tab' => 'abonos'])
            ->with('status', 'Abono cancelado. El historial se conserva.');
    }
}
