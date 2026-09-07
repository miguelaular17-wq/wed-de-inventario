<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class UserPermissionAccessTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
    }

    public function test_vendedor_does_not_get_foreign_role_views_by_default(): void
    {
        $user = $this->makeUser(User::ROLE_VENDEDOR);

        $this->assertFalse($user->canAccess('nomina'));
        $this->assertFalse($user->canAccess('operacion'));

        $this->actingAs($user)
            ->get(route('nomina.empleados.index'))
            ->assertRedirect('/');
    }

    public function test_extra_permission_unlocks_a_view_from_another_role(): void
    {
        $user = $this->makeUser(User::ROLE_VENDEDOR);
        $user->syncExtraPermissions(['nomina']);

        $this->assertTrue($user->canAccess('nomina'));
        $this->assertFalse($user->canAccess('cobranza'));

        $this->actingAs($user)
            ->get(route('nomina.empleados.index'))
            ->assertOk();
    }

    public function test_role_permissions_are_not_stored_as_extras(): void
    {
        $user = $this->makeUser(User::ROLE_COBRANZA);
        $user->syncExtraPermissions(['cobranza', 'nomina']);

        $this->assertEquals(['nomina'], $user->fresh()->extraPermissionKeys());
        $this->assertTrue($user->fresh()->canAccess('cobranza'));
        $this->assertTrue($user->fresh()->canAccess('nomina'));
    }

    public function test_finanzas_editar_implies_finanzas_ver(): void
    {
        $user = $this->makeUser(User::ROLE_VENDEDOR);
        $user->syncExtraPermissions(['finanzas.editar']);

        $this->assertTrue($user->canAccess('finanzas.editar'));
        $this->assertTrue($user->canAccess('finanzas.ver'));
        $this->assertFalse($user->canAccess('conciliaciones'));
    }

    public function test_auditor_does_not_need_sede_and_skips_selection(): void
    {
        $auditor = $this->makeUser(User::ROLE_AUDITOR);

        $this->assertFalse($auditor->requiresSede());

        $this->actingAs($auditor)
            ->get(route('sede.select'))
            ->assertRedirect('/');

        $this->actingAs($auditor)
            ->get('/')
            ->assertRedirect(route('finanzas.flujo_caja'));
    }

    public function test_finanzas_role_can_delete_egresos_transfers_divisas_and_avances(): void
    {
        $finanzas = $this->makeUser(User::ROLE_FINANZAS);
        $auditor = $this->makeUser(User::ROLE_AUDITOR);

        $this->assertTrue($finanzas->canAccess('finanzas.eliminar'));
        $this->assertFalse($auditor->canAccess('finanzas.eliminar'));
        $this->assertFalse($this->makeUser(User::ROLE_VENDEDOR)->canAccess('finanzas.eliminar'));
    }

    public function test_supervisor_can_be_limited_to_sobrestock_compras_tab(): void
    {
        $user = $this->makeUser(User::ROLE_SUPERVISOR);
        $user->syncExtraPermissions(['compras.sobrestock']);

        $this->assertFalse($user->canAccess('compras'));
        $this->assertTrue($user->canEnterCompras());
        $this->assertTrue($user->canAccessComprasTab('sobrestock'));
        $this->assertFalse($user->canAccessComprasTab('distribucion'));
        $this->assertFalse($user->canAccessComprasTab('qpedir'));
        $this->assertFalse($user->canAccessComprasTab('existencias'));
        $this->assertSame(
            ['tab' => 'sobrestock'],
            $user->defaultComprasRouteParams()
        );

        $this->actingAs($user)
            ->get(route('comprador.dashboard', ['tab' => 'productos']))
            ->assertRedirect(route('comprador.dashboard', ['tab' => 'sobrestock']));

        $this->actingAs($user)
            ->get(route('comprador.existencias'))
            ->assertForbidden();
    }

    public function test_full_compras_extra_still_unlocks_every_tab(): void
    {
        $user = $this->makeUser(User::ROLE_SUPERVISOR);
        $user->syncExtraPermissions(['compras']);

        $this->assertTrue($user->hasFullComprasAccess());
        $this->assertTrue($user->canAccessComprasTab('distribucion'));
        $this->assertTrue($user->canAccessComprasTab('sobrestock'));
        $this->assertTrue($user->canAccessComprasTab('existencias'));
    }

    public function test_supervisor_does_not_see_catalog_prices_without_permission(): void
    {
        $supervisor = $this->makeUser(User::ROLE_SUPERVISOR);
        $vendedor = $this->makeUser(User::ROLE_VENDEDOR);

        $this->assertFalse($supervisor->canSeeCatalogoPrecios());
        $this->assertTrue($vendedor->canSeeCatalogoPrecios());

        $supervisor->syncExtraPermissions(['catalogo.precios']);
        $this->assertTrue($supervisor->fresh()->canSeeCatalogoPrecios());
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'Usuario '.$role,
            'email' => $role.'-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => $role,
            'sede' => 'DORAL',
        ]);
    }
}
