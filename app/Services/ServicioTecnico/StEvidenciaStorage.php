<?php

namespace App\Services\ServicioTecnico;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StEvidenciaStorage
{
    public const BUCKET = 'ST';

    public const DISK_LOCAL = 'public';

    public const LOCAL_DIR = 'st-evidencias';

    public function supabaseDisponible(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '';
    }

    /** @deprecated usar supabaseDisponible(); local siempre puede guardar */
    public function disponible(): bool
    {
        return true;
    }

    /**
     * @param  list<UploadedFile|null>  $imagenes
     * @return array{imagenes: list<string>, video: ?string}
     */
    public function subirDesdeRequest(array $imagenes, ?UploadedFile $video = null, ?string $prefijo = null): array
    {
        $prefijo = trim((string) $prefijo, '/');
        if ($prefijo === '') {
            $prefijo = 'ordenes/'.now()->format('Y/m');
        }

        $urls = [];
        foreach (array_values(array_filter($imagenes)) as $i => $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            if (count($urls) >= 3) {
                break;
            }
            $urls[] = $this->subirArchivo($file, $prefijo, 'img'.($i + 1));
        }

        $videoUrl = null;
        if ($video instanceof UploadedFile && $video->isValid()) {
            $videoUrl = $this->subirArchivo($video, $prefijo, 'video');
        }

        return [
            'imagenes' => $urls,
            'video' => $videoUrl,
        ];
    }

    public function subirArchivo(UploadedFile $file, string $prefijo, string $etiqueta): string
    {
        $comprimido = $this->comprimirImagenSiAplica($file);
        $usar = $comprimido['path'] ?? $file->getRealPath();
        $mime = $comprimido['mime'] ?? ($file->getMimeType() ?: 'application/octet-stream');
        $ext = $comprimido['ext'] ?? null;

        try {
            if ($this->supabaseDisponible()) {
                return $this->subirASupabase($file, $prefijo, $etiqueta, $usar, $mime, $ext);
            }

            return $this->subirLocal($file, $prefijo, $etiqueta, $usar, $ext);
        } finally {
            if (! empty($comprimido['path']) && is_file($comprimido['path'])) {
                @unlink($comprimido['path']);
            }
        }
    }

