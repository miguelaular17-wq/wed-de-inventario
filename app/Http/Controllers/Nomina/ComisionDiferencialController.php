<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaComisionDiferencial;
use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaPeriodo;
use App\Services\BcvRateService;
use App\Services\Nomina\CommissionDifferentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
