<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPrestamo;
use App\Services\Nomina\LoanPaymentService;
use App\Services\Nomina\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrestamoController extends Controller
{
    public function __construct(
        private LoanService $loans,
        private LoanPaymentService $payments,
    ) {
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
