<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaEmpresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmpresaNominaController extends Controller
{
    public function index(): View
    {
        return view('nomina.empresas.index', [
            'empresas' => NominaEmpresa::query()->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $empresa = NominaEmpresa::create($data);
        NominaAuditLog::registrar('CREAR', 'empresa', $empresa->id, null, $data);

        return back()->with('status', 'Empresa creada.');
    }

    public function update(Request $request, NominaEmpresa $empresa): RedirectResponse
    {
        $data = $this->validated($request, $empresa->id);
        $anterior = $empresa->toArray();
        $empresa->update($data);
        NominaAuditLog::registrar('ACTUALIZAR', 'empresa', $empresa->id, $anterior, $data);

        return back()->with('status', 'Empresa actualizada.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $request->merge([
            'codigo' => strtoupper(preg_replace('/[\s.-]+/', '', (string) $request->input('codigo')) ?? ''),
        ]);

        return $request->validate([
            'codigo' => ['required', 'string', 'max:32', 'unique:nomina_empresas,codigo'.($id ? ','.$id : '')],
            'nombre' => ['required', 'string', 'max:160'],
            'estado' => ['required', 'in:ACTIVO,INACTIVO'],
        ]);
    }
}
