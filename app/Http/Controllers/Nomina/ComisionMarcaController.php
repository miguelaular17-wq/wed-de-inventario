<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaComisionMarca;
use App\Models\Nomina\NominaEmpleado;
use App\Services\Nomina\ComisionMarcaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ComisionMarcaController extends Controller
{
    public function __construct(private ComisionMarcaService $service) {}

    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);
        $registros = $this->consulta($filtros)->get();

        return view('nomina.comisiones_marca.index', [
            'empleados' => $this->empleados(),
            'registros' => $registros,
            'totales' => $this->service->totales($registros),
            'filtros' => $filtros,
            'marcas' => NominaComisionMarca::MARCAS,
        ]);
    }

    public function reporte(Request $request): Response
    {
        $filtros = $this->filtros($request);
        $registros = $this->consulta($filtros)->get();

        $pdf = Pdf::loadView('nomina.comisiones_marca.reporte', [
            'registros' => $registros,
            'totales' => $this->service->totales($registros),
            'filtros' => $filtros,
            'marcas' => NominaComisionMarca::MARCAS,
            'logoPath' => $this->logoPdf(),
        ])->setPaper('a4', 'portrait');

        $nombre = 'comisiones-marca-'.$filtros['desde'].'-'.$filtros['hasta'].'.pdf';

        return $pdf->download($nombre);
    }

    private function logoPdf(): ?string
    {
        $path = public_path('logo.png');
        if (! is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);

        return ($raw === false || $raw === '') ? null : 'data:image/png;base64,'.base64_encode($raw);
    }

    public function tasa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
        ]);
        $tasa = $this->service->tasaDelDia($data['fecha']);

        return response()->json([
            'tasa' => $tasa,
            'fecha' => $data['fecha'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nomina_empleado_id' => ['required', 'integer', 'exists:nomina_empleados,id'],
            'marca' => ['required', 'in:SAMSUNG,HONOR'],
            'fecha' => ['required', 'date'],
            'monto_bs' => ['required', 'numeric', 'min:0.01'],
            'nota' => ['nullable', 'string', 'max:255'],
        ]);

        $tasa = $this->service->tasaDelDia($data['fecha']);
        $usd = $this->service->aUsd((float) $data['monto_bs'], $tasa);
        if ($tasa === null || $usd === null) {
            return back()
                ->withInput()
                ->withErrors([
                    'fecha' => 'Ese día no tiene tasa de egreso en flujo de caja. Registra la tasa y vuelve a guardar.',
                ]);
        }

        $empleado = NominaEmpleado::query()->findOrFail($data['nomina_empleado_id']);
        NominaComisionMarca::query()->create([
            'nomina_empleado_id' => $empleado->id,
            'marca' => $data['marca'],
            'fecha' => $data['fecha'],
            'monto_bs' => round((float) $data['monto_bs'], 2),
            'tasa' => $tasa,
            'monto_usd' => $usd,
            'nota' => $data['nota'] ?? null,
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('nomina.comisiones_marca.index', $request->only(['desde', 'hasta', 'marca_filtro', 'empleado_id']))
            ->with('status', 'Comisión de '.NominaComisionMarca::MARCAS[$data['marca']].' guardada para '.$empleado->nombre().': Bs '.number_format((float) $data['monto_bs'], 2, ',', '.').' = $'.number_format($usd, 2).'.');
    }

    public function destroy(Request $request, NominaComisionMarca $comision): RedirectResponse
    {
        $comision->delete();

        return redirect()
            ->route('nomina.comisiones_marca.index', $request->only(['desde', 'hasta', 'marca_filtro', 'empleado_id']))
            ->with('status', 'Comisión eliminada.');
    }

    /**
     * @return array{desde: string, hasta: string, marca: string, empleado_id: string}
     */
    private function filtros(Request $request): array
    {
        return [
            'desde' => (string) $request->query('desde', now()->startOfMonth()->toDateString()),
            'hasta' => (string) $request->query('hasta', now()->toDateString()),
            'marca' => (string) $request->query('marca_filtro', ''),
            'empleado_id' => (string) $request->query('empleado_id', ''),
        ];
    }

    private function consulta(array $filtros)
    {
        return NominaComisionMarca::query()
            ->with('empleado.cliente')
            ->whereDate('fecha', '>=', $filtros['desde'])
            ->whereDate('fecha', '<=', $filtros['hasta'])
            ->when($filtros['marca'] !== '', fn ($q) => $q->where('marca', $filtros['marca']))
            ->when($filtros['empleado_id'] !== '', fn ($q) => $q->where('nomina_empleado_id', (int) $filtros['empleado_id']))
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    private function empleados()
    {
        return NominaEmpleado::query()
            ->activos()
            ->with('cliente')
            ->get()
            ->sortBy(fn (NominaEmpleado $empleado) => $empleado->nombre())
            ->values();
    }
}
