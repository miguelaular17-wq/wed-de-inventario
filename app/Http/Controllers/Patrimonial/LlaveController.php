<?php
namespace App\Http\Controllers\Patrimonial;

use App\Http\Controllers\Controller;
use App\Models\Patrimonial\Llave;
use App\Models\Patrimonial\Propiedad;
use Illuminate\Http\Request;

class LlaveController extends Controller
{
    public function index(Request $request)
    {
        $propiedadId = $request->get('propiedad_id');
        $query = Llave::with('propiedad')->orderBy('propiedad_id')->orderBy('descripcion');
        if ($propiedadId) $query->where('propiedad_id', $propiedadId);

        $llaves      = $query->paginate(30)->withQueryString();
        $propiedades = Propiedad::orderBy('nombre')->get(['id', 'nombre', 'tipo']);

        return view('patrimonial.llaves.index', compact('llaves', 'propiedades', 'propiedadId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'propiedad_id'    => 'required|exists:pat_propiedades,id',
            'descripcion'     => 'required|string|max:128',
            'ubicacion_actual'=> 'nullable|string|max:256',
            'responsable'     => 'nullable|string|max:256',
            'fecha_entrega'   => 'nullable|date',
            'fecha_devolucion'=> 'nullable|date',
            'observaciones'   => 'nullable|string',
        ]);
        Llave::create($data);
        return back()->with('status', "✅ Llave '{$data['descripcion']}' registrada.");
    }

    public function update(Request $request, Llave $llave)
    {
        $data = $request->validate([
            'descripcion'     => 'required|string|max:128',
            'ubicacion_actual'=> 'nullable|string|max:256',
            'responsable'     => 'nullable|string|max:256',
            'fecha_entrega'   => 'nullable|date',
            'fecha_devolucion'=> 'nullable|date',
            'observaciones'   => 'nullable|string',
        ]);
        $llave->update($data);
        return back()->with('status', '✅ Llave actualizada.');
    }

    public function destroy(Llave $llave)
    {
        $llave->delete();
        return back()->with('status', '🗑️ Llave eliminada.');
    }
}
