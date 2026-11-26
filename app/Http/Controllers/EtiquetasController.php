<?php

namespace App\Http\Controllers;

use App\Services\EtiquetasPrecioService;
use App\Services\EtiquetasTemporadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EtiquetasController extends Controller
{
    public function __construct(
        private EtiquetasPrecioService $etiquetas,
        private EtiquetasTemporadaService $temporada,
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
            'temas' => EtiquetasTemporadaService::TEMAS,
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
            'items.*.precio_unidad' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.precio_mayor' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.cantidad_mayor' => ['nullable', 'integer', 'min:2', 'max:999'],
            'items.*.texto_unidad' => ['nullable', 'string', 'max:' . EtiquetasPrecioService::TEXTO_UNIDAD_MAX],
            'tipo' => ['nullable', Rule::in(array_keys(EtiquetasPrecioService::TIPOS))],
            'cantidad_mayor' => ['nullable', 'integer', 'min:2', 'max:999'],
            'ancho_mm' => ['nullable', 'numeric', 'min:' . EtiquetasPrecioService::MEDIDA_MIN['ancho'], 'max:' . EtiquetasPrecioService::MEDIDA_MAX['ancho']],
            'alto_mm' => ['nullable', 'numeric', 'min:' . EtiquetasPrecioService::MEDIDA_MIN['alto'], 'max:' . EtiquetasPrecioService::MEDIDA_MAX['alto']],
            'recargo_regular' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tema' => ['nullable', Rule::in(array_keys(EtiquetasTemporadaService::TEMAS))],
        ]);

        $tipo = $data['tipo'] ?? 'clasica';
        $tema = $data['tema'] ?? 'ninguno';
        $esTecnoHouse = str_starts_with($tipo, 'th_');
        $esMovistar = $tipo === 'movistar';
        if ($esMovistar) {
            $tema = 'ninguno';
        }
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
            'fondo' => $esMovistar
                ? $this->fondoMovistar($tamano['ancho'], $tamano['alto'])
                : ($esTecnoHouse
                    ? $this->fondoTecnoHouse($tamano['ancho'], $tamano['alto'], $tema)
                    : $this->fondoDegradado($tamano['ancho'], $tamano['alto'], $tema)),
            'tema' => $tema,
            'gorro' => $esMovistar ? null : (($g = $this->temporada->gorro($tema)) ? $this->imagenConRatio($g) : null),
            'fecha' => now()->format('d/m/Y'),
            'web' => EtiquetasPrecioService::WEB,
            'logosMarca' => $tipo !== 'clasica' && $tipo !== 'th_clasica' && ! $esMovistar ? $this->logosMarca($fichas) : [],
            'logoTh' => $esTecnoHouse ? $this->imagenConRatio(public_path('img/tecno_house.png')) : null,
            'iconosTh' => $esTecnoHouse ? $this->iconosTecnoHouse() : [],
            'logoMovistar' => $esMovistar ? $this->logoMovistar() : null,
            'logoCashea' => $esMovistar ? $this->logoCashea() : null,
            'headerLocura' => $tipo === 'locura' ? $this->headerLocura() : null,
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
        foreach (array_unique(array_filter([...array_column($fichas, 'logo_marca'), ...array_column($fichas, 'logo_sistema')])) as $clave) {
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
    private function fondoTecnoHouse(float $anchoMm, float $altoMm, string $tema = 'ninguno'): ?string
    {
        $w = (int) ceil($anchoMm / 25.4 * 150);
        $h = (int) ceil($altoMm / 25.4 * 150);
        $sufijo = $tema === 'ninguno' ? '' : "_{$tema}";
        $path = storage_path("app/etiquetas/fondo_th_v1_{$w}x{$h}{$sufijo}.png");
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
        $this->temporada->decorar($img, $w, $h, $tema, true);

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        imagepng($img, $path);
        imagedestroy($img);

        return $this->imagenDataUri($path);
    }

    /**
     * Fondo Movistar: azul con planos diagonales (como el arte de referencia). Dompdf no dibuja degradados.
     */
    private function fondoMovistar(float $anchoMm, float $altoMm): ?string
    {
        $w = (int) ceil($anchoMm / 25.4 * 150);
        $h = (int) ceil($altoMm / 25.4 * 150);
        $path = storage_path("app/etiquetas/fondo_movistar_v1_{$w}x{$h}.png");
        if (is_file($path)) {
            return $this->imagenDataUri($path);
        }
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $img = imagecreatetruecolor($w, $h);
        // Base: azul Movistar oscuro → cian.
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $t = ($x * 0.55 + $y * 0.45) / max(1, $w + $h);
                $r = (int) round(0 + (0 - 0) * $t + 8);
                $g = (int) round(85 + (169 - 85) * $t);
                $b = (int) round(150 + (224 - 150) * $t);
                imagesetpixel($img, $x, $y, imagecolorallocate($img, $r, $g, $b));
            }
        }

        // Planos diagonales translúcidos (forma de “M” abstracta / ribbons).
        $capas = [
            [[0.00, 0.00], [0.42, 0.00], [0.18, 1.00], [0.00, 1.00], [0, 90, 160, 55]],
            [[0.28, 0.00], [0.72, 0.00], [0.48, 1.00], [0.08, 1.00], [0, 140, 200, 70]],
            [[0.55, 0.00], [1.00, 0.00], [1.00, 0.55], [0.70, 1.00], [20, 170, 220, 80]],
            [[0.00, 0.35], [0.35, 0.00], [0.55, 0.00], [0.15, 0.70], [0, 120, 190, 90]],
            [[0.62, 0.00], [1.00, 0.00], [1.00, 0.40], [0.78, 0.55], [80, 200, 235, 100]],
            [[0.10, 0.55], [0.40, 0.20], [0.52, 0.35], [0.22, 0.85], [0, 155, 210, 75]],
        ];
        foreach ($capas as $poly) {
            $alpha = array_pop($poly);
            $a = (int) $alpha[3];
            $col = imagecolorallocatealpha($img, (int) $alpha[0], (int) $alpha[1], (int) $alpha[2], $a);
            $pts = [];
            foreach ($poly as [$px, $py]) {
                $pts[] = (int) round($px * $w);
                $pts[] = (int) round($py * $h);
            }
            imagefilledpolygon($img, $pts, $col);
        }

        // Destellos diagonales finos.
        $cian = imagecolorallocatealpha($img, 120, 220, 255, 85);
        for ($i = -2; $i < 8; $i++) {
            $x0 = (int) ($w * (0.05 + $i * 0.14));
            imageline($img, $x0, 0, $x0 + (int) ($w * 0.22), $h, $cian);
            imageline($img, $x0 + 1, 0, $x0 + 1 + (int) ($w * 0.22), $h, $cian);
        }

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        imagepng($img, $path);
        imagedestroy($img);

        return $this->imagenDataUri($path);
    }

    /** Header gráfico «PRECIOS DE LOCURA» + logo PD (fondo negro ya limpio). */
    private function headerLocura(): ?array
    {
        $path = storage_path('app/etiquetas/locura/header_limpia.png');
        if (! is_file($path)) {
            $path = public_path('img/etiquetas/locura_header.png');
        }

        return $this->imagenConRatio($path);
    }

    /**
     * @return array{src: string, ratio: float}|null
     */
    private function logoMovistar(): ?array
    {
        $path = storage_path('app/etiquetas/logo_movistar_v1.png');
        if (! is_file($path) && function_exists('imagecreatetruecolor')) {
            $w = 520;
            $h = 140;
            $img = imagecreatetruecolor($w, $h);
            imagesavealpha($img, true);
            imagealphablending($img, false);
            $trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
            imagefilledrectangle($img, 0, 0, $w, $h, $trans);
            imagealphablending($img, true);

            // Cinta “M” Movistar (aprox. del isotipo).
            $azul = imagecolorallocate($img, 0, 169, 224);
            $claro = imagecolorallocate($img, 90, 210, 245);
            $m = [
                8, 118, 28, 22, 52, 22, 78, 78, 104, 22, 128, 22, 148, 118,
                128, 118, 116, 48, 90, 100, 66, 100, 40, 48, 28, 118,
            ];
            imagefilledpolygon($img, $m, $azul);
            $brillo = [40, 48, 52, 22, 66, 22, 78, 48, 66, 78, 52, 78];
            imagefilledpolygon($img, $brillo, $claro);

            $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
            if (is_file($font) && function_exists('imagettftext')) {
                $blanco = imagecolorallocate($img, 255, 255, 255);
                imagettftext($img, 52, 0, 170, 95, $blanco, $font, 'movistar');
            }

            if (! is_dir(dirname($path))) {
                @mkdir(dirname($path), 0775, true);
            }
            imagepng($img, $path);
            imagedestroy($img);
        }

        return $this->imagenConRatio($path);
    }

    /**
     * @return array{src: string, ratio: float}|null
     */
    private function logoCashea(): ?array
    {
        $path = storage_path('app/etiquetas/logo_cashea_v1.png');
        if (! is_file($path) && function_exists('imagecreatetruecolor')) {
            $w = 360;
            $h = 90;
            $img = imagecreatetruecolor($w, $h);
            imagesavealpha($img, true);
            imagealphablending($img, false);
            $trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
            imagefilledrectangle($img, 0, 0, $w, $h, $trans);
            imagealphablending($img, true);

            $amarillo = imagecolorallocate($img, 255, 214, 0);
            $negro = imagecolorallocate($img, 20, 20, 20);
            imagefilledrectangle($img, 4, 8, 76, 80, $amarillo);
            // Esquinas redondeadas simples.
            $r = 14;
            imagefilledellipse($img, 4 + $r, 8 + $r, $r * 2, $r * 2, $amarillo);
            imagefilledellipse($img, 76 - $r, 8 + $r, $r * 2, $r * 2, $amarillo);
            imagefilledellipse($img, 4 + $r, 80 - $r, $r * 2, $r * 2, $amarillo);
            imagefilledellipse($img, 76 - $r, 80 - $r, $r * 2, $r * 2, $amarillo);

            $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
            if (is_file($font) && function_exists('imagettftext')) {
                imagettftext($img, 42, 0, 22, 62, $negro, $font, 'C');
                imagettftext($img, 36, 0, 92, 60, $negro, $font, 'cashea');
            }

            if (! is_dir(dirname($path))) {
                @mkdir(dirname($path), 0775, true);
            }
            imagepng($img, $path);
            imagedestroy($img);
        }

        return $this->imagenConRatio($path);
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
     * el PNG de diseño (palmeras / rosa→morado→cian) al tamaño exacto de la etiqueta.
     * Temporada (Halloween/Navidad): se mantiene el degradado temático + adornos.
     */
    private function fondoDegradado(float $anchoMm, float $altoMm, string $tema = 'ninguno'): ?string
    {
        $dpi = $tema === 'ninguno' ? 96 : 150;
        $w = (int) ceil($anchoMm / 25.4 * $dpi);
        $h = (int) ceil($altoMm / 25.4 * $dpi);
        $sufijo = $tema === 'ninguno' ? '' : "_{$tema}";
        $path = storage_path("app/etiquetas/fondo_v2_{$w}x{$h}{$sufijo}.png");
        if (is_file($path)) {
            return $this->imagenDataUri($path);
        }
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $img = imagecreatetruecolor($w, $h);
        $base = storage_path('app/etiquetas/fondo_base_v1.png');
        if ($tema === 'ninguno' && is_file($base) && ($src = @imagecreatefrompng($base))) {
            imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
            imagedestroy($src);
        } else {
            $paradas = $this->temporada->paradas($tema);
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
        }
        $this->temporada->decorar($img, $w, $h, $tema);

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        imagepng($img, $path);
        imagedestroy($img);

        return $this->imagenDataUri($path);
    }
}
