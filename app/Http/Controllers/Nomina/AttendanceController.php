<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaHoraExtra;
use App\Models\Nomina\NominaInasistencia;
use App\Services\Nomina\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance)
    {
    }

    public function storeInasistencia(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'cantidad' => ['required', 'numeric', 'min:0.5'],
            'motivo' => ['nullable', 'string'],
        ]);

        $this->attendance->registrarInasistencia($empleado, $data, auth()->id());

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'nomina'])
            ->with('status', 'Inasistencia registrada. Se descuenta del sueldo en esa quincena.');
    }

    public function marcarFaltoHoy(NominaEmpleado $empleado): RedirectResponse
    {
        $this->attendance->marcarFaltoHoy($empleado, auth()->id());

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'nomina'])
            ->with('status', 'Quedó marcado que faltó hoy. Se descuenta 1 día en esta quincena.');
    }

    public function cancelarInasistencia(NominaInasistencia $inasistencia): RedirectResponse
    {
        $this->attendance->cancelarInasistencia($inasistencia);

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $inasistencia->empleado_id, 'tab' => 'nomina'])
            ->with('status', 'Inasistencia cancelada. El historial se conserva.');
    }

    public function storeHorasExtras(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'horas' => ['required', 'numeric', 'min:0.25'],
            'motivo' => ['nullable', 'string'],
        ]);

        $this->attendance->registrarHorasExtras($empleado, $data, auth()->id());

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'nomina'])
            ->with('status', 'Horas extras registradas. Entran al sueldo de esa quincena.');
    }

    public function cancelarHorasExtras(NominaHoraExtra $horaExtra): RedirectResponse
    {
        $this->attendance->cancelarHorasExtras($horaExtra);

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $horaExtra->empleado_id, 'tab' => 'nomina'])
            ->with('status', 'Horas extras canceladas. El historial se conserva.');
    }
}
