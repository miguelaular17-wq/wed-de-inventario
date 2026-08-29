<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPrestamo;
use App\Services\Nomina\LoanDiscountPlanService;
use App\Services\Nomina\LoanPaymentService;
use App\Services\Nomina\LoanService;
use App\Services\Nomina\SalaryAdvanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrestamoController extends Controller
{
    public function __construct(
        private LoanService $loans,
        private LoanPaymentService $payments,
        private LoanDiscountPlanService $planes,
        private SalaryAdvanceService $quincenas,
    ) {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $quincena = $this->quincenas->quincenaDe(now());
        $deudores = $this->planes->deudores($q !== '' ? $q : null);

        return view('nomina.prestamos.index', [
            'q' => $q,
            'quincena' => $quincena,
            'deudores' => $deudores,
            'planes' => $this->planes->planesDeQuincena($quincena),
            'kpis' => $this->planes->kpis($quincena, $deudores),
            'kpisGlobales' => $this->loans->kpis(),
        ]);
    }

    public function programar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string'],
            'descuentos' => ['sometimes', 'array'],
            'descuentos.*.aplicar' => ['sometimes'],
            'descuentos.*.cuota_id' => ['required_with:descuentos.*.aplicar', 'integer'],
            'descuentos.*.monto' => ['nullable', 'numeric', 'min:0'],
            'descuentos.*.destino' => ['nullable', 'in:NOMINA,COMISION'],
        ]);

        $quincena = $this->quincenas->quincenaDe(now());
        $guardados = $this->planes->guardarParaQuincena(
            array_values($data['descuentos'] ?? []),
            $quincena,
            auth()->id()
        );

        return redirect()
            ->route('nomina.prestamos.index', ['q' => $data['q'] ?? null])
            ->with('status', $guardados > 0
                ? "Quedaron {$guardados} cuota(s) para descontar en {$quincena['etiqueta']}. Se verán en la ficha y al calcular la nómina."
                : 'No quedó ninguna cuota marcada para esta quincena.');
    }

    public function store(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto_original' => ['required', 'numeric', 'min:0.01'],
            'numero_cuotas' => ['required', 'integer', 'min:1', 'max:120'],
            'frecuencia' => ['required', 'in:SEMANAL,QUINCENAL,MENSUAL'],
            'fecha_inicio' => ['required', 'date'],
            'motivo' => ['nullable', 'string'],
        ]);

        $this->loans->create($empleado, $data, auth()->id());

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'prestamos'])
            ->with('status', 'Préstamo registrado y calendario de cuotas generado.');
    }

    public function abonar(Request $request, NominaPrestamo $prestamo): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'tipo' => ['required', 'in:DESCUENTO_NOMINA,EFECTIVO,TRANSFERENCIA,EXTRAORDINARIO,AJUSTE'],
            'cuota_id' => ['nullable', 'integer'],
            'observacion' => ['nullable', 'string'],
        ]);

        $this->payments->registrarAbono($prestamo, $data, auth()->id());

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $prestamo->empleado_id, 'tab' => 'prestamos'])
            ->with('status', 'Pago a préstamo registrado.');
    }

    public function cancelar(Request $request, NominaPrestamo $prestamo): RedirectResponse
    {
        $data = $request->validate([
            'observacion' => ['nullable', 'string'],
        ]);

        $this->loans->cancelar($prestamo, $data['observacion'] ?? null);

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $prestamo->empleado_id, 'tab' => 'prestamos'])
            ->with('status', 'Préstamo cancelado. El historial se conserva.');
    }
}
