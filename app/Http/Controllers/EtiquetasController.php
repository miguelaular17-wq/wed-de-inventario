<?php

namespace App\Http\Controllers;

use App\Services\EtiquetasPrecioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EtiquetasController extends Controller
{
    public function __construct(
        private EtiquetasPrecioService $etiquetas,
    ) {}

    public function index(Request $request): View
    {
        $this->autorizar($request);

        return view('etiquetas.index', [
            'tamanos' => EtiquetasPrecioService::TAMANOS,
            'medidaMin' => EtiquetasPrecioService::MEDIDA_MIN,
            'medidaMax' => EtiquetasPrecioService::MEDIDA_MAX,
            'hojaUtil' => EtiquetasPrecioService::HOJA_UTIL,
            'separacion' => EtiquetasPrecioService::SEPARACION,
            'tipos' => EtiquetasPrecioService::TIPOS,
            'specsPc' => EtiquetasPrecioService::SPECS_PC,
            'tiposConSpecs' => EtiquetasPrecioService::TIPOS_CON_SPECS,
            'recargoRegular' => EtiquetasPrecioService::RECARGO_REGULAR,
            'maxEtiquetas' => EtiquetasPrecioService::MAX_ETIQUETAS,
        ]);
    }

    public function buscar(Request $request): JsonResponse
    {
        $this->autorizar($request);
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);

        return response()->json($this->etiquetas->buscar($q));
    }

    public function pdf(Request $request): Response
    {
        $this->autorizar($request);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.id' => ['required', 'integer'],
            'items.*.copias' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.specs' => ['nullable', 'array'],
            'items.*.specs.*' => ['nullable', 'string', 'max:40'],
            'tipo' => ['nullable', Rule::in(array_keys(EtiquetasPrecioService::TIPOS))],
            'cantidad_mayor' => ['nullable', 'integer', 'min:2', 'max:999'],
            'ancho_mm' => ['nullable', 'numeric', 'min:' . EtiquetasPrecioService::MEDIDA_MIN['ancho'], 'max:' . EtiquetasPrecioService::MEDIDA_MAX['ancho']],
            'alto_mm' => ['nullable', 'numeric', 'min:' . EtiquetasPrecioService::MEDIDA_MIN['alto'], 'max:' . EtiquetasPrecioService::MEDIDA_MAX['alto']],
            'recargo_regular' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $tipo = $data['tipo'] ?? 'clasica';
        $esTecnoHouse = str_starts_with($tipo, 'th_');
        if (! in_array($tipo, EtiquetasPrecioService::TIPOS_CON_SPECS, true)) {
            foreach ($data['items'] as &$item) {
                unset($item['specs']);
            }
            unset($item);
        }

        $fichas = $this->etiquetas->armarEtiquetas(
            $data['items'],
            (int) ($data['cantidad_mayor'] ?? 3),
            (float) ($data['recargo_regular'] ?? EtiquetasPrecioService::RECARGO_REGULAR),
        );
        if ($fichas === []) {
            return back()->with('error', 'Ninguno de los productos de la lista está activo.');
        }

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $tamano = $this->etiquetas->tamano(
            $tipo,
            isset($data['ancho_mm']) ? (float) $data['ancho_mm'] : null,
            isset($data['alto_mm']) ? (float) $data['alto_mm'] : null,
        );
        $porPagina = $tamano['columnas'] * $tamano['filas'];

        $pdf = app('dompdf.wrapper');
        $metricas = $pdf->getDomPDF()->getFontMetrics();
        $fuente = $metricas->getFont('DejaVu Sans', 'bold');
        $anchoPt = fn (string $texto, float $fs) => (float) $metricas->getTextWidth($texto, $fuente, $fs);
        $altoLineaPt = fn (float $fs) => (float) $metricas->getFontHeight($fuente, $fs);

        $pdf->loadView('etiquetas.pdf', [
            'paginas' => array_chunk($fichas, $porPagina),
            'ajustarNombre' => fn (string $nombre, float $anchoMm, float $altoMm, array $tamanos, int $maxLineas = 3, bool $mayusculas = true)
                => $this->etiquetas->ajustarNombre($nombre, $anchoPt, $altoLineaPt, $anchoMm, $altoMm, $tamanos, $maxLineas, $mayusculas),
            'anchoTextoMm' => fn (string $texto, float $fs) => $anchoPt($texto, $fs) * 25.4 / 72,
            'tipo' => $tipo,
            'tamano' => $tamano,
            'logo' => $this->imagenDataUri(public_path('logo.png')),
            'fondo' => $esTecnoHouse
                ? $this->fondoTecnoHouse($tamano['ancho'], $tamano['alto'])
                : $this->fondoDegradado($tamano['ancho'], $tamano['alto']),
            'fecha' => now()->format('d/m/Y'),
            'web' => EtiquetasPrecioService::WEB,
            'logosMarca' => $tipo !== 'clasica' && $tipo !== 'th_clasica' ? $this->logosMarca($fichas) : [],
            'logoTh' => $esTecnoHouse ? $this->imagenConRatio(public_path('img/tecno_house.png')) : null,
            'iconosTh' => $esTecnoHouse ? $this->iconosTecnoHouse() : [],
        ])->setPaper('letter', 'portrait');

        return $pdf->stream('etiquetas_' . now()->format('Ymd_His') . '.pdf');
    }

    private function autorizar(Request $request): void
    {
        // Sin sesión se permite (igual que los precios del catálogo); con sesión se respeta el permiso de ver precios.
        $user = $request->user();
        abort_if($user && ! $user->canSeeCatalogoPrecios(), 403, 'No tienes permiso para ver precios del catálogo.');
    }

    /**
     * @param  list<array<string, mixed>>  $fichas
     * @return array<string, array{src: string, ratio: float}>
     */
    private function logosMarca(array $fichas): array
    {
        $out = [];
        foreach (array_unique(array_filter(array_column($fichas, 'logo_marca'))) as $clave) {
            $path = public_path("img/marcas/{$clave}.png");
            $info = @getimagesize($path);
            $src = $this->imagenDataUri($path);
            if ($info && $src) {
                $out[$clave] = ['src' => $src, 'ratio' => $info[0] / max(1, $info[1])];
            }
        }

        return $out;
    }

    /**
     * @return array{src: string, ratio: float}|null
     */
    private function imagenConRatio(string $path): ?array
    {
        $info = @getimagesize($path);
        $src = $this->imagenDataUri($path);

        return $info && $src ? ['src' => $src, 'ratio' => $info[0] / max(1, $info[1])] : null;
    }

    /**
     * @return array<string, string>
     */
    private function iconosTecnoHouse(): array
    {
        $out = [];
        foreach (glob(public_path('img/etiquetas_th/*.png')) ?: [] as $path) {
            $out[basename($path, '.png')] = (string) $this->imagenDataUri($path);
        }

        return $out;
    }

    /**
     * Fondo Tecno House: azul noche con destellos diagonales cian y azul, más intensos hacia los bordes.
     * Se genera una vez por medida (150 dpi) porque Dompdf no dibuja degradados.
     */
    private function fondoTecnoHouse(float $anchoMm, float $altoMm): ?string
    {
        $w = (int) ceil($anchoMm / 25.4 * 150);
        $h = (int) ceil($altoMm / 25.4 * 150);
        $path = storage_path("app/etiquetas/fondo_th_v1_{$w}x{$h}.png");
        if (is_file($path)) {
            return $this->imagenDataUri($path);
        }
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $theta = deg2rad(58);
        [$nx, $ny] = [sin($theta), cos($theta)];
        $rango = $w * $nx + $h * $ny;
        // [posición (0–1 del recorrido diagonal), ancho (fracción), intensidad, color]
        $cian = [34, 211, 238];
        $azul = [37, 99, 235];
        $blanco = [224, 242, 254];
        $bandas = [
            [0.10, 0.050, 0.35, $azul], [0.30, 0.040, 0.25, $azul], [0.70, 0.050, 0.30, $azul], [0.90, 0.045, 0.35, $azul],
            [0.07, 0.012, 0.60, $cian], [0.17, 0.008, 0.70, $cian], [0.62, 0.006, 0.45, $cian], [0.79, 0.010, 0.60, $cian], [0.94, 0.012, 0.65, $cian],
            [0.11, 0.0025, 0.80, $blanco], [0.21, 0.0020, 0.65, $blanco], [0.81, 0.0020, 0.75, $blanco], [0.91, 0.0025, 0.60, $blanco],
        ];

        $img = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $dx = ($x - $w * 0.5) / ($w * 0.65);
                $dy = ($y - $h * 0.35) / ($h * 0.75);
                $brillo = exp(-($dx * $dx + $dy * $dy));
                $r = 3 + 8 * $brillo;
                $g = 14 + 34 * $brillo;
                $b = 48 + 76 * $brillo;

                $borde = min(1.0, abs($x - $w / 2) / ($w / 2));
                $mascara = 0.3 + 0.7 * $borde ** 1.4;
                $p = ($x * $nx + $y * $ny) / $rango;
                foreach ($bandas as [$p0, $ancho, $int, $col]) {
                    $d = ($p - $p0) / $ancho;
                    if ($d > 3 || $d < -3) {
                        continue;
                    }
                    $f = $int * exp(-$d * $d) * $mascara;
                    $r += $col[0] * $f;
                    $g += $col[1] * $f;
                    $b += $col[2] * $f;
                }
                imagesetpixel($img, $x, $y, imagecolorallocate($img, (int) min(255, $r), (int) min(255, $g), (int) min(255, $b)));
            }
        }

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        imagepng($img, $path);
        imagedestroy($img);

        return $this->imagenDataUri($path);
    }

    private function imagenDataUri(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
    }

    /**
     * Dompdf no dibuja linear-gradient ni escala fondos (background-size): se genera una vez
     * un PNG rosa → morado → azul al tamaño exacto de la etiqueta (96 dpi).
     */
    private function fondoDegradado(float $anchoMm, float $altoMm): ?string
    {
        $w = (int) ceil($anchoMm / 25.4 * 96);
        $h = (int) ceil($altoMm / 25.4 * 96);
        $path = storage_path("app/etiquetas/fondo_v1_{$w}x{$h}.png");
        if (is_file($path)) {
            return $this->imagenDataUri($path);
        }
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $img = imagecreatetruecolor($w, $h);
        $paradas = [[0.0, [236, 72, 153]], [0.5, [147, 51, 234]], [1.0, [59, 130, 246]]];
        $diag = $w + $h;
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $t = ($x + ($h - $y)) / $diag;
                [$t0, $c0] = $t <= 0.5 ? $paradas[0] : $paradas[1];
                [$t1, $c1] = $t <= 0.5 ? $paradas[1] : $paradas[2];
                $k = ($t - $t0) / ($t1 - $t0);
                $color = imagecolorallocate(
                    $img,
                    (int) round($c0[0] + ($c1[0] - $c0[0]) * $k),
                    (int) round($c0[1] + ($c1[1] - $c0[1]) * $k),
                    (int) round($c0[2] + ($c1[2] - $c0[2]) * $k),
                );
                imagesetpixel($img, $x, $y, $color);
            }
        }

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        imagepng($img, $path);
        imagedestroy($img);

        return $this->imagenDataUri($path);
    }
}
