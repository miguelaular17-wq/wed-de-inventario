<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaPagoManual;
use App\Services\Nomina\PagoManualNominaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PagoManualNominaController extends Controller
{
    public function __construct(
        private PagoManualNominaService $pagos,
    ) {
    }

    public function index(Request $request): View
    {
        $estado = $request->query('estado', NominaPagoManual::PENDIENTE);
        $tipo = $request->query('tipo');
        $periodoId = $request->query('periodo_id');

        $query = NominaPagoManual::query()
            ->with(['empleado.cliente', 'periodo', 'enviadoPor'])
            ->orderByDesc('id');

        if (in_array($estado, [NominaPagoManual::PENDIENTE, NominaPagoManual::PAGADO], true)) {
            $query->where('estado', $estado);
        }

        if (in_array($tipo, [NominaPagoManual::TIPO_NOMINA, NominaPagoManual::TIPO_COMISION], true)) {
            $query->where('tipo', $tipo);
        }

        if ($periodoId) {
            $query->where('periodo_id', (int) $periodoId);
        }

        $items = $query->paginate(40)->withQueryString();

        return view('finanzas.pagos_manual_nomina.index', [
            'items' => $items,
            'estado' => $estado,
            'tipo' => $tipo,
            'periodoId' => $periodoId,
            'totalPendienteUsd' => round((float) NominaPagoManual::query()
                ->where('estado', NominaPagoManual::PENDIENTE)
                ->sum('monto_usd'), 2),
        ]);
    }

    public function pagar(NominaPagoManual $pago): RedirectResponse
    {
        if (! $pago->isPendiente()) {
            return redirect()
                ->route('finanzas.pagos_manual_nomina.index')
                ->withErrors(['pago' => 'Ese pago ya no está pendiente.']);
        }

        return redirect()->to($this->pagos->urlPagar($pago));
    }
}
