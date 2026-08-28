<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaPeriodo;
use App\Services\Nomina\PayrollPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PeriodoController extends Controller
{
    public function __construct(private PayrollPeriodService $periods)
    {
    }

    public function index(): View
    {
        $periodos = NominaPeriodo::query()
            ->withCount('registros')
            ->withSum('registros', 'total_pagar')
            ->orderByDesc('fecha_inicio')
            ->get();

        return view('nomina.periodos.index', [
            'periodos' => $periodos,
            'estados' => NominaPeriodo::estados(),
            'fechaSugerida' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
        ]);

        $periodo = $this->periods->abrir($data['fecha'], auth()->id());

        return redirect()
            ->route('nomina.periodos.show', $periodo)
            ->with('status', 'Quincena abierta correctamente.');
    }

    public function show(NominaPeriodo $periodo): View
    {
        $periodo->load([
            'registros.empleado.cliente',
            'liquidacionesComision.empleado.cliente',
            'calculadoPor',
            'aprobadoPor',
            'pagadoPor',
            'cerradoPor',
        ]);

        $historial = NominaAuditLog::query()
            ->where('entidad', 'periodo')
            ->where('entidad_id', $periodo->id)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return view('nomina.periodos.show', [
            'periodo' => $periodo,
            'historial' => $historial,
        ]);
    }

    public function calcularForm(NominaPeriodo $periodo): View|RedirectResponse
    {
        if ($periodo->estado !== NominaPeriodo::ABIERTO) {
            return redirect()
                ->route('nomina.periodos.show', $periodo)
                ->withErrors(['periodo' => 'Esta quincena ya no está abierta para calcular.']);
        }

        return view('nomina.periodos.calcular', [
            'periodo' => $periodo,
            'candidatos' => $this->periods->empleadosConCuotasPendientes($periodo),
        ]);
    }

    public function calcular(Request $request, NominaPeriodo $periodo): RedirectResponse
    {
        $data = $request->validate([
            'descontar_empleado_ids' => ['sometimes', 'array'],
            'descontar_empleado_ids.*' => ['integer'],
        ]);

        $this->periods->calcular(
            $periodo,
            auth()->id(),
            $data['descontar_empleado_ids'] ?? []
        );

        return $this->volver($periodo, 'Nómina calculada. Los importes quedaron congelados para revisión.');
    }

    public function revertir(NominaPeriodo $periodo): RedirectResponse
    {
        $this->periods->revertirCalculo($periodo, auth()->id());

        return $this->volver($periodo, 'Se deshizo el cálculo. La quincena volvió a ABIERTA: adelantos, faltas, horas extras y cuotas de préstamo quedaron como estaban.');
    }

    public function aprobar(NominaPeriodo $periodo): RedirectResponse
    {
        $this->periods->aprobar($periodo, auth()->id());

        return $this->volver($periodo, 'Nómina aprobada.');
    }

    public function pagar(NominaPeriodo $periodo): RedirectResponse
    {
        $this->periods->pagar($periodo, auth()->id());

        return $this->volver($periodo, 'Nómina marcada como pagada.');
    }

    public function cerrar(NominaPeriodo $periodo): RedirectResponse
    {
        $this->periods->cerrar($periodo, auth()->id());

        return $this->volver($periodo, 'Quincena cerrada. El período quedó en modo de solo lectura.');
    }

    private function volver(NominaPeriodo $periodo, string $status): RedirectResponse
    {
        return redirect()
            ->route('nomina.periodos.show', $periodo)
            ->with('status', $status);
    }
}
