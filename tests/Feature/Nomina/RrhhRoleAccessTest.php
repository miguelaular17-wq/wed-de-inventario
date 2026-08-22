<?php

namespace Tests\Feature\Nomina;

use App\Models\User;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class RrhhRoleAccessTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
    }

    public function test_rrhh_accede_a_empleados_y_no_a_admin(): void
    {
        $user = User::create([
            'name' => 'Diana RRHH',
            'email' => 'rrhh@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);

        $this->actingAs($user);

        $this->get('/')->assertRedirect(route('nomina.empleados.index'));
        $this->get(route('nomina.empleados.index'))->assertOk();
        $this->get(route('nomina.organizacion'))->assertOk();
        $this->get(route('nomina.configuracion.index'))->assertOk()->assertSee('Valor por día');
        $this->get(route('admin.dashboard'))->assertRedirect();
        $this->get(route('finanzas.flujo_caja'))->assertRedirect('/');
    }

    public function test_vendedor_no_accede_a_nomina(): void
    {
        $user = User::create([
            'name' => 'Vendedor',
            'email' => 'vendedor-nomina@test.local',
            'password' => 'password123',
            'role' => User::ROLE_VENDEDOR,
        ]);

        $this->actingAs($user);

        $this->get(route('nomina.empleados.index'))->assertRedirect('/');
    }

    public function test_catalogo_clientes_solo_admin(): void
    {
        config(['inventario.catalogo_cliente_token' => 'tokentestcatalogocliente12']);

        $admin = User::create([
            'name' => 'Admin Cat',
            'email' => 'admin-cat@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);
        $gerente = User::create([
            'name' => 'Gerente Cat',
            'email' => 'gerente-cat@test.local',
            'password' => 'password123',
            'role' => User::ROLE_GERENTE,
        ]);
        $supervisor = User::create([
            'name' => 'Supervisor Cat',
            'email' => 'supervisor-cat@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($admin)
            ->get(route('catalogo.ir_clientes'))
            ->assertRedirect(route('catalogo.cliente', 'tokentestcatalogocliente12'));

        $this->actingAs($gerente)
            ->get(route('catalogo.ir_clientes'))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->get(route('catalogo.ir_clientes'))
            ->assertForbidden();
    }
}
