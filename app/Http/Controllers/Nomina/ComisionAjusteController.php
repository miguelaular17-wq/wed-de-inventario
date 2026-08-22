<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaComisionAbono;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaEmpleado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ComisionAjusteController extends Controller
{
    public function storeAbono(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        NominaComisionAbono::create([
            'empleado_id' => $empleado->id,
            'fecha' => $data['fecha'],
            'monto' => round((float) $data['monto'], 2),
            'motivo' => $data['motivo'] ?? null,
            'estado' => 'PENDIENTE',
            'created_by' => auth()->id(),
        ]);

        return back()->with('status', 'Abono de comisión registrado. Se aplica al liquidar esa quincena.');
    }

    public function storeDescuento(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'in:'.implode(',', NominaComisionDescuento::TIPOS)],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        NominaComisionDescuento::create([
            'empleado_id' => $empleado->id,
            'fecha' => $data['fecha'],
            'tipo' => $data['tipo'] === 'PRESTAMO' ? 'OTRO' : $data['tipo'],
            'monto' => round((float) $data['monto'], 2),
            'motivo' => $data['motivo'] ?? null,
            'estado' => 'PENDIENTE',
            'created_by' => auth()->id(),
        ]);

        return back()->with('status', 'Descuento de comisión registrado. Se aplica al liquidar esa quincena.');
    }
}
