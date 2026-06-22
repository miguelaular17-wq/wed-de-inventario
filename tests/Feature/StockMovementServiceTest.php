<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductSedeMetric;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['inventario.display' => [
            'JRZ' => 'JRZ',
            'DORAL' => 'DORAL',
            'VIRTUDES' => 'Virtude',
            'ZAMORA' => 'Zamora',
            'CENTRO' => 'Centro',
            'SAMBIL' => 'Sambil',
        ]]);
    }

    public function test_apply_requisition_sqlite_adjusts_stock_and_creates_movements()
    {
        $product = Product::create([
            'cod_centro' => 'PROD999',
            'producto' => 'Cables HDMI',
            'categoria' => 'Electrónica',
            'subcategoria' => 'Accesorios',
            'proveedor' => 'Proveedor HDMI',
        ]);

        $metricOrigen = ProductSedeMetric::create([
            'product_id' => $product->id,
            'sede' => 'JRZ',
            'existencia' => 15,
        ]);

        $metricDestino = ProductSedeMetric::create([
            'product_id' => $product->id,
            'sede' => 'DORAL',
            'existencia' => 2,
        ]);

        $service = app(StockMovementService::class);
        $lines = collect([
            [
                'codigo' => 'PROD999',
                'cantidad' => 5,
            ]
        ]);

        $applied = $service->applyRequisition($lines, 'JRZ', 'DORAL', 'test_user@gmail.com');

        $this->assertEquals(1, $applied);

        $metricOrigen->refresh();
        $metricDestino->refresh();

        $this->assertEquals(10, $metricOrigen->existencia);
        $this->assertEquals(7, $metricDestino->existencia);

        $this->assertDatabaseHas('stock_movements', [
            'cod_centro' => 'PROD999',
            'sede_origen' => 'JRZ',
            'sede_destino' => 'DORAL',
            'cantidad' => 5,
            'tipo' => 'requisicion',
        ]);
    }
}
