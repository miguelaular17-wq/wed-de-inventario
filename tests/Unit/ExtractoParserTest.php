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

    public function test_lee_el_xls_de_banesco_que_en_realidad_es_una_tabla_html(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bn') . '.xls';
        file_put_contents($tmp, "<table border='2'><tr><td><b>Fecha</b></td><td><b>Referencia</b></td><td><b>Descripción</b></td><td><b>Monto</b></td><td><b>Balance</b></td></tr>"
            . "<tr><td>2026/08/03</td><td>21517002254</td><td>COMPRA POS CTA/CTE   </td><td>-59.486,27</td><td>2.302.615,89</td></tr>"
            . "<tr><td>2026/08/03</td><td>00000000915</td><td>TD 0001425125 000915 31/07/26 0037417107</td><td>+6.761,24</td><td>2.309.377,13</td></tr></table>");

        $filas = iterator_to_array($this->parser->filas($tmp, 'xls', 'BANESCO'), false);
        @unlink($tmp);

        $this->assertCount(2, $filas);
        $this->assertSame(['2026-08-03', 'COMPRA POS CTA/CTE', '21517002254', '59486.27', 'cargo'], array_values($filas[0]['datos']));
        $this->assertSame(['abono', '6761.24'], [$filas[1]['datos']['tipo'], $filas[1]['datos']['monto']]);
        $this->assertSame('Descripción', iterator_to_array($this->parser->filasCrudas($this->html(), 'xls'), false)[0][2]);
    }

    public function test_lee_el_excel_xml_de_banesco_en_linea_con_el_signo_del_tipo_o_del_saldo(): void
    {
        $celda = fn (string $v) => '<Cell ss:StyleID="StringLiteral"><Data ss:Type="String">'.$v.'</Data></Cell>';
        $fila = fn (array $vs) => '<Row>'.implode('', array_map($celda, $vs)).'</Row>';
        $tmp = tempnam(sys_get_temp_dir(), 'bx') . '.xls';
        file_put_contents($tmp, "<xml version=\"1.0\">\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\">"
            .'<Styles><Style ss:ID="Default"/></Styles><Worksheet ss:Name="Movimientos"><Table>'
            .$fila(['Fecha', 'Referencia', 'Descripción', 'Monto', 'Saldo', 'Tipo de Movimiento'])
            .$fila(['3 de agosto de 2026', '21517002254', 'COMPRA POS CTA/CTE', '59.486,27', '2.302.615,89', ''])
            .$fila(['3 de agosto de 2026', '21518860015', 'COMPRA POS CTA/CTE BAJO CERO', '19.391,61', '2.283.224,28', ''])
            .$fila(['3 de agosto de 2026', '00000000915', 'TD 0001425125 000915', '6.761,24', '2.289.985,52', ''])
            .$fila(['31 de agosto de 2026', '01569450551', 'Com. Banesco Pago Movil', '141,44', '2.289.844,08', 'Nota de Débito'])
            .$fila(['31 de agosto de 2026', '01037135392', 'Banesco Pago Movil', '5.009,00', '2.294.853,08', 'Nota de Crédito'])
            .'</Table></Worksheet></Workbook>');

        $filas = iterator_to_array($this->parser->filas($tmp, 'xls', 'BANESCO'), false);
        @unlink($tmp);

        // La primera fila no trae tipo ni saldo anterior: no se adivina el signo.
        $this->assertSame(['tipo' => 'rechazo', 'fila' => 2, 'motivo' => 'Sin monto'], $filas[0]);
        $this->assertSame(['2026-08-03', 'COMPRA POS CTA/CTE BAJO CERO', '21518860015', '19391.61', 'cargo'], array_values($filas[1]['datos']));
        $this->assertSame(['abono', '6761.24'], [$filas[2]['datos']['tipo'], $filas[2]['datos']['monto']]);
        $this->assertSame(['2026-08-31', 'cargo', '141.44'], [$filas[3]['datos']['fecha'], $filas[3]['datos']['tipo'], $filas[3]['datos']['monto']]);
        $this->assertSame('abono', $filas[4]['datos']['tipo']);
    }

    private function html(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bh') . '.xls';
        file_put_contents($tmp, "\xEF\xBB\xBF  <table><tr><th>Fecha</th><th>Ref</th><th>Descripción</th></tr></table>");

        return $tmp;
    }

    public function test_normaliza_fechas_de_texto_y_seriales_de_excel(): void
    {
        $this->assertSame('2026-08-03', $this->parser->normalizarFecha('03/08/2026'));
        $this->assertSame('2026-08-03', $this->parser->normalizarFecha('03-08-2026'));
        $this->assertSame('2026-08-03', $this->parser->normalizarFecha('2026-08-03 00:00:00'));
        $this->assertSame('2026-08-03', $this->parser->normalizarFecha('46237'));
        $this->assertSame('2026-08-03', $this->parser->normalizarFecha('3 de agosto de 2026'));
        $this->assertSame('2026-12-31', $this->parser->normalizarFecha('31 de Diciembre de 2026'));
        $this->assertNull($this->parser->normalizarFecha('31 de febrero de 2026'));
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
