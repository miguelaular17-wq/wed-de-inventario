<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccesosService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class AccesosBloqueadosTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        (require database_path('migrations/2026_10_26_120000_create_accesos_bloqueados_table.php'))->up();
    }

    public function test_cada_acceso_del_catalogo_apunta_a_rutas_que_existen(): void
    {
        $nombres = collect(Route::getRoutes()->getRoutes())->map->getName()->filter()->values()->all();
        foreach (config('accesos.grupos') as $grupo => $items) {
            foreach ($items as $clave => $acceso) {
                foreach ($acceso['rutas'] as $patron) {
                    $this->assertNotEmpty(
                        array_filter($nombres, fn ($n) => Str::is($patron, $n)),
                        "«{$grupo} · {$acceso['label']}» ({$clave}): el patrón {$patron} no coincide con ninguna ruta."
                    );
                }
            }
        }
    }

    public function test_al_gerente_se_le_puede_quitar_un_acceso_y_una_funcion(): void
    {
        $admin = $this->usuario(User::ROLE_ADMIN);
        $gerente = $this->usuario(User::ROLE_GERENTE);
        $otroGerente = $this->usuario(User::ROLE_GERENTE);

        $this->actingAs($admin)->post(route('admin.accesos.guardar'), [
            'usuario' => $gerente->id,
            'claves' => ['reportes', 'permiso:finanzas.eliminar', 'permiso:compras.distribucion', 'no_existe'],
        ])->assertRedirect(route('admin.accesos.index', ['usuario' => $gerente->id]));

        $this->assertSame(3, \DB::table('accesos_bloqueados')->where('user_id', $gerente->id)->count());

        $this->actingAs($gerente->fresh())->get(route('reportes.index'))->assertForbidden()->assertSee('desactivado por el administrador');
        $this->actingAs($gerente->fresh())->getJson(route('reportes.index'))->assertStatus(403);

        $svc = app(AccesosService::class);
        $g = $gerente->fresh();
        $this->assertFalse($g->canAccess('finanzas.eliminar'));
        $this->assertTrue($g->canAccess('finanzas.ver'));
        $this->assertFalse($g->canAccessComprasTab('distribucion'));
        $this->assertTrue($g->canAccessComprasTab('proveedores'));

        // Otro gerente y el administrador no se ven afectados.
        $this->assertFalse($svc->rutaBloqueada($otroGerente, 'reportes.index'));
        $this->assertTrue($otroGerente->canAccess('finanzas.eliminar'));
        $this->assertFalse($svc->rutaBloqueada($admin, 'reportes.index'));
    }

    public function test_desactivado_para_todos_aplica_a_cualquiera_menos_al_admin_y_sale_del_menu(): void
    {
        $admin = $this->usuario(User::ROLE_ADMIN);
        $supervisor = $this->usuario(User::ROLE_SUPERVISOR);
        $gerente = $this->usuario(User::ROLE_GERENTE);

        $this->actingAs($admin)->post(route('admin.accesos.guardar'), ['claves' => ['etiquetas', 'ventas_diarias']])
            ->assertRedirect(route('admin.accesos.index'));

        $svc = app(AccesosService::class);
        foreach ([$supervisor, $gerente] as $u) {
            $this->assertTrue($svc->rutaBloqueada($u, 'etiquetas.index'));
            $this->assertTrue($svc->rutaBloqueada($u, 'ventas_diarias.create'));
            $this->assertFalse($svc->rutaBloqueada($u, 'ventas.index'));
        }
        $this->assertFalse($svc->rutaBloqueada($admin, 'etiquetas.index'));

        $menu = [
            ['type' => 'link', 'label' => 'Ventas', 'url' => route('ventas.index')],
            ['type' => 'link', 'label' => 'Etiquetas', 'url' => route('etiquetas.index')],
            ['type' => 'drop', 'label' => 'Finanzas', 'items' => [
                ['type' => 'link', 'label' => 'Ventas diarias', 'url' => route('ventas_diarias.index')],
            ]],
        ];
        $this->assertSame(['Ventas'], array_column($svc->filtrarMenu($gerente, $menu), 'label'));
        $this->assertCount(3, $svc->filtrarMenu($admin, $menu));

        // La persona ve en gris lo que está desactivado para todos; quitarlo de todos lo devuelve.
        $this->actingAs($admin)->get(route('admin.accesos.index', ['usuario' => $gerente->id]))
            ->assertOk()->assertSee('para todos')->assertSee('Desactivar para');
        $this->actingAs($admin)->post(route('admin.accesos.guardar'), ['claves' => []]);
        $this->assertFalse(app(AccesosService::class)->rutaBloqueada($gerente, 'etiquetas.index'));
    }

    public function test_accesos_rapidos_de_la_pantalla_de_inicio(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Acceso rápido')->assertSee('Sacar etiquetas')->assertSee('Lector QR');

        $admin = $this->usuario(User::ROLE_ADMIN);
        $this->actingAs($admin)->post(route('admin.accesos.guardar'), ['claves' => ['rapido_etiquetas', 'rapido_q_pedir']])->assertRedirect();
        auth()->logout();

        $this->get(route('login'))->assertOk()
            ->assertDontSee('Sacar etiquetas')->assertDontSee('id="btn-q-pedir"', false)
            ->assertSee('Lector QR')->assertSee('Existencias');
        $this->get(route('etiquetas.index'))->assertForbidden();
        $this->getJson(route('pedidos.categorias'))->assertStatus(403);

        // Con sesión no aplica: para eso está el acceso normal "Sacar etiquetas".
        $vendedor = $this->usuario(User::ROLE_VENDEDOR);
        $this->assertFalse(app(AccesosService::class)->rutaBloqueada($vendedor, 'etiquetas.index'));

        // Por persona no se guardan (solo existen "para todos").
        $this->actingAs($admin)->post(route('admin.accesos.guardar'), ['usuario' => $vendedor->id, 'claves' => ['rapido_lector_qr', 'reportes']]);
        $this->assertSame(['reportes'], \DB::table('accesos_bloqueados')->where('user_id', $vendedor->id)->pluck('clave')->all());
        $this->actingAs($admin)->get(route('admin.accesos.index', ['usuario' => $vendedor->id]))->assertOk()->assertDontSee('pantalla de inicio');
        $this->actingAs($admin)->get(route('admin.accesos.index'))->assertOk()->assertSee('Accesos rápidos (pantalla de inicio)');

        // Todos desactivados: desaparece el bloque completo.
        $this->actingAs($admin)->post(route('admin.accesos.guardar'), ['claves' => app(AccesosService::class)->clavesInvitado()]);
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertDontSee('Acceso rápido');
    }

    public function test_solo_el_administrador_gestiona_los_accesos(): void
    {
        $gerente = $this->usuario(User::ROLE_GERENTE);
        $this->actingAs($gerente)->get(route('admin.accesos.index'))->assertForbidden();
        $this->actingAs($gerente)->post(route('admin.accesos.guardar'), ['claves' => ['reportes']])->assertForbidden();
        $this->assertSame(0, \DB::table('accesos_bloqueados')->count());

        $admin = $this->usuario(User::ROLE_ADMIN);
        $this->actingAs($admin)->get(route('admin.accesos.index'))->assertOk()
            ->assertSee('Accesos y funciones')->assertSee('Todos los reportes')->assertSee('Eliminar egresos');
        $this->actingAs($admin)->post(route('admin.accesos.guardar'), ['usuario' => $admin->id, 'claves' => ['reportes']])->assertNotFound();
    }

    private function usuario(string $rol): User
    {
        return User::create([
            'name' => ucfirst($rol) . ' ' . uniqid(),
            'email' => $rol . '-' . uniqid() . '@test.local',
            'password' => 'password123',
            'role' => $rol,
            'sede' => 'DORAL',
        ]);
    }
}
