<?php

namespace App\Support;

use App\Models\Nomina\NominaConfig;

class VentaDescuento
{
    public static function porcentaje(): float
    {
        return NominaConfig::getDecimal(
            'descuento_venta_pct',
            (float) config('inventario.descuento_venta_pct', 25)
        );
    }

    public static function factorDescuento(): float
    {
        return self::porcentaje() / 100;
    }

    public static function factorNeto(): float
    {
        return 1 - self::factorDescuento();
    }

    public static function montoDescuento(float $precio): float
    {
        return round($precio * self::factorDescuento(), 2);
    }

    public static function precioNeto(float $precio): float
    {
        return round($precio * self::factorNeto(), 2);
    }

    public static function etiqueta(): string
    {
        return rtrim(rtrim(number_format(self::porcentaje(), 2, '.', ''), '0'), '.').'%';
    }
}
