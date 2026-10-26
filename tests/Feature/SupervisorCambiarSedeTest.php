<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPermission;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class SupervisorCambiarSedeTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        config(['inventario.sedes_locales' => ['DORAL', 'JRZ']]);
    }

    public function test_supervisor_sin_permiso_sigue_con_su_sede_fija(): void
    {
        $sup = $this->supervisor();

        $this->assertTrue($sup->sedeIsLocked());
        $this->actingAs($sup)->withSession(['sede_local' => 'DORAL'])
            ->post(route('sede.store'), ['sede_local' => 'JRZ'])
            ->assertSessionHasErrors('error')
            ->assertSessionHas('sede_local', 'DORAL');
    }

    public function test_supervisor_con_permiso_puede_operar_en_otra_sede(): void
    {
        $sup = $this->supervisor();
        UserPermission::create(['user_id' => $sup->id, 'permission' => 'sede.cambiar']);
        $sup = $sup->fresh();

        $this->assertFalse($sup->sedeIsLocked());
        $this->assertTrue($sup->tieneSedeFija());

        $this->actingAs($sup)->withSession(['sede_local' => 'DORAL'])
            ->post(route('sede.change'))
            ->assertRedirect(route('sede.select'))
            ->assertSessionMissing('sede_local');

        $this->actingAs($sup)
            ->post(route('sede.store'), ['sede_local' => 'JRZ'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sede_local', 'JRZ');

        // Opera en JRZ, pero su sede asignada no cambia.
        $this->assertSame('DORAL', $sup->fresh()->sede);
    }

    public function test_la_sede_asignada_de_un_rol_con_sede_fija_solo_la_cambia_un_admin(): void
    {
        $sup = $this->supervisor();
        UserPermission::create(['user_id' => $sup->id, 'permission' => 'sede.cambiar']);

        $this->actingAs($sup->fresh())
            ->post(route('user.sede.update'), ['sede' => 'JRZ'])
            ->assertForbidden();
        $this->assertSame('DORAL', $sup->fresh()->sede);
    }

    public function test_el_permiso_aparece_para_asignar(): void
    {
        $this->assertArrayHasKey('sede.cambiar', config('permissions.assignable'));
        $this->assertContains('sede.cambiar', config('permissions.groups.Operaciones'));
    }

    private function supervisor(): User
    {
        return User::create([
            'name' => 'Supervisor',
            'email' => 'sup-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);
    }
}
