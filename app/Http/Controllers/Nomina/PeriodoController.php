<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaPeriodo;
use App\Services\BcvRateService;
use App\Services\Nomina\LoanDiscountPlanService;
use App\Services\Nomina\PayrollBankFileService;
use App\Services\Nomina\PayrollPeriodService;
use App\Support\SimpleXlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PeriodoController extends Controller
{
    public function __construct(
        private PayrollPeriodService $periods,
        private PayrollBankFileService $bankFile,
        private BcvRateService $bcv,
        private LoanDiscountPlanService $loanPlans,
    ) {
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
            'registros.empleado.empresa',
            'registros.empleado.sedeCatalogo',
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
            'bancoPorEmpresa' => $this->bankFile->resumenPorEmpresa($periodo),
            'tasaBcv' => $this->bcv->getRateForToday(),
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
            'planes' => $this->loanPlans->planesDeQuincena([
                'inicio' => $periodo->fecha_inicio,
                'fin' => $periodo->fecha_fin,
                'etiqueta' => $periodo->etiqueta,
            ]),
        ]);
    }

    public function calcular(Request $request, NominaPeriodo $periodo): RedirectResponse
    {
        $data = $request->validate([
            'descontar_empleado_ids' => ['sometimes', 'array'],
            'descontar_empleado_ids.*' => ['integer'],
            'descuentos' => ['sometimes', 'array'],
            'descuentos.*.aplicar' => ['sometimes'],
            'descuentos.*.cuota_id' => ['required_with:descuentos.*.aplicar', 'integer'],
            'descuentos.*.monto' => ['nullable', 'numeric', 'min:0'],
            'descuentos.*.destino' => ['nullable', 'in:NOMINA,COMISION'],
        ]);

        $descuentos = [];
        foreach ($data['descuentos'] ?? [] as $fila) {
            if (empty($fila['aplicar'])) {
                continue;
            }
            $descuentos[] = [
                'cuota_id' => (int) $fila['cuota_id'],
                'monto' => array_key_exists('monto', $fila) && $fila['monto'] !== null && $fila['monto'] !== ''
                    ? (float) $fila['monto']
                    : null,
                'destino' => $fila['destino'] ?? null,
            ];
        }

        $this->periods->calcular(
            $periodo,
            auth()->id(),
            $data['descontar_empleado_ids'] ?? [],
            $descuentos,
            $request->has('descuentos') || $request->has('descontar_empleado_ids')
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

    public function exportarBanco(NominaPeriodo $periodo, NominaEmpresa $empresa): StreamedResponse|RedirectResponse
    {
        if ($periodo->estado === NominaPeriodo::ABIERTO) {
            return $this->volver($periodo, 'Calcula la nómina antes de generar el archivo del banco.');
        }

        $periodo->load(['registros.empleado.cliente', 'registros.empleado.empresa']);
        $tasa = $this->bcv->getRateForToday();
        $contenido = $this->bankFile->generar($periodo, $empresa, $tasa);
        $nombre = $this->bankFile->nombreArchivo($periodo, $empresa);

        return response()->streamDownload(function () use ($contenido) {
            echo $contenido;
        }, $nombre, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function relacion(Request $request, NominaPeriodo $periodo)
    {
        if ($periodo->estado === NominaPeriodo::ABIERTO) {
            return redirect()
                ->route('nomina.periodos.show', $periodo)
                ->withErrors(['periodo' => 'Calcula la nómina antes de descargar la relación.']);
        }

        $periodo->load([
            'registros.empleado.cliente',
            'registros.empleado.empresa',
            'registros.empleado.sedeCatalogo',
        ]);
        $tasaBcv = $this->bcv->getRateForToday();
        [$filas, $totales] = $this->filasRelacionNomina($periodo, $tasaBcv);
        $nombreBase = 'relacion_nomina_'.$periodo->id.'_'.$periodo->fecha_inicio?->format('Ymd');

        if ($request->query('formato') === 'xlsx') {
            $xlsx = SimpleXlsxWriter::toString([
                'Nómina' => array_merge(
                    [[
                        'Cédula', 'Empleado', 'Empresa', 'Sede',
                        'Salario USD', 'Horas extra', 'IAS', 'Adelantos', 'Mercancía', 'Préstamos',
                        'Deducciones', 'A pagar USD', 'A pagar Bs',
                    ]],
                    array_map(fn ($f) => [
                        $f['cedula'], $f['nombre'], $f['empresa'], $f['sede'],
                        $f['salario'], $f['horas_extras'], $f['inasistencias'], $f['adelantos'], $f['mercancia'], $f['prestamos'],
                        $f['deducciones'], $f['pagar_usd'], $f['pagar_bs'],
                    ], $filas),
                    [[
                        'TOTALES', count($filas).' trabajadores', '', '',
                        $totales['salario'], $totales['horas_extras'], $totales['inasistencias'],
                        $totales['adelantos'], $totales['mercancia'], $totales['prestamos'], $totales['deducciones'],
                        $totales['pagar_usd'], $totales['pagar_bs'],
                    ]]
                ),
            ]);

            return response($xlsx, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$nombreBase.'.xlsx"',
            ]);
        }

        $pdf = Pdf::loadView('nomina.periodos.pdf-relacion', [
            'periodo' => $periodo,
            'filas' => $filas,
            'totales' => $totales,
            'tasaBcv' => $tasaBcv,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($nombreBase.'.pdf');
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array<string, float>}
     */
    private function filasRelacionNomina(NominaPeriodo $periodo, float $tasaBcv): array
    {
        $filas = [];
        $totales = [
            'salario' => 0.0, 'horas_extras' => 0.0, 'inasistencias' => 0.0,
            'adelantos' => 0.0, 'mercancia' => 0.0, 'prestamos' => 0.0, 'deducciones' => 0.0,
            'pagar_usd' => 0.0, 'pagar_bs' => 0.0,
        ];

        $registros = $periodo->registros->sortBy(fn ($r) => mb_strtoupper($r->empleado?->nombre() ?? '', 'UTF-8'));
        foreach ($registros as $registro) {
            $desglose = json_decode($registro->observaciones ?: '{}', true) ?: [];
            $fila = [
                'cedula' => $registro->empleado?->cedula() ?? '',
                'nombre' => $registro->empleado?->nombre() ?? 'Sin nombre',
                'empresa' => $registro->empleado?->nombreEmpresa() ?? '—',
                'sede' => $registro->empleado?->nombreSede() ?? '—',
                'salario' => round((float) $registro->salario_base, 2),
                'horas_extras' => round((float) ($desglose['horas_extras'] ?? 0), 2),
                'inasistencias' => round((float) ($desglose['inasistencias'] ?? 0), 2),
                'adelantos' => round((float) ($desglose['abonos_sueldo'] ?? 0), 2),
                'mercancia' => round((float) ($desglose['mercancia'] ?? 0), 2),
                'prestamos' => round((float) ($desglose['prestamos'] ?? 0), 2),
                'deducciones' => round((float) $registro->total_deducciones, 2),
                'pagar_usd' => round((float) $registro->total_pagar, 2),
                'pagar_bs' => round((float) $registro->total_pagar * $tasaBcv, 2),
            ];
            $filas[] = $fila;
            foreach ($totales as $k => $v) {
                if (isset($fila[$k])) {
                    $totales[$k] = round($v + (float) $fila[$k], 2);
                }
            }
            $totales['pagar_usd'] = round($totales['pagar_usd'], 2);
            $totales['pagar_bs'] = round($totales['pagar_bs'], 2);
        }

        return [$filas, $totales];
    }

    private function volver(NominaPeriodo $periodo, string $status): RedirectResponse
    {
        return redirect()
            ->route('nomina.periodos.show', $periodo)
            ->with('status', $status);
    }
}
