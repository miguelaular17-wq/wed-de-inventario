<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductSedeMetric;
use App\Models\RequisicionManual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualRequisitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['inventario.sedes_stock' => ['JRZ', 'DORAL', 'ZAMORA']]);
        config(['inventario.display' => [
            'JRZ' => 'JRZ',
            'DORAL' => 'DORAL',
            'ZAMORA' => 'Zamora',
        ]]);
    }

    public function test_store_and_destroy_manual_requisition_via_json()
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $product = Product::create([
            'cod_centro' => 'PROD123',
            'producto' => 'Test Product',
            'categoria' => 'Electrónica',
            'subcategoria' => 'Accesorios',
            'proveedor' => 'Proveedor A',
        ]);

        ProductSedeMetric::create([
            'product_id' => $product->id,
            'sede' => 'JRZ',
            'existencia' => 10,
        ]);

        ProductSedeMetric::create([
            'product_id' => $product->id,
            'sede' => 'DORAL',
            'existencia' => 2,
        ]);

        // 1. Post/Store Manual Requisition
        $response = $this->actingAs($user)
            ->withSession(['sede_local' => 'ZAMORA'])
            ->postJson(route('inventario.manual.store'), [
                'codigo' => 'PROD123',
                'producto' => 'Test Product',
                'sede_origen' => 'JRZ',
                'cantidad' => 3,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'total_manual',
            'manuales_list' => [
                '*' => [
                    'id',
                    'sede_origen',
                    'cantidad',
                    'pendiente',
                    'accion'
                ]
            ]
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['total_manual']);
        $this->assertCount(1, $data['manuales_list']);
        $this->assertEquals('JRZ', $data['manuales_list'][0]['sede_origen']);
        $this->assertEquals(3, $data['manuales_list'][0]['cantidad']);
        $this->assertTrue($data['manuales_list'][0]['pendiente']);

        // 2. Delete Manual Requisition
        $responseDel = $this->actingAs($user)
            ->withSession(['sede_local' => 'ZAMORA'])
            ->deleteJson(route('inventario.manual.destroy'), [
                'codigo' => 'PROD123',
                'sede_origen' => 'JRZ',
            ]);

        $responseDel->assertOk();
        $responseDel->assertJsonStructure([
            'success',
            'message',
            'total_manual',
            'manuales_list'
        ]);

        $dataDel = $responseDel->json();
        $this->assertTrue($dataDel['success']);
        $this->assertEquals(0, $dataDel['total_manual']);
        $this->assertCount(0, $dataDel['manuales_list']);
    }
}
