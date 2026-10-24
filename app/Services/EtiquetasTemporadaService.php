<?php

namespace App\Services;

/**
 * Toque de temporada para las etiquetas (Halloween / Navidad): colores del fondo,
 * adornos dibujados en el fondo (murciélagos, telarañas, copos) y el gorro sobre el logo.
 * Todo se dibuja con GD porque Dompdf no muestra emojis ni degradados.
 */
class EtiquetasTemporadaService
{
    public const TEMAS = [
        'ninguno' => 'Sin temporada',
        'halloween' => '🎃 Halloween',
        'navidad' => '🎄 Navidad',
    ];

    /** Degradado de la etiqueta clásica / locura / PC según el tema. */
    public function paradas(string $tema): array
    {
        return match ($tema) {
            'halloween' => [[0.0, [249, 115, 22]], [0.5, [126, 34, 206]], [1.0, [28, 25, 23]]],
            'navidad' => [[0.0, [220, 38, 38]], [0.5, [153, 27, 27]], [1.0, [21, 128, 61]]],
            default => [[0.0, [236, 72, 153]], [0.5, [147, 51, 234]], [1.0, [59, 130, 246]]],
        };
    }

    /** Archivo del gorro que va sobre el logo (public/img/temporada). */
    public function gorro(string $tema): ?string
    {
        $archivo = match ($tema) {
            'halloween' => 'sombrero_bruja.png',
            'navidad' => 'gorro_navidad.png',
            default => null,
        };

        return $archivo && is_file(public_path("img/temporada/{$archivo}")) ? public_path("img/temporada/{$archivo}") : null;
    }

    /**
     * Adornos sobre un fondo ya pintado. $oscuro: el fondo es azul noche (Tecno House).
     *
     * @param  \GdImage  $img
     */
    public function decorar($img, int $w, int $h, string $tema, bool $oscuro = false): void
    {
        if ($tema === 'ninguno') {
            return;
        }
        imagealphablending($img, true);
        mt_srand(20261031 + $w * 7 + $h);
        $u = min($w, $h);

        if ($tema === 'halloween') {
            $tela = imagecolorallocatealpha($img, 255, 255, 255, $oscuro ? 70 : 60);
            $this->telarana($img, 0, 0, (int) ($u * 0.32), 1, 1, $tela);
            $this->telarana($img, $w - 1, $h - 1, (int) ($u * 0.28), -1, -1, $tela);
            $color = $oscuro ? imagecolorallocatealpha($img, 249, 115, 22, 25) : imagecolorallocatealpha($img, 12, 10, 9, 30);
            foreach ([[0.80, 0.10, 0.10], [0.62, 0.05, 0.07], [0.93, 0.30, 0.06], [0.08, 0.55, 0.07], [0.30, 0.94, 0.06], [0.70, 0.62, 0.05]] as [$fx, $fy, $fs]) {
                $this->murcielago($img, (int) ($w * $fx), (int) ($h * $fy), (int) ($u * $fs * 1.2), $color);
            }
            $estrella = imagecolorallocatealpha($img, 254, 215, 170, 40);
            for ($i = 0; $i < 18; $i++) {
                $r = mt_rand(1, max(2, (int) ($u * 0.006)));
                imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), $r * 2, $r * 2, $estrella);
            }

