<?php

namespace Tests\Unit;

use App\Support\SimpleXlsxWriter;
use Tests\TestCase;
use ZipArchive;

class SimpleXlsxWriterTest extends TestCase
{
    public function test_genera_xlsx_con_hojas_y_numeros(): void
    {
        $bin = SimpleXlsxWriter::toString([
            'Egresos Realizados' => [
                ['Fecha', 'USD'],
                ['25/08/2026', 12.5],
                ['TOTALES', 12.5],
            ],
        ]);

        $this->assertSame('PK', substr($bin, 0, 2));

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $bin);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Egresos', $zip->getFromName('xl/workbook.xml'));
        $this->assertStringContainsString('12.5', $sheet);
        $zip->close();
        @unlink($tmp);
    }

    public function test_no_falla_con_texto_invalido_para_xml(): void
    {
        $bin = SimpleXlsxWriter::toString([
            'Reporte' => [
                ["Motivo\x00con\x01control", "áéí"],
            ],
        ]);

        $this->assertSame('PK', substr($bin, 0, 2));
        $this->assertNotSame('', $bin);
    }

    public function test_zip_files_empaqueta_binarios(): void
    {
        $bin = SimpleXlsxWriter::zipFiles([
            'Sede_Doral.pdf' => "%PDF-1.4\n%",
            'Area_Marketing.pdf' => "%PDF-1.4\n%",
        ]);

        $this->assertSame('PK', substr($bin, 0, 2));
        $tmp = tempnam(sys_get_temp_dir(), 'zipf');
        file_put_contents($tmp, $bin);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertNotFalse($zip->locateName('Sede_Doral.pdf'));
        $this->assertNotFalse($zip->locateName('Area_Marketing.pdf'));
        $zip->close();
        @unlink($tmp);
    }
}
