<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\V2\Producto;
use App\Models\V2\StockActual;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;

class CatalogoController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderCatalogo($request, false);
    }

    public function cliente(Request $request, string $token)
    {
        abort_unless(hash_equals($this->clienteToken(), $token), 404);

        $request->query->remove('search');
        $request->request->remove('search');
        $request->merge(['solo_existencia' => '1']);

        return $this->renderCatalogo($request, true, $token);
    }

    public function irClientes()
    {
        abort_unless(auth()->user()?->role === User::ROLE_ADMIN, 403);

        return redirect()->route('catalogo.cliente', $this->clienteToken());
    }

    private function renderCatalogo(Request $request, bool $modoCliente, ?string $token = null)
    {
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
            foreach ($data as $row) {
                $map[$row->categoria][] = $row->subcategoria;
            }
            foreach ($map as $cat => &$subs) {
                sort($subs);
            }
            return $map;
        });

        $sedes = Cache::remember('catalogo_sedes', 3600, function () {
            return StockActual::select('sede')->distinct()->pluck('sede')->sort();
        })->reject(fn ($sede) => $this->sedeOcultaEnCatalogo((string) $sede))->values();

        $query = $this->buildQuery($request, $modoCliente);

        $perPage = $request->input('per_page', 24);
        if (! in_array((int) $perPage, [24, 48, 72, 96], true)) {
            $perPage = 24;
        }

        $productos = $query->paginate($perPage)->withQueryString();

        return view('catalogo.index', [
            'productos' => $productos,
            'categorias' => $categorias,
            'subcategorias' => $subcategorias,
            'catSubcatMap' => $catSubcatMap,
            'sedes' => $sedes,
            'modoCliente' => $modoCliente,
            'clienteToken' => $token,
            'casheaLevels' => $this->casheaLevels(),
            'enlaceCliente' => url('/catalogo/cliente/' . $this->clienteToken()),
        ]);
    }

    public function exportPdf(Request $request)
    {
        try {
            ini_set('max_execution_time', 300); // Dar 5 minutos por si son muchos
            ini_set('memory_limit', '512M'); // Dar más memoria para dompdf (evita error 500)

            $query = clone $this->buildQuery($request);
            
            $scope = $request->input('pdf_scope', 'page');
            if ($scope === 'page') {
                $perPage = $request->input('per_page', 24);
                $productos = $query->paginate($perPage);
            } else {
                $productos = $query->get();
            }

            $sedeFiltro = $request->sede && $request->sede !== 'todas' && ! $this->sedeOcultaEnCatalogo((string) $request->sede)
                ? $request->sede
                : 'Global (Todas)';
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
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Excepción atrapada: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function uploadImageByUrl(Request $request)
    {
        // Solo permitir vendedores o administradores según la vista (asumiendo vendedor, admin, supervisor)
        if (!auth()->check() || auth()->user()->role !== 'vendedor' && !in_array(auth()->user()->role, ['admin', 'supervisor'])) {
            return response()->json(['success' => false, 'error' => 'No tienes permiso para actualizar imágenes.'], 403);
        }

        $request->validate([
            'codigo' => 'required|string',
            'imagen_url' => 'required|url'
        ]);

        $codigo = $request->input('codigo');
        $imageUrl = $request->input('imagen_url');

        // 1. Intentar usar wsrv.nl como conversor en la nube (soporta WebP, AVIF, HEIC, etc.)
        $wsrvUrl = 'https://wsrv.nl/?url=' . urlencode($imageUrl) . '&output=jpg&bg=white&q=85';
        $imageResponse = \Illuminate\Support\Facades\Http::withoutVerifying()->get($wsrvUrl);
        $imageContent = null;
        $isConverted = false;

        if ($imageResponse->successful()) {
            $imageContent = $imageResponse->body();
            $isConverted = true;
        } else {
            // Fallback: descarga directa si el proxy es bloqueado por el servidor origen
            $imageResponse = \Illuminate\Support\Facades\Http::withoutVerifying()->get($imageUrl);
            if (!$imageResponse->successful()) {
                return response()->json(['success' => false, 'error' => 'No se pudo descargar la imagen de la URL proporcionada. Asegúrate de que el enlace sea público.'], 400);
            }
            $imageContent = $imageResponse->body();
        }

        // 2. Si no pasó por el proxy (descarga directa), intentar convertir usando PHP GD
        if (!$isConverted) {
            $gdImage = @imagecreatefromstring($imageContent);
            if (!$gdImage) {
                return response()->json(['success' => false, 'error' => 'El enlace proporcionado no contiene una imagen válida o es un formato WebP no soportado por este servidor.'], 400);
            }

            // Crear una imagen blanca de fondo en caso de que sea un PNG transparente
            $width = imagesx($gdImage);
            $height = imagesy($gdImage);
            $bg = imagecreatetruecolor($width, $height);
            imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
            imagecopy($bg, $gdImage, 0, 0, 0, 0, $width, $height);

            // Capturar el JPEG en memoria (calidad 85 para reducir peso)
            ob_start();
            imagejpeg($bg, null, 85);
            $imageContent = ob_get_clean();
            
            imagedestroy($gdImage);
            imagedestroy($bg);
        }

        $contentType = 'image/jpeg';
        $extension = '.jpg';

        // 3. Subir a Supabase
        $fileName = rawurlencode($codigo) . $extension;
        $supabaseUrl = env('SUPABASE_URL');
        $supabaseKey = env('SUPABASE_KEY');

        if (!$supabaseUrl || !$supabaseKey) {
            return response()->json(['success' => false, 'error' => 'Credenciales de Supabase no configuradas en el servidor.'], 500);
        }

        $supabaseUrl = rtrim($supabaseUrl, '/');
        // Usamos el endpoint para subir a 'imagenes_producto' dentro de 'imagenes'
        $uploadUrl = "{$supabaseUrl}/storage/v1/object/imagenes_producto/imagenes/{$fileName}";

        // En Supabase, para sobreescribir un archivo existente usamos un header especial o PUT
        // Pero el método de subir por defecto es POST, si existe fallará a menos que usemos upsert=true en el header
        $supabaseResponse = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
            'Authorization' => "Bearer {$supabaseKey}",
            'Content-Type' => $contentType,
            'x-upsert' => 'true'
        ])->withBody($imageContent, $contentType)->post($uploadUrl);

        if ($supabaseResponse->successful()) {
            // Limpiar caché de la imagen para que el PDF se actualice de inmediato
            \Illuminate\Support\Facades\Cache::forget('img_base64_' . $codigo);
            
            // Actualizar la base de datos con la nueva URL
            $publicUrl = "{$supabaseUrl}/storage/v1/object/public/imagenes_producto/imagenes/{$fileName}";
            if (config('database.default') === 'pgsql') {
                \Illuminate\Support\Facades\DB::connection('pgsql')->table('inventario_v2.productos')
                    ->where('codigo', 'like', $codigo . '%')
                    ->update(['url_imagen' => $publicUrl]);
            } else {
                \App\Models\Product::where('cod_centro', 'like', $codigo . '%')
                    ->update(['url_imagen' => $publicUrl]);
            }

            return response()->json(['success' => true]);
        }

        return response()->json([
            'success' => false, 
            'error' => 'Error al guardar la imagen en Supabase: ' . $supabaseResponse->body()
        ], 500);
    }

    private function buildQuery(Request $request, bool $modoCliente = false)
    {
        $query = Producto::select('id', 'codigo', 'nombre as descripcion', 'categoria', 'subcategoria', 'precio_unidad', 'precio_mayor')
            ->where('activo', true);

        if ($request->filled('categoria') && $request->categoria !== 'todas') {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('subcategoria') && $request->subcategoria !== 'todas') {
            $query->where('subcategoria', $request->subcategoria);
        }

        if (! $modoCliente && $request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'ilike', $search)
                  ->orWhere('nombre', 'ilike', $search);
            });
        }

        $sede = $request->input('sede');
        if ($this->sedeOcultaEnCatalogo((string) $sede)) {
            $sede = null;
        }
        if ($sede && $sede !== 'todas') {
            $query->withSum(['stock as existencia' => function ($q) use ($sede) {
                $q->where('sede', $sede);
            }], 'existencia');
        } else {
            $query->withSum('stock as existencia', 'existencia');
        }

        $soloExistencia = $modoCliente || ($request->has('solo_existencia') && $request->solo_existencia == '1');
        if ($soloExistencia) {
            if ($sede && $sede !== 'todas') {
                $query->whereHas('stock', function ($q) use ($sede) {
                    $q->where('sede', $sede)->where('existencia', '>', 0);
                });
            } else {
                $query->whereHas('stock', function ($q) {
                    $q->where('existencia', '>', 0);
                });
            }
        }

        $query->orderBy('nombre', 'asc');

        return $query;
    }

    private function sedeOcultaEnCatalogo(string $sede): bool
    {
        $ocultas = array_map('strtoupper', config('inventario.catalogo_sedes_ocultas', ['NUNES', 'MOVISTAR', 'JRZ']));

        return in_array(strtoupper(trim($sede)), $ocultas, true);
    }

    private function clienteToken(): string
    {
        $fromEnv = (string) config('inventario.catalogo_cliente_token', '');
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $path = storage_path('app/catalogo_cliente_token.txt');
        if (! is_file($path)) {
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $token = \Illuminate\Support\Str::random(40);
            file_put_contents($path, $token);

            return $token;
        }

        return trim((string) file_get_contents($path));
    }

    private function casheaLevels(): array
    {
        $defaultLevels = [1 => 60, 2 => 50, 3 => 40, 4 => 40, 5 => 40, 6 => 40];
        $path = storage_path('app/cashea_levels.json');
        if (! is_file($path)) {
            return $defaultLevels;
        }

        $stored = json_decode((string) file_get_contents($path), true);
        if (! is_array($stored)) {
            return $defaultLevels;
        }

        foreach (range(1, 6) as $nivel) {
            if (isset($stored[$nivel])) {
                $defaultLevels[$nivel] = (int) $stored[$nivel];
            }
        }

        return $defaultLevels;
    }
}
