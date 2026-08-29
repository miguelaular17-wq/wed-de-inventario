<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Services\BcvRateService;
use App\Services\Nomina\PayrollBankFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComisionController extends Controller
{
    public function __construct(
        private PayrollBankFileService $bankFile,
        private BcvRateService $bcv,
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
}