    /**
     * Baja un poco peso/resolución de fotos de evidencia para que carguen más rápido.
     *
     * @return array{path?:string,mime?:string,ext?:string}|array{}
     */
    private function comprimirImagenSiAplica(UploadedFile $file): array
    {
        if (! function_exists('imagecreatetruecolor')) {
            return [];
        }

        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $origen = $file->getRealPath();
        if (! $origen || ! is_file($origen)) {
            return [];
        }

        $src = match (true) {
            str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => @imagecreatefromjpeg($origen),
            str_contains($mime, 'png') => @imagecreatefrompng($origen),
            str_contains($mime, 'webp') && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($origen),
            default => null,
        };
        if (! $src) {
            return [];
        }

        $ancho = imagesx($src);
        $alto = imagesy($src);
        if ($ancho < 1 || $alto < 1) {
            imagedestroy($src);

            return [];
        }

        $maxLado = 1280;
        $escala = min(1.0, $maxLado / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $dst = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        if (! $dst) {
            imagedestroy($src);

            return [];
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($src);

        $tmp = tempnam(sys_get_temp_dir(), 'stimg_');
        if ($tmp === false) {
            imagedestroy($dst);

            return [];
        }
        $tmpJpg = $tmp.'.jpg';
        @unlink($tmp);
        imagejpeg($dst, $tmpJpg, 72);
        imagedestroy($dst);

        if (! is_file($tmpJpg) || filesize($tmpJpg) < 1) {
            @unlink($tmpJpg);

            return [];
        }

        return [
            'path' => $tmpJpg,
            'mime' => 'image/jpeg',
            'ext' => 'jpg',
        ];
    }

    private function subirASupabase(
        UploadedFile $file,
        string $prefijo,
        string $etiqueta,
        ?string $contenidoPath = null,
        ?string $contentType = null,
        ?string $extForzada = null,
    ): string {
        $ext = $extForzada ?: strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
        $nombre = $etiqueta.'_'.now()->format('YmdHis').'_'.Str::lower(Str::random(8)).'.'.$ext;
        $path = trim($prefijo, '/').'/'.$nombre;

        $contentType = $contentType ?: ($file->getMimeType() ?: 'application/octet-stream');
        $body = file_get_contents($contenidoPath ?: $file->getRealPath());
        if ($body === false || $body === '') {
            throw ValidationException::withMessages([
                'evidencias' => 'No se pudo leer el archivo '.$file->getClientOriginalName(),
            ]);
        }

        $uploadUrl = $this->baseUrl().'/storage/v1/object/'.self::BUCKET.'/'.$path;
        $response = Http::withoutVerifying()
            ->timeout(90)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey(),
                'Content-Type' => $contentType,
                'x-upsert' => 'true',
            ])
            ->withBody($body, $contentType)
            ->post($uploadUrl);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'evidencias' => 'Error al subir a Supabase: '.$response->body(),
            ]);
        }

        return $this->baseUrl().'/storage/v1/object/public/'.self::BUCKET.'/'.$path;
    }

    private function subirLocal(
        UploadedFile $file,
        string $prefijo,
        string $etiqueta,
        ?string $contenidoPath = null,
        ?string $extForzada = null,
    ): string {
        $ext = $extForzada ?: strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
        $nombre = $etiqueta.'_'.now()->format('YmdHis').'_'.Str::lower(Str::random(8)).'.'.$ext;
        $path = trim(self::LOCAL_DIR.'/'.trim($prefijo, '/').'/'.$nombre, '/');

        if ($contenidoPath && is_file($contenidoPath)) {
            $ok = Storage::disk(self::DISK_LOCAL)->put($path, file_get_contents($contenidoPath));
            $stored = $ok ? $path : false;
        } else {
            $stored = Storage::disk(self::DISK_LOCAL)->putFileAs(
                dirname($path),
                $file,
                basename($path)
            );
        }

        if (! $stored) {
            throw ValidationException::withMessages([
                'evidencias' => 'No se pudo guardar la evidencia en el disco local. Revisa storage/app/public.',
            ]);
        }

        return url(Storage::disk(self::DISK_LOCAL)->url($stored));
    }

    /**
     * Miniatura JPEG para listados (más liviana que el original).
     */
    public function miniaturaDesdeBinario(string $binario, int $maxLado = 480, int $calidad = 62): ?string
    {
        if ($binario === '' || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $src = @imagecreatefromstring($binario);
        if (! $src) {
            return null;
        }

        $ancho = imagesx($src);
        $alto = imagesy($src);
        if ($ancho < 1 || $alto < 1) {
            imagedestroy($src);

            return null;
        }

        $escala = min(1.0, $maxLado / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));
        $dst = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        if (! $dst) {
            imagedestroy($src);

            return null;
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($src);

        ob_start();
        imagejpeg($dst, null, max(40, min(90, $calidad)));
        imagedestroy($dst);
        $out = ob_get_clean();

        return is_string($out) && $out !== '' ? $out : null;
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) (env('SUPABASE_URL') ?: ''), '/');
        if ($url === '') {
            // Solo se usa si hay KEY; si no, cae a disco local.
            $url = rtrim((string) 'https://hbhqbmzixgcvxkilwsau.supabase.co', '/');
        }

        return $url;
    }

    private function apiKey(): string
    {
        return trim((string) (
            env('SUPABASE_KEY')
            ?: env('SUPABASE_SERVICE_ROLE_KEY')
            ?: env('SUPABASE_ANON_KEY')
            ?: ''
        ));
    }
}
