<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use Illuminate\View\View;

class ComisionController extends Controller
{
    public function index(): View
    {
        $periodos = NominaPeriodo::query()
            ->withCount('liquidacionesComision')
            ->withSum('liquidacionesComision', 'total_pagar')
            ->withSum('liquidacionesComision', 'comision_total')
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
            ->with('empleado.cliente')
            ->orderBy('id')
            ->get();

        return view('nomina.comisiones.show', [
            'periodo' => $periodo,
            'liquidaciones' => $liquidaciones,
        ]);
    }
}
