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
}
