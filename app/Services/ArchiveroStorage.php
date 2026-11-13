<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Guarda y lee los archivos del archivero en el bucket «archivero» de Supabase (con la clave del servidor). */
class ArchiveroStorage
{
    public const BUCKET = 'archivero';

    /** Nombre aleatorio para el bucket: el enlace no se puede adivinar aunque el bucket sea público. */
    public function nuevaClave(string $extension): string
    {
        $ext = preg_replace('/[^a-z0-9]/', '', mb_strtolower($extension));

        return now()->format('Y/m').'/'.Str::uuid().($ext !== '' ? '.'.$ext : '');
    }

    /** @param  string|resource  $contenido */
    public function subir(string $clave, $contenido, string $mime): void
    {
        [$url, $key] = $this->credenciales();
        $r = Http::withoutVerifying()->timeout(600)->withHeaders([
            'Authorization' => "Bearer {$key}",
            'Content-Type' => $mime,
            'x-upsert' => 'true',
        ])->withBody($contenido, $mime)->post("{$url}/storage/v1/object/".self::BUCKET.'/'.$clave);

        if (! $r->successful()) {
            throw new RuntimeException('No se pudo guardar en el archivero: '.mb_substr($r->body(), 0, 300));
        }
    }

    /**
     * Sube varios archivos a la vez.
     *
     * @param  array<string, array{ruta: string, mime: string}>  $archivos  clave => archivo local
     * @return array<string, ?string>  clave => null si subió, o el error
     */
    public function subirVarios(array $archivos): array
    {
        [$url, $key] = $this->credenciales();
        $abiertos = [];
        $respuestas = Http::pool(function ($pool) use ($archivos, $url, $key, &$abiertos) {
            $peticiones = [];
            foreach ($archivos as $clave => $a) {
                $abiertos[$clave] = fopen($a['ruta'], 'r');
                $peticiones[] = $pool->as($clave)->withoutVerifying()->connectTimeout(30)->timeout(900)->withHeaders([
                    'Authorization' => "Bearer {$key}",
                    'Content-Type' => $a['mime'],
                    'x-upsert' => 'true',
                ])->withBody($abiertos[$clave], $a['mime'])->post("{$url}/storage/v1/object/".self::BUCKET.'/'.$clave);
            }

            return $peticiones;
        });
        foreach ($abiertos as $h) {
            if (is_resource($h)) {
                fclose($h);
            }
        }

        $out = [];
        foreach ($archivos as $clave => $_) {
            $r = $respuestas[$clave] ?? null;
            $out[$clave] = $r instanceof \Illuminate\Http\Client\Response && $r->successful()
                ? null
                : ($r instanceof \Throwable ? $r->getMessage() : mb_substr((string) ($r?->body() ?? 'sin respuesta'), 0, 300));
        }

        return $out;
    }

    public function descargar(string $clave): \Illuminate\Http\Client\Response
    {
        [$url, $key] = $this->credenciales();
        $r = Http::withoutVerifying()->timeout(300)->withHeaders(['Authorization' => "Bearer {$key}"])
            ->withOptions(['stream' => true])
            ->get("{$url}/storage/v1/object/".self::BUCKET.'/'.$clave);
        if (! $r->successful()) {
            throw new RuntimeException('No se encontró el archivo en el archivero.');
        }

        return $r;
    }

    public function borrar(string $clave): void
    {
        [$url, $key] = $this->credenciales();
        Http::withoutVerifying()->timeout(60)->withHeaders(['Authorization' => "Bearer {$key}"])
            ->delete("{$url}/storage/v1/object/".self::BUCKET.'/'.$clave);
    }

    /** @return array{0: string, 1: string} */
    private function credenciales(): array
    {
        $url = rtrim((string) env('SUPABASE_URL'), '/');
        $key = (string) env('SUPABASE_KEY');
        if ($url === '' || $key === '') {
            throw new RuntimeException('Faltan SUPABASE_URL o SUPABASE_KEY en el servidor.');
        }

        return [$url, $key];
    }
}