            return;
        }

        $copo = imagecolorallocatealpha($img, 255, 255, 255, 45);
        $punto = imagecolorallocatealpha($img, 255, 255, 255, 60);
        foreach ([[0.06, 0.08, 0.055], [0.94, 0.09, 0.05], [0.5, 0.04, 0.03], [0.04, 0.6, 0.04], [0.96, 0.55, 0.045], [0.25, 0.96, 0.035], [0.78, 0.95, 0.04], [0.35, 0.3, 0.025], [0.68, 0.36, 0.03]] as [$fx, $fy, $fs]) {
            $this->copo($img, (int) ($w * $fx), (int) ($h * $fy), (int) ($u * $fs), $copo);
        }
        for ($i = 0; $i < 40; $i++) {
            $r = mt_rand(1, max(2, (int) ($u * 0.008)));
            imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), $r * 2, $r * 2, $punto);
        }
        $oro = imagecolorallocatealpha($img, 250, 204, 21, 35);
        for ($i = 0; $i < 10; $i++) {
            $this->estrella($img, mt_rand(0, $w), mt_rand(0, $h), max(3, (int) ($u * 0.012)), $oro);
        }
    }

    /**
     * Genera los gorros (una vez) en public/img/temporada.
     */
    public function generarGorros(): void
    {
        $dir = public_path('img/temporada');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->guardarReducido($this->dibujarGorroNavidad(), "{$dir}/gorro_navidad.png", 200);
        $this->guardarReducido($this->dibujarSombreroBruja(), "{$dir}/sombrero_bruja.png", 200);
    }

    /** Mosaico transparente con los adornos, para la vista previa en pantalla. */
    public function generarPatrones(): void
    {
        foreach (['halloween', 'navidad'] as $tema) {
            foreach ([false, true] as $oscuro) {
                $img = imagecreatetruecolor(480, 400);
                imagesavealpha($img, true);
                imagealphablending($img, false);
                imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
                $this->decorar($img, 480, 400, $tema, $oscuro);
                imagepng($img, public_path('img/temporada/patron_' . $tema . ($oscuro ? '_oscuro' : '') . '.png'), 9);
                imagedestroy($img);
            }
        }
    }

    private function telarana($img, int $cx, int $cy, int $r, int $sx, int $sy, int $color): void
    {
        imagesetthickness($img, max(1, (int) ($r / 90)));
        $angulos = [0, 18, 36, 54, 72, 90];
        foreach ($angulos as $a) {
            $rad = deg2rad($a);
            imageline($img, $cx, $cy, (int) ($cx + $sx * cos($rad) * $r), (int) ($cy + $sy * sin($rad) * $r), $color);
        }
        foreach ([0.3, 0.52, 0.74, 0.96] as $f) {
            for ($i = 0; $i < count($angulos) - 1; $i++) {
                $a1 = deg2rad($angulos[$i]);
                $a2 = deg2rad($angulos[$i + 1]);
                $rr = $r * $f;
                // Hilo combado hacia la esquina entre dos radios.
                $prev = null;
                for ($t = 0; $t <= 1.0001; $t += 0.25) {
                    $a = $a1 + ($a2 - $a1) * $t;
                    $comba = 1 - 0.12 * sin(M_PI * $t);
                    $p = [(int) ($cx + $sx * cos($a) * $rr * $comba), (int) ($cy + $sy * sin($a) * $rr * $comba)];
                    if ($prev) {
                        imageline($img, $prev[0], $prev[1], $p[0], $p[1], $color);
                    }
                    $prev = $p;
                }
            }
        }
        imagesetthickness($img, 1);
    }

    private function murcielago($img, int $cx, int $cy, int $s, int $color): void
    {
        $der = [[0, -0.14], [0.06, -0.3], [0.1, -0.12], [0.3, -0.3], [0.55, -0.32], [0.98, -0.5], [0.86, -0.18], [0.78, 0.08], [0.6, -0.02], [0.48, 0.18], [0.32, 0.02], [0.16, 0.22], [0, 0.12]];
        $pts = [];
        foreach ($der as [$x, $y]) {
            $pts[] = (int) ($cx + $x * $s / 2);
            $pts[] = (int) ($cy + $y * $s / 2);
        }
        foreach (array_reverse(array_slice($der, 1, -1)) as [$x, $y]) {
            $pts[] = (int) ($cx - $x * $s / 2);
            $pts[] = (int) ($cy + $y * $s / 2);
        }
        imagefilledpolygon($img, $pts, $color);
    }

    private function copo($img, int $cx, int $cy, int $r, int $color): void
    {
        imagesetthickness($img, max(1, (int) ($r / 9)));
        for ($i = 0; $i < 6; $i++) {
            $a = $i * M_PI / 3;
            [$ex, $ey] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            imageline($img, $cx, $cy, (int) $ex, (int) $ey, $color);
            foreach ([0.55, 0.8] as $f) {
                [$bx, $by] = [$cx + cos($a) * $r * $f, $cy + sin($a) * $r * $f];
                $l = $r * 0.28 * (1.15 - $f * 0.5);
                foreach ([-0.7, 0.7] as $da) {
                    imageline($img, (int) $bx, (int) $by, (int) ($bx + cos($a + $da) * $l), (int) ($by + sin($a + $da) * $l), $color);
                }
            }
        }
        imagesetthickness($img, 1);
    }

    private function estrella($img, int $cx, int $cy, int $r, int $color): void
    {
        $pts = [];
        for ($i = 0; $i < 8; $i++) {
            $rr = $i % 2 === 0 ? $r : $r * 0.35;
            $a = -M_PI / 2 + $i * M_PI / 4;
            $pts[] = (int) ($cx + cos($a) * $rr);
            $pts[] = (int) ($cy + sin($a) * $rr);
        }
        imagefilledpolygon($img, $pts, $color);
    }

    private function lienzo(int $w, int $h)
    {
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);

        return $img;
    }

    /** Gorro de Papá Noel inclinado, ala abajo (lienzo 800×560, ala centrada en y≈470). */
    private function dibujarGorroNavidad()
    {
        $img = $this->lienzo(800, 560);
        $rojo = imagecolorallocate($img, 220, 38, 38);
        $rojoOsc = imagecolorallocate($img, 153, 27, 27);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        $gris = imagecolorallocate($img, 226, 232, 240);

        imagefilledpolygon($img, [150, 460, 230, 220, 380, 90, 560, 70, 700, 170, 730, 300, 660, 300, 600, 200, 520, 190, 600, 460], $rojo);
        imagefilledpolygon($img, [470, 460, 540, 230, 600, 200, 600, 460], $rojoOsc);
        imagefilledellipse($img, 705, 330, 120, 120, $gris);
        imagefilledellipse($img, 700, 322, 104, 104, $blanco);
        imagefilledrectangle($img, 130, 420, 670, 520, $blanco);
        imagefilledellipse($img, 130, 470, 100, 100, $blanco);
        imagefilledellipse($img, 670, 470, 100, 100, $blanco);
        imagefilledrectangle($img, 130, 495, 670, 520, $gris);

        return $img;
    }

    /** Sombrero de bruja (lienzo 800×560, ala en y≈470). */
    private function dibujarSombreroBruja()
    {
        $img = $this->lienzo(800, 560);
        $morado = imagecolorallocate($img, 46, 16, 101);
        $moradoOsc = imagecolorallocate($img, 28, 10, 62);
        $naranja = imagecolorallocate($img, 249, 115, 22);
        $oro = imagecolorallocate($img, 250, 204, 21);

        imagefilledellipse($img, 400, 470, 780, 150, $moradoOsc);
        imagefilledellipse($img, 400, 462, 740, 120, $morado);
        imagefilledpolygon($img, [220, 450, 300, 280, 370, 150, 470, 40, 620, 10, 540, 90, 500, 200, 560, 450], $morado);
        imagefilledpolygon($img, [440, 450, 470, 230, 500, 200, 560, 450], $moradoOsc);
        imagefilledpolygon($img, [236, 410, 258, 360, 552, 360, 562, 410], $naranja);
        imagesetthickness($img, 14);
        imagerectangle($img, 370, 352, 440, 418, $oro);

        return $img;
    }

    private function guardarReducido($img, string $path, int $anchoFinal): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $hf = (int) round($h * $anchoFinal / $w);
        $out = imagecreatetruecolor($anchoFinal, $hf);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $anchoFinal, $hf, $w, $h);
        imagepng($out, $path, 9);
        imagedestroy($img);
        imagedestroy($out);
    }
}
