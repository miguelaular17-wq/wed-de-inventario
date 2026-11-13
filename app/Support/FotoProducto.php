<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/** Foto del producto como en el catálogo general: imagenes/{código principal}.jpg, si no .png. */
class FotoProducto
{
    public const BASE = 'https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/object/public/imagenes_producto/imagenes/';

    public const SIN_IMAGEN = self::BASE.'no-image.jpg';

    /** URL sin extensión: se le agrega «.jpg» o «.png». */
    public static function base(?string $codigo): string
    {
        $partes = explode('/', (string) $codigo);
        if (count($partes) === 1) {
            $partes = explode(' ', (string) $codigo);
        }

        return self::BASE.rawurlencode(trim($partes[0]));
    }

    /**
     * Fotos embebidas (data URI) para un PDF; se descargan en paralelo y la que no exista queda en null.
     *
     * @param  array<int|string, string>  $codigos  clave => código
     * @return array<int|string, ?string>
     */
    public static function embebidas(array $codigos): array
    {
        $out = array_fill_keys(array_keys($codigos), null);
        $pendientes = $codigos;
        foreach (['jpg' => 'image/jpeg', 'png' => 'image/png'] as $ext => $mime) {
            if ($pendientes === []) {
                break;
            }
            foreach (array_chunk($pendientes, 25, true) as $lote) {
                $respuestas = Http::pool(fn ($pool) => collect($lote)->map(
                    fn ($codigo, $clave) => $pool->as((string) $clave)->timeout(15)->get(self::base($codigo).'.'.$ext)
                )->all());
                foreach ($lote as $clave => $codigo) {
                    $r = $respuestas[(string) $clave] ?? null;
                    if ($r instanceof \Illuminate\Http\Client\Response && $r->successful() && str_starts_with((string) $r->header('Content-Type'), 'image/')) {
                        $out[$clave] = 'data:'.$mime.';base64,'.base64_encode($r->body());
                        unset($pendientes[$clave]);
                    }
                }
            }
        }

        return $out;
    }
}
