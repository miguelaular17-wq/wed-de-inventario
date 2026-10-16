<?php

namespace App\Http\Controllers;

use App\Services\BcvRateService;
use App\Support\VentaDescuento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LectorQrController extends Controller
{
    public function index(BcvRateService $bcv): View
    {
        $sedes = collect(config('inventario.sedes_locales', []))
            ->map(fn (string $sede) => [
                'codigo' => $sede,
                'nombre' => config('inventario.display.'.$sede, $sede),
            ])
            ->values()
            ->all();

        $pin = (string) config('lector_qr.pin', '2580');

        return view('lector_qr.index', [
            'sedes' => $sedes,
            'logoUrl' => asset('logo.png'),
            'pinLength' => max(4, min(6, strlen($pin))),
            'casheaLevels' => $this->casheaLevels(),
            'netoFactor' => VentaDescuento::factorNeto(),
            'descPct' => (int) round(VentaDescuento::factorDescuento() * 100),
            'tasaBcv' => (float) $bcv->getRateForToday(),
        ]);
    }

    /** @return array<int, int> */
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

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sede' => ['required', 'string', 'max:40'],
            'codigo' => ['required', 'string', 'max:200'],
        ]);

        $sede = strtoupper(trim($data['sede']));
        $permitidas = config('inventario.sedes_locales', []);
        if (! in_array($sede, $permitidas, true)) {
            return response()->json(['ok' => false, 'error' => 'Sede no válida.'], 422);
        }

        $codigo = $this->normalizarCodigo($data['codigo']);
        if ($codigo === '') {
            return response()->json(['ok' => false, 'error' => 'Código vacío.'], 422);
        }

        $producto = $this->buscarProducto($codigo, $sede);
        if ($producto) {
            return response()->json([
                'ok' => true,
                'producto' => $producto,
            ]);
        }

        // Código físico distinto al del sistema: probar por los primeros 7 dígitos.
        $candidatos = $this->buscarPorPrefijoSieteDigitos($codigo, $sede);
        if (count($candidatos) === 1) {
            return response()->json([
                'ok' => true,
                'producto' => $candidatos[0],
                'match' => 'prefijo7',
            ]);
        }
        if (count($candidatos) > 1) {
            return response()->json([
                'ok' => true,
                'multiples' => true,
                'prefijo' => $this->prefijoSieteDigitos($codigo),
                'productos' => $candidatos,
            ]);
        }

        return response()->json([
            'ok' => false,
            'error' => 'Producto no encontrado.',
            'codigo_buscado' => $codigo,
        ], 404);
    }

    public function unlock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'max:32'],
        ]);

        $intentos = (int) $request->session()->get('lector_qr_pin_intentos', 0);
        if ($intentos >= 8) {
            return response()->json([
                'ok' => false,
                'error' => 'Demasiados intentos. Espera un momento o reinicia la tablet.',
            ], 429);
        }

        $esperado = (string) config('lector_qr.pin', '2580');
        if (! hash_equals($esperado, trim($data['pin']))) {
            $request->session()->put('lector_qr_pin_intentos', $intentos + 1);

            return response()->json(['ok' => false, 'error' => 'Código incorrecto.'], 403);
        }

        $request->session()->forget('lector_qr_pin_intentos');

        return response()->json([
            'ok' => true,
            'redirect' => route('login'),
        ]);
    }

    private function normalizarCodigo(string $raw): string
    {
        $raw = trim($raw);
        // Escáner/teclado a veces manda apóstrofe en vez de guion (ej. YQ'665 → YQ-665)
        $raw = str_replace(["'", "\u{2018}", "\u{2019}", "\u{02BC}", '`'], '-', $raw);

        // Si el QR trae una URL, usar el último segmento
        if (preg_match('#^https?://#i', $raw)) {
            $path = parse_url($raw, PHP_URL_PATH) ?: '';
            $parts = array_values(array_filter(explode('/', $path)));
            $raw = $parts !== [] ? (string) end($parts) : $raw;
        }

        // Si el escáner manda varios códigos juntos, tomar el primero
        if (str_contains($raw, '/')) {
            $partes = array_values(array_filter(array_map('trim', explode('/', $raw))));
            $raw = $partes[0] ?? $raw;
        } elseif (preg_match('/\s{2,}|\t/', $raw)) {
            $raw = trim(preg_split('/\s+/', $raw, 2)[0]);
        }

        return trim($raw);
    }

    private function prefijoSieteDigitos(string $codigo): ?string
    {
        $digitos = preg_replace('/\D+/', '', $codigo) ?? '';
        if (strlen($digitos) < 7) {
            return null;
        }

        return substr($digitos, 0, 7);
    }

    /** @return array<string, mixed>|null */
    private function buscarProducto(string $codigo, string $sede): ?array
    {
        if (config('database.default') !== 'pgsql') {
            return null;
        }

        // Códigos compuestos: "A / B / C" — buscar por cualquier tramo
        $row = DB::connection('pgsql')
            ->table('productos as p')
            ->where('p.activo', true)
            ->where(function ($q) use ($codigo) {
                $q->where('p.codigo', $codigo)
                    ->orWhereRaw('UPPER(TRIM(p.codigo)) = UPPER(?)', [$codigo])
                    ->orWhere('p.codigo', 'ilike', $codigo.'/%')
                    ->orWhere('p.codigo', 'ilike', $codigo.' /%')
                    ->orWhere('p.codigo', 'ilike', '%/ '.$codigo)
                    ->orWhere('p.codigo', 'ilike', '%/'.$codigo)
                    ->orWhere('p.codigo', 'ilike', '%/ '.$codigo.' /%')
                    ->orWhere('p.codigo', 'ilike', '%/'.$codigo.'/%')
                    ->orWhere('p.codigo', 'ilike', '% '.$codigo)
                    ->orWhere('p.codigo', 'ilike', '%'.$codigo.'%');
            })
            ->orderByRaw(
                "CASE
                    WHEN UPPER(TRIM(p.codigo)) = UPPER(?) THEN 0
                    WHEN p.codigo ILIKE ? THEN 1
                    WHEN p.codigo ILIKE ? THEN 2
                    ELSE 3
                END",
                [$codigo, $codigo.' /%', '%/ '.$codigo]
            )
            ->first([
                'p.id',
                'p.codigo',
                'p.nombre',
                'p.categoria',
                'p.subcategoria',
                'p.proveedor',
                'p.precio_unidad',
                'p.precio_mayor',
                'p.url_imagen',
            ]);

        return $row ? $this->mapProducto($row, $sede) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buscarPorPrefijoSieteDigitos(string $codigo, string $sede): array
    {
        if (config('database.default') !== 'pgsql') {
            return [];
        }

        $prefijo = $this->prefijoSieteDigitos($codigo);
        if ($prefijo === null) {
            return [];
        }

        $rows = DB::connection('pgsql')
            ->table('productos as p')
            ->where('p.activo', true)
            ->where(function ($q) use ($prefijo) {
                $q->where('p.codigo', 'ilike', $prefijo.'%')
                    ->orWhereRaw(
                        "regexp_replace(split_part(TRIM(p.codigo), '/', 1), '[^0-9]', '', 'g') LIKE ?",
                        [$prefijo.'%']
                    );
            })
            ->orderBy('p.nombre')
            ->limit(24)
            ->get([
                'p.id',
                'p.codigo',
                'p.nombre',
                'p.categoria',
                'p.subcategoria',
                'p.proveedor',
                'p.precio_unidad',
                'p.precio_mayor',
                'p.url_imagen',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->mapProducto($row, $sede);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function mapProducto(object $row, string $sede): array
    {
        $existencia = (int) (DB::connection('pgsql')
            ->table('stock_actual')
            ->where('producto_id', $row->id)
            ->where('sede', $sede)
            ->value('existencia') ?? 0);

        $primaryCode = trim(explode('/', (string) $row->codigo)[0]);
        $base = rtrim((string) env('SUPABASE_URL', 'https://hbhqbmzixgcvxkilwsau.supabase.co'), '/');
        $imgBase = $base.'/storage/v1/object/public/imagenes_producto/imagenes/';
        $imagen = trim((string) ($row->url_imagen ?? ''));
        if ($imagen === '') {
            $imagen = $imgBase.rawurlencode($primaryCode).'.jpg';
        }

        return [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'categoria' => (string) ($row->categoria ?? ''),
            'subcategoria' => (string) ($row->subcategoria ?? ''),
            'proveedor' => (string) ($row->proveedor ?? ''),
            'precio_unidad' => (float) ($row->precio_unidad ?? 0),
            'precio_mayor' => (float) ($row->precio_mayor ?? 0),
            'existencia' => $existencia,
            'sede' => $sede,
            'sede_label' => config('inventario.display.'.$sede, $sede),
            'imagen_url' => $imagen,
            'imagen_png' => $imgBase.rawurlencode($primaryCode).'.png',
            'imagen_fallback' => $imgBase.'no-image.jpg',
        ];
    }
}
