<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaComisionDiferencial;
use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaPeriodo;
use App\Services\BcvRateService;
use App\Services\Nomina\CommissionDifferentialService;
use App\Support\SimpleXlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComisionDiferencialController extends Controller
{
    public function __construct(
        private CommissionDifferentialService $diferenciales,
        private BcvRateService $bcv,
    ) {
    }

    public function index(NominaPeriodo $periodo): View|RedirectResponse
    {
        if ($redirect = $this->exigirCalculado($periodo)) {
            return $redirect;
        }

        $diferenciales = NominaComisionDiferencial::query()
            ->where('periodo_id', $periodo->id)
            ->with(['lineas.empleado.cliente', 'creador'])
            ->orderByDesc('id')
            ->get();

        return view('nomina.comisiones.diferenciales.index', [
            'periodo' => $periodo,
            'diferenciales' => $diferenciales,
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
            'tasa_bcv' => $this->bcv->getRateForToday(),
            'config' => $this->diferenciales->configActual(),
            'seleccion' => [],
        ]);
    }

    public function store(Request $request, NominaPeriodo $periodo): View|RedirectResponse
    {
        if ($redirect = $this->exigirCalculado($periodo)) {
            return $redirect;
        }

        $reglasPct = [];
        foreach (array_keys(CommissionDifferentialService::PORCENTAJES) as $clave) {
            $reglasPct['config.'.$clave] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }
        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
            'fecha_pago' => ['required', 'date'],
            'tasa_bcv' => ['required', 'numeric', 'gt:0'],
            'empleados' => ['nullable', 'array'],
            'empleados.*' => ['integer'],
            'pagado' => ['nullable', 'array'],
            'accion' => ['nullable', 'in:previsualizar,guardar'],
        ] + $reglasPct, [
            'motivo.required' => 'Escribe el motivo del diferencial.',
            'tasa_bcv.gt' => 'La tasa BCV debe ser mayor a cero.',
        ]);

        $config = $this->diferenciales->normalizarConfig($data['config'] ?? []);
        $seleccion = [];
        foreach ($data['empleados'] ?? [] as $empleadoId) {
            $seleccion[(int) $empleadoId] = $request->input('pagado.'.$empleadoId) === 'no';
        }

        if (($data['accion'] ?? 'guardar') === 'previsualizar') {
            return $this->formulario($periodo, [
                'motivo' => $data['motivo'],
                'fecha_pago' => $data['fecha_pago'],
                'tasa_bcv' => (float) $data['tasa_bcv'],
                'config' => $config,
                'seleccion' => $seleccion,
            ], $this->diferenciales->calcularSeleccion($periodo, $config, $seleccion));
        }

        $diferencial = $this->diferenciales->crear(
            $periodo,
            $data['motivo'],
            $data['fecha_pago'],
            (float) $data['tasa_bcv'],
            $config,
            $seleccion,
            auth()->id()
        );

        return redirect()
            ->route('nomina.comisiones.diferenciales.show', [$periodo, $diferencial])
            ->with('status', 'Diferencial guardado. Descarga el TXT del banco por empresa.');
    }

    public function show(NominaPeriodo $periodo, NominaComisionDiferencial $diferencial): View
    {
        abort_unless((int) $diferencial->periodo_id === (int) $periodo->id, 404);

        $diferencial->load(['lineas.empleado.cliente', 'lineas.empleado.empresa', 'lineas.empleado.sedeCatalogo', 'creador']);

        return view('nomina.comisiones.diferenciales.show', [
            'periodo' => $periodo,
            'diferencial' => $diferencial,
            'lineas' => $diferencial->lineas->sortBy(fn ($l) => mb_strtolower($l->empleado?->nombre() ?? ''))->values(),
            'bancoPorEmpresa' => $this->diferenciales->resumenPorEmpresa($diferencial),
            'configActual' => $this->diferenciales->configActual(),
        ]);
    }

    public function reporte(Request $request, NominaPeriodo $periodo, NominaComisionDiferencial $diferencial): Response
    {
        abort_unless((int) $diferencial->periodo_id === (int) $periodo->id, 404);

        $diferencial->load(['lineas.empleado.cliente', 'lineas.empleado.empresa', 'lineas.empleado.sedeCatalogo', 'lineas.empleado.cargoCatalogo', 'creador']);
        $tasa = (float) $diferencial->tasa_bcv;
        $filas = $diferencial->lineas
            ->sortBy(fn ($l) => mb_strtolower($l->empleado?->nombre() ?? ''))
            ->map(fn ($l) => [
                'cedula' => $l->empleado?->cedula() ?? '',
                'nombre' => $l->empleado?->nombre() ?? '—',
                'empresa' => $l->empleado?->empresa?->codigo ?? 'Sin empresa',
                'sede' => $l->empleado?->nombreSede() ?? '—',
                'situacion' => $l->no_pagado ? 'No se le pagó' : 'Ya se le pagó',
                'comision_original' => (float) $l->comision_original,
                'comision_ajustada' => (float) $l->comision_ajustada,
                'abonos' => (float) $l->abonos,
                'retencion_ajustada' => (float) $l->retencion_ajustada,
                'descuentos' => (float) $l->descuentos,
                'total_ajustado' => (float) $l->total_ajustado,
                'pagado' => (float) $l->pagado,
                'diferencia' => (float) $l->diferencia,
                'diferencia_bs' => round(max(0, (float) $l->diferencia) * $tasa, 2),
            ])
            ->values()
            ->all();

        $totales = [];
        foreach (['comision_original', 'comision_ajustada', 'abonos', 'retencion_ajustada', 'descuentos', 'total_ajustado', 'pagado', 'diferencia', 'diferencia_bs'] as $campo) {
            $totales[$campo] = round(array_sum(array_column($filas, $campo)), 2);
        }
        $totales['a_pagar'] = round(array_sum(array_filter(array_column($filas, 'diferencia'), fn ($v) => $v > 0)), 2);

        $nombreBase = 'diferencial_comisiones_'.$periodo->id.'_'.$diferencial->id.'_'.$diferencial->fecha_pago->format('Ymd');

        if ($request->query('formato') === 'xlsx') {
            $hoja = [[
                'Cédula', 'Empleado', 'Empresa', 'Sede / área', 'Situación',
                'Comisión quincena', 'Comisión ajustada', 'Bonos', 'Retención', 'Desc. / préstamos',
                'Total ajustado', 'Ya pagado', 'Diferencia USD', 'Diferencia Bs',
            ]];
            foreach ($filas as $f) {
                $hoja[] = [
                    $f['cedula'], $f['nombre'], $f['empresa'], $f['sede'], $f['situacion'],
                    $f['comision_original'], $f['comision_ajustada'], $f['abonos'], $f['retencion_ajustada'], $f['descuentos'],
                    $f['total_ajustado'], $f['pagado'], $f['diferencia'], $f['diferencia_bs'],
                ];
            }
            $hoja[] = [
                'TOTALES', count($filas).' empleados', '', '', '',
                $totales['comision_original'], $totales['comision_ajustada'], $totales['abonos'], $totales['retencion_ajustada'], $totales['descuentos'],
                $totales['total_ajustado'], $totales['pagado'], $totales['diferencia'], $totales['diferencia_bs'],
            ];
            $hoja[] = ['A PAGAR (TXT)', '', '', '', '', null, null, null, null, null, null, null, $totales['a_pagar'], $totales['diferencia_bs']];

            return response(SimpleXlsxWriter::toString(['Diferencial' => $hoja]), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$nombreBase.'.xlsx"',
            ]);
        }

        $logo = is_file(public_path('logo.png'))
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('logo.png')))
            : null;

        return Pdf::loadView('nomina.comisiones.diferenciales.pdf', [
            'periodo' => $periodo,
            'diferencial' => $diferencial,
            'filas' => $filas,
            'totales' => $totales,
            'tasa' => $tasa,
            'configActual' => $this->diferenciales->configActual(),
            'logoPath' => $logo,
        ])->setPaper('a4', 'landscape')->download($nombreBase.'.pdf');
    }

    public function destroy(NominaPeriodo $periodo, NominaComisionDiferencial $diferencial): RedirectResponse
    {
        abort_unless((int) $diferencial->periodo_id === (int) $periodo->id, 404);

        $diferencial->delete();

        return redirect()
            ->route('nomina.comisiones.diferenciales.index', $periodo)
            ->with('status', 'Diferencial eliminado.');
    }

    public function banco(NominaPeriodo $periodo, NominaComisionDiferencial $diferencial, NominaEmpresa $empresa): StreamedResponse
    {
        abort_unless((int) $diferencial->periodo_id === (int) $periodo->id, 404);

        $diferencial->load('lineas.empleado.cliente');
        $contenido = $this->diferenciales->generarTxt($diferencial, $empresa);
        $nombre = $this->diferenciales->nombreArchivo($diferencial, $empresa);

        return response()->streamDownload(function () use ($contenido) {
            echo $contenido;
        }, $nombre, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{motivo:string,fecha_pago:string,tasa_bcv:float,config:array<string,float>,seleccion:array<int,bool>}  $valores
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>|null  $vistaPrevia
     */
    private function formulario(NominaPeriodo $periodo, array $valores, $vistaPrevia = null): View
    {
        $liquidaciones = $this->diferenciales->liquidaciones($periodo);

        return view('nomina.comisiones.diferenciales.create', [
            'periodo' => $periodo,
            'liquidaciones' => $liquidaciones,
            'configActual' => $this->diferenciales->configActual(),
            'pctQuincena' => $this->diferenciales->porcentajesQuincena($liquidaciones),
            'valores' => $valores,
            'vistaPrevia' => $vistaPrevia,
        ]);
    }

    private function exigirCalculado(NominaPeriodo $periodo): ?RedirectResponse
    {
        if ($periodo->estado !== NominaPeriodo::ABIERTO) {
            return null;
        }

        return redirect()
            ->route('nomina.comisiones.show', $periodo)
            ->withErrors(['periodo' => 'Calcula la nómina antes de generar un diferencial de comisiones.']);
    }
}
