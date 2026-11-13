<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\V2\Producto;
use App\Models\V2\StockActual;
use App\Services\ExportarPreciosDescuentoService;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;

class CatalogoController extends Controller
{
    /** Escalones de las barras de stock y precio: el último tope de la barra es «sin límite». */
    public const PASOS_STOCK = [0, 1, 2, 3, 5, 10, 20, 30, 50, 100, 200, 500, 1000];

    public const PASOS_PRECIO = [0, 1, 2, 5, 10, 15, 20, 30, 50, 75, 100, 150, 200, 300, 500, 1000];

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

    public function exportarPrecios(Request $request, ExportarPreciosDescuentoService $exportador)
    {
        $descuento = (float) $request->query('descuento', 25);
        if ($descuento < 0 || $descuento > 100) {
            $descuento = 25;
        }

        try {
            [$xlsx, $filename] = $exportador->generarXlsx($descuento);
        } catch (\Throwable $e) {
            return redirect()
                ->route('catalogo.index')
                ->with('error', $e->getMessage());
        }

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
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

        // Desglose por sede de las unidades (al pasar el mouse); solo para los productos de esta página.
        // Catálogo interno (también el acceso rápido sin sesión de las tiendas); el enlace para clientes no lo muestra.
        $verDesglose = ! $modoCliente;
        if ($verDesglose) {
            $filtrarSede = $this->filtroSede($request);
            $productos->getCollection()->load(['stock' => function ($q) use ($filtrarSede) {
                $filtrarSede($q);
                $q->where('existencia', '>', 0)->orderByDesc('existencia');
            }]);
        }

        return view('catalogo.index', [
            'verDesgloseStock' => $verDesglose,
            'nombresSede' => ['JRZ' => 'JRZ', 'TECNOHOUSE' => 'Tecno House'],
            'productos' => $productos,
            'categorias' => $categorias,
            'subcategorias' => $subcategorias,
            'subcategoriasElegidas' => $this->subcategoriasElegidas($request),
            'sedesExcluidas' => $this->sedesExcluidas($request),
            'catSubcatMap' => $catSubcatMap,
            'sedes' => $sedes,
            'modoCliente' => $modoCliente,
            'clienteToken' => $token,
            'casheaLevels' => $this->casheaLevels(),
            'enlaceCliente' => url('/catalogo/cliente/' . $this->clienteToken()),
            'fotosExtra' => \App\Support\FotoProducto::extras($productos->getCollection()->pluck('codigo')),
            'puedeCambiarImagenes' => ! $modoCliente && $this->puedeCambiarImagenes(),
            'rangos' => [
                'stock' => ['titulo' => 'Stock (unidades)', 'pasos' => self::PASOS_STOCK, 'prefijo' => ''],
                'precio' => ['titulo' => 'Precio unidad', 'pasos' => self::PASOS_PRECIO, 'prefijo' => '$'],
            ],
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

            $pdf = Pdf::setOptions(['isRemoteEnabled' => true])
                ->loadView('catalogo.pdf', compact('productos'));
            
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

    /**
     * Cambia la foto principal del producto o le agrega otra, desde un archivo (equipo o teléfono) o un enlace.
     * Principal: imagenes/{código principal}.jpg. Adicionales: imagenes/{código}_{n}.jpg, registradas en producto_imagenes.
     */
    public function uploadImageByUrl(Request $request)
    {
        if (! $this->puedeCambiarImagenes()) {
            return response()->json(['success' => false, 'error' => 'No tienes permiso para actualizar imágenes.'], 403);
        }

        $request->validate([
            'codigo' => 'required|string|max:120',
            'modo' => 'nullable|in:principal,extra',
            'imagen_url' => 'nullable|required_without:archivo|url',
            'archivo' => 'nullable|required_without:imagen_url|file|image|max:10240',
        ]);

        $codigo = \App\Support\FotoProducto::codigoPrincipal($request->input('codigo'));
        if ($codigo === '') {
            return response()->json(['success' => false, 'error' => 'El producto no tiene código.'], 422);
        }
        $extra = $request->input('modo') === 'extra';
        if ($extra && \App\Models\ProductoImagen::query()->where('codigo', $codigo)->count() >= \App\Models\ProductoImagen::MAXIMO_EXTRA) {
            return response()->json(['success' => false, 'error' => 'Este producto ya tiene '.\App\Models\ProductoImagen::MAXIMO_EXTRA.' fotos adicionales. Borra una para agregar otra.'], 422);
        }

        $imageContent = $request->hasFile('archivo')
            ? $this->jpegDesdeContenido((string) file_get_contents($request->file('archivo')->getRealPath()))
            : $this->jpegDesdeUrl((string) $request->input('imagen_url'));
        if (is_string($imageContent) && str_starts_with($imageContent, 'ERROR:')) {
            return response()->json(['success' => false, 'error' => substr($imageContent, 6)], 400);
        }

        $supabaseUrl = rtrim((string) env('SUPABASE_URL'), '/');
        $supabaseKey = (string) env('SUPABASE_KEY');
        if ($supabaseUrl === '' || $supabaseKey === '') {
            return response()->json(['success' => false, 'error' => 'Credenciales de Supabase no configuradas en el servidor.'], 500);
        }

        $archivo = $extra ? $codigo.'_'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)).'.jpg' : $codigo.'.jpg';
        $ruta = 'imagenes/'.rawurlencode($archivo);
        $supabaseResponse = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
            'Authorization' => "Bearer {$supabaseKey}",
            'Content-Type' => 'image/jpeg',
            'x-upsert' => 'true',
            'cache-control' => 'max-age=60',
        ])->withBody($imageContent, 'image/jpeg')->post("{$supabaseUrl}/storage/v1/object/imagenes_producto/{$ruta}");

