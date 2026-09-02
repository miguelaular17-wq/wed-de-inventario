<?php

namespace App\Http\Controllers\ServicioTecnico;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ServicioTecnico\StDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StDashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('servicio.dashboard', [
            'metricas' => $this->dashboard->metricas(
                $user,
                $request->query('sede'),
                $request->query('desde'),
                $request->query('hasta'),
            ),
            'sedes' => config('inventario.sedes_locales'),
            'puedeFiltrarSede' => ! $user->scopesServicioToOwnSede(),
            'filtros' => [
                'sede' => $request->query('sede'),
                'desde' => $request->query('desde'),
                'hasta' => $request->query('hasta'),
            ],
        ]);
    }
}
