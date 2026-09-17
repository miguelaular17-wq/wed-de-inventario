<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaEmpleado;
use App\Services\Nomina\FaltanteCajaService;
use App\Services\Nomina\QuincenaMovimientosExcelService;
use App\Services\Nomina\SalaryAdvanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class FaltanteCajaController extends Controller
{
    public function __construct(
        private FaltanteCajaService $faltantes,
        private SalaryAdvanceService $quincenas,
        private QuincenaMovimientosExcelService $excelQuincena,
    ) {
    }

    public function index(Request $request): View
    {
        $fecha = $this->fechaConsulta($request);
        $q = trim((string) $request->query('q', ''));
        $cajeras = $this->faltantes->cajeras($q !== '' ? $q : null);
        $cuentasPorEmpleado = $cajeras->mapWithKeys(
            fn (NominaEmpleado $empleado) => [$empleado->id => $this->faltantes->resumenCuenta($empleado)]
        );
        $delDia = $this->faltantes->delDia($fecha);
        $historialDia = $this->faltantes->historialDelDiaAgrupado($fecha);
        $quincena = $this->quincenas->quincenaDe($fecha);

        return view('nomina.faltante_caja.index', [
            'fecha' => $fecha->toDateString(),
            'q' => $q,
            'cajeras' => $cajeras,
            'cuentasPorEmpleado' => $cuentasPorEmpleado,
            'historialDia' => $historialDia,
            'quincena' => $quincena,
            'kpis' => $this->faltantes->kpis($fecha),
        ]);
    }

    public function exportarExcel(Request $request): Response
    {
        $quincena = $this->excelQuincena->resolverDesdeRequest(
            $request->query('inicio'),
            $request->query('fecha', $this->fechaConsulta($request)->toDateString())
        );

        return $this->excelQuincena->descargar('faltante_caja', $quincena);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empleado_id' => ['required', 'integer', 'exists:nomina_empleados,id'],
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string'],
        ]);

        $empleado = NominaEmpleado::query()->findOrFail($data['empleado_id']);
        $this->faltantes->create($empleado, $data, auth()->id());

        return redirect()
            ->route('nomina.faltante_caja.index', [
                'fecha' => $data['fecha'],
                'q' => $data['q'] ?? '',
            ])
            ->with('status', 'Faltante sumado a la cuenta de '.$empleado->nombre().'. Luego indica cuánto descontar.');
    }

    public function descontarCuenta(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empleado_id' => ['required', 'integer', 'exists:nomina_empleados,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'accion' => ['required', 'in:NOMINA,COMISION,NO_DESCONTAR,DESCONTAR'],
            'fecha' => ['nullable', 'date'],
            'fecha_descuento' => ['nullable', 'date'],
            'q' => ['nullable', 'string'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $empleado = NominaEmpleado::query()->findOrFail($data['empleado_id']);
        $monto = round((float) $data['monto'], 2);
        $fechaDescuento = $data['fecha_descuento'] ?? $data['fecha'] ?? now()->toDateString();
        $accion = $data['accion'] === 'DESCONTAR' ? 'COMISION' : $data['accion'];

        if ($accion === 'NO_DESCONTAR') {
            $this->faltantes->noDescontarDeCuenta(
                $empleado,
                $monto,
                auth()->id(),
                $data['motivo'] ?? null,
                $fechaDescuento
            );
            $msg = '$'.number_format($monto, 2).' salió de la cuenta sin descontar (historial).';
        } else {
            $destino = $accion === 'NOMINA'
                ? NominaComisionDescuento::DESTINO_NOMINA
                : NominaComisionDescuento::DESTINO_COMISION;
            $this->faltantes->descontarDeCuenta(
                $empleado,
                $monto,
                auth()->id(),
                $data['motivo'] ?? null,
                $fechaDescuento,
                $destino
            );
            $donde = $destino === NominaComisionDescuento::DESTINO_NOMINA
                ? 'nómina'
                : 'comisión';
            $msg = '$'.number_format($monto, 2).' de '.$empleado->nombre().' se descontará de '.$donde.' (fecha '.$fechaDescuento.').';
        }

        return redirect()
            ->route('nomina.faltante_caja.index', [
                'fecha' => $data['fecha'] ?? now()->toDateString(),
                'q' => $data['q'] ?? '',
            ])
            ->with('status', $msg);
    }

    public function decidir(Request $request, NominaComisionDescuento $descuento): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:DESCONTAR,NO_DESCONTAR,PENDIENTE'],
            'q' => ['nullable', 'string'],
            'fecha' => ['nullable', 'date'],
        ]);

        $this->faltantes->decidir($descuento, $data['decision'], auth()->id());

        $msg = match ($data['decision']) {
            'DESCONTAR' => 'Se descontará en comisión (o nómina si no genera comisión) al liquidar la quincena.',
            'NO_DESCONTAR' => 'Queda en historial sin descontar.',
            default => 'Decisión marcada como pendiente.',
        };

        return redirect()
            ->route('nomina.faltante_caja.index', [
                'fecha' => $data['fecha'] ?? $descuento->fecha?->toDateString() ?: now()->toDateString(),
                'q' => $data['q'] ?? '',
            ])
            ->with('status', $msg);
    }

    public function cancelar(Request $request, NominaComisionDescuento $descuento): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $this->faltantes->cancelar($descuento, $data['motivo'] ?? null);

        return redirect()
            ->route('nomina.faltante_caja.index', [
                'fecha' => $descuento->fecha?->toDateString() ?: now()->toDateString(),
                'q' => $request->input('q'),
            ])
            ->with('status', 'Faltante de caja cancelado.');
    }

    private function fechaConsulta(Request $request): Carbon
    {
        $raw = $request->query('fecha');
        try {
            return $raw ? Carbon::parse($raw)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}
