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
