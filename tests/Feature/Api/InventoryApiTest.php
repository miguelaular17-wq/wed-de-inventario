<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductSedeMetric;
use App\Models\RequisicionManual;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        config([
            'inventario.sedes_locales' => ['DORAL', 'ZAMORA'],
            'inventario.sedes_stock' => ['JRZ', 'DORAL', 'ZAMORA'],
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->string('sede')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('cod_centro')->unique();
            $table->string('producto');
            $table->string('categoria')->nullable();
            $table->string('subcategoria')->nullable();
            $table->string('proveedor')->nullable();
            $table->timestamps();
        });
        Schema::create('product_sede_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('sede');
            $table->integer('existencia')->default(0);
            $table->float('ventas_60d')->default(0);
            $table->date('ultima_venta')->nullable();
            $table->integer('promedio_15d')->default(0);
            $table->timestamps();
        });
        Schema::create('requisiciones_manuales', function (Blueprint $table) {
            $table->id();
            $table->string('sede_local');
            $table->string('codigo');
            $table->string('producto')->nullable();
            $table->string('sede_origen');
            $table->integer('cantidad');
            $table->string('usuario')->nullable();
            $table->timestamp('aplicada_at')->nullable();
            $table->timestamps();
            $table->unique(['sede_local', 'codigo', 'sede_origen', 'usuario'], 'requisicion_manual_api_unique');
        });
    }

    public function test_login_me_and_logout_use_sanctum_tokens(): void
    {
        $user = $this->user('admin@test.local', 'admin');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => strtoupper($user->email),
            'password' => 'password123',
            'device_name' => 'Prueba Android',
        ])->assertOk()->assertJsonPath('user.id', $user->id);

        $token = $login->json('token');
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_fixed_sede_user_cannot_change_sede(): void
    {
        $user = $this->user('supervisor@test.local', 'supervisor', 'DORAL');
        $token = $user->createToken('test', ['operacion'])->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Sede-Local', 'ZAMORA')
            ->getJson('/api/v1/inventario')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sede');
    }

    public function test_inventory_is_paginated_and_searchable(): void
    {
        $user = $this->user('admin-inventory@test.local', 'admin');
        $token = $user->createToken('test', ['operacion'])->plainTextToken;
        $this->product('AAA-1', 'Audífonos', 'Audio');
        $this->product('BBB-2', 'Cargador', 'Energía');

        $this->withToken($token)
            ->withHeader('X-Sede-Local', 'ZAMORA')
            ->getJson('/api/v1/inventario?per_page=1&q=aud')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.codigo', 'AAA-1')
            ->assertJsonPath('data.0.stocks.JRZ', 10);
    }

    public function test_requisition_crud_is_scoped_and_applied_rows_are_read_only(): void
    {
        $owner = $this->user('owner@test.local', 'supervisor', 'ZAMORA');
        $other = $this->user('other@test.local', 'supervisor', 'ZAMORA');
        $ownerToken = $owner->createToken('test', ['operacion'])->plainTextToken;
        $otherToken = $other->createToken('test', ['operacion'])->plainTextToken;
        $this->product('REQ-1', 'Producto requisición', 'Pruebas');

        $created = $this->withToken($ownerToken)
            ->withHeader('X-Sede-Local', 'ZAMORA')
            ->postJson('/api/v1/requisiciones', [
                'codigo' => 'REQ-1',
                'sede_origen' => 'JRZ',
                'cantidad' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'PENDIENTE');

        $id = $created->json('data.id');
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)
            ->withHeader('X-Sede-Local', 'ZAMORA')
            ->getJson('/api/v1/requisiciones')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $otherCreated = $this->withToken($otherToken)
            ->withHeader('X-Sede-Local', 'ZAMORA')
            ->postJson('/api/v1/requisiciones', [
                'codigo' => 'REQ-1',
                'sede_origen' => 'JRZ',
                'cantidad' => 1,
            ])
            ->assertCreated();
        $this->assertNotSame($id, $otherCreated->json('data.id'));
        $this->withToken($otherToken)
            ->withHeader('X-Sede-Local', 'ZAMORA')
            ->deleteJson("/api/v1/requisiciones/{$id}")
            ->assertNotFound();

        RequisicionManual::findOrFail($id)->update(['aplicada_at' => now()]);
        $this->app['auth']->forgetGuards();
        $this->withToken($ownerToken)
            ->withHeader('X-Sede-Local', 'ZAMORA')
            ->deleteJson("/api/v1/requisiciones/{$id}")
            ->assertUnprocessable();
    }

    private function user(string $email, string $role, ?string $sede = null): User
    {
        return User::create([
            'name' => 'Usuario API',
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'sede' => $sede,
        ]);
    }

    private function product(string $code, string $name, string $category): Product
    {
        $product = Product::create([
            'cod_centro' => $code,
            'producto' => $name,
            'categoria' => $category,
        ]);

        ProductSedeMetric::create([
            'product_id' => $product->id,
            'sede' => 'ZAMORA',
            'existencia' => 2,
            'ventas_60d' => 8,
            'promedio_15d' => 2,
        ]);
        ProductSedeMetric::create([
            'product_id' => $product->id,
            'sede' => 'JRZ',
            'existencia' => 10,
            'ventas_60d' => 2,
            'promedio_15d' => 1,
        ]);

        return $product;
    }
}
