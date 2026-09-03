<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Services\BcvRateService;
use App\Services\Nomina\PayrollBankFileService;
use App\Services\Nomina\PayrollPeriodService;
use App\Support\SimpleXlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComisionController extends Controller
{
    public function __construct(
        private PayrollBankFileService $bankFile,
        private BcvRateService $bcv,
        private PayrollPeriodService $periods,
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
            ->with(['empleado.cliente', 'empleado.empresa', 'empleado.sedeCatalogo'])
            ->orderBy('id')
            ->get();

        $periodo->setRelation('liquidacionesComision', $liquidaciones);

        return view('nomina.comisiones.show', [
            'periodo' => $periodo,
            'liquidaciones' => $liquidaciones,
            'bancoPorEmpresa' => $this->bankFile->resumenComisionesPorEmpresa($periodo),
            'tasaBcv' => $this->bcv->getRateForToday(),
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
            ->with(['empleado.cliente', 'empleado.sedeCatalogo'])
            ->orderBy('id')
            ->get();

        if ($liquidaciones->isEmpty()) {
            return redirect()
                ->route('nomina.comisiones.show', $periodo)
                ->withErrors(['periodo' => 'No hay comisiones calculadas para este período.']);
        }

        $tasaBcv = $this->bcv->getRateForToday();
        [$filasVentas, $filasSt, $totalesVentas, $totalesSt] = $this->filasRelacionComisiones($liquidaciones, $tasaBcv);
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
            'grupoTitulo' => null,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($nombreBase.'.pdf');
    }

    public function recalcular(NominaPeriodo $periodo): RedirectResponse
    {
        $this->periods->recalcularComisiones($periodo, auth()->id());

        return redirect()
            ->route('nomina.comisiones.show', $periodo)
            ->with('status', 'Comisiones recalculadas con los datos actuales de ventas.');
    }

    public function exportarBanco(NominaPeriodo $periodo, NominaEmpresa $empresa): StreamedResponse|RedirectResponse
    {
        if ($periodo->estado === NominaPeriodo::ABIERTO) {
            return redirect()
                ->route('nomina.comisiones.show', $periodo)
                ->withErrors(['periodo' => 'Calcula la nómina antes de generar el archivo del banco.']);
        }

        $tasa = $this->bcv->getRateForToday();
        $contenido = $this->bankFile->generarComisiones($periodo, $empresa, $tasa);
        $nombre = $this->bankFile->nombreArchivoComisiones($periodo, $empresa);

        return response()->streamDownload(function () use ($contenido) {
            echo $contenido;
        }, $nombre, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
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

            if ($liq->esServicioTecnico()) {
                $comisionSt = $liq->comisionSt();
                $comisionOtros = $liq->comisionOtrosProductos();
                $abonos = round((float) $liq->abonos, 2);
                $retencion = $liq->retencionOtrosProductos();
                $pagarSt = round($comisionSt - $descuentos, 2);
                $pagarOtros = round($comisionOtros + $abonos - $retencion, 2);

                $fila = [
                    'cedula' => $meta['cedula'],
                    'nombre' => $meta['nombre'],
                    'sede' => $meta['sede'],
                    'grupo_tipo' => $meta['grupo_tipo'],
                    'grupo_clave' => $meta['grupo_clave'],
                    'facturas_st' => $liq->ventasSt(),
                    'egresos_058' => $liq->egresos058(),
                    'comision' => $comisionSt,
                    'abonos' => 0.0,
                    'retencion' => 0.0,
                    'descuentos' => $descuentos,
                    'pagar_usd' => $pagarSt,
                    'pagar_bs' => round($pagarSt * $tasaBcv, 2),
                ];
                $filasSt[] = $fila;
                foreach (['facturas_st', 'egresos_058', 'comision', 'abonos', 'retencion', 'descuentos', 'pagar_usd', 'pagar_bs'] as $k) {
                    $totalesSt[$k] = round($totalesSt[$k] + (float) $fila[$k], 2);
                }

                // Supervisores/vendedores: Otros productos con retención.
                $filaVentas = [
                    'cedula' => $meta['cedula'],
                    'nombre' => $meta['nombre'],
                    'sede' => $meta['sede'],
                    'grupo_tipo' => $meta['grupo_tipo'],
                    'grupo_clave' => $meta['grupo_clave'],
                    'ventas' => $liq->ventasOtrosProductos(),
                    'base_telefonia' => round((float) $liq->base_telefonia, 2),
                    'base_otros' => round((float) $liq->base_otros, 2),
                    'es_supervisor' => false,
                    'comision' => $comisionOtros,
                    'abonos' => $abonos,
                    'retencion' => $retencion,
                    'descuentos' => 0.0,
                    'pagar_usd' => $pagarOtros,
                    'pagar_bs' => round($pagarOtros * $tasaBcv, 2),
                ];
                $filasVentas[] = $filaVentas;
                foreach (['ventas', 'base_telefonia', 'base_otros', 'comision', 'abonos', 'retencion', 'descuentos', 'pagar_usd', 'pagar_bs'] as $k) {
                    $totalesVentas[$k] = round($totalesVentas[$k] + (float) $filaVentas[$k], 2);
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
                foreach (['ventas', 'base_telefonia', 'base_otros', 'comision', 'abonos', 'retencion', 'descuentos', 'pagar_usd', 'pagar_bs'] as $k) {
                    $totalesVentas[$k] = round($totalesVentas[$k] + (float) $fila[$k], 2);
                }
            }
        }

        return [$filasVentas, $filasSt, $totalesVentas, $totalesSt];
    }

    /**
     * @return array{cedula: string, nombre: string, sede: string, grupo_tipo: string, grupo_clave: string}
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
            $f['cedula'], $f['nombre'], $f['sede'],
            $f['ventas'],
            $f['es_supervisor'] ? null : $f['base_telefonia'],
            $f['es_supervisor'] ? null : $f['base_otros'],
            $f['comision'], $f['abonos'], $f['retencion'], $f['descuentos'],
            $f['pagar_usd'], $f['pagar_bs'],
        ], $filas);

        return array_merge(
            [[
                'Cédula', 'Empleado', 'Sede', 'Ventas', 'Base telefonía', 'Base otros',
                'Comisión', 'Abonos', 'Retención', 'Desc. / préstamos', 'A pagar USD', 'A pagar BCV',
            ]],
            $cuerpo,
            [[
                'TOTALES', count($filas).' empleados', '',
                $totales['ventas'], $totales['base_telefonia'], $totales['base_otros'],
                $totales['comision'], $totales['abonos'], $totales['retencion'], $totales['descuentos'],
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
            $f['cedula'], $f['nombre'], $f['sede'],
            $f['facturas_st'], $f['egresos_058'],
            $f['comision'], $f['abonos'], $f['retencion'], $f['descuentos'],
            $f['pagar_usd'], $f['pagar_bs'],
        ], $filas);

        return array_merge(
            [[
                'Cédula', 'Empleado', 'Sede', 'Facturas ST', 'Egresos 058',
                'Comisión', 'Abonos', 'Retención', 'Desc. / préstamos', 'A pagar USD', 'A pagar BCV',
            ]],
            $cuerpo,
            [[
                'TOTALES', count($filas).' empleados', '',
                $totales['facturas_st'], $totales['egresos_058'],
                $totales['comision'], $totales['abonos'], $totales['retencion'], $totales['descuentos'],
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
            'comision' => 0.0, 'abonos' => 0.0, 'retencion' => 0.0, 'descuentos' => 0.0,
            'pagar_usd' => 0.0, 'pagar_bs' => 0.0,
        ];
    }

    /** @return array<string, float> */
    private function totalesVaciosSt(): array
    {
        return [
            'facturas_st' => 0.0, 'egresos_058' => 0.0,
            'comision' => 0.0, 'abonos' => 0.0, 'retencion' => 0.0, 'descuentos' => 0.0,
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
