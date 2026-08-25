<?php

namespace App\Http\Controllers;

use App\Services\GerencialDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GerencialController extends Controller
{
    public function dashboard(Request $request, GerencialDashboardService $gerencial): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $periodo = $gerencial->resolverPeriodo(
            $request->query('preset'),
            $request->query('desde'),
            $request->query('hasta')
        );
        $filtros = [
            'sede' => $request->query('sede', 'todas'),
            'categoria' => $request->query('categoria'),
            'vendedor' => $request->query('vendedor'),
            'producto' => $request->query('producto'),
            'preset' => $periodo['preset'],
            'desde' => $periodo['inicio']->toDateString(),
            'hasta' => $periodo['fin']->toDateString(),
            'ranking' => $request->query('ranking') === 'unidades' ? 'unidades' : 'usd',
        ];
        $data = $gerencial->resumen(
            $periodo,
            $filtros['sede'],
            $filtros['categoria'],
            $filtros['vendedor'],
            $filtros['producto'],
            $filtros['ranking']
        );

        return view('gerencial.dashboard', [
            'periodo' => $periodo,
            'filtros' => $filtros,
            'sedes' => $gerencial->sedesVentas(),
            'catalogos' => $gerencial->catalogos(),
            'total' => $data['total'],
            'porSede' => $data['por_sede'],
            'tops' => $data['tops'],
            'diario' => $data['diario'],
            'usaLineas' => $data['usa_lineas'],
        ]);
    }
}
