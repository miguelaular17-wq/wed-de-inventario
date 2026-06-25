<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite']);
    }

    public function test_supervisor_role_access(): void
    {
        $user = User::create([
            'name' => 'Supervisor',
            'email' => 'supervisor@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL']);

        // Has access to Sede views
        $this->get(route('ventas.index'))->assertOk();
        $this->get(route('inventario.index'))->assertOk();
        $this->get(route('requisicion.form'))->assertOk();

        // Cannot access admin dashboard or user list
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_telefonia_role_category_restriction(): void
    {
        $user = User::create([
            'name' => 'Telefonia',
            'email' => 'telefonia@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TELEFONIA,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL']);

        $repo = app(\App\Services\ProductRepository::class);

        // Seed some sample products
        \App\Models\Product::create([
            'cod_centro' => '111111',
            'producto' => 'Samsung S24',
            'categoria' => 'Telefonía',
            'subcategoria' => 'Celulares',
            'proveedor' => 'Samsung',
        ]);

        \App\Models\Product::create([
            'cod_centro' => '222222',
            'producto' => 'Nutella 25g',
            'categoria' => 'Alimentos',
            'subcategoria' => 'Dulces',
            'proveedor' => 'Ferrero',
        ]);

        $products = $repo->loadForSede('DORAL');

        // Should only load Samsung S24 (Telefonía)
        $this->assertCount(1, $products);
        $this->assertEquals('111111', $products->first()['cod_centro']);
    }

    public function test_gerente_role_access_and_messaging(): void
    {
        $gerente = User::create([
            'name' => 'Gerente',
            'email' => 'gerente@test.local',
            'password' => 'password123',
            'role' => User::ROLE_GERENTE,
        ]);

        $supervisor = User::create([
            'name' => 'Supervisor Doral',
            'email' => 'supervisor@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($gerente);

        // Can access movements page
        $this->get(route('admin.movimientos.index'))->assertOk();

        // Can access gerente dashboard
        $this->get(route('gerente.dashboard'))->assertOk();

        // Cannot access admin dashboard
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        // Can send a message to supervisor
        $response = $this->post(route('gerente.message.send'), [
            'receiver_id' => $supervisor->id,
            'message' => 'Instrucción de inventario importante para Doral.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'sender_id' => $gerente->id,
            'receiver_id' => $supervisor->id,
            'message' => 'Instrucción de inventario importante para Doral.',
        ]);
    }

    public function test_comprador_role_access_and_redistribution_notifications(): void
    {
        $comprador = User::create([
            'name' => 'Comprador',
            'email' => 'comprador@test.local',
            'password' => 'password123',
            'role' => User::ROLE_COMPRADOR,
        ]);

        $supervisor = User::create([
            'name' => 'Supervisor Doral',
            'email' => 'supervisor@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($comprador);

        // Can access compras dashboard
        $this->get(route('comprador.dashboard'))->assertOk();

        // Can trigger redistribution alert notification
        $response = $this->post(route('comprador.notify'), [
            'codigo' => '999999',
            'producto' => 'Producto Prueba',
            'sede_destino' => 'DORAL',
            'sede_origen' => 'JRZ',
            'cantidad' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'sender_id' => $comprador->id,
            'receiver_id' => $supervisor->id,
        ]);
    }

    public function test_notifications_lifecycle(): void
    {
        $user = User::create([
            'name' => 'Supervisor Doral',
            'email' => 'supervisor@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);

        $notification = Notification::create([
            'receiver_id' => $user->id,
            'message' => 'Notificación de prueba',
        ]);

        $this->actingAs($user);

        // Appears in notifications index
        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notificación de prueba');

        // Mark as read
        $this->post(route('notifications.read', $notification))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_vendedor_role_access(): void
    {
        // Seed database with sedes stock config to prevent register route from failing on config validation
        config(['inventario.sedes_locales' => ['DORAL', 'JRZ']]);

        // 1. Check registration assigns vendedor role
        $response = $this->post(route('register.store'), [
            'name' => 'Vendedor Test',
            'email' => 'vendedor@test.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'sede' => 'DORAL',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'vendedor@test.local',
            'role' => User::ROLE_VENDEDOR,
            'sede' => 'DORAL',
        ]);

        $user = User::where('email', 'vendedor@test.local')->first();

        // Should be logged in and redirected to vendedor dashboard
        $response->assertRedirect(route('vendedor.dashboard'));

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL']);

        // 2. Can access vendedor dashboard
        $this->get(route('vendedor.dashboard'))->assertOk();

        // 3. Cannot access Sede views (redirected to / which will redirect back to vendedor.dashboard)
        $this->get(route('ventas.index'))->assertRedirect('/');

        // 4. Update role to Sede
        $user->role = User::ROLE_SEDE;
        $user->save();

        // 5. Now can access Sede views but cannot access vendedor dashboard
        $this->get(route('ventas.index'))->assertOk();
        $this->get(route('vendedor.dashboard'))->assertRedirect('/');
    }

    public function test_mayor_demanda_view_access(): void
    {
        $user = User::create([
            'name' => 'Supervisor Doral',
            'email' => 'supervisor_md@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('ventas.mayor_demanda'));

        $response->assertOk();
    }
}
