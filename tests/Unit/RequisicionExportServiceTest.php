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
}