        if (! $supabaseResponse->successful()) {
            return response()->json([
                'success' => false,
                'error' => 'Error al guardar la imagen en Supabase: '.$supabaseResponse->body(),
            ], 500);
        }

        $publicUrl = "{$supabaseUrl}/storage/v1/object/public/imagenes_producto/{$ruta}";
        if ($extra) {
            \App\Models\ProductoImagen::create([
                'codigo' => $codigo,
                'archivo' => $archivo,
                'url' => $publicUrl,
                'orden' => ((int) \App\Models\ProductoImagen::query()->where('codigo', $codigo)->max('orden')) + 1,
                'user_id' => auth()->id(),
            ]);

            return response()->json(['success' => true, 'url' => $publicUrl]);
        }

        \Illuminate\Support\Facades\Cache::forget('img_base64_'.$codigo);
        // Solo este producto: «1775» o «1775 / …», no los códigos que empiezan igual (17751…).
        $mismoProducto = fn ($w, string $columna) => $w->where($columna, $codigo)->orWhere($columna, 'like', $codigo.' / %');
        if (config('database.default') === 'pgsql') {
            \Illuminate\Support\Facades\DB::connection('pgsql')->table('inventario_v2.productos')
                ->where(fn ($w) => $mismoProducto($w, 'codigo'))
                ->update(['url_imagen' => $publicUrl]);
        } else {
            \App\Models\Product::where(fn ($w) => $mismoProducto($w, 'cod_centro'))
                ->update(['url_imagen' => $publicUrl]);
        }

