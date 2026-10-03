<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Services\BcvRateService;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaEmpleadoAjuste;
use App\Models\Nomina\NominaPrestamoPlan;
use App\Services\Nomina\AjusteService;
use App\Services\Nomina\FaltanteCajaService;
use App\Services\Nomina\LoanDiscountPlanService;
use App\Services\Nomina\LoanService;
use App\Services\Nomina\MerchandiseDeductionService;
use App\Services\Nomina\PayrollBankFileService;
use App\Services\Nomina\PayrollPeriodService;
use App\Services\Nomina\PayrollSedeAreaTotals;
use App\Support\SimpleXlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComisionController extends Controller
{
    public function __construct(
        private PayrollBankFileService $bankFile,
        private BcvRateService $bcv,
        private PayrollPeriodService $periods,
        private PayrollSedeAreaTotals $sedeAreaTotals,
        private AjusteService $ajustes,
        private FaltanteCajaService $faltantes,
        private LoanService $loans,
        private LoanDiscountPlanService $planes,
        private MerchandiseDeductionService $mercancia,
    ) {
    }

    public function index(): View
    {
        $visibles = fn ($q) => $q->visibles();
        $periodos = NominaPeriodo::query()
            ->withCount(['liquidacionesComision as liquidaciones_comision_count' => $visibles])
            ->withSum(['liquidacionesComision as liquidaciones_comision_sum_total_pagar' => $visibles], 'total_pagar')
            ->withSum(['liquidacionesComision as liquidaciones_comision_sum_comision_total' => $visibles], 'comision_total')
            ->orderByDesc('fecha_inicio')
            ->get();

        return view('nomina.comisiones.index', [
            'periodos' => $periodos,
        ]);
    }

    public function show(NominaPeriodo $periodo): View
    {
        $liquidaciones = NominaLiquidacionComision::query()
            ->where('periodo_id', $periodo->id)
            ->visibles()
            ->with(['empleado.cliente', 'empleado.empresa', 'empleado.sedeCatalogo', 'periodo'])
            ->orderBy('id')
            ->get();

        $periodo->setRelation('liquidacionesComision', $liquidaciones);

        $tasaBcv = $this->bcv->tasaParaPeriodo($periodo);

        return view('nomina.comisiones.show', [
            'periodo' => $periodo,
            'liquidaciones' => $liquidaciones,
            'bonosComision' => $this->bonosDePeriodo($periodo),
            'bancoPorEmpresa' => $this->bankFile->resumenComisionesPorEmpresa($periodo),
            'tasaBcv' => $tasaBcv,
            'totalesPorGrupo' => $this->sedeAreaTotals->deLiquidaciones($liquidaciones, $tasaBcv),
            'ajustesPeriodo' => $this->ajustesPeriodo($periodo),
        ]);
    }

    public function relacion(Request $request, NominaPeriodo $periodo)
    {
        if ($periodo->estado === NominaPeriodo::ABIERTO) {
            return redirect()
                ->route('nomina.comisiones.show', $periodo)
                ->withErrors(['periodo' => 'Calcula la nómina antes de descargar la relación de comisiones.']);
        }

        $liquidaciones = NominaLiquidacionComision::query()
            ->where('periodo_id', $periodo->id)
            ->visibles()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'empleado.cargoCatalogo'])
            ->orderBy('id')
            ->get();

        if ($liquidaciones->isEmpty()) {
            return redirect()
                ->route('nomina.comisiones.show', $periodo)
                ->withErrors(['periodo' => 'No hay comisiones calculadas para este período.']);
        }

        $tasaBcv = $this->bcv->tasaParaPeriodo($periodo);
        [$filasVentas, $filasSt, $totalesVentas, $totalesSt] = $this->filasRelacionComisiones($liquidaciones, $tasaBcv);
        $grupo = trim((string) $request->query('grupo', ''));
        if ($grupo !== '') {
            $filasVentas = array_values(array_filter($filasVentas, fn ($fila) => ($fila['grupo_clave'] ?? '') === $grupo));
            $filasSt = array_values(array_filter($filasSt, fn ($fila) => ($fila['grupo_clave'] ?? '') === $grupo));
            if ($filasVentas === [] && $filasSt === []) {
                return redirect()
                    ->route('nomina.comisiones.show', $periodo)
                    ->withErrors(['periodo' => 'Esa sede o área no tiene comisiones en esta quincena.']);
            }
        }
        $referenciaGrupo = $grupo !== '' ? ($filasVentas[0] ?? $filasSt[0] ?? null) : null;
        $grupoTitulo = $referenciaGrupo
            ? ((($referenciaGrupo['grupo_tipo'] ?? '') === 'AREA' ? 'Área' : 'Sede').': '.($referenciaGrupo['sede'] ?? ''))
            : null;
        $nombreBase = 'relacion_comisiones_'.$periodo->id.'_'.$periodo->fecha_inicio?->format('Ymd');

        if ($request->query('formato') === 'zip') {
            return $this->descargarZipPorSedeYArea(
                $periodo,
                $filasVentas,
                $filasSt,
                $nombreBase,
                $tasaBcv
            );
        }

        if ($request->query('formato') === 'xlsx') {
            $xlsx = SimpleXlsxWriter::toString([
                'Supervisores y vendedores' => $this->hojaVentas($filasVentas, $totalesVentas),
                'Servicio técnico' => $this->hojaSt($filasSt, $totalesSt),
            ]);

            return response($xlsx, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$nombreBase.'.xlsx"',
            ]);
        }

        $pdf = Pdf::loadView('nomina.comisiones.pdf-relacion', [
            'periodo' => $periodo,
            'filasVentas' => $filasVentas,
            'filasSt' => $filasSt,
            'totalesVentas' => $totalesVentas,
            'totalesSt' => $totalesSt,
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
                ->route('nomina.comisiones.show', $periodo)
                ->withErrors(['periodo' => 'Calcula la nómina antes de descargar este reporte.']);
        }

        $liquidaciones = NominaLiquidacionComision::query()
            ->where('periodo_id', $periodo->id)
            ->visibles()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo'])
            ->orderBy('id')
            ->get();

        $tasaBcv = $this->bcv->tasaParaPeriodo($periodo);
        $filas = $this->sedeAreaTotals->conVentasNetas(
            $this->sedeAreaTotals->deLiquidaciones($liquidaciones, $tasaBcv),
            $periodo
        );

        $pdf = Pdf::loadView('nomina.periodos.pdf-totales-sede', [
            'periodo' => $periodo,
            'filas' => $filas,
            'tasaBcv' => $tasaBcv,
            'logoPath' => $this->logoNominaPdf(),
            'titulo' => 'Totales de comisiones por sede',
        ])->setPaper('a4', 'landscape');

        return $pdf->download('totales_sedes_comisiones_'.$periodo->id.'_'.$periodo->fecha_inicio?->format('Ymd').'.pdf');
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
            'comision' => $empleado->generaComision(),
        ])->values());
    }

    public function registrarAjuste(Request $request, NominaPeriodo $periodo): RedirectResponse
    {
        $tab = $this->tabDeConcepto((string) $request->input('concepto', 'bono'));
        $volver = fn () => redirect()->route('nomina.comisiones.show', ['periodo' => $periodo, 'ajuste' => $tab]);

        if ($periodo->estado === NominaPeriodo::CERRADO) {
            return $volver()->withErrors(['estado' => 'La quincena está cerrada. Ya no se pueden cargar ajustes.']);
        }

        $validator = validator($request->all(), [
            'concepto' => ['required', 'in:bono,prestamo,faltante,descuento'],
            'empleado_id' => ['required', 'integer', 'exists:nomina_empleados,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string', 'max:500'],
            'formato' => ['nullable', 'in:deduccion,mercancia,PERDIDA,DANO,OTRO'],
        ]);
        if ($validator->fails()) {
            return $volver()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $empleado = NominaEmpleado::query()->with(['cliente', 'cargoCatalogo'])->findOrFail($data['empleado_id']);
        $motivo = trim((string) ($data['motivo'] ?? ''));
        $fecha = $periodo->fecha_fin->toDateString();

        try {
            if (! $empleado->generaComision()) {
                throw ValidationException::withMessages([
                    'empleado_id' => 'Esa persona no genera comisión. El registro no entra en esta quincena.',
                ]);
            }

            $mensaje = match ($data['concepto']) {
                'bono' => $this->registrarBonoComision($empleado, $fecha, (float) $data['monto'], $motivo),
                'prestamo' => $this->registrarPrestamoComision($empleado, $periodo, $fecha, (float) $data['monto'], $motivo),
                'faltante' => $this->registrarFaltanteComision($empleado, $fecha, (float) $data['monto'], $motivo),
                default => $this->registrarDescuentoComision($empleado, $fecha, (float) $data['monto'], $motivo, (string) ($data['formato'] ?? 'deduccion')),
            };
        } catch (ValidationException $e) {
            return $volver()->withErrors($e->errors())->withInput();
        }

        if ($periodo->estado !== NominaPeriodo::ABIERTO) {
            $this->periods->recalcularComisiones($periodo, auth()->id());
            $mensaje .= ' Comisiones recalculadas.';
        }

        return $volver()->with('status', $mensaje);
    }

    public function quitarBono(NominaPeriodo $periodo, NominaEmpleadoAjuste $ajuste): RedirectResponse
    {
        $this->ajustes->quitarBonoComision($ajuste, $periodo);

        if ($periodo->estado !== NominaPeriodo::ABIERTO) {
            $this->periods->recalcularComisiones($periodo, auth()->id());
        }

        return redirect()
            ->route('nomina.comisiones.show', $periodo)
            ->with('status', 'Bono quitado de la quincena.');
    }

    public function aplicarBonos(Request $request, NominaPeriodo $periodo): RedirectResponse
    {
        if ($periodo->estado === NominaPeriodo::CERRADO) {
            return redirect()
                ->route('nomina.comisiones.show', $periodo)
                ->withErrors(['estado' => 'La quincena está cerrada. Ya no se pueden cambiar los bonos.']);
        }

        $marcadosBonos = array_map('intval', (array) $request->input('ajuste_ids', []));
        $marcadosPlanes = array_map('intval', (array) $request->input('plan_ids', []));
        $marcadosFaltantes = array_map('intval', (array) $request->input('faltante_ids', []));
        $marcadosDescuentos = array_map('intval', (array) $request->input('descuento_ids', []));
        $marcadosMercancia = array_map('intval', (array) $request->input('mercancia_ids', []));
        $ajustes = $this->ajustesPeriodo($periodo);
        $quitados = 0;

        foreach ($ajustes['bonos'] as $bono) {
            if (in_array((int) $bono->id, $marcadosBonos, true)) {
                continue;
            }
            $this->ajustes->quitarBonoComision($bono, $periodo);
            $quitados++;
        }
        $quitados += $this->omitirNoMarcados($ajustes['prestamos'], $marcadosPlanes, function ($plan) {
            $plan->estado = \App\Models\Nomina\NominaPrestamoPlan::CANCELADO;
            $plan->nomina_periodo_id = null;
            $plan->save();
        });
        $quitados += $this->omitirNoMarcados($ajustes['faltantes'], $marcadosFaltantes, function ($item) {
            $item->estado = 'CANCELADO';
            $item->periodo_id = null;
            $item->save();
        });
        $quitados += $this->omitirNoMarcados($ajustes['descuentos'], $marcadosDescuentos, function ($item) {
            $item->estado = \App\Models\Nomina\NominaEmpleadoAjuste::CANCELADO;
            $item->nomina_periodo_id = null;
            $item->save();
        });
        $marcadosOtros = array_map('intval', (array) $request->input('otro_ids', []));
        $quitados += $this->omitirNoMarcados($ajustes['otros'], $marcadosOtros, function ($item) {
            $item->estado = 'CANCELADO';
            $item->periodo_id = null;
            $item->save();
        });
        $quitados += $this->omitirNoMarcados($ajustes['mercancia'], $marcadosMercancia, function ($item) {
            $item->estado = 'CANCELADO';
            $item->nomina_periodo_id = null;
            $item->save();
        });

        if ($quitados > 0 && $periodo->estado !== NominaPeriodo::ABIERTO) {
            $this->periods->recalcularComisiones($periodo, auth()->id());
        }

        $mensaje = $quitados > 0
            ? 'Selección aplicada. Se quitaron '.$quitados.' concepto(s) de la quincena.'
            : 'Selección aplicada. Lo marcado sigue en la quincena.';

        return redirect()
            ->route('nomina.comisiones.show', $periodo)
            ->with('status', $mensaje);
    }

    public function recalcular(NominaPeriodo $periodo): RedirectResponse
    {
        $this->periods->recalcularComisiones($periodo, auth()->id());

        return redirect()
            ->route('nomina.comisiones.show', $periodo)
            ->with('status', 'Comisiones recalculadas con los datos actuales de ventas y la tasa BCV del día.');
    }

    public function toggleExentoRetencion(Request $request, NominaPeriodo $periodo, NominaEmpleado $empleado): RedirectResponse
    {
        $exento = $request->boolean('exento_retencion_comision');

        $empleado->exento_retencion_comision = $exento;
        $empleado->save();

        $msg = $exento
            ? $empleado->nombre().': marcado sin retención de comisión.'
            : $empleado->nombre().': se vuelve a aplicar retención de comisión.';

        if ($periodo->estado !== NominaPeriodo::ABIERTO && $periodo->estado !== NominaPeriodo::CERRADO) {
            $this->periods->recalcularComisiones($periodo, auth()->id());
            $msg .= ' Comisiones recalculadas.';
        } else {
            $msg .= ' Usa Recalcular comisiones para actualizar montos.';
        }

        return redirect()
            ->route('nomina.comisiones.show', $periodo)
            ->with('status', $msg);
    }

    public function exportarBanco(NominaPeriodo $periodo, NominaEmpresa $empresa): StreamedResponse|RedirectResponse
    {
        if ($periodo->estado === NominaPeriodo::ABIERTO) {
            return redirect()
                ->route('nomina.comisiones.show', $periodo)
                ->withErrors(['periodo' => 'Calcula la nómina antes de generar el archivo del banco.']);
        }

        $tasa = $this->bcv->tasaParaPeriodo($periodo);
        $contenido = $this->bankFile->generarComisiones($periodo, $empresa, $tasa);
        $nombre = $this->bankFile->nombreArchivoComisiones($periodo, $empresa);

        return response()->streamDownload(function () use ($contenido) {
            echo $contenido;
        }, $nombre, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function tabDeConcepto(string $concepto): string
    {
        return match ($concepto) {
            'prestamo' => 'prestamos',
            'faltante' => 'faltantes',
            'descuento' => 'descuentos',
            default => 'bonos',
        };
    }

    private function registrarBonoComision(NominaEmpleado $empleado, string $fecha, float $monto, string $motivo): string
    {
        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo del bono.']);
        }

        $this->ajustes->create($empleado, [
            'fecha' => $fecha,
            'tipo' => NominaEmpleadoAjuste::TIPO_BONIFICACION,
            'destino' => NominaEmpleadoAjuste::DESTINO_COMISION,
            'monto' => $monto,
            'motivo' => $motivo,
        ], auth()->id());

        return 'Bono de $'.number_format($monto, 2).' registrado a '.$empleado->nombre().'.';
    }

    private function registrarPrestamoComision(NominaEmpleado $empleado, NominaPeriodo $periodo, string $fecha, float $monto, string $motivo): string
    {
        $prestamo = $this->loans->create($empleado, [
            'fecha' => $fecha,
            'fecha_inicio' => $fecha,
            'monto_original' => $monto,
            'motivo' => $motivo !== '' ? $motivo : null,
        ], auth()->id());

        $programados = $this->planes->programarLibreEmpleado(
            $empleado,
            $monto,
            NominaPrestamoPlan::DESTINO_COMISION,
            [
                'inicio' => $periodo->fecha_inicio,
                'fin' => $periodo->fecha_fin,
                'etiqueta' => $periodo->etiqueta,
            ],
            auth()->id(),
            $prestamo->id
        );

        if ($programados < 1) {
            throw ValidationException::withMessages([
                'monto' => 'El préstamo se creó, pero no se pudo programar el descuento de esta quincena.',
            ]);
        }

        return 'Préstamo de $'.number_format($monto, 2).' registrado a '.$empleado->nombre().' y programado en esta quincena.';
    }

    private function registrarFaltanteComision(NominaEmpleado $empleado, string $fecha, float $monto, string $motivo): string
    {
        $row = $this->faltantes->create($empleado, [
            'fecha' => $fecha,
            'monto' => $monto,
            'motivo' => $motivo !== '' ? $motivo : null,
        ], auth()->id());

        if (Schema::hasColumn('nomina_comision_descuentos', 'decision')) {
            $row->decision = NominaComisionDescuento::DECISION_DESCONTAR;
        }
        if (Schema::hasColumn('nomina_comision_descuentos', 'destino')) {
            $row->destino = NominaComisionDescuento::DESTINO_COMISION;
        }
        $row->save();

        return 'Faltante de caja de $'.number_format($monto, 2).' registrado a '.$empleado->nombre().'. Se descuenta de esta quincena.';
    }

    private function registrarDescuentoComision(NominaEmpleado $empleado, string $fecha, float $monto, string $motivo, string $formato): string
    {
        if (in_array($formato, ['deduccion', 'mercancia'], true) && $motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo del descuento.']);
        }

        if ($formato === 'mercancia') {
            $this->mercancia->create($empleado, [
                'fecha' => $fecha,
                'destino' => 'COMISION',
                'monto' => $monto,
                'motivo' => $motivo,
            ], auth()->id());

            return 'Descuento de mercancía de $'.number_format($monto, 2).' registrado a '.$empleado->nombre().'.';
        }

        if ($formato === 'deduccion' || $formato === '') {
            $this->ajustes->create($empleado, [
                'fecha' => $fecha,
                'tipo' => NominaEmpleadoAjuste::TIPO_DEDUCCION,
                'destino' => NominaEmpleadoAjuste::DESTINO_COMISION,
                'monto' => $monto,
                'motivo' => $motivo,
            ], auth()->id());

            return 'Deducción de $'.number_format($monto, 2).' registrada a '.$empleado->nombre().'.';
        }

        $tipo = in_array($formato, ['PERDIDA', 'DANO', 'OTRO'], true) ? $formato : 'OTRO';
        NominaComisionDescuento::create([
            'empleado_id' => $empleado->id,
            'fecha' => $fecha,
            'tipo' => $tipo,
            'monto' => round($monto, 2),
            'motivo' => $motivo !== '' ? mb_substr($motivo, 0, 255) : null,
            'estado' => 'PENDIENTE',
            'created_by' => auth()->id(),
        ]);

        $etiqueta = match ($tipo) {
            'PERDIDA' => 'Pérdida',
            'DANO' => 'Daño',
            default => 'Otro descuento',
        };

        return $etiqueta.' de $'.number_format($monto, 2).' registrado a '.$empleado->nombre().'.';
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

    /**
     * @return array{bonos: \Illuminate\Support\Collection, prestamos: \Illuminate\Support\Collection, faltantes: \Illuminate\Support\Collection, descuentos: \Illuminate\Support\Collection, otros: \Illuminate\Support\Collection, mercancia: \Illuminate\Support\Collection}
     */
    private function ajustesPeriodo(NominaPeriodo $periodo): array
    {
        $vacio = collect();
        $schema = \Illuminate\Support\Facades\Schema::class;

        $prestamos = $schema::hasTable('nomina_prestamo_planes')
            ? \App\Models\Nomina\NominaPrestamoPlan::query()
                ->with(['empleado.cliente', 'prestamo'])
                ->where('destino', \App\Models\Nomina\NominaPrestamoPlan::DESTINO_COMISION)
                ->where('estado', '!=', \App\Models\Nomina\NominaPrestamoPlan::CANCELADO)
                ->where(function ($query) use ($periodo) {
                    $query->where('nomina_periodo_id', $periodo->id)
                        ->orWhere(function ($pendiente) use ($periodo) {
                            $pendiente->where('estado', \App\Models\Nomina\NominaPrestamoPlan::PENDIENTE)
                                ->whereDate('quincena_inicio', $periodo->fecha_inicio->toDateString())
                                ->whereDate('quincena_fin', $periodo->fecha_fin->toDateString());
                        });
                })
                ->orderBy('id')
                ->get()
            : $vacio;

        $descuentosComision = $schema::hasTable('nomina_comision_descuentos')
            ? \App\Models\Nomina\NominaComisionDescuento::query()
                ->with(['empleado.cliente'])
                ->where('estado', '!=', 'CANCELADO')
                ->where(function ($query) use ($periodo) {
                    $query->where('periodo_id', $periodo->id)
                        ->orWhere(function ($pendiente) use ($periodo) {
                            $pendiente->where('estado', 'PENDIENTE')
                                ->whereDate('fecha', '>=', $periodo->fecha_inicio->toDateString())
                                ->whereDate('fecha', '<=', $periodo->fecha_fin->toDateString());
                        });
                })
                ->orderBy('id')
                ->get()
            : $vacio;

        $descuentos = $schema::hasTable('nomina_empleado_ajustes')
            ? NominaEmpleadoAjuste::query()
                ->with(['empleado.cliente'])
                ->where('tipo', NominaEmpleadoAjuste::TIPO_DEDUCCION)
                ->where('destino', NominaEmpleadoAjuste::DESTINO_COMISION)
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
                ->get()
            : $vacio;

        $mercancia = $schema::hasTable('nomina_descuentos_mercancia')
            ? \App\Models\Nomina\NominaDescuentoMercancia::query()
                ->with(['empleado.cliente'])
                ->where('destino', \App\Models\Nomina\NominaDescuentoMercancia::DESTINO_COMISION)
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
                ->get()
            : $vacio;

        return [
            'bonos' => $this->bonosDePeriodo($periodo),
            'prestamos' => $prestamos,
            'faltantes' => $descuentosComision->where('tipo', 'FALTANTE')->values(),
            'descuentos' => $descuentos,
            'otros' => $descuentosComision->whereNotIn('tipo', ['FALTANTE', 'PRESTAMO'])->values(),
            'mercancia' => $mercancia,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, NominaEmpleadoAjuste>
     */
    private function bonosDePeriodo(NominaPeriodo $periodo)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('nomina_empleado_ajustes')) {
            return collect();
        }

        return NominaEmpleadoAjuste::query()
            ->with(['empleado.cliente'])
            ->where('tipo', NominaEmpleadoAjuste::TIPO_BONIFICACION)
            ->where('destino', NominaEmpleadoAjuste::DESTINO_COMISION)
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

    /**
     * @param  \Illuminate\Support\Collection<int, NominaLiquidacionComision>  $liquidaciones
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: array<string, float>, 3: array<string, float>}
     */
    private function filasRelacionComisiones($liquidaciones, float $tasaBcv): array
    {
        $filasVentas = [];
        $filasSt = [];
        $totalesVentas = $this->totalesVaciosVentas();
        $totalesSt = $this->totalesVaciosSt();

        $ordenadas = $liquidaciones->sortBy(fn ($liq) => mb_strtoupper($liq->empleado?->nombre() ?? '', 'UTF-8'));
        foreach ($ordenadas as $liq) {
            $meta = $this->metaEmpleado($liq);
            $descuentos = round((float) $liq->descuentos + (float) $liq->prestamos, 2);
            $base = [
                'cedula' => $meta['cedula'],
                'nombre' => $meta['nombre'],
                'cargo' => $meta['cargo'],
                'sede' => $meta['sede'],
                'grupo_tipo' => $meta['grupo_tipo'],
                'grupo_clave' => $meta['grupo_clave'],
                'comision' => round((float) $liq->comision_total, 2),
                'abonos' => round((float) $liq->abonos, 2),
                'retencion' => round((float) $liq->retencion, 2),
                'descuentos' => $descuentos,
                'pagar_usd' => round((float) $liq->total_pagar, 2),
                'pagar_bs' => round((float) $liq->total_pagar * $tasaBcv, 2),
            ];
            $base['total_comisiones'] = round($base['comision'] + $base['abonos'], 2);

            if ($liq->esServicioTecnico()) {
                $comisionSt = $liq->comisionSt();
                $pagarSt = round($comisionSt - $descuentos, 2);

                $fila = [
                    'cedula' => $meta['cedula'],
                    'nombre' => $meta['nombre'],
                    'cargo' => $meta['cargo'],
                    'sede' => $meta['sede'],
                    'grupo_tipo' => $meta['grupo_tipo'],
                    'grupo_clave' => $meta['grupo_clave'],
                    'facturas_st' => $liq->ventasSt(),
                    'egresos_058' => $liq->egresos058(),
                    'comision' => $comisionSt,
                    'retencion' => 0.0,
                    'descuentos' => $descuentos,
                    'pagar_usd' => $pagarSt,
                    'pagar_bs' => round($pagarSt * $tasaBcv, 2),
                ];
                $filasSt[] = $fila;
                foreach (['facturas_st', 'egresos_058', 'comision', 'retencion', 'descuentos', 'pagar_usd', 'pagar_bs'] as $k) {
                    $totalesSt[$k] = round($totalesSt[$k] + (float) $fila[$k], 2);
                }
            } else {
                $esSupervisor = in_array($liq->modo, array_merge(
                    NominaEmpleado::modosComisionAgregadosSede(),
                    NominaEmpleado::modosComisionAgregadosEquipo()
                ), true);
                $fila = $base + [
                    'ventas' => $liq->totalVentas(),
                    'base_telefonia' => round((float) $liq->base_telefonia, 2),
                    'base_otros' => round((float) $liq->base_otros, 2),
                    'es_supervisor' => $esSupervisor,
                ];
                $filasVentas[] = $fila;
                foreach (['ventas', 'base_telefonia', 'base_otros', 'comision', 'abonos', 'total_comisiones', 'retencion', 'descuentos', 'pagar_usd', 'pagar_bs'] as $k) {
                    $totalesVentas[$k] = round($totalesVentas[$k] + (float) $fila[$k], 2);
                }
            }
        }

        return [$filasVentas, $filasSt, $totalesVentas, $totalesSt];
    }

    /**
     * @return array{cedula: string, nombre: string, cargo: string, sede: string, grupo_tipo: string, grupo_clave: string}
     */
    private function metaEmpleado(NominaLiquidacionComision $liq): array
    {
        $empleado = $liq->empleado;
        $sede = $empleado?->sedeCatalogo;
        $sedeNombre = $sede?->nombre ?? $empleado?->sede ?? 'Sin sede';
        $sedeTipo = $sede?->tipo === 'AREA' ? 'AREA' : 'SEDE';

        return [
            'cedula' => $empleado?->cedula() ?? '',
            'nombre' => $empleado?->nombre() ?? 'Sin nombre',
            'cargo' => $empleado?->nombreCargo() ?? '—',
            'sede' => $sedeNombre,
            'grupo_tipo' => $sedeTipo,
            'grupo_clave' => $sedeTipo.'|'.mb_strtoupper((string) ($sede?->codigo ?? $sedeNombre), 'UTF-8'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filasVentas
     * @param  list<array<string, mixed>>  $filasSt
     */
    private function descargarZipPorSedeYArea(
        NominaPeriodo $periodo,
        array $filasVentas,
        array $filasSt,
        string $nombreBase,
        float $tasaBcv
    ) {
        ini_set('memory_limit', '512M');

        try {
            $archivos = [];
            $logoPath = $this->logoNominaPdf();
            $grupos = collect($filasVentas)
                ->merge($filasSt)
                ->pluck('grupo_clave')
                ->unique()
                ->values();

            foreach ($grupos as $grupoClave) {
                $ventasGrupo = array_values(array_filter($filasVentas, fn ($f) => ($f['grupo_clave'] ?? '') === $grupoClave));
                $stGrupo = array_values(array_filter($filasSt, fn ($f) => ($f['grupo_clave'] ?? '') === $grupoClave));
                if ($ventasGrupo === [] && $stGrupo === []) {
                    continue;
                }

                $referencia = $ventasGrupo[0] ?? $stGrupo[0];
                $tipo = ($referencia['grupo_tipo'] ?? 'SEDE') === 'AREA' ? 'Area' : 'Sede';
                $sedeNombre = (string) ($referencia['sede'] ?? 'sin_sede');
                $pdf = Pdf::loadView('nomina.comisiones.pdf-relacion', [
                    'periodo' => $periodo,
                    'filasVentas' => $ventasGrupo,
                    'filasSt' => $stGrupo,
                    'totalesVentas' => $this->totalesDeFilasVentas($ventasGrupo),
                    'totalesSt' => $this->totalesDeFilasSt($stGrupo),
                    'tasaBcv' => $tasaBcv,
                    'logoPath' => $logoPath,
                    'grupoTitulo' => ($tipo === 'Area' ? 'Area' : 'Sede').': '.$sedeNombre,
                ])->setPaper('a4', 'landscape');
                $archivos[$tipo.'_'.$this->slugArchivo($sedeNombre).'.pdf'] = $pdf->output();
                unset($pdf);
            }

            if ($archivos === []) {
                return back()->withErrors(['periodo' => 'No hay comisiones para armar el ZIP.']);
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
     * @param  array<string, float>  $totales
     * @return list<list<string|int|float|null>>
     */
    private function hojaVentas(array $filas, array $totales): array
    {
        $cuerpo = array_map(fn ($f) => [
            $f['cedula'], $f['nombre'], $f['cargo'],
            $f['ventas'],
            $f['es_supervisor'] ? null : $f['base_telefonia'],
            $f['es_supervisor'] ? null : $f['base_otros'],
            $f['abonos'], $f['total_comisiones'], $f['retencion'], $f['descuentos'],
            $f['pagar_usd'], $f['pagar_bs'],
        ], $filas);

        return array_merge(
            [[
                'Cédula', 'Empleado', 'Cargo', 'Venta neta', 'Ventas telefonía', 'Ventas otros',
                'Bonos', 'Total comisiones', 'Retención', 'Desc. / préstamos', 'A pagar USD', 'A pagar BCV',
            ]],
            $cuerpo,
            [[
                'TOTALES', count($filas).' empleados', '',
                $totales['ventas'], $totales['base_telefonia'], $totales['base_otros'],
                $totales['abonos'], $totales['total_comisiones'], $totales['retencion'], $totales['descuentos'],
                $totales['pagar_usd'], $totales['pagar_bs'],
            ]]
        );
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, float>  $totales
     * @return list<list<string|int|float|null>>
     */
    private function hojaSt(array $filas, array $totales): array
    {
        $cuerpo = array_map(fn ($f) => [
            $f['cedula'], $f['nombre'],
            $f['facturas_st'], $f['egresos_058'],
            $f['comision'], $f['retencion'], $f['descuentos'],
            $f['pagar_usd'], $f['pagar_bs'],
        ], $filas);

        return array_merge(
            [[
                'Cédula', 'Empleado', 'Facturas ST', 'Egresos',
                'Comisión', 'Retención', 'Desc. / préstamos', 'A pagar USD', 'A pagar BCV',
            ]],
            $cuerpo,
            [[
                'TOTALES', count($filas).' empleados',
                $totales['facturas_st'], $totales['egresos_058'],
                $totales['comision'], $totales['retencion'], $totales['descuentos'],
                $totales['pagar_usd'], $totales['pagar_bs'],
            ]]
        );
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float>
     */
    private function totalesDeFilasVentas(array $filas): array
    {
        $totales = $this->totalesVaciosVentas();
        foreach ($filas as $fila) {
            foreach ($totales as $k => $v) {
                $totales[$k] = round($v + (float) ($fila[$k] ?? 0), 2);
            }
        }

        return $totales;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float>
     */
    private function totalesDeFilasSt(array $filas): array
    {
        $totales = $this->totalesVaciosSt();
        foreach ($filas as $fila) {
            foreach ($totales as $k => $v) {
                $totales[$k] = round($v + (float) ($fila[$k] ?? 0), 2);
            }
        }

        return $totales;
    }

    /** @return array<string, float> */
    private function totalesVaciosVentas(): array
    {
        return [
            'ventas' => 0.0, 'base_telefonia' => 0.0, 'base_otros' => 0.0,
            'comision' => 0.0, 'abonos' => 0.0, 'total_comisiones' => 0.0,
            'retencion' => 0.0, 'descuentos' => 0.0,
            'pagar_usd' => 0.0, 'pagar_bs' => 0.0,
        ];
    }

    /** @return array<string, float> */
    private function totalesVaciosSt(): array
    {
        return [
            'facturas_st' => 0.0, 'egresos_058' => 0.0,
            'comision' => 0.0, 'retencion' => 0.0, 'descuentos' => 0.0,
            'pagar_usd' => 0.0, 'pagar_bs' => 0.0,
        ];
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
}
