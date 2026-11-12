<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Comprobantes y evidencias: al bucket «comprobantes» de Supabase (como Finanzas); sin Supabase, al disco público. */
class ComprobantesStorage
{
    public function subir(UploadedFile $archivo, string $prefijo): string
    {
        return $this->subirContenido(
            (string) file_get_contents($archivo->getRealPath()),
            (string) $archivo->getClientMimeType(),
            strtolower($archivo->getClientOriginalExtension() ?: 'bin'),
            $prefijo,
        );
    }

    public function subirContenido(string $contenido, string $mime, string $extension, string $prefijo): string
    {
        $nombre = preg_replace('/[^A-Za-z0-9_-]/', '_', $prefijo).'_'.date('Ymd_His').'_'.uniqid().'.'.$extension;
        $supabaseUrl = rtrim((string) env('SUPABASE_URL'), '/');
        $supabaseKey = (string) env('SUPABASE_KEY');
        if ($supabaseUrl !== '' && $supabaseKey !== '') {
            $respuesta = Http::withoutVerifying()->withHeaders([
                'Authorization' => "Bearer {$supabaseKey}",
                'Content-Type' => $mime,
            ])->withBody($contenido, $mime)->post("{$supabaseUrl}/storage/v1/object/comprobantes/{$nombre}");
            if (! $respuesta->successful()) {
                throw ValidationException::withMessages(['archivo' => 'No se pudo subir el archivo: '.$respuesta->body()]);
            }

            return "{$supabaseUrl}/storage/v1/object/public/comprobantes/{$nombre}";
        }
        Storage::disk('public')->put('comprobantes/'.$nombre, $contenido);

        return Storage::disk('public')->url('comprobantes/'.$nombre);
    }
}
