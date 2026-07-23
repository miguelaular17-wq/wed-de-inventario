<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use App\Models\V2\Producto;

class CatalogoAutoController extends Controller
{
    public function index()
    {
        // Load categories/subcategories available
        $categorias = Producto::where('activo', true)
            ->whereNotNull('categoria')
            ->where('categoria', '!=', '')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        $catSubcatMap = Producto::where('activo', true)
            ->whereNotNull('categoria')->where('categoria', '!=', '')
            ->whereNotNull('subcategoria')->where('subcategoria', '!=', '')
            ->select('categoria', 'subcategoria')
            ->distinct()
            ->orderBy('subcategoria')
            ->get()
            ->groupBy('categoria')
            ->map(fn($items) => $items->pluck('subcategoria')->sort()->values());

        // Load all configured catalogs
        $catalogos = DB::connection('pgsql')->table('catalogo_config')->orderBy('id', 'desc')->get();

        return view('admin.catalogo_auto.index', compact(
            'categorias', 'catSubcatMap', 'catalogos'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|string|max:100',
            'categorias'    => 'nullable|array',
            'subcategorias' => 'nullable|array',
            'solo_existencia' => 'nullable',
        ]);

        $nombre = trim($request->input('nombre'));
        $archivo = Str::slug($nombre) . '.pdf';

        $categorias    = $request->input('categorias', []);
        $subcategorias = $request->input('subcategorias', []);
        $soloExistencia = $request->boolean('solo_existencia', true);

        DB::connection('pgsql')->table('catalogo_config')->insert([
            'nombre'          => $nombre,
            'archivo'         => $archivo,
            'categorias'      => json_encode(array_values($categorias)),
            'subcategorias'   => json_encode(array_values($subcategorias)),
            'solo_existencia' => $soloExistencia,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return back()->with('success', 'Automatizador "' . $nombre . '" creado correctamente.');
    }

    public function destroy($id)
    {
        $config = DB::connection('pgsql')->table('catalogo_config')->find($id);
        if ($config) {
            DB::connection('pgsql')->table('catalogo_config')->where('id', $id)->delete();
            return back()->with('success', 'Automatizador eliminado correctamente.');
        }
        return back()->with('error', 'Automatizador no encontrado.');
    }

    public function generate($id)
    {
        $config = DB::connection('pgsql')->table('catalogo_config')->find($id);
        
        if (!$config) {
            return back()->with('error', 'Automatizador no encontrado.');
        }

        // Run the generation command specifically for this ID
        try {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 300);

            $exitCode = Artisan::call('catalogo:generar', ['--id' => $id]);

            if ($exitCode !== 0) {
                $output = Artisan::output();
                return back()->with('error', 'Error al generar el catálogo: ' . $output);
            }

            // fetch the updated config
            $updatedConfig = DB::connection('pgsql')->table('catalogo_config')->find($id);

            return back()
                ->with('success', 'Catálogo "' . $config->nombre . '" generado y subido a Supabase correctamente.')
                ->with('url_generada', $updatedConfig->url_supabase ?? null);
        } catch (\Throwable $e) {
            Log::error('CatalogoAutoController@generate: ' . $e->getMessage());
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
