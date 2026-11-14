<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaTasaDiferencial;
use App\Services\BcvRateService;
use App\Services\Nomina\PayrollRateDifferentialService;
use App\Support\SimpleXlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PeriodoDiferencialTasaController extends Controller
{
    public function __construct(
        private PayrollRateDifferentialService $diferenciales,
        private BcvRateService $bcv,
    ) {
    }

    public function index(NominaPeriodo $periodo): View|RedirectResponse
    {
        if ($redirect = $this->exigirCalculado($periodo)) {
            return $redirect;
        }

        return view('nomina.periodos.diferenciales_tasa.index', [
            'periodo' => $periodo,
            'diferenciales' => NominaTasaDiferencial::query()
                ->where('periodo_id', $periodo->id)
                ->with(['lineas', 'creador'])
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function create(NominaPeriodo $periodo): View|RedirectResponse
    {
        if ($redirect = $this->exigirCalculado($periodo)) {
            return $redirect;
        }

        return $this->formulario($periodo, [
            'motivo' => '',
            'fecha_pago' => now()->toDateString(),
            'tasa_pagada' => $this->bcv->tasaParaPeriodo($periodo),
            'tasa_nueva' => $this->bcv->getRateForToday(),
            'seleccion' => null,
        ]);
    }

    public function store(Request $request, NominaPeriodo $periodo): View|RedirectResponse
    {
        if ($redirect = $this->exigirCalculado($periodo)) {
            return $redirect;
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
            'fecha_pago' => ['required', 'date'],
            'tasa_pagada' => ['required', 'numeric', 'gt:0'],
            'tasa_nueva' => ['required', 'numeric', 'gt:0'],
            'empleados' => ['nullable', 'array'],
            'empleados.*' => ['integer'],
            'pagado' => ['nullable', 'array'],
            'accion' => ['nullable', 'in:previsualizar,guardar'],
        ], [
            'motivo.required' => 'Escribe el motivo del diferencial.',
            'tasa_pagada.gt' => 'La tasa con que se pagó debe ser mayor a cero.',
            'tasa_nueva.gt' => 'La tasa nueva debe ser mayor a cero.',
        ]);

        $tasaPagada = round((float) $data['tasa_pagada'], 4);
        $tasaNueva = round((float) $data['tasa_nueva'], 4);
        $seleccion = [];
        foreach ($data['empleados'] ?? [] as $empleadoId) {
            $seleccion[(int) $empleadoId] = $request->input('pagado.'.$empleadoId) !== 'si';
        }

        $this->diferenciales->validarTasas($tasaPagada, $tasaNueva);

        if (($data['accion'] ?? 'guardar') === 'previsualizar') {
            return $this->formulario($periodo, [
                'motivo' => $data['motivo'],
                'fecha_pago' => $data['fecha_pago'],
                'tasa_pagada' => $tasaPagada,
                'tasa_nueva' => $tasaNueva,
                'seleccion' => $seleccion,
            ], $this->diferenciales->calcular($periodo, $tasaPagada, $tasaNueva, $seleccion));
        }

        $diferencial = $this->diferenciales->crear(
            $periodo,
            $data['motivo'],
            $data['fecha_pago'],
            $tasaPagada,
            $tasaNueva,
            $seleccion,
            auth()->id()
        );

        return redirect()
            ->route('nomina.periodos.diferenciales_tasa.show', [$periodo, $diferencial])
            ->with('status', 'Diferencial por tasa guardado. Descarga el TXT del banco por empresa.');
    }

    public function show(NominaPeriodo $periodo, NominaTasaDiferencial $diferencial): View
    {
        abort_unless((int) $diferencial->periodo_id === (int) $periodo->id, 404);

        $diferencial->load(['lineas.empleado.cliente', 'lineas.empleado.empresa', 'lineas.empleado.sedeCatalogo', 'creador']);

        return view('nomina.periodos.diferenciales_tasa.show', [
            'periodo' => $periodo,
            'diferencial' => $diferencial,
            'lineas' => $this->ordenadas($diferencial),
            'bancoPorEmpresa' => $this->diferenciales->resumenPorEmpresa($diferencial),
        ]);
    }

    public function reporte(Request $request, NominaPeriodo $periodo, NominaTasaDiferencial $diferencial): Response
    {
        abort_unless((int) $diferencial->periodo_id === (int) $periodo->id, 404);

        $diferencial->load(['lineas.empleado.cliente', 'lineas.empleado.empresa', 'lineas.empleado.sedeCatalogo', 'creador']);
        $filas = $this->ordenadas($diferencial)
            ->map(fn ($l) => [
                'cedula' => $l->empleado?->cedula() ?? '',
                'nombre' => $l->empleado?->nombre() ?? '—',
                'empresa' => $l->empleado?->empresa?->codigo ?? 'Sin empresa',
                'sede' => $l->empleado?->nombreSede() ?? '—',
                'situacion' => $l->no_pagado ? 'No se le canceló la diferencia' : 'Ya se le pagó la diferencia',
                'total_usd' => (float) $l->total_usd,
                'bs_pagado' => (float) $l->bs_pagado,
                'bs_nuevo' => (float) $l->bs_nuevo,
                'diferencia_bs' => (float) $l->diferencia_bs,
                'a_pagar_bs' => $l->aPagarBs(),
            ])
            ->all();

        $totales = [];
        foreach (['total_usd', 'bs_pagado', 'bs_nuevo', 'diferencia_bs', 'a_pagar_bs'] as $campo) {
            $totales[$campo] = round(array_sum(array_column($filas, $campo)), 2);
        }

        $tasaPagada = (float) $diferencial->tasa_pagada;
        $tasaNueva = (float) $diferencial->tasa_nueva;
        $nombreBase = 'diferencial_tasa_nomina_'.$periodo->id.'_'.$diferencial->id.'_'.$diferencial->fecha_pago->format('Ymd');

        if ($request->query('formato') === 'xlsx') {
            $hoja = [[
                'Cédula', 'Empleado', 'Empresa', 'Sede / área', 'Situación', 'Nómina USD',
                'Pagado Bs (tasa '.number_format($tasaPagada, 4, ',', '.').')',
                'Correspondía Bs (tasa '.number_format($tasaNueva, 4, ',', '.').')',
                'Diferencia Bs', 'A pagar Bs (TXT)',
            ]];
            foreach ($filas as $f) {
                $hoja[] = [$f['cedula'], $f['nombre'], $f['empresa'], $f['sede'], $f['situacion'], $f['total_usd'], $f['bs_pagado'], $f['bs_nuevo'], $f['diferencia_bs'], $f['a_pagar_bs']];
            }
            $hoja[] = ['TOTALES', count($filas).' empleados', '', '', '', $totales['total_usd'], $totales['bs_pagado'], $totales['bs_nuevo'], $totales['diferencia_bs'], $totales['a_pagar_bs']];

            return response(SimpleXlsxWriter::toString(['Diferencial tasa' => $hoja]), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$nombreBase.'.xlsx"',
            ]);
        }

        $logo = is_file(public_path('logo.png'))
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('logo.png')))
            : null;

        return Pdf::loadView('nomina.periodos.diferenciales_tasa.pdf', [
            'periodo' => $periodo,
            'diferencial' => $diferencial,
            'filas' => $filas,
            'totales' => $totales,
            'logoPath' => $logo,
        ])->setPaper('a4', 'landscape')->download($nombreBase.'.pdf');
    }

    public function destroy(NominaPeriodo $periodo, NominaTasaDiferencial $diferencial): RedirectResponse
    {
        abort_unless((int) $diferencial->periodo_id === (int) $periodo->id, 404);

        $diferencial->delete();

        return redirect()
            ->route('nomina.periodos.diferenciales_tasa.index', $periodo)
            ->with('status', 'Diferencial por tasa eliminado.');
    }

    public function banco(NominaPeriodo $periodo, NominaTasaDiferencial $diferencial, NominaEmpresa $empresa): StreamedResponse
    {
        abort_unless((int) $diferencial->periodo_id === (int) $periodo->id, 404);

        $diferencial->load('lineas.empleado.cliente');
        $contenido = $this->diferenciales->generarTxt($diferencial, $empresa);

        return response()->streamDownload(function () use ($contenido) {
            echo $contenido;
        }, $this->diferenciales->nombreArchivo($diferencial, $empresa), [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{motivo:string,fecha_pago:string,tasa_pagada:float,tasa_nueva:float,seleccion:array<int,bool>|null}  $valores
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>|null  $vistaPrevia
     */
    private function formulario(NominaPeriodo $periodo, array $valores, $vistaPrevia = null): View
    {
        return view('nomina.periodos.diferenciales_tasa.create', [
            'periodo' => $periodo,
            'candidatos' => $this->diferenciales->candidatos($periodo),
            'tasaPeriodo' => $this->bcv->tasaParaPeriodo($periodo),
            'valores' => $valores,
            'vistaPrevia' => $vistaPrevia,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\Nomina\NominaTasaDiferencialLinea> */
    private function ordenadas(NominaTasaDiferencial $diferencial)
    {
        return $diferencial->lineas
            ->sortBy(fn ($l) => mb_strtolower($l->empleado?->nombre() ?? ''))
            ->values();
    }

    private function exigirCalculado(NominaPeriodo $periodo): ?RedirectResponse
    {
        if ($periodo->estado !== NominaPeriodo::ABIERTO) {
            return null;
        }

        return redirect()
            ->route('nomina.periodos.show', $periodo)
            ->withErrors(['periodo' => 'Calcula la nómina antes de generar un diferencial por tasa.']);
    }
}
