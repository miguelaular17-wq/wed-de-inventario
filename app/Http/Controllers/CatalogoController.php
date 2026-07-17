<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\V2\Producto;
use App\Models\V2\StockActual;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;

class CatalogoController extends Controller
{
    public function index(Request $request)
    {
        // 1. Cargar datos base en caché por 1 hora (3600 segundos)
        $categorias = Cache::remember('catalogo_categorias', 3600, function () {
            return Producto::where('activo', true)
                ->whereNotNull('categoria')
                ->where('categoria', '!=', '')
                ->distinct()
                ->pluck('categoria')
                ->sort();
        });

        $subcategorias = Cache::remember('catalogo_subcategorias', 3600, function () {
            return Producto::where('activo', true)
                ->whereNotNull('subcategoria')
                ->where('subcategoria', '!=', '')
                ->distinct()
                ->pluck('subcategoria')
                ->sort();
        });

        $catSubcatMap = Cache::remember('catalogo_cat_subcat_map', 3600, function () {
            $data = Producto::where('activo', true)
                ->whereNotNull('categoria')
                ->where('categoria', '!=', '')
                ->whereNotNull('subcategoria')
                ->where('subcategoria', '!=', '')
                ->select('categoria', 'subcategoria')
                ->distinct()
                ->get();
                
            $map = [];
            foreach($data as $row) {
                $map[$row->categoria][] = $row->subcategoria;
            }
            foreach($map as $cat => &$subs) {
                sort($subs);
            }
            return $map;
        });

        $sedes = Cache::remember('catalogo_sedes', 3600, function () {
            return StockActual::select('sede')->distinct()->pluck('sede')->sort();
        });

        // 2. Aplicar filtros a la consulta base
        $query = $this->buildQuery($request);

        // Paginación dinámica
        $perPage = $request->input('per_page', 24);
        if (!in_array($perPage, [24, 48, 72, 96])) {
            $perPage = 24;
        }

        $productos = $query->paginate($perPage)->withQueryString();

        return view('catalogo.index', compact('productos', 'categorias', 'subcategorias', 'catSubcatMap', 'sedes'));
    }

    public function exportPdf(Request $request)
    {
        ini_set('max_execution_time', 300); // Dar 5 minutos por si son muchos

        $query = clone $this->buildQuery($request);
        
        $scope = $request->input('pdf_scope', 'page');
        if ($scope === 'page') {
            $perPage = $request->input('per_page', 24);
            $productos = $query->paginate($perPage);
        } else {
            $productos = $query->get();
        }

        $sedeFiltro = $request->sede && $request->sede !== 'todas' ? $request->sede : 'Global (Todas)';
        $catFiltro = $request->categoria && $request->categoria !== 'todas' ? $request->categoria : 'Todas';
        $subcatFiltro = $request->subcategoria && $request->subcategoria !== 'todas' ? $request->subcategoria : 'Todas';

        $filtrosActivos = [
            'Sede' => $sedeFiltro,
            'Categoría' => $catFiltro,
            'Subcategoría' => $subcatFiltro,
        ];

        $pdf = Pdf::setOptions(['isRemoteEnabled' => true])
            ->loadView('catalogo.pdf', compact('productos', 'filtrosActivos'));
        
        $pdf->setPaper('A4', 'portrait');

        if ($request->wantsJson() || $request->ajax()) {
            $pdfContent = $pdf->output();
            $fileName = 'catalogo_' . date('Ymd_His') . '.pdf';
            
            $supabaseUrl = env('SUPABASE_URL');
            $supabaseKey = env('SUPABASE_KEY');
            
            if (!$supabaseUrl || !$supabaseKey) {
                return response()->json(['success' => false, 'error' => 'Faltan credenciales SUPABASE_URL o SUPABASE_KEY en el archivo .env.'], 500);
            }
            
            // Eliminar posibles slashes al final
            $supabaseUrl = rtrim($supabaseUrl, '/');
            $uploadUrl = "{$supabaseUrl}/storage/v1/object/catalogos/{$fileName}";
            
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
                'Authorization' => "Bearer {$supabaseKey}",
                'Content-Type' => 'application/pdf',
            ])->withBody($pdfContent, 'application/pdf')->post($uploadUrl);
            
            if ($response->successful()) {
                $publicUrl = "{$supabaseUrl}/storage/v1/object/public/catalogos/{$fileName}";
                return response()->json([
                    'success' => true,
                    'url' => $publicUrl,
                    'file_name' => $fileName
                ]);
            }
            
            return response()->json([
                'success' => false, 
                'error' => 'Error al subir a Supabase: ' . $response->body()
            ], 500);
        }

        return $pdf->download('catalogo.pdf');
    }

    private function buildQuery(Request $request)
    {
        // Seleccionamos solo las columnas necesarias
        $query = Producto::select('id', 'codigo', 'nombre as descripcion', 'categoria', 'subcategoria', 'precio_unidad', 'precio_mayor')
            ->where('activo', true);

        // Filtro: Categoría
        if ($request->filled('categoria') && $request->categoria !== 'todas') {
            $query->where('categoria', $request->categoria);
        }

        // Filtro: Subcategoría
        if ($request->filled('subcategoria') && $request->subcategoria !== 'todas') {
            $query->where('subcategoria', $request->subcategoria);
        }

        // Filtro: Búsqueda por Código o Nombre
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function($q) use ($search) {
                // ILIKE si es postgres, sino LIKE
                $q->where('codigo', 'ilike', $search)
                  ->orWhere('nombre', 'ilike', $search);
            });
        }

        // Sumatoria de existencia (según sede o global)
        $sede = $request->input('sede');
        if ($sede && $sede !== 'todas') {
            $query->withSum(['stock as existencia' => function($q) use ($sede) {
                $q->where('sede', $sede);
            }], 'existencia');
        } else {
            $query->withSum('stock as existencia', 'existencia');
        }

        // Filtro: Solo con existencia
        if ($request->has('solo_existencia') && $request->solo_existencia == '1') {
            if ($sede && $sede !== 'todas') {
                $query->whereHas('stock', function($q) use ($sede) {
                    $q->where('sede', $sede)->where('existencia', '>', 0);
                });
            } else {
                $query->whereHas('stock', function($q) {
                    $q->where('existencia', '>', 0);
                });
            }
        }

        $query->orderBy('nombre', 'asc');

        return $query;
    }
}
