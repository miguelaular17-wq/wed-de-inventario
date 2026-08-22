<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaCargo;
use App\Models\Nomina\NominaSede;
use App\Services\Nomina\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizacionController extends Controller
{
    public function __construct(private OrganizationService $organization)
    {
    }

    public function index(Request $request): View
    {
        $sedeId = $request->integer('sede_id') ?: null;
        $supervisorId = $request->integer('supervisor_id') ?: null;
        $cargoId = $request->integer('cargo_id') ?: null;

        return view('nomina.organizacion.index', [
            'arbol' => $this->organization->tree($sedeId, $supervisorId, $cargoId),
            'sedes' => NominaSede::query()->ordenCatalogo()->get(),
            'cargos' => NominaCargo::query()->orderBy('nombre')->get(),
            'supervisores' => $this->organization->supervisoresDisponibles(),
            'filters' => compact('sedeId', 'supervisorId', 'cargoId'),
        ]);
    }
}
