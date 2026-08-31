<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaHoraExtra;
use App\Models\Nomina\NominaInasistencia;
use App\Models\Nomina\NominaSede;
use App\Services\Nomina\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

    public function indexHorasExtras(Request $request): View
    {
        $fecha = $this->fechaConsulta($request);
        $sedeId = $request->filled('sede_id') ? (int) $request->query('sede_id') : null;
        $alcance = (string) $request->query('alcance', 'TODOS');
        if (! in_array($alcance, ['TODOS', 'TRABAJADORES', 'SUPERVISORES'], true)) {
            $alcance = 'TODOS';
        }

        $candidatos = $sedeId
            ? $this->attendance->empleadosDeSede($sedeId, $alcance)->map(function ($empleado) {
                $empleado->setAttribute('hora_supervisor', $this->attendance->usaTarifaHoraSupervisor($empleado));

                return $empleado;
            })
            : collect();

        return view('nomina.horas_extras.index', [
            'fecha' => $fecha->toDateString(),
            'sedeId' => $sedeId,
            'alcance' => $alcance,
            'candidatos' => $candidatos,
            'sedes' => NominaSede::query()->where('estado', 'ACTIVO')->ordenCatalogo()->get(),
            'valorHoraTrabajador' => $this->attendance->valorHoraTrabajador(),
            'valorHoraSupervisor' => $this->attendance->valorHoraSupervisor(),
            'delDia' => $this->attendance->extrasDelDia($fecha, $sedeId),
        ]);
    }

    public function storeHorasExtrasMasivas(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sede_id' => ['required', 'integer', 'exists:nomina_sedes,id'],
            'alcance' => ['required', 'in:TODOS,TRABAJADORES,SUPERVISORES'],
            'fecha' => ['required', 'date'],
            'unidad' => ['nullable', 'in:HORAS,DIAS'],
            'horas' => ['required', 'numeric', 'min:0.25'],
            'motivo' => ['nullable', 'string'],
            'empleado_ids' => ['nullable', 'array'],
            'empleado_ids.*' => ['integer', 'exists:nomina_empleados,id'],
        ]);

        $data['unidad'] = $data['unidad'] ?? 'HORAS';
        $creados = $this->attendance->registrarHorasExtrasMasivas(
            $data['empleado_ids'] ?? [],
            $data,
            auth()->id()
        );

        $esDias = $data['unidad'] === 'DIAS';

        return redirect()
            ->route('nomina.horas_extras.index', [
                'sede_id' => $data['sede_id'],
                'alcance' => $data['alcance'],
                'fecha' => $data['fecha'],
            ])
            ->with('status', $esDias
                ? 'Días extras aplicados a '.$creados->count().' persona(s) de la sede.'
                : 'Horas extras aplicadas a '.$creados->count().' persona(s) de la sede.');
    }

    public function storeHorasExtras(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'unidad' => ['nullable', 'in:HORAS,DIAS'],
            'horas' => ['required', 'numeric', 'min:0.25'],
            'motivo' => ['nullable', 'string'],
        ]);

        $data['unidad'] = $data['unidad'] ?? 'HORAS';
        $this->attendance->registrarHorasExtras($empleado, $data, auth()->id());

        $esDias = $data['unidad'] === 'DIAS';

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'nomina'])
            ->with('status', $esDias
                ? 'Días extras registrados. El valor es salario ÷ 30 y entran al sueldo de esa quincena.'
                : 'Horas extras registradas. Entran al sueldo de esa quincena.');
    }

    public function cancelarHorasExtras(Request $request, NominaHoraExtra $horaExtra): RedirectResponse
    {
        $this->attendance->cancelarHorasExtras($horaExtra);

        if ($request->input('origen') === 'horas_extras') {
            return redirect()
                ->route('nomina.horas_extras.index', [
                    'fecha' => $horaExtra->fecha?->toDateString(),
                    'sede_id' => $request->input('sede_id'),
                    'alcance' => $request->input('alcance', 'TODOS'),
                ])
                ->with('status', 'Horas extras canceladas. El historial se conserva.');
        }

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $horaExtra->empleado_id, 'tab' => 'nomina'])
            ->with('status', 'Horas extras canceladas. El historial se conserva.');
    }

    private function fechaConsulta(Request $request): Carbon
    {
        $valor = $request->query('fecha', $request->input('fecha'));

        return $valor ? Carbon::parse($valor)->startOfDay() : now()->startOfDay();
    }
}
