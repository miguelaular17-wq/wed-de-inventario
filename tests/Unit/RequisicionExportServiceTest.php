<?php

namespace Tests\Unit;

use App\Services\RequisicionExportService;
use Tests\TestCase;

class RequisicionExportServiceTest extends TestCase
{
    public function test_excluye_usando_el_mismo_codigo_normalizado_que_muestra_la_vista(): void
    {
        config([
            'inventario.sedes_stock' => ['JRZ', 'DORAL'],
            'inventario.display' => ['JRZ' => 'JRZ', 'DORAL' => 'DORAL'],
        ]);

        $ventas = collect([[
            'accion' => 'HACER REQUISICION',
            'cod_centro' => '231554531221 / CODIGO ALTERNO',
            'producto' => 'Producto de prueba',
            'categoria' => 'GENERAL',
            'subcategoria' => 'GENERAL',
            'opc' => 'JRZ',
            'sugerido_nec' => 5,
            'excedentes' => ['JRZ' => 10],
        ]]);

        $service = new RequisicionExportService();

        $this->assertCount(1, $service->buildExport($ventas, 'JRZ', 'DORAL'));
        $this->assertCount(0, $service->buildExport(
            $ventas,
            'JRZ',
            'DORAL',
            false,
            'Todas',
            'Todas',
            [],
            ['231554531221']
        ));
    }

    public function test_el_separador_lo_define_la_sede_que_descarga(): void
    {
        $service = new RequisicionExportService();
        $lines = collect([[
            'codigo' => 'ABC123',
            'unidad' => 'UND',
            'cantidad' => 2,
        ]]);

        $this->assertSame("codigo,unidad,cantidad\nABC123,UND,2\n", $service->toCsv($lines, $service->csvDelimiterForSede('VIRTUDES')));
        $this->assertSame("codigo;unidad;cantidad\nABC123;UND;2\n", $service->toCsv($lines, $service->csvDelimiterForSede('ZAMORA')));
        $this->assertSame("codigo;unidad;cantidad\nABC123;UND;2\n", $service->toCsv($lines, $service->csvDelimiterForSede('CENTRO')));
        $this->assertSame(',', $service->csvDelimiterForSede('Virtude'));
        $this->assertSame(';', $service->csvDelimiterForSede('JRZ'));
    }
}
