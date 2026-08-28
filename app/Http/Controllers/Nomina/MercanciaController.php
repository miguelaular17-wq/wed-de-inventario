<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaDescuentoMercancia;
use App\Models\Nomina\NominaEmpleado;
use App\Services\Nomina\MerchandiseDeductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MercanciaController extends Controller
{
    public function __construct(private MerchandiseDeductionService $mercancia)
    {
    }

    public function store(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string'],
        ]);

        $this->mercancia->create($empleado, $data, auth()->id());

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'mercancia'])
            ->with('status', 'Descuento de mercancía registrado. Se restará del sueldo en esa quincena.');
    }

    public function cancelar(Request $request, NominaDescuentoMercancia $descuento): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['nullable', 'string'],
        ]);

        $this->mercancia->cancelar($descuento, $data['motivo'] ?? null);

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $descuento->empleado_id, 'tab' => 'mercancia'])
            ->with('status', 'Descuento de mercancía cancelado.');
    }
}
