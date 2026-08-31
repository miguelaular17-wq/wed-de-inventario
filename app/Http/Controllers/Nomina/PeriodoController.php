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

        if ($request->query('formato') === 'zip') {
            return $this->descargarZipPorSedeYArea($periodo, $filas, $nombreBase, $tasaBcv);
        }

        if ($request->query('formato') === 'xlsx') {
            $xlsx = SimpleXlsxWriter::toString([
                'Nómina' => $this->hojaRelacion($filas, $totales),
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
            'logoPath' => $this->logoNominaPdf(),
            'grupoTitulo' => null,
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
            'adelantos' => 0.0, 'bonificaciones' => 0.0, 'ajustes_deduccion' => 0.0, 'prestamos' => 0.0, 'deducciones' => 0.0,
            'pagar_usd' => 0.0, 'pagar_bs' => 0.0,
        ];

        $registros = $periodo->registros->sortBy(fn ($r) => mb_strtoupper($r->empleado?->nombre() ?? '', 'UTF-8'));
        foreach ($registros as $registro) {
            $desglose = $registro->desglose();
            $sede = $registro->empleado?->sedeCatalogo;
            $sedeNombre = $sede?->nombre ?? $registro->empleado?->sede ?? 'Sin sede';
            $sedeTipo = $sede?->tipo === 'AREA' ? 'AREA' : 'SEDE';
            $fila = [
                'cedula' => $registro->empleado?->cedula() ?? '',
                'nombre' => $registro->empleado?->nombre() ?? 'Sin nombre',
                'sede' => $sedeNombre,
                'grupo_tipo' => $sedeTipo,
                'grupo_clave' => $sedeTipo.'|'.mb_strtoupper((string) ($sede?->codigo ?? $sedeNombre), 'UTF-8'),
                'salario' => round((float) $registro->salario_base, 2),
                'horas_extras' => round((float) ($desglose['horas_extras'] ?? 0), 2),
                'inasistencias' => round((float) ($desglose['inasistencias'] ?? 0), 2),
                'adelantos' => round((float) ($desglose['abonos_sueldo'] ?? 0), 2),
                'bonificaciones' => $registro->montoBonificaciones(),
                'ajustes_deduccion' => $registro->montoDeduccionesAjuste(),
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
        }

        return [$filas, $totales];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, float>  $totales
     * @return list<list<string|int|float|null>>
     */
    private function hojaRelacion(array $filas, array $totales): array
    {
        $cuerpo = array_map(fn ($f) => [
            $f['cedula'], $f['nombre'], $f['sede'],
            $f['salario'], $f['horas_extras'], $f['inasistencias'], $f['adelantos'], $f['bonificaciones'],
            $f['ajustes_deduccion'], $f['prestamos'],
            $f['deducciones'], $f['pagar_usd'], $f['pagar_bs'],
        ], $filas);

        return array_merge(
            [[
                'Cédula', 'Empleado', 'Sede',
                'Salario USD', 'Horas extra', 'Ausencias', 'Adelantos', 'Bonificaciones', 'Deducciones', 'Préstamos',
                'Total deducciones', 'Total Pagar USD', 'Total a Pagar BCV',
            ]],
            $cuerpo,
            [[
                'TOTALES', count($filas).' trabajadores', '',
                $totales['salario'], $totales['horas_extras'], $totales['inasistencias'],
                $totales['adelantos'], $totales['bonificaciones'], $totales['ajustes_deduccion'], $totales['prestamos'], $totales['deducciones'],
                $totales['pagar_usd'], $totales['pagar_bs'],
            ]]
        );
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function descargarZipPorSedeYArea(NominaPeriodo $periodo, array $filas, string $nombreBase, float $tasaBcv)
    {
        ini_set('memory_limit', '512M');

        try {
            $archivos = [];
            $logoPath = $this->logoNominaPdf();
            foreach (collect($filas)->groupBy('grupo_clave') as $grupoFilas) {
                $lista = $grupoFilas->values()->all();
                if ($lista === []) {
                    continue;
                }
                $totales = $this->totalesDeFilas($lista);
                $tipo = ($lista[0]['grupo_tipo'] ?? 'SEDE') === 'AREA' ? 'Area' : 'Sede';
                $sedeNombre = (string) ($lista[0]['sede'] ?? 'sin_sede');
                $pdf = Pdf::loadView('nomina.periodos.pdf-relacion', [
                    'periodo' => $periodo,
                    'filas' => $lista,
                    'totales' => $totales,
                    'tasaBcv' => $tasaBcv,
                    'logoPath' => $logoPath,
                    'grupoTitulo' => ($tipo === 'Area' ? 'Area' : 'Sede').': '.$sedeNombre,
                ])->setPaper('a4', 'landscape');
                $archivos[$tipo.'_'.$this->slugArchivo($sedeNombre).'.pdf'] = $pdf->output();
                unset($pdf);
            }

            if ($archivos === []) {
                return back()->withErrors(['periodo' => 'No hay recibos para armar el ZIP.']);
            }

            $binario = SimpleXlsxWriter::zipFiles($archivos);
            if ($binario === '') {
                return back()->withErrors(['periodo' => 'No se pudo armar el ZIP.']);
            }

            return response($binario, 200, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="'.$nombreBase.'_por_sede_y_area.zip"',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['periodo' => 'No se pudo armar el ZIP.']);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float>
     */
    private function totalesDeFilas(array $filas): array
    {
        $totales = [
            'salario' => 0.0, 'horas_extras' => 0.0, 'inasistencias' => 0.0,
            'adelantos' => 0.0, 'bonificaciones' => 0.0, 'ajustes_deduccion' => 0.0, 'prestamos' => 0.0, 'deducciones' => 0.0,
            'pagar_usd' => 0.0, 'pagar_bs' => 0.0,
        ];
        foreach ($filas as $fila) {
            foreach ($totales as $k => $v) {
                $totales[$k] = round($v + (float) ($fila[$k] ?? 0), 2);
            }
        }

        return $totales;
    }

    private function logoNominaPdf(): ?string
    {
        $path = public_path('logo.png');
        if (! is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($raw);
    }

    private function slugArchivo(string $texto): string
    {
        $texto = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $texto = (string) preg_replace('/[^A-Za-z0-9]+/', '_', $texto);

        return trim($texto, '_') ?: 'sin_nombre';
    }

    private function volver(NominaPeriodo $periodo, string $status): RedirectResponse
    {
        return redirect()
            ->route('nomina.periodos.show', $periodo)
            ->with('status', $status);
    }
}
