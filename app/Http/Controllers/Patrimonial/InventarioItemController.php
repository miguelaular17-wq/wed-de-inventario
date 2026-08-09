<?php
namespace App\Http\Controllers\Patrimonial;

use App\Http\Controllers\Controller;
use App\Models\Patrimonial\InventarioItem;
use App\Models\Patrimonial\Propiedad;
use Illuminate\Http\Request;

class InventarioItemController extends Controller
{
    public function index(Request $request)
    {
        $propiedadId = $request->get('propiedad_id');
        $query = InventarioItem::with('propiedad')->orderBy('articulo');
        if ($propiedadId) $query->where('propiedad_id', $propiedadId);

        $items       = $query->paginate(30)->withQueryString();
        $propiedades = Propiedad::orderBy('nombre')->get(['id', 'nombre', 'tipo']);

        return view('patrimonial.inventario.index', compact('items', 'propiedades', 'propiedadId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'propiedad_id'   => 'required|exists:pat_propiedades,id',
            'articulo'       => 'required|string|max:256',
            'cantidad'       => 'required|integer|min:0',
            'estado_articulo'=> 'nullable|in:bueno,regular,dañado',
            'observacion'    => 'nullable|string',
            'fotos.*'        => 'nullable|image|max:5120',
        ]);

        $fotos = [];
        if ($request->hasFile('fotos')) {
            foreach ($request->file('fotos') as $foto) {
                $fotos[] = $foto->store('patrimonial/inventario', 'public');
            }
        }
        $data['fotos'] = $fotos ?: null;

        InventarioItem::create($data);
        return back()->with('status', "✅ Artículo '{$data['articulo']}' registrado.");
    }

    public function update(Request $request, InventarioItem $inventarioItem)
    {
        $data = $request->validate([
            'articulo'       => 'required|string|max:256',
            'cantidad'       => 'required|integer|min:0',
            'estado_articulo'=> 'nullable|in:bueno,regular,dañado',
            'observacion'    => 'nullable|string',
        ]);
        $inventarioItem->update($data);
        return back()->with('status', '✅ Artículo actualizado.');
    }

    public function destroy(InventarioItem $inventarioItem)
    {
        $inventarioItem->delete();
        return back()->with('status', '🗑️ Artículo eliminado.');
    }
}
