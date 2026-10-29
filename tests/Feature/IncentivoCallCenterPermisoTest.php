<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPermission;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class IncentivoCallCenterPermisoTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
    }

    public function test_el_permiso_aparece_para_asignar(): void
    {
        $this->assertArrayHasKey('incentivos.call_center', config('permissions.assignable'));
        $this->assertContains('incentivos.call_center', config('permissions.groups.Gerencial'));
        $this->assertSame('incentivos.call_center', config('incentivos.modulos.call_center.permiso'));
    }

    public function test_con_solo_call_center_no_entra_a_los_otros_modulos(): void
    {
        $user = $this->usuario();
        UserPermission::create(['user_id' => $user->id, 'permission' => 'incentivos.call_center']);
        $user = $user->fresh();

        $this->assertTrue($user->canAccess('incentivos.call_center'));
        $this->assertFalse($user->canAccess('incentivos'));

        $this->actingAs($user)->get(route('incentivos.show', 'ventas'))->assertForbidden();
        $this->actingAs($user)->get(route('incentivos.show', 'supervisor'))->assertForbidden();
    }

    public function test_sin_permiso_no_entra_a_call_center(): void
    {
        $this->actingAs($this->usuario())
            ->get(route('incentivos.show', 'call_center'))
            ->assertRedirect('/');
    }

    public function test_quien_ve_todos_los_incentivos_tambien_ve_call_center(): void
    {
        $user = $this->usuario();
        UserPermission::create(['user_id' => $user->id, 'permission' => 'incentivos']);

        $this->assertTrue($user->fresh()->canAccess('incentivos.call_center'));
    }

    private function usuario(): User
    {
        return User::create([
            'name' => 'Usuario',
            'email' => 'u-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);
    }
}
