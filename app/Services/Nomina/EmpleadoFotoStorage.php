<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpleado;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Foto del empleado: recorte cuadrado centrado, JPEG liviano y subida al bucket público "user" de Supabase.
 * Sin credenciales de Supabase se guarda en el disco "public" local.
 */
class EmpleadoFotoStorage
{
    public const BUCKET = 'user';

    public const LADO = 600;

    public function guardar(NominaEmpleado $empleado, UploadedFile $foto): string
    {
        $jpeg = $this->recortarCuadrado($foto);
        $nombre = 'empleado_'.$empleado->id.'_'.now()->format('YmdHis').'_'.Str::lower(Str::random(6)).'.jpg';

        $url = $this->supabaseDisponible()
            ? $this->subirASupabase($nombre, $jpeg)
            : $this->guardarLocal($nombre, $jpeg);

        $anterior = $empleado->foto_url;
        $empleado->forceFill(['foto_url' => $url])->save();
        $this->borrar($anterior);

        return $url;
    }

    public function quitar(NominaEmpleado $empleado): void
    {
        $anterior = $empleado->foto_url;
        $empleado->forceFill(['foto_url' => null])->save();
        $this->borrar($anterior);
    }

    private function recortarCuadrado(UploadedFile $foto): string
    {
        $binario = (string) file_get_contents($foto->getRealPath());
        $src = $binario !== '' && function_exists('imagecreatefromstring') ? @imagecreatefromstring($binario) : false;
        if (! $src) {
            throw ValidationException::withMessages(['foto' => 'No se pudo leer la imagen. Usa una foto JPG, PNG o WEBP.']);
        }

        $src = $this->corregirOrientacion($src, $foto);
        $ancho = imagesx($src);
        $alto = imagesy($src);
        $lado = min($ancho, $alto);
        // Retratos: el recorte se sube un poco para no cortar la cabeza.
        $x = (int) (($ancho - $lado) / 2);
        $y = $alto > $ancho ? (int) (($alto - $lado) * 0.25) : (int) (($alto - $lado) / 2);

        $destino = min(self::LADO, $lado);
        $dst = imagecreatetruecolor($destino, $destino);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, $x, $y, $destino, $destino, $lado, $lado);
        imagedestroy($src);

        ob_start();
        imagejpeg($dst, null, 82);
        imagedestroy($dst);

        return (string) ob_get_clean();
    }

    /** Fotos de celular vienen giradas según el EXIF. */
    private function corregirOrientacion(\GdImage $src, UploadedFile $foto): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_contains(strtolower((string) $foto->getMimeType()), 'jpeg')) {
            return $src;
        }
        $exif = @exif_read_data($foto->getRealPath());
        $rotar = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($rotar === 0) {
            return $src;
        }
        $girada = imagerotate($src, $rotar, 0);
        if ($girada) {
            imagedestroy($src);

            return $girada;
        }

        return $src;
    }

    private function subirASupabase(string $nombre, string $jpeg): string
    {
        $response = Http::withoutVerifying()
            ->timeout(60)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey(),
                'Content-Type' => 'image/jpeg',
                'x-upsert' => 'true',
            ])
            ->withBody($jpeg, 'image/jpeg')
            ->post($this->baseUrl().'/storage/v1/object/'.self::BUCKET.'/'.$nombre);

        if (! $response->successful()) {
            throw ValidationException::withMessages(['foto' => 'No se pudo subir la foto a Supabase: '.$response->body()]);
        }

        return $this->baseUrl().'/storage/v1/object/public/'.self::BUCKET.'/'.$nombre;
    }

    private function guardarLocal(string $nombre, string $jpeg): string
    {
        $path = 'empleados/'.$nombre;
        if (! Storage::disk('public')->put($path, $jpeg)) {
            throw ValidationException::withMessages(['foto' => 'No se pudo guardar la foto en el disco local.']);
        }

        return url(Storage::disk('public')->url($path));
    }

    private function borrar(?string $url): void
    {
        if (! $url) {
            return;
        }

        $prefijo = $this->baseUrl().'/storage/v1/object/public/'.self::BUCKET.'/';
        if (str_starts_with($url, $prefijo) && $this->supabaseDisponible()) {
            try {
                Http::withoutVerifying()
                    ->timeout(20)
                    ->withHeaders(['Authorization' => 'Bearer '.$this->apiKey()])
                    ->delete($this->baseUrl().'/storage/v1/object/'.self::BUCKET.'/'.substr($url, strlen($prefijo)));
            } catch (\Throwable $e) {
                Log::warning('No se pudo borrar foto anterior de empleado', ['url' => $url, 'error' => $e->getMessage()]);
            }

            return;
        }

        $local = url(Storage::disk('public')->url('empleados/'));
        if (str_starts_with($url, $local)) {
            Storage::disk('public')->delete('empleados/'.basename($url));
        }
    }

    public function supabaseDisponible(): bool
    {
        return $this->apiKey() !== '';
    }

    private function baseUrl(): string
    {
        return rtrim((string) (env('SUPABASE_URL') ?: 'https://hbhqbmzixgcvxkilwsau.supabase.co'), '/');
    }

    private function apiKey(): string
    {
        return trim((string) (env('SUPABASE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY') ?: env('SUPABASE_ANON_KEY') ?: ''));
    }
}
