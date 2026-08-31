<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaDeduccion;
use App\Models\Nomina\NominaEmpleado;
use App\Services\Nomina\AjusteService;
use App\Services\Nomina\OtherDeductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeduccionController extends Controller
{
    public function __construct(
        private OtherDeductionService $deducciones,
        private AjusteService $ajustes,
    ) {
    }

    public function store(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['required', 'string', 'max:500'],
            'tipo' => ['nullable', 'in:DEDUCCION,BONIFICACION'],
            'destino' => ['nullable', 'in:NOMINA,COMISION'],
        ]);

        $data['tipo'] = $data['tipo'] ?? 'DEDUCCION';
        $data['destino'] = $data['destino'] ?? 'NOMINA';
        $this->ajustes->create($empleado, $data, auth()->id());

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'ajustes'])
            ->with('status', 'Descuento registrado. Se restará del sueldo en esa quincena.');
    }

    public function cancelar(Request $request, NominaDeduccion $deduccion): RedirectResponse
    {
        $data = $request->validate([
            'motivo' => ['nullable', 'string'],
        ]);

        $this->deducciones->cancelar($deduccion, $data['motivo'] ?? null);

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $deduccion->empleado_id, 'tab' => 'ajustes'])
            ->with('status', 'Descuento cancelado. El historial se conserva.');
    }
}
