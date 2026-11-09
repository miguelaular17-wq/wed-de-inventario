<?php

namespace App\Support;

/**
 * La web guarda todos los códigos de un producto en `codigo` separados por " / " (el principal primero y
 * luego los alternos de Suite). En pantalla se muestra solo el principal; las búsquedas usan el campo completo.
 */
class Sku
{
    public const SEPARADOR = ' / ';

    public static function principal(?string $codigo): string
    {
        $codigo = (string) $codigo;
        if (! str_contains($codigo, self::SEPARADOR)) {
            return trim($codigo);
        }

        foreach (preg_split('#(?:^|\s)/(?:\s|$)#', $codigo) as $parte) {
            if (trim($parte) !== '') {
                return trim($parte);
            }
        }

        return trim($codigo);
    }
}
