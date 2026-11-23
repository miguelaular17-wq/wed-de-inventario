<?php

namespace App\Support;

/** Convierte montos a letras en español (dólares o bolívares). */
final class MontoEnLetras
{
    public static function dolares(float $monto): string
    {
        return self::monto($monto, 'DÓLARES AMERICANOS');
    }

    public static function bolivares(float $monto): string
    {
        return self::monto($monto, 'BOLÍVARES');
    }

    public static function monto(float $monto, string $moneda): string
    {
        $parts = number_format(abs($monto), 2, '.', '');
        [$entero, $centavos] = array_map('intval', explode('.', $parts));
        $letras = self::entero($entero).' '.$moneda;
        if ($centavos > 0) {
            $letras .= ' CON '.$centavos.'/100';
        }

        return $letras;
    }

    private static function entero(int $num): string
    {
        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
            'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
        $decenas = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        if ($num <= 0) {
            return 'CERO';
        }
        if ($num === 100) {
            return 'CIEN';
        }

        $resultado = '';
        if ($num >= 1000) {
            $miles = intdiv($num, 1000);
            $resultado .= $miles === 1 ? 'MIL' : self::entero($miles).' MIL';
            $num %= 1000;
            if ($num > 0) {
                $resultado .= ' ';
            }
        }
        if ($num >= 100) {
            $resultado .= $centenas[intdiv($num, 100)];
            $num %= 100;
            if ($num > 0) {
                $resultado .= ' ';
            }
        }
        if ($num >= 20) {
            $resultado .= $decenas[intdiv($num, 10)];
            if ($num % 10 > 0) {
                $resultado .= ' Y '.$unidades[$num % 10];
            }
        } elseif ($num > 0) {
            $resultado .= $unidades[$num];
        }

        return trim($resultado);
    }
}
