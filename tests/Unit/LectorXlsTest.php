<?php

namespace Tests\Unit;

use App\Services\Conciliacion\LectorXls;
use PHPUnit\Framework\TestCase;

class LectorXlsTest extends TestCase
{
    /** Hoja BIFF8 mínima: BOF + celdas LABEL + EOF (así exporta el BNC sus movimientos). */
    private function hoja(array $labels): string
    {
        $registro = fn (int $tipo, string $datos) => pack('vv', $tipo, strlen($datos)) . $datos;
        $bin = $registro(0x0809, pack('vvvvVV', 0x0600, 0x0010, 0, 0, 0, 0));
        foreach ($labels as [$fila, $col, $texto, $utf16]) {
            $chars = $utf16 ? mb_convert_encoding($texto, 'UTF-16LE', 'UTF-8') : mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
            $bin .= $registro(0x0204, pack('vvvvC', $fila, $col, 0, mb_strlen($texto), $utf16 ? 1 : 0) . $chars);
        }

        return $bin . $registro(0x000A, '');
    }

    private function leer(string $hoja): array
    {
        $xls = (new \ReflectionClass(LectorXls::class))->newInstanceWithoutConstructor();
        $xls->data = $hoja;
        $xls->sn = 0;
        $parse = new \ReflectionMethod($xls, 'parseSheet');
        $parse->invoke($xls, 0);

        return $xls->sheets[0]['cells'];
    }

    public function test_label_de_excel_97_se_lee_completo_y_sin_el_byte_de_opciones(): void
    {
        $celdas = $this->leer($this->hoja([
            [0, 1, '22/08/2026', false],
            [0, 7, 'Abono Pago Móvil BNC', false],
            [1, 1, 'Descripción ñandú', true],
        ]));

        $this->assertSame('22/08/2026', $celdas[0][1]);
        $this->assertSame('Abono Pago Móvil BNC', $celdas[0][7]);
        $this->assertSame('Descripción ñandú', $celdas[1][1]);
    }
}
