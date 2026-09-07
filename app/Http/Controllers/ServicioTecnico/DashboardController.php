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
        $metricas = $this->dashboard->metricas(
            $user,
            $request->query('sede'),
            $request->query('desde'),
            $request->query('hasta'),
        );

        return view('servicio.dashboard', [
            'metricas' => $metricas,
            'sedes' => config('inventario.sedes_locales'),
            'puedeFiltrarSede' => ! $user->scopesServicioToOwnSede(),
            'filtros' => [
                'sede' => $request->query('sede'),
                'desde' => $metricas['rango']['desde'] ?? $request->query('desde'),
                'hasta' => $metricas['rango']['hasta'] ?? $request->query('hasta'),
            ],
        ]);
    }
}
