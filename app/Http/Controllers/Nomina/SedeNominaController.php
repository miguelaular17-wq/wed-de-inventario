<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaSede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SedeNominaController extends Controller
{
    public function index(): View
    {
        return view('nomina.sedes.index', [
            'sedes' => NominaSede::query()->ordenCatalogo()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $sede = NominaSede::create($data);
        NominaAuditLog::registrar('CREAR', 'sede', $sede->id, null, $data);

        return back()->with('status', 'Sede creada.');
    }

    public function update(Request $request, NominaSede $sede): RedirectResponse
    {
        $data = $this->validated($request, $sede->id);
        $anterior = $sede->toArray();
        $sede->update($data);
        NominaAuditLog::registrar('ACTUALIZAR', 'sede', $sede->id, $anterior, $data);

        return back()->with('status', 'Sede actualizada.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:128'],
            'codigo' => ['required', 'string', 'max:32', 'unique:nomina_sedes,codigo'.($id ? ','.$id : '')],
            'direccion' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', 'in:SEDE,AREA'],
            'estado' => ['required', 'in:ACTIVO,INACTIVO'],
            'excluir_comision' => ['nullable', 'boolean'],
        ]) + ['excluir_comision' => $request->boolean('excluir_comision')];
    }
}
