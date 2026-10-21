<?php

namespace Tests\Unit;

use App\Services\Conciliacion\ExtractoParser;
use PHPUnit\Framework\TestCase;

class ExtractoParserTest extends TestCase
{
    private ExtractoParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ExtractoParser;
    }

    public function test_normaliza_importes_en_los_formatos_de_los_bancos(): void
    {
        $casos = [
            '1.234.567,89' => '1234567.89',   // europeo (Banesco, Mercantil)
            '-45,50' => '-45.50',
            '45,5' => '45.50',
            '1,234.50' => '1234.50',          // americano
            '-1234.5' => '-1234.50',
            'Bs. 1.000,00' => '1000.00',
            '(12,00)' => '-12.00',            // negativo entre paréntesis
            '250,00-' => '-250.00',           // negativo al final
            '1.234.567' => '1234567.00',      // miles sin decimales
            '0,005' => '0.01',                // redondeo a 2 decimales (mitad hacia arriba)
            '10.004' => '10.00',
            '' => '0.00',
        ];
        foreach ($casos as $entrada => $esperado) {
            $this->assertSame($esperado, $this->parser->normalizarMonto($entrada), "Entrada: {$entrada}");
        }

        $this->assertSame('12.30', $this->parser->normalizarMonto(12.3));
        $this->assertSame('-7.00', $this->parser->normalizarMonto(-7));
        $this->assertNull($this->parser->normalizarMonto('abc'));
        $this->assertNull($this->parser->normalizarMonto('12-34'));
    }

    public function test_normaliza_fechas_de_texto_y_seriales_de_excel(): void
    {
        $this->assertSame('2026-08-03', $this->parser->normalizarFecha('03/08/2026'));
        $this->assertSame('2026-08-03', $this->parser->normalizarFecha('03-08-2026'));
        $this->assertSame('2026-08-03', $this->parser->normalizarFecha('2026-08-03 00:00:00'));
        $this->assertSame('2026-08-03', $this->parser->normalizarFecha('46237'));
        $this->assertNull($this->parser->normalizarFecha('fecha'));
        $this->assertNull($this->parser->normalizarFecha('01/01/1890'));
        $this->assertNull($this->parser->normalizarFecha(''));
    }

    public function test_cargo_y_abono_con_columna_unica_o_separada(): void
    {
        $banesco = ExtractoParser::MAPEOS['BANESCO'];
        $cargo = $this->parser->procesarFila($banesco, 1, ['03/08/2026', '123', 'PAGO', '-1.500,25', '0']);
        $this->assertSame('linea', $cargo['tipo']);
        $this->assertSame(['fecha' => '2026-08-03', 'descripcion' => 'PAGO', 'referencia' => '123', 'monto' => '1500.25', 'tipo' => 'cargo'], $cargo['datos']);

        $bancaribe = ExtractoParser::MAPEOS['BANCARIBE'];
        $abono = $this->parser->procesarFila($bancaribe, 2, ['04/08/2026', '99', 'TRANSF', '0,00', '300,00']);
        $this->assertSame('abono', $abono['datos']['tipo']);
        $this->assertSame('300.00', $abono['datos']['monto']);
    }

    public function test_omite_cabeceras_y_saldos_y_rechaza_filas_invalidas(): void
    {
        $map = ExtractoParser::MAPEOS['BANESCO'];
        $this->assertNull($this->parser->procesarFila($map, 0, ['Fecha', 'Ref', 'Desc', 'Monto']));
        $this->assertNull($this->parser->procesarFila($map, 3, ['03/08/2026', '', 'SALDO INICIAL', '100,00']));
        $this->assertNull($this->parser->procesarFila($map, 4, ['Total', '', '', '']));

        $this->assertSame('Fecha inválida', $this->parser->procesarFila($map, 5, ['99/99/9999', '1', 'X', '10,00'])['motivo']);
        $this->assertSame('Sin monto', $this->parser->procesarFila($map, 6, ['03/08/2026', '1', 'X', '0,00'])['motivo']);
        $this->assertSame('Monto con formato inválido', $this->parser->procesarFila($map, 7, ['03/08/2026', '1', 'X', 'N/A'])['motivo']);
    }
}
