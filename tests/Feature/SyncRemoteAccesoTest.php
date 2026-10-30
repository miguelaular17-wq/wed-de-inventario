<?php

namespace Tests\Feature;

use App\Models\SyncCommand;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class SyncRemoteAccesoTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        // Los modelos del sincronizador usan la conexión pgsql: en la prueba es un sqlite en memoria.
        config(['database.connections.pgsql' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        DB::purge('pgsql');
        DB::connection('pgsql')->statement("ATTACH DATABASE ':memory:' AS inventario_v2");
        $schema = Schema::connection('pgsql');
        $schema->create('inventario_v2.sync_heartbeats', function (Blueprint $t) {
            $t->id();
            $t->string('sede', 20);
            $t->string('version', 20)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->string('estado', 20)->default('activo');
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        $schema->create('inventario_v2.sync_commands', function (Blueprint $t) {
            $t->id();
            $t->string('sede', 20);
            $t->string('comando', 50);
            $t->json('parametros')->nullable();
            $t->string('estado', 20)->default('pendiente');
            $t->text('mensaje')->nullable();
            $t->string('solicitado_por', 120)->nullable();
            $t->timestamp('ejecutado_at')->nullable();
            $t->timestamps();
        });
        DB::connection('pgsql')->table('inventario_v2.sync_heartbeats')->insert(['sede' => 'DORAL', 'version' => '2.2.0', 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function usuario(string $role): User
    {
        return User::create(['name' => 'U '.$role, 'email' => $role.uniqid().'@test.local', 'password' => 'password123', 'role' => $role]);
    }

    public function test_sin_el_permiso_no_entra(): void
    {
        $this->actingAs($this->usuario(User::ROLE_CONTABILIDAD));

        $this->get(route('admin.sync.index'))->assertRedirect('/');
        $this->post(route('admin.sync.command'), ['sede' => 'DORAL', 'comando' => 'sync_stock'])->assertRedirect('/');
        $this->assertSame(0, SyncCommand::query()->count());
    }

    public function test_con_el_permiso_que_da_el_admin_ve_el_panel_y_sincroniza_pero_no_publica_ni_separa(): void
    {
        $user = $this->usuario(User::ROLE_CONTABILIDAD);
        $user->syncExtraPermissions(['sincronizadores']);
        $this->actingAs($user->fresh());

        $this->get(route('admin.sync.index'))->assertOk()
            ->assertSee('DORAL')
            ->assertSee('Enviar Comando Remoto')
            ->assertDontSee('Publicar nueva versión')
            ->assertDontSee('Separar en la web');
        $this->getJson(route('admin.sync.status'))->assertOk()->assertJsonPath('heartbeats.0.sede', 'DORAL');

        $this->post(route('admin.sync.command'), ['sede' => 'DORAL', 'comando' => 'sync_stock'])
            ->assertSessionHas('success');
        $this->assertSame(1, SyncCommand::query()->where('comando', 'sync_stock')->count());

        $this->post(route('admin.sync.command'), ['sede' => 'DORAL', 'comando' => 'separar_codigos_compuestos'])
            ->assertSessionHasErrors('comando');
        $this->assertSame(0, SyncCommand::query()->where('comando', 'separar_codigos_compuestos')->count());

        $this->post(route('admin.sync.publish'), ['version' => '9.9.9'])->assertRedirect(route('login'));
    }

    public function test_el_admin_sigue_viendo_publicar_y_separar(): void
    {
        $this->actingAs($this->usuario('admin'));

        $this->get(route('admin.sync.index'))->assertOk()
            ->assertSee('Publicar nueva versión')
            ->assertSee('Separar en la web');
    }

    public function test_sin_sesion_no_entra(): void
    {
        $this->get(route('admin.sync.index'))->assertRedirect(route('login'));
    }
}
