<?php

namespace Tests\Unit;

use App\Services\VentasCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class VentasCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['inventario.tiempo_venta_sede' => 15]);
        config(['inventario.tiempo_venta_jrz' => 60]);
        config(['inventario.tiempo_pronostico_default' => 15]);
        config(['inventario.minimo_sugerido_ventas' => 1]);
        config(['inventario.minimo_inv_solicitar' => 6]);
        config(['inventario.sedes_stock' => ['JRZ', 'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL']]);
        config(['inventario.display' => [
            'JRZ' => 'JRZ',
            'DORAL' => 'DORAL',
            'VIRTUDES' => 'Virtude',
            'ZAMORA' => 'Zamora',
            'CENTRO' => 'Centro',
            'SAMBIL' => 'Sambil',
        ]]);
    }

    public function test_calcular_returns_correct_recommendations_and_demands()
    {
        $calculator = new VentasCalculator();

        $productData = [
            'id' => 1,
            'cod_centro' => 'PROD001',
            'producto' => 'Producto De Prueba',
            'categoria' => 'Categoria A',
            'subcategoria' => 'Sub A',
            'proveedor' => 'Proveedor A',
            'existencia' => 1,
            'venta' => 2,
            'ventas_60d' => 12.0,
            'stocks' => [
                'JRZ' => 10,
                'DORAL' => 1,
                'VIRTUDES' => 0,
                'ZAMORA' => 0,
                'CENTRO' => 0,
                'SAMBIL' => 0,
            ],
            'ventas_internas' => [
                'JRZ' => 0,
                'DORAL' => 0,
                'VIRTUDES' => 0,
                'ZAMORA' => 0,
                'CENTRO' => 0,
                'SAMBIL' => 0,
            ],
            'ventas_internas_15d' => [
                'JRZ' => 0,
                'DORAL' => 0,
                'VIRTUDES' => 0,
                'ZAMORA' => 0,
                'CENTRO' => 0,
                'SAMBIL' => 0,
            ],
        ];

        $products = collect([$productData]);
        $results = $calculator->calcular($products, 'DORAL', 15.0);

        $this->assertCount(1, $results);
        $result = $results->first();

        $this->assertEquals('HACER REQUISICION', $result['accion']);
        $this->assertEquals(3, $result['demanda']);
        $this->assertEquals('JRZ', $result['opc']);
        $this->assertEquals(2, $result['sugerido_nec']);
        $this->assertEquals('req_ok', $result['req_tag']);
    }

    public function test_calcular_filters_out_products_with_no_activity()
    {
        $calculator = new VentasCalculator();

        $productData = [
            'id' => 2,
            'cod_centro' => 'PROD002',
            'producto' => 'Producto Sin Actividad',
            'categoria' => 'Categoria A',
            'subcategoria' => 'Sub A',
            'proveedor' => 'Proveedor A',
            'existencia' => 0,
            'venta' => 0,
            'ventas_60d' => 0.0,
            'stocks' => [
                'JRZ' => 0,
                'DORAL' => 0,
                'VIRTUDES' => 0,
                'ZAMORA' => 0,
                'CENTRO' => 0,
                'SAMBIL' => 0,
            ],
            'ventas_internas' => [
                'JRZ' => 0,
                'DORAL' => 0,
                'VIRTUDES' => 0,
                'ZAMORA' => 0,
                'CENTRO' => 0,
                'SAMBIL' => 0,
            ],
            'ventas_internas_15d' => [
                'JRZ' => 0,
                'DORAL' => 0,
                'VIRTUDES' => 0,
                'ZAMORA' => 0,
                'CENTRO' => 0,
                'SAMBIL' => 0,
            ],
        ];

        $products = collect([$productData]);
        $results = $calculator->calcular($products, 'DORAL', 15.0);

        $this->assertCount(0, $results);
    }
}
