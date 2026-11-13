<?php

namespace App\Models\Mayor;

use Illuminate\Database\Eloquent\Model;

/** Cómo se vende un producto al mayor: por unidad, caja o paquete, a qué precio y con qué costo. */
class MayorCatalogo extends Model
{
    public const PRESENTACIONES = ['unidad' => 'Unidad', 'caja' => 'Caja', 'paquete' => 'Paquete'];

    protected $table = 'mayor_catalogo';

    protected $fillable = ['producto_id', 'presentacion', 'unidades', 'precio_usd', 'costo_usd', 'user_id'];

    protected $casts = [
        'unidades' => 'integer',
        'precio_usd' => 'float',
        'costo_usd' => 'float',
    ];

    /** «Caja x12», «Paquete x6» o «Unidad». */
    public static function etiqueta(string $presentacion, int $unidades): string
    {
        $nombre = self::PRESENTACIONES[$presentacion] ?? 'Unidad';

        return $presentacion === 'unidad' || $unidades <= 1 ? $nombre : $nombre.' x'.$unidades;
    }

    /** «3 cajas», «1 paquete», «5 und.». */
    public static function cantidadTexto(int $cantidad, string $presentacion): string
    {
        return match ($presentacion) {
            'caja' => $cantidad.' '.($cantidad === 1 ? 'caja' : 'cajas'),
            'paquete' => $cantidad.' '.($cantidad === 1 ? 'paquete' : 'paquetes'),
            default => $cantidad.' und.',
        };
    }
}
