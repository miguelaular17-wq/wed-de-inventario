<?php
namespace App\Http\Controllers\Patrimonial;

use App\Http\Controllers\Controller;
use App\Models\Patrimonial\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class PropiedadController extends Controller
{
    public function index(Request $request)
    {
        $query = Propiedad::query()->orderBy('nombre');

        if ($tipo = $request->get('tipo')) {
            $query->where('tipo', $tipo);
        }
        if ($estado = $request->get('estado')) {
            $query->where('estado', $estado);
        }
        if ($q = $request->get('q')) {
            $query->where(fn($qb) => $qb->where('nombre', 'ilike', "%$q%")
                                        ->orWhere('codigo', 'ilike', "%$q%")
                                        ->orWhere('propietario', 'ilike', "%$q%"));
        }

        $propiedades = $query->paginate(20)->withQueryString();

        $tipos   = Propiedad::select('tipo')->distinct()->orderBy('tipo')->pluck('tipo');
        $estados = ['disponible', 'alquilado', 'uso_propio', 'remodelacion', 'no_disponible'];

        return view('patrimonial.propiedades.index', compact('propiedades', 'tipos', 'estados'));
    }

    public function create()
    {
        return view('patrimonial.propiedades.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo'            => 'required|string|max:32|unique:pat_propiedades',
            'nombre'            => 'required|string|max:256',
            'tipo'              => 'required|string|max:64',
            'direccion'         => 'nullable|string',
            'ubicacion'         => 'nullable|string|max:256',
            'estado'            => 'required|in:disponible,alquilado,uso_propio,remodelacion,no_disponible',
            'propietario'       => 'nullable|string|max:256',
            'responsable'       => 'nullable|string|max:256',
            'fecha_adquisicion' => 'nullable|date',
            'valor_inversion'   => 'nullable|numeric|min:0',
            'observaciones'     => 'nullable|string',
            'fotos.*'           => 'nullable|image|max:5120',
        ]);

        $fotos = [];
        if ($request->hasFile('fotos')) {
            $supabaseUrl = env('SUPABASE_URL');
            $supabaseKey = env('SUPABASE_KEY');

            foreach ($request->file('fotos') as $foto) {
                $codigo = $data['codigo'];
                if ($supabaseUrl && $supabaseKey) {
                    $filename = "{$codigo}_foto_" . uniqid() . '_' . str_replace(' ', '_', $foto->getClientOriginalName());
                    $response = Http::withoutVerifying()->withHeaders([
                        'Authorization' => "Bearer {$supabaseKey}",
                        'apikey'        => $supabaseKey,
                        'Content-Type'  => $foto->getMimeType(),
                    ])->send('POST', "{$supabaseUrl}/storage/v1/object/alquileres/{$filename}", [
                        'body' => file_get_contents($foto->getRealPath())
                    ]);
                    
                    if ($response->successful()) {
                        $fotos[] = "{$supabaseUrl}/storage/v1/object/public/alquileres/{$filename}";
                    }
                } else {
                    $fotos[] = $foto->store('patrimonial/fotos', 'public');
                }
            }
        }
        $data['fotos'] = $fotos ?: null;

        Propiedad::create($data);

        return redirect()->route('patrimonial.propiedades.index')
            ->with('status', "✅ Propiedad '{$data['nombre']}' creada exitosamente.");
    }

    public function show(Propiedad $propiedad)
    {
        $propiedad->load(['alquileres.pagos', 'reservas', 'inventarioItems', 'llaves', 'documentos', 'transacciones']);
        $alquilerActivo = $propiedad->alquilerActivo();
        $balanceMes = $propiedad->balanceMes(now()->month, now()->year);

        return view('patrimonial.propiedades.show', compact('propiedad', 'alquilerActivo', 'balanceMes'));
    }

    public function edit(Propiedad $propiedad)
    {
        return view('patrimonial.propiedades.edit', compact('propiedad'));
    }

    public function update(Request $request, Propiedad $propiedad)
    {
        $data = $request->validate([
            'codigo'            => 'required|string|max:32|unique:pat_propiedades,codigo,' . $propiedad->id,
            'nombre'            => 'required|string|max:256',
            'tipo'              => 'required|string|max:64',
            'direccion'         => 'nullable|string',
            'ubicacion'         => 'nullable|string|max:256',
            'estado'            => 'required|in:disponible,alquilado,uso_propio,remodelacion,no_disponible',
            'propietario'       => 'nullable|string|max:256',
            'responsable'       => 'nullable|string|max:256',
            'fecha_adquisicion' => 'nullable|date',
            'valor_inversion'   => 'nullable|numeric|min:0',
            'observaciones'     => 'nullable|string',
            'fotos.*'           => 'nullable|image|max:5120',
        ]);

        $fotos = $propiedad->fotos ?? [];
        if ($request->hasFile('fotos')) {
            $supabaseUrl = env('SUPABASE_URL');
            $supabaseKey = env('SUPABASE_KEY');

            foreach ($request->file('fotos') as $foto) {
                $codigo = $data['codigo'];
                if ($supabaseUrl && $supabaseKey) {
                    $filename = "{$codigo}_foto_" . uniqid() . '_' . str_replace(' ', '_', $foto->getClientOriginalName());
                    $response = Http::withoutVerifying()->withHeaders([
                        'Authorization' => "Bearer {$supabaseKey}",
                        'apikey'        => $supabaseKey,
                        'Content-Type'  => $foto->getMimeType(),
                    ])->send('POST', "{$supabaseUrl}/storage/v1/object/alquileres/{$filename}", [
                        'body' => file_get_contents($foto->getRealPath())
                    ]);
                    
                    if ($response->successful()) {
                        $fotos[] = "{$supabaseUrl}/storage/v1/object/public/alquileres/{$filename}";
                    }
                } else {
                    $fotos[] = $foto->store('patrimonial/fotos', 'public');
                }
            }
        }
        $data['fotos'] = $fotos ?: null;

        $propiedad->update($data);

        return redirect()->route('patrimonial.propiedades.show', $propiedad)
            ->with('status', "✅ Propiedad actualizada exitosamente.");
    }

    public function destroy(Propiedad $propiedad)
    {
        $nombre = $propiedad->nombre;
        $propiedad->delete();

        return redirect()->route('patrimonial.propiedades.index')
            ->with('status', "🗑️ Propiedad '{$nombre}' eliminada.");
    }

    public function deleteFoto(Request $request, Propiedad $propiedad)
    {
        $fotoUrl = $request->input('foto_url');
        $fotos = $propiedad->fotos ?? [];
        
        $nuevasFotos = array_filter($fotos, fn($f) => $f !== $fotoUrl);
        
        if (count($fotos) !== count($nuevasFotos)) {
            $propiedad->update(['fotos' => array_values($nuevasFotos)]);
            
            if (filter_var($fotoUrl, FILTER_VALIDATE_URL)) {
                try {
                    $supabaseUrl = env('SUPABASE_URL');
                    $supabaseKey = env('SUPABASE_KEY');
                    $filename = basename(parse_url($fotoUrl, PHP_URL_PATH));
                    
                    Http::withoutVerifying()->withHeaders([
                        'Authorization' => "Bearer {$supabaseKey}",
                        'apikey'        => $supabaseKey,
                    ])->delete("{$supabaseUrl}/storage/v1/object/alquileres/{$filename}");
                } catch (\Exception $e) {
                    // Ignorar fallos de red al eliminar en Supabase
                }
            } else {
                Storage::disk('public')->delete($fotoUrl);
            }
        }
        
        return back()->with('status', '🗑️ Foto eliminada exitosamente.');
    }
}