        return response()->json(['success' => true, 'url' => $publicUrl]);
    }

    /** Borra una foto adicional del producto. */
    public function borrarImagen(\App\Models\ProductoImagen $imagen)
    {
        if (! $this->puedeCambiarImagenes()) {
            return response()->json(['success' => false, 'error' => 'No tienes permiso para actualizar imágenes.'], 403);
        }
        $supabaseUrl = rtrim((string) env('SUPABASE_URL'), '/');
        $supabaseKey = (string) env('SUPABASE_KEY');
        if ($supabaseUrl !== '' && $supabaseKey !== '') {
            \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders(['Authorization' => "Bearer {$supabaseKey}"])
                ->delete("{$supabaseUrl}/storage/v1/object/imagenes_producto/imagenes/".rawurlencode($imagen->archivo));
        }
        $imagen->delete();

        return response()->json(['success' => true]);
    }

    private function puedeCambiarImagenes(): bool
    {
        $rol = auth()->user()?->role;

        return in_array($rol, ['vendedor', 'admin', 'supervisor'], true);
    }

    /** La imagen del enlace en JPEG (wsrv.nl convierte WebP, AVIF, HEIC…); «ERROR:…» si no se pudo. */
    private function jpegDesdeUrl(string $imageUrl): string
    {
        $wsrv = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(30)
            ->get('https://wsrv.nl/?url='.urlencode($imageUrl).'&output=jpg&bg=white&q=85&w=1200&h=1200&fit=inside&we');
        if ($wsrv->successful() && str_starts_with((string) $wsrv->header('Content-Type'), 'image/')) {
            return $wsrv->body();
        }
        $directa = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(30)->get($imageUrl);
        if (! $directa->successful()) {
            return 'ERROR:No se pudo descargar la imagen del enlace. Asegúrate de que el enlace sea público.';
        }

        return $this->jpegDesdeContenido($directa->body());
    }

    /** JPEG con fondo blanco y como máximo 1200 px por lado; «ERROR:…» si no es una imagen que el servidor entienda. */
    private function jpegDesdeContenido(string $contenido): string
    {
        $gd = @imagecreatefromstring($contenido);
        if (! $gd) {
            return 'ERROR:El archivo no es una imagen válida (usa JPG, PNG o WEBP).';
        }
        $ancho = imagesx($gd);
        $alto = imagesy($gd);
        $escala = min(1, 1200 / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));
        $lienzo = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopyresampled($lienzo, $gd, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        ob_start();
        imagejpeg($lienzo, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($gd);
        imagedestroy($lienzo);

        return $jpeg;
    }

    /**
     * Subcategorías del filtro: una (?subcategoria=X, enlaces viejos) o varias (?subcategoria[]=X&subcategoria[]=Y).
     *
     * @return list<string>
     */
    private function subcategoriasElegidas(Request $request): array
    {
        $valor = $request->input('subcategoria');
        $lista = is_array($valor) ? $valor : [$valor];

        return array_values(array_unique(array_filter(
            array_map(fn ($s) => trim((string) $s), $lista),
            fn ($s) => $s !== '' && strtolower($s) !== 'todas'
        )));
    }

    private function buildQuery(Request $request, bool $modoCliente = false)
    {
        $query = Producto::select('id', 'codigo', 'nombre as descripcion', 'categoria', 'subcategoria', 'precio_unidad', 'precio_mayor')
            ->where('activo', true);

        if ($request->filled('categoria') && $request->categoria !== 'todas') {
            $query->where('categoria', $request->categoria);
        }

        $subcategorias = $this->subcategoriasElegidas($request);
        if ($subcategorias !== []) {
            $query->whereIn('subcategoria', $subcategorias);
        }

        if (! $modoCliente && $request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'ilike', $search)
                  ->orWhere('nombre', 'ilike', $search);
            });
        }

        $filtrarSede = $this->filtroSede($request);

        $query->withSum(['stock as existencia' => $filtrarSede], 'existencia');

        $soloExistencia = $modoCliente || ($request->has('solo_existencia') && $request->solo_existencia == '1');
        if ($soloExistencia) {
            $query->whereHas('stock', function ($q) use ($filtrarSede) {
                $filtrarSede($q);
                $q->where('existencia', '>', 0);
            });
        }

        [$stockMin, $stockMax] = $this->rango($request, 'stock');
        if ($stockMin !== null || $stockMax !== null) {
            $suma = StockActual::query()->selectRaw('COALESCE(SUM(existencia), 0)')
                ->whereColumn('stock_actual.producto_id', 'productos.id');
            $filtrarSede($suma);
            if ($stockMin !== null) {
                $query->where(clone $suma, '>=', $stockMin);
            }
            if ($stockMax !== null) {
                $query->where(clone $suma, '<=', $stockMax);
            }
        }

        [$precioMin, $precioMax] = $this->rango($request, 'precio');
        if ($precioMin !== null) {
            $query->where('precio_unidad', '>=', $precioMin);
        }
        if ($precioMax !== null) {
            $query->where('precio_unidad', '<=', $precioMax);
        }

        $query->orderBy('nombre', 'asc');

        return $query;
    }

    /**
     * Desde/hasta de una barra (?stock_min=&stock_max=, ?precio_min=&precio_max=); vacío o 0 en «desde» no filtra.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function rango(Request $request, string $clave): array
    {
        $leer = function (string $campo) use ($request): ?float {
            $valor = $request->input($campo);

            return is_numeric($valor) && (float) $valor >= 0 ? (float) $valor : null;
        };
        $min = $leer($clave.'_min');
        $max = $leer($clave.'_max');
        if ($min !== null && $min <= 0) {
            $min = null;
        }
        if ($min !== null && $max !== null && $max < $min) {
            [$min, $max] = [$max, $min];
        }

        return [$min, $max];
    }

    /**
     * Sedes que suman en las unidades del catálogo: la sede elegida, o todas menos las excluidas.
     */
    private function filtroSede(Request $request): \Closure
    {
        $sede = $request->input('sede');
        if ($this->sedeOcultaEnCatalogo((string) $sede)) {
            $sede = null;
        }
        $excluidas = $this->sedesExcluidas($request);

        return function ($q) use ($sede, $excluidas) {
            if ($sede && $sede !== 'todas') {
                $q->where('sede', $sede);
            } elseif ($excluidas !== []) {
                $q->whereNotIn('sede', $excluidas);
            }
        };
    }

    /**
     * Sedes que no suman existencia en la vista Global (?excluir_sede[]=X&excluir_sede[]=Y).
     *
     * @return list<string>
     */
    private function sedesExcluidas(Request $request): array
    {
        $valor = $request->input('excluir_sede', []);
        $lista = is_array($valor) ? $valor : [$valor];

        return array_values(array_unique(array_filter(
            array_map(fn ($s) => strtoupper(trim((string) $s)), $lista),
            fn ($s) => $s !== ''
        )));
    }

    private function sedeOcultaEnCatalogo(string $sede): bool
    {
        $ocultas = array_map('strtoupper', config('inventario.catalogo_sedes_ocultas', ['NUNES', 'MOVISTAR']));

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
