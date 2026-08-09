<?php
namespace App\Http\Controllers\Patrimonial;

use App\Http\Controllers\Controller;
use App\Models\Patrimonial\Documento;
use App\Models\Patrimonial\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class DocumentoController extends Controller
{
    public function index(Request $request)
    {
        $propiedadId = $request->get('propiedad_id');
        $tipo        = $request->get('tipo');

        $query = Documento::with('propiedad')->orderByDesc('created_at');
        if ($propiedadId) $query->where('propiedad_id', $propiedadId);
        if ($tipo)        $query->where('tipo', $tipo);

        $documentos  = $query->paginate(25)->withQueryString();
        $propiedades = Propiedad::orderBy('nombre')->get(['id', 'nombre']);
        $tipos       = ['contrato', 'factura', 'permiso', 'foto', 'otro'];

        return view('patrimonial.documentos.index', compact(
            'documentos', 'propiedades', 'tipos', 'propiedadId', 'tipo'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'propiedad_id'  => 'required|exists:pat_propiedades,id',
            'tipo'          => 'required|in:contrato,factura,permiso,foto,otro',
            'nombre'        => 'required|string|max:256',
            'archivo'       => 'required|file|max:20480',  // 20 MB
            'observaciones' => 'nullable|string',
        ]);

        $file = $request->file('archivo');
        $supabaseUrl = env('SUPABASE_URL');
        $supabaseKey = env('SUPABASE_KEY');

        if ($supabaseUrl && $supabaseKey) {
            $propiedad = Propiedad::findOrFail($request->propiedad_id);
            $filename = "{$propiedad->codigo}_doc_" . uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => "Bearer {$supabaseKey}",
                'apikey'        => $supabaseKey,
                'Content-Type'  => $file->getMimeType(),
            ])->send('POST', "{$supabaseUrl}/storage/v1/object/alquileres/{$filename}", [
                'body' => file_get_contents($file->getRealPath())
            ]);

            if (!$response->successful()) {
                return back()->withErrors(['archivo' => 'Error subiendo a Supabase: ' . $response->body()]);
            }
            
            $ruta = "{$supabaseUrl}/storage/v1/object/public/alquileres/{$filename}";
        } else {
            $ruta = $file->store('patrimonial/documentos', 'public');
        }

        Documento::create([
            'propiedad_id'  => $request->propiedad_id,
            'tipo'          => $request->tipo,
            'nombre'        => $request->nombre,
            'ruta_archivo'  => $ruta,
            'tamano_bytes'  => $file->getSize(),
            'observaciones' => $request->observaciones,
        ]);

        return back()->with('status', "✅ Documento '{$request->nombre}' subido exitosamente.");
    }

    public function destroy(Documento $documento)
    {
        if (filter_var($documento->ruta_archivo, FILTER_VALIDATE_URL)) {
            try {
                $supabaseUrl = env('SUPABASE_URL');
                $supabaseKey = env('SUPABASE_KEY');
                $filename = basename(parse_url($documento->ruta_archivo, PHP_URL_PATH));
                
                Http::withoutVerifying()->withHeaders([
                    'Authorization' => "Bearer {$supabaseKey}",
                    'apikey'        => $supabaseKey,
                ])->delete("{$supabaseUrl}/storage/v1/object/alquileres/{$filename}");
            } catch (\Exception $e) {
                // Ignore network errors on delete
            }
        } else {
            Storage::disk('public')->delete($documento->ruta_archivo);
        }

        $documento->delete();
        return back()->with('status', '🗑️ Documento eliminado.');
    }
}
