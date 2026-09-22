<?php

namespace App\Services\ServicioTecnico;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StEvidenciaStorage
{
    public const BUCKET = 'ST';

    public function disponible(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '';
    }

    /**
     * @param  list<UploadedFile|null>  $imagenes
     * @return array{imagenes: list<string>, video: ?string}
     */
    public function subirDesdeRequest(array $imagenes, ?UploadedFile $video = null, ?string $prefijo = null): array
    {
        if (! $this->disponible()) {
            throw ValidationException::withMessages([
                'evidencias' => 'Faltan SUPABASE_URL o SUPABASE_KEY para subir evidencias.',
            ]);
        }

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
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
        $nombre = $etiqueta.'_'.now()->format('YmdHis').'_'.Str::lower(Str::random(8)).'.'.$ext;
        $path = trim($prefijo, '/').'/'.$nombre;

        $contentType = $file->getMimeType() ?: 'application/octet-stream';
        $body = file_get_contents($file->getRealPath());
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

    private function baseUrl(): string
    {
        $url = rtrim((string) (env('SUPABASE_URL') ?: 'https://hbhqbmzixgcvxkilwsau.supabase.co'), '/');

        return $url;
    }

    private function apiKey(): string
    {
        return trim((string) env('SUPABASE_KEY', ''));
    }
}
