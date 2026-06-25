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

    public function test_store_batch_manual_requisitions()
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin_batch@test.local',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $product = Product::create([
            'cod_centro' => 'PROD999',
            'producto' => 'Batch Product',
            'categoria' => 'Electrónica',
            'subcategoria' => 'Accesorios',
            'proveedor' => 'Proveedor B',
        ]);

        ProductSedeMetric::create([
            'product_id' => $product->id,
            'sede' => 'JRZ',
            'existencia' => 10,
        ]);

        ProductSedeMetric::create([
            'product_id' => $product->id,
            'sede' => 'DORAL',
            'existencia' => 8,
        ]);

        // 1. Post batch store: JRZ=5, DORAL=3
        $response = $this->actingAs($user)
            ->withSession(['sede_local' => 'ZAMORA'])
            ->postJson(route('inventario.manual.store_batch'), [
                'codigo' => 'PROD999',
                'producto' => 'Batch Product',
                'quantities' => [
                    'JRZ' => 5,
                    'DORAL' => 3,
                ],
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'total_manual',
            'manuales_list'
        ]);

        $this->assertDatabaseHas('requisiciones_manuales', [
            'sede_local' => 'ZAMORA',
            'codigo' => 'PROD999',
            'sede_origen' => 'JRZ',
            'cantidad' => 5,
        ]);

        $this->assertDatabaseHas('requisiciones_manuales', [
            'sede_local' => 'ZAMORA',
            'codigo' => 'PROD999',
            'sede_origen' => 'DORAL',
            'cantidad' => 3,
        ]);

        // 2. Post batch update: JRZ=0 (delete), DORAL=7 (update)
        $responseUpdate = $this->actingAs($user)
            ->withSession(['sede_local' => 'ZAMORA'])
            ->postJson(route('inventario.manual.store_batch'), [
                'codigo' => 'PROD999',
                'producto' => 'Batch Product',
                'quantities' => [
                    'JRZ' => 0,
                    'DORAL' => 7,
                ],
            ]);

        $responseUpdate->assertOk();
        
        $this->assertDatabaseMissing('requisiciones_manuales', [
            'sede_local' => 'ZAMORA',
            'codigo' => 'PROD999',
            'sede_origen' => 'JRZ',
        ]);

        $this->assertDatabaseHas('requisiciones_manuales', [
            'sede_local' => 'ZAMORA',
            'codigo' => 'PROD999',
            'sede_origen' => 'DORAL',
            'cantidad' => 7,
        ]);
    }
}
