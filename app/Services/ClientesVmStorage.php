<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Archivos de clientes al mayor → bucket «Clientes VM» en Supabase. */
class ClientesVmStorage
{
    public const BUCKET = 'Clientes VM';

    public function subir(UploadedFile $archivo, string $prefijo): string
    {
        return $this->subirContenido(
            (string) file_get_contents($archivo->getRealPath()),
            (string) $archivo->getClientMimeType(),
            strtolower($archivo->getClientOriginalExtension() ?: 'bin'),
            $prefijo,
            mb_substr($archivo->getClientOriginalName(), 0, 80),
        );
    }

    public function subirContenido(
        string $contenido,
        string $mime,
        string $extension,
        string $prefijo,
        ?string $nombreOriginal = null,
    ): string {
        $base = $nombreOriginal
            ? pathinfo(preg_replace('/[^A-Za-z0-9 _.-]+/u', '', $nombreOriginal) ?: 'archivo', PATHINFO_FILENAME)
            : 'archivo';
        $base = trim(preg_replace('/\s+/', ' ', $base)) ?: 'archivo';
        $prefijoLimpio = preg_replace('/[^A-Za-z0-9_-]+/', '_', $prefijo) ?: 'cliente';
        $nombre = $prefijoLimpio.'_'.$base.'_'.date('Ymd_His').'_'.uniqid().'.'.$extension;

        [$urlBase, $key] = $this->credencialesOpcionales();
        if ($urlBase !== null && $key !== null) {
            $bucketEnc = rawurlencode(self::BUCKET);
            $objetoEnc = str_replace('%2F', '/', rawurlencode($nombre));
            $respuesta = Http::withoutVerifying()->timeout(120)->withHeaders([
                'Authorization' => "Bearer {$key}",
                'Content-Type' => $mime,
                'x-upsert' => 'true',
            ])->withBody($contenido, $mime)
                ->post("{$urlBase}/storage/v1/object/{$bucketEnc}/{$objetoEnc}");

            if (! $respuesta->successful()) {
                throw ValidationException::withMessages([
                    'archivo' => 'No se pudo subir al bucket Clientes VM: '.mb_substr($respuesta->body(), 0, 240),
                ]);
            }

            return "{$urlBase}/storage/v1/object/public/{$bucketEnc}/{$objetoEnc}";
        }

        Storage::disk('public')->put('clientes_vm/'.$nombre, $contenido);

        return Storage::disk('public')->url('clientes_vm/'.$nombre);
    }

    public function borrarPorUrl(?string $url): void
    {
        $url = trim((string) $url);
        if ($url === '') {
            return;
        }

        [$urlBase, $key] = $this->credencialesOpcionales();
        if ($urlBase !== null && $key !== null) {
            $marker = '/storage/v1/object/public/'.rawurlencode(self::BUCKET).'/';
            $markerAlt = '/storage/v1/object/public/'.self::BUCKET.'/';
            $pos = strpos($url, $marker);
            $objeto = null;
            if ($pos !== false) {
                $objeto = urldecode(substr($url, $pos + strlen($marker)));
            } else {
                $pos = strpos($url, $markerAlt);
                if ($pos !== false) {
                    $objeto = urldecode(substr($url, $pos + strlen($markerAlt)));
                }
            }
            if ($objeto) {
                $bucketEnc = rawurlencode(self::BUCKET);
                $objetoEnc = str_replace('%2F', '/', rawurlencode($objeto));
                Http::withoutVerifying()->timeout(60)->withHeaders([
                    'Authorization' => "Bearer {$key}",
                ])->delete("{$urlBase}/storage/v1/object/{$bucketEnc}/{$objetoEnc}");
            }

            return;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        if (str_contains($path, '/clientes_vm/')) {
            $rel = ltrim(substr($path, strpos($path, '/clientes_vm/') + 1), '/');
            if ($rel !== '' && Storage::disk('public')->exists($rel)) {
                Storage::disk('public')->delete($rel);
            }
        }
    }

    /** @return array{0: ?string, 1: ?string} */
    private function credencialesOpcionales(): array
    {
        $url = rtrim((string) env('SUPABASE_URL'), '/');
        $key = (string) env('SUPABASE_KEY');
        if ($url === '' || $key === '') {
            return [null, null];
        }

        return [$url, $key];
    }
}
