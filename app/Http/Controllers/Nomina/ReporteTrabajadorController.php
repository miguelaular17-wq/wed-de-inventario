<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Services\Nomina\ReporteTrabajadorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReporteTrabajadorController extends Controller
{
    public function __construct(private ReporteTrabajadorService $reportes)
    {
    }

    public function index(Request $request): View
    {
        $periodos = NominaPeriodo::query()
            ->withCount(['liquidacionesComision as liquidaciones_comision_count' => fn ($q) => $q->visibles()])
            ->orderByDesc('fecha_inicio')
            ->get()
            ->filter(fn (NominaPeriodo $p) => $p->liquidaciones_comision_count > 0)
            ->values();

        $periodo = $periodos->firstWhere('id', (int) $request->query('periodo')) ?? $periodos->first();

        $trabajadores = $periodo
            ? NominaLiquidacionComision::query()
                ->where('periodo_id', $periodo->id)
                ->visibles()
                ->with(['empleado.cliente', 'empleado.sedeCatalogo'])
                ->get()
                ->map(fn (NominaLiquidacionComision $liq) => [
                    'id' => $liq->empleado_id,
                    'nombre' => $liq->empleado?->nombre() ?? 'Sin nombre',
                    'cedula' => $liq->empleado?->cedula() ?? '',
                    'sede' => $liq->empleado?->nombreSede() ?? '—',
                ])
                ->sortBy(fn (array $t) => mb_strtoupper($t['nombre'], 'UTF-8'))
                ->values()
            : collect();

        return view('nomina.reportes.index', [
            'periodos' => $periodos,
            'periodo' => $periodo,
            'trabajadores' => $trabajadores,
            'empleadoId' => (int) $request->query('empleado'),
        ]);
    }

    public function pdf(Request $request)
    {
        $data = $request->validate([
            'periodo' => ['required', 'integer', 'exists:nomina_periodos,id'],
            'empleado' => ['required', 'integer', 'exists:nomina_empleados,id'],
        ], [
            'empleado.required' => 'Selecciona un trabajador.',
            'periodo.required' => 'Selecciona una quincena.',
        ]);

        $periodo = NominaPeriodo::query()->findOrFail($data['periodo']);
        $empleado = NominaEmpleado::query()->findOrFail($data['empleado']);
        $liq = $this->reportes->liquidacion($periodo, $empleado);

        if (! $liq) {
            return redirect()
                ->route('nomina.reportes.index', ['periodo' => $periodo->id, 'empleado' => $empleado->id])
                ->withErrors(['empleado' => 'Ese trabajador no tiene comisión calculada en esta quincena.']);
        }

        $reporte = $this->reportes->datos($liq);

        $pdf = Pdf::loadView('nomina.reportes.pdf-trabajador', [
            'periodo' => $periodo,
            'r' => $reporte,
            'logoPath' => $this->logoPdf(),
        ])->setPaper('a4');

        $nombre = 'reporte_'.$this->slug($reporte['empleado']['nombre']).'_'.$periodo->fecha_inicio?->format('Ymd').'.pdf';

        return $pdf->stream($nombre);
    }

    private function logoPdf(): ?string
    {
        $path = public_path('logo.png');
        $raw = is_file($path) ? @file_get_contents($path) : false;

        return $raw ? 'data:image/png;base64,'.base64_encode($raw) : null;
    }

    private function slug(string $texto): string
    {
        $texto = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;

        return trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $texto), '_') ?: 'trabajador';
    }
}
