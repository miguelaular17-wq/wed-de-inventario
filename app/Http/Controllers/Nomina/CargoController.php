<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaCargo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CargoController extends Controller
{
    public function index(): View
    {
        return view('nomina.cargos.index', [
            'cargos' => NominaCargo::query()->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $cargo = NominaCargo::create($data);
        NominaAuditLog::registrar('CREAR', 'cargo', $cargo->id, null, $data);

        return back()->with('status', 'Cargo creado.');
    }

    public function update(Request $request, NominaCargo $cargo): RedirectResponse
    {
        $data = $this->validated($request, $cargo->id);
        $anterior = $cargo->toArray();
        $cargo->update($data);
        NominaAuditLog::registrar('ACTUALIZAR', 'cargo', $cargo->id, $anterior, $data);

        return back()->with('status', 'Cargo actualizado.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:128', 'unique:nomina_cargos,nombre'.($id ? ','.$id : '')],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['required', 'in:ACTIVO,INACTIVO'],
        ]);
    }
}
