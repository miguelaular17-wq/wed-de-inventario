<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaDeduccion;
use App\Models\Nomina\NominaDescuentoMercancia;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaEmpleadoAjuste;
use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaHoraExtra;
use App\Models\Nomina\NominaInasistencia;
use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaPeriodo;
use App\Services\BcvRateService;
use App\Services\Nomina\AjusteService;
use App\Services\Nomina\AttendanceService;
use App\Services\Nomina\FaltanteCajaService;
use App\Services\Nomina\LoanDiscountPlanService;
use App\Services\Nomina\MerchandiseDeductionService;
use App\Services\Nomina\OtherDeductionService;
use App\Services\Nomina\PayrollBankFileService;
use App\Services\Nomina\PayrollPeriodService;
use App\Services\Nomina\PayrollSedeAreaTotals;
use App\Services\Nomina\SalaryAdvanceService;
use App\Support\SimpleXlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PeriodoController extends Controller
{
    public function __construct(
        private PayrollPeriodService $periods,
        private PayrollBankFileService $bankFile,
        private BcvRateService $bcv,
        private LoanDiscountPlanService $loanPlans,
        private PayrollSedeAreaTotals $sedeAreaTotals,
        private AttendanceService $attendance,
        private SalaryAdvanceService $adelantos,
        private AjusteService $ajustes,
        private MerchandiseDeductionService $mercancia,
        private FaltanteCajaService $faltantes,
        private OtherDeductionService $otrasDeducciones,
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
            'registros.empleado.cargoCatalogo',
            'liquidacionesComision.empleado.cliente',
            'calculadoPor',
            'aprobadoPor',
            'pagadoPor',
            'cerradoPor',
        ]);
        $periodo->registros->each(fn ($registro) => $registro->setRelation('periodo', $periodo));

        $historial = NominaAuditLog::query()
            ->where('entidad', 'periodo')
            ->where('entidad_id', $periodo->id)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        $tasaBcv = $this->bcv->tasaParaPeriodo($periodo);

        return view('nomina.periodos.show', [
            'periodo' => $periodo,
            'historial' => $historial,
            'bancoPorEmpresa' => $this->bankFile->resumenPorEmpresa($periodo),
            'tasaBcv' => $tasaBcv,
            'totalesPorGrupo' => $this->sedeAreaTotals->deRegistros($periodo->registros, $tasaBcv),
            'movimientos' => $this->movimientosPeriodo($periodo),
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

    public function recalcular(NominaPeriodo $periodo): RedirectResponse
    {
        $this->periods->recalcular($periodo, auth()->id());

        return $this->volver($periodo, 'Nómina recalculada.');
    }

    public function aprobar(NominaPeriodo $periodo): RedirectResponse
    {
        $this->periods->aprobar($periodo, auth()->id());

        return $this->volver($periodo, 'Nómina cerrada.');
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
        $tasa = $this->bcv->tasaParaPeriodo($periodo);
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
            'registros.empleado.cargoCatalogo',
        ]);
        $grupo = trim((string) $request->query('grupo', ''));
        $grupoTitulo = null;
        if ($grupo !== '') {
            $filtrados = $periodo->registros->filter(
                fn ($registro) => $this->sedeAreaTotals->grupoDeEmpleado($registro->empleado)['clave'] === $grupo
            )->values();
            if ($filtrados->isEmpty()) {
                return redirect()
                    ->route('nomina.periodos.show', $periodo)
                    ->withErrors(['periodo' => 'Esa sede o área no tiene nómina en esta quincena.']);
            }
            $meta = $this->sedeAreaTotals->grupoDeEmpleado($filtrados->first()->empleado);
            $grupoTitulo = $meta['etiqueta'].': '.$meta['nombre'];
            $periodo->setRelation('registros', $filtrados);
        }
        $tasaBcv = $this->bcv->tasaParaPeriodo($periodo);
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
            'grupoTitulo' => $grupoTitulo,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($nombreBase.'.pdf');
    }

    public function reporteSedesPdf(NominaPeriodo $periodo)
    {
        if ($periodo->estado === NominaPeriodo::ABIERTO) {
            return redirect()
                ->route('nomina.periodos.show', $periodo)
                ->withErrors(['periodo' => 'Calcula la nómina antes de descargar este reporte.']);
        }

        $periodo->load([
            'registros.empleado.cliente',
            'registros.empleado.sedeCatalogo',
        ]);
        $tasaBcv = $this->bcv->tasaParaPeriodo($periodo);
        $filas = $this->sedeAreaTotals->conVentasNetas(
            $this->sedeAreaTotals->deRegistros($periodo->registros, $tasaBcv),
            $periodo
        );

        $pdf = Pdf::loadView('nomina.periodos.pdf-totales-sede', [
            'periodo' => $periodo,
            'filas' => $filas,
            'tasaBcv' => $tasaBcv,
            'logoPath' => $this->logoNominaPdf(),
            'titulo' => 'Totales de nómina por sede',
        ])->setPaper('a4', 'landscape');

        return $pdf->download('totales_sedes_nomina_'.$periodo->id.'_'.$periodo->fecha_inicio?->format('Ymd').'.pdf');
    }

    public function buscarEmpleados(Request $request, NominaPeriodo $periodo): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $empleados = NominaEmpleado::query()
            ->activos()
            ->buscar($q)
            ->with(['cliente', 'sedeCatalogo', 'cargoCatalogo'])
            ->join('clientes', 'clientes.id', '=', 'nomina_empleados.cliente_id')
            ->select('nomina_empleados.*')
            ->orderBy('clientes.nombre')
            ->limit(12)
            ->get();

        return response()->json($empleados->map(fn (NominaEmpleado $empleado) => [
            'id' => $empleado->id,
            'nombre' => $empleado->nombre(),
            'cedula' => $empleado->cedula() ?: '',
            'sede' => $empleado->nombreSede(),
        ])->values());
    }

    public function registrarMovimiento(Request $request, NominaPeriodo $periodo): RedirectResponse
    {
        $tab = $this->tabDeMovimiento((string) $request->input('concepto', 'extras'));
        $volver = fn () => redirect()->route('nomina.periodos.show', ['periodo' => $periodo, 'ajuste' => $tab]);

        if (in_array($periodo->estado, [NominaPeriodo::PAGADO, NominaPeriodo::CERRADO], true)) {
            return $volver()->withErrors(['estado' => 'Esta quincena ya no se puede modificar.']);
        }

        $rules = [
            'concepto' => ['required', 'in:extras,ias,adelanto,bono,deduccion'],
            'empleado_id' => ['required', 'integer', 'exists:nomina_empleados,id'],
            'motivo' => ['nullable', 'string', 'max:500'],
            'formato' => ['nullable', 'in:deduccion,mercancia,faltante,otra'],
        ];
        $concepto = (string) $request->input('concepto');
        if ($concepto === 'extras') {
            $rules['unidad'] = ['required', 'in:HORAS,DIAS'];
            $rules['horas'] = ['required', 'numeric', 'min:0.25'];
        } elseif ($concepto === 'ias') {
            $rules['cantidad'] = ['required', 'numeric', 'min:0.5'];
        } else {
            $rules['monto'] = ['required', 'numeric', 'min:0.01'];
        }

        $validator = validator($request->all(), $rules);
        if ($validator->fails()) {
            return $volver()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $empleado = NominaEmpleado::query()->with(['cliente', 'cargoCatalogo'])->findOrFail($data['empleado_id']);
        $motivo = trim((string) ($data['motivo'] ?? ''));
        $fecha = $periodo->fecha_fin->toDateString();

        try {
            $mensaje = match ($concepto) {
                'extras' => $this->registrarExtrasPeriodo($empleado, $fecha, $data),
                'ias' => $this->registrarIasPeriodo($empleado, $fecha, (float) $data['cantidad'], $motivo),
                'adelanto' => $this->registrarAdelantoPeriodo($empleado, $fecha, (float) $data['monto'], $motivo),
                'bono' => $this->registrarBonoPeriodo($empleado, $fecha, (float) $data['monto'], $motivo),
                default => $this->registrarDeduccionPeriodo($empleado, $fecha, (float) $data['monto'], $motivo, (string) ($data['formato'] ?? 'deduccion')),
            };
        } catch (ValidationException $e) {
            return $volver()->withErrors($e->errors())->withInput();
        }

        if (in_array($periodo->estado, [NominaPeriodo::CALCULADO, NominaPeriodo::APROBADO], true)) {
            $this->periods->recalcular($periodo, auth()->id());
            $mensaje .= ' Nómina recalculada.';
        }

        return $volver()->with('status', $mensaje);
    }

    public function aplicarMovimientos(Request $request, NominaPeriodo $periodo): RedirectResponse
    {
        if (in_array($periodo->estado, [NominaPeriodo::PAGADO, NominaPeriodo::CERRADO], true)) {
            return redirect()
                ->route('nomina.periodos.show', $periodo)
                ->withErrors(['estado' => 'Esta quincena ya no se puede modificar.']);
        }

        $mov = $this->movimientosPeriodo($periodo);
        $quitados = 0;
        $quitados += $this->omitirNoMarcados($mov['extras'], $this->ids($request, 'extra_ids'), fn ($item) => $this->cancelarMovimiento($item));
        $quitados += $this->omitirNoMarcados($mov['ias'], $this->ids($request, 'ias_ids'), fn ($item) => $this->cancelarMovimiento($item));
        $quitados += $this->omitirNoMarcados($mov['adelantos'], $this->ids($request, 'adelanto_ids'), fn ($item) => $this->cancelarMovimiento($item));
        $quitados += $this->omitirNoMarcados($mov['bonos'], $this->ids($request, 'bono_ids'), fn ($item) => $this->cancelarMovimiento($item));
        $quitados += $this->omitirNoMarcados($mov['deducciones'], $this->ids($request, 'deduccion_ids'), fn ($item) => $this->cancelarMovimiento($item));
        $quitados += $this->omitirNoMarcados($mov['mercancia'], $this->ids($request, 'mercancia_ids'), fn ($item) => $this->cancelarMovimiento($item));
        $quitados += $this->omitirNoMarcados($mov['faltantes'], $this->ids($request, 'faltante_ids'), fn ($item) => $this->cancelarMovimiento($item, 'periodo_id'));
        $quitados += $this->omitirNoMarcados($mov['otras'], $this->ids($request, 'otra_ids'), fn ($item) => $this->cancelarMovimiento($item));

        if ($quitados > 0 && in_array($periodo->estado, [NominaPeriodo::CALCULADO, NominaPeriodo::APROBADO], true)) {
            $this->periods->recalcular($periodo, auth()->id());
        }

        $mensaje = $quitados > 0
            ? 'Selección aplicada. Se quitaron '.$quitados.' concepto(s) de la quincena.'
            : 'Selección aplicada. Lo marcado sigue en la quincena.';

        return redirect()
            ->route('nomina.periodos.show', ['periodo' => $periodo, 'ajuste' => $request->input('tab', 'extras')])
            ->with('status', $mensaje);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array<string, float>}
     */
    private function filasRelacionNomina(NominaPeriodo $periodo, float $tasaBcv): array
    {
        $filas = [];
        $totales = [
            'salario' => 0.0, 'horas_extras' => 0.0, 'bonificaciones' => 0.0, 'total_asignaciones' => 0.0,
            'inasistencias' => 0.0, 'adelantos' => 0.0, 'ajustes_deduccion' => 0.0, 'prestamos' => 0.0, 'deducciones' => 0.0,
            'pagar_usd' => 0.0, 'pagar_bs' => 0.0,
        ];

        $registros = $periodo->registros->sortBy(fn ($r) => mb_strtoupper($r->empleado?->nombre() ?? '', 'UTF-8'));
        foreach ($registros as $registro) {
            $desglose = $registro->desglose();
            $sede = $registro->empleado?->sedeCatalogo;
            $sedeNombre = $sede?->nombre ?? $registro->empleado?->sede ?? 'Sin sede';
            $sedeTipo = $sede?->tipo === 'AREA' ? 'AREA' : 'SEDE';
            $salario = round((float) $registro->salario_base, 2);
            $horasExtras = round((float) ($desglose['horas_extras'] ?? 0), 2);
            $bonificaciones = $registro->montoBonificaciones();
            $fila = [
                'cedula' => $registro->empleado?->cedula() ?? '',
                'nombre' => $registro->empleado?->nombre() ?? 'Sin nombre',
                'cargo' => $registro->empleado?->nombreCargo() ?? '—',
                'sede' => $sedeNombre,
                'grupo_tipo' => $sedeTipo,
                'grupo_clave' => $sedeTipo.'|'.mb_strtoupper((string) ($sede?->codigo ?? $sedeNombre), 'UTF-8'),
                'salario' => $salario,
                'horas_extras' => $horasExtras,
                'bonificaciones' => $bonificaciones,
                'total_asignaciones' => round($salario + $horasExtras + $bonificaciones, 2),
                'inasistencias' => round((float) ($desglose['inasistencias'] ?? 0), 2),
                'adelantos' => round((float) ($desglose['abonos_sueldo'] ?? 0), 2),
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
            $f['cedula'], $f['nombre'], $f['cargo'],
            $f['salario'], $f['horas_extras'], $f['bonificaciones'], $f['total_asignaciones'],
            $f['inasistencias'], $f['adelantos'], $f['ajustes_deduccion'], $f['prestamos'],
            $f['deducciones'], $f['pagar_usd'], $f['pagar_bs'],
        ], $filas);

        return array_merge(
            [[
                'Cédula', 'Empleado', 'Cargo',
                'Salario USD', 'Horas extra', 'Bonificaciones', 'Total asignaciones',
                'Ausencias', 'Adelantos', 'Deducciones', 'Préstamos',
                'Total deducciones', 'Total Pagar USD', 'Total a Pagar BCV',
            ]],
            $cuerpo,
            [[
                'TOTALES', count($filas).' trabajadores', '',
                $totales['salario'], $totales['horas_extras'], $totales['bonificaciones'], $totales['total_asignaciones'],
                $totales['inasistencias'], $totales['adelantos'], $totales['ajustes_deduccion'], $totales['prestamos'],
                $totales['deducciones'], $totales['pagar_usd'], $totales['pagar_bs'],
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
            'salario' => 0.0, 'horas_extras' => 0.0, 'bonificaciones' => 0.0, 'total_asignaciones' => 0.0,
            'inasistencias' => 0.0, 'adelantos' => 0.0, 'ajustes_deduccion' => 0.0, 'prestamos' => 0.0, 'deducciones' => 0.0,
            'pagar_usd' => 0.0, 'pagar_bs' => 0.0,
        ];
        foreach ($filas as $fila) {
            foreach ($totales as $k => $v) {
                $totales[$k] = round($v + (float) ($fila[$k] ?? 0), 2);
            }
        }

        return $totales;
    }

    private function tabDeMovimiento(string $concepto): string
    {
        return match ($concepto) {
            'ias' => 'ias',
            'adelanto' => 'adelantos',
            'bono' => 'bonos',
            'deduccion' => 'deducciones',
            default => 'extras',
        };
    }

    /**
     * @return list<int>
     */
    private function ids(Request $request, string $campo): array
    {
        return array_map('intval', (array) $request->input($campo, []));
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @param  list<int>  $marcados
     */
    private function omitirNoMarcados(iterable $items, array $marcados, callable $quitar): int
    {
        $quitados = 0;
        foreach ($items as $item) {
            if (in_array((int) $item->id, $marcados, true)) {
                continue;
            }
            $quitar($item);
            $quitados++;
        }

        return $quitados;
    }

    private function cancelarMovimiento(object $item, string $periodoFk = 'nomina_periodo_id'): void
    {
        $item->estado = 'CANCELADO';
        $item->{$periodoFk} = null;
        $item->save();
    }

    /**
     * @return array{extras: \Illuminate\Support\Collection, ias: \Illuminate\Support\Collection, adelantos: \Illuminate\Support\Collection, bonos: \Illuminate\Support\Collection, deducciones: \Illuminate\Support\Collection, mercancia: \Illuminate\Support\Collection, faltantes: \Illuminate\Support\Collection, otras: \Illuminate\Support\Collection}
     */
    private function movimientosPeriodo(NominaPeriodo $periodo): array
    {
        $vacio = collect();

        return [
            'extras' => $this->deQuincena(NominaHoraExtra::class, $periodo),
            'ias' => $this->deQuincena(NominaInasistencia::class, $periodo),
            'adelantos' => $this->deQuincena(NominaAbonoSueldo::class, $periodo),
            'bonos' => $this->ajustesNomina($periodo, NominaEmpleadoAjuste::TIPO_BONIFICACION),
            'deducciones' => $this->ajustesNomina($periodo, NominaEmpleadoAjuste::TIPO_DEDUCCION),
            'mercancia' => Schema::hasTable('nomina_descuentos_mercancia')
                ? NominaDescuentoMercancia::query()
                    ->with(['empleado.cliente'])
                    ->where('estado', '!=', 'CANCELADO')
                    ->where(function ($q) {
                        $q->where('destino', NominaDescuentoMercancia::DESTINO_NOMINA)->orWhereNull('destino');
                    })
                    ->where(function ($q) use ($periodo) {
                        $q->where('nomina_periodo_id', $periodo->id)
                            ->orWhere(function ($pendiente) use ($periodo) {
                                $pendiente->where('estado', 'PENDIENTE')
                                    ->whereDate('quincena_inicio', $periodo->fecha_inicio->toDateString())
                                    ->whereDate('quincena_fin', $periodo->fecha_fin->toDateString());
                            });
                    })
                    ->orderBy('id')
                    ->get()
                : $vacio,
            'faltantes' => $this->faltantesNomina($periodo),
            'otras' => Schema::hasTable('nomina_deducciones')
                ? $this->deQuincena(NominaDeduccion::class, $periodo)
                : $vacio,
        ];
    }

    private function deQuincena(string $modelo, NominaPeriodo $periodo)
    {
        $tabla = (new $modelo)->getTable();
        if (! Schema::hasTable($tabla)) {
            return collect();
        }

        return $modelo::query()
            ->with(['empleado.cliente'])
            ->where('estado', '!=', 'CANCELADO')
            ->where(function ($query) use ($periodo) {
                $query->where('nomina_periodo_id', $periodo->id)
                    ->orWhere(function ($pendiente) use ($periodo) {
                        $pendiente->where('estado', 'PENDIENTE')
                            ->whereDate('quincena_inicio', $periodo->fecha_inicio->toDateString())
                            ->whereDate('quincena_fin', $periodo->fecha_fin->toDateString());
                    });
            })
            ->orderBy('id')
            ->get();
    }

    private function ajustesNomina(NominaPeriodo $periodo, string $tipo)
    {
        if (! Schema::hasTable('nomina_empleado_ajustes')) {
            return collect();
        }

        return NominaEmpleadoAjuste::query()
            ->with(['empleado.cliente'])
            ->where('tipo', $tipo)
            ->where('destino', NominaEmpleadoAjuste::DESTINO_NOMINA)
            ->where('estado', '!=', NominaEmpleadoAjuste::CANCELADO)
            ->where(function ($query) use ($periodo) {
                $query->where('nomina_periodo_id', $periodo->id)
                    ->orWhere(function ($pendiente) use ($periodo) {
                        $pendiente->where('estado', NominaEmpleadoAjuste::PENDIENTE)
                            ->whereDate('quincena_inicio', $periodo->fecha_inicio->toDateString())
                            ->whereDate('quincena_fin', $periodo->fecha_fin->toDateString());
                    });
            })
            ->orderBy('id')
            ->get();
    }

    private function faltantesNomina(NominaPeriodo $periodo)
    {
        if (! Schema::hasTable('nomina_comision_descuentos')) {
            return collect();
        }

        $query = NominaComisionDescuento::query()
            ->with(['empleado.cliente'])
            ->where('tipo', 'FALTANTE')
            ->where('estado', '!=', 'CANCELADO')
            ->where(function ($q) use ($periodo) {
                $q->where('periodo_id', $periodo->id)
                    ->orWhere(function ($pendiente) use ($periodo) {
                        $pendiente->where('estado', 'PENDIENTE')
                            ->whereDate('fecha', '>=', $periodo->fecha_inicio->toDateString())
                            ->whereDate('fecha', '<=', $periodo->fecha_fin->toDateString());
                    });
            });

        if (Schema::hasColumn('nomina_comision_descuentos', 'destino')) {
            $query->where(function ($q) {
                $q->where('destino', NominaComisionDescuento::DESTINO_NOMINA)->orWhereNull('destino');
            });
        }

        return $query->orderBy('id')->get();
    }

    private function registrarExtrasPeriodo(NominaEmpleado $empleado, string $fecha, array $data): string
    {
        $this->attendance->registrarHorasExtras($empleado, [
            'fecha' => $fecha,
            'unidad' => $data['unidad'],
            'horas' => $data['horas'],
            'motivo' => $data['motivo'] ?? null,
        ], auth()->id());

        $etiqueta = $data['unidad'] === 'DIAS' ? 'día(s)' : 'hora(s)';

        return number_format((float) $data['horas'], 2).' '.$etiqueta.' extra(s) de '.$empleado->nombre().' quedaron en esta quincena.';
    }

    private function registrarIasPeriodo(NominaEmpleado $empleado, string $fecha, float $cantidad, string $motivo): string
    {
        $this->attendance->registrarInasistencia($empleado, [
            'fecha' => $fecha,
            'cantidad' => $cantidad,
            'motivo' => $motivo !== '' ? $motivo : null,
        ], auth()->id());

        return number_format($cantidad, 2).' día(s) de inasistencia de '.$empleado->nombre().' quedaron en esta quincena.';
    }

    private function registrarAdelantoPeriodo(NominaEmpleado $empleado, string $fecha, float $monto, string $motivo): string
    {
        $this->adelantos->create($empleado, [
            'fecha' => $fecha,
            'monto' => $monto,
            'motivo' => $motivo !== '' ? $motivo : null,
        ], auth()->id());

        return 'Adelanto de $'.number_format($monto, 2).' registrado a '.$empleado->nombre().'.';
    }

    private function registrarBonoPeriodo(NominaEmpleado $empleado, string $fecha, float $monto, string $motivo): string
    {
        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo de la bonificación.']);
        }

        $this->ajustes->create($empleado, [
            'fecha' => $fecha,
            'tipo' => NominaEmpleadoAjuste::TIPO_BONIFICACION,
            'destino' => NominaEmpleadoAjuste::DESTINO_NOMINA,
            'monto' => $monto,
            'motivo' => $motivo,
        ], auth()->id());

        return 'Bonificación de $'.number_format($monto, 2).' registrada a '.$empleado->nombre().'.';
    }

    private function registrarDeduccionPeriodo(NominaEmpleado $empleado, string $fecha, float $monto, string $motivo, string $formato): string
    {
        if (in_array($formato, ['deduccion', 'mercancia', 'otra', ''], true) && $motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo del descuento.']);
        }

        if ($formato === 'mercancia') {
            $this->mercancia->create($empleado, [
                'fecha' => $fecha,
                'destino' => NominaDescuentoMercancia::DESTINO_NOMINA,
                'monto' => $monto,
                'motivo' => $motivo,
            ], auth()->id());

            return 'Descuento de mercancía de $'.number_format($monto, 2).' registrado a '.$empleado->nombre().'.';
        }

        if ($formato === 'faltante') {
            $row = $this->faltantes->create($empleado, [
                'fecha' => $fecha,
                'monto' => $monto,
                'motivo' => $motivo !== '' ? $motivo : null,
            ], auth()->id());
            if (Schema::hasColumn('nomina_comision_descuentos', 'decision')) {
                $row->decision = NominaComisionDescuento::DECISION_DESCONTAR;
            }
            if (Schema::hasColumn('nomina_comision_descuentos', 'destino')) {
                $row->destino = NominaComisionDescuento::DESTINO_NOMINA;
            }
            $row->save();

            return 'Faltante de caja de $'.number_format($monto, 2).' registrado a '.$empleado->nombre().'. Se descuenta del sueldo.';
        }

        if ($formato === 'otra') {
            $this->otrasDeducciones->create($empleado, [
                'fecha' => $fecha,
                'monto' => $monto,
                'motivo' => $motivo,
            ], auth()->id());

            return 'Descuento de $'.number_format($monto, 2).' registrado a '.$empleado->nombre().'.';
        }

        $this->ajustes->create($empleado, [
            'fecha' => $fecha,
            'tipo' => NominaEmpleadoAjuste::TIPO_DEDUCCION,
            'destino' => NominaEmpleadoAjuste::DESTINO_NOMINA,
            'monto' => $monto,
            'motivo' => $motivo,
        ], auth()->id());

        return 'Deducción de $'.number_format($monto, 2).' registrada a '.$empleado->nombre().'.';
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
