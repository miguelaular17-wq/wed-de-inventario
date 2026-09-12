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

    public function test_asignar_cobranza_guarda_tipo_de_clientes(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $user = $this->makeUser(User::ROLE_VENDEDOR);

        $this->actingAs($admin)
            ->post(route('admin.users.update', $user), [
                'role' => User::ROLE_VENDEDOR,
                'sede' => 'DORAL',
                'extra_permissions' => ['cobranza'],
                'cobranza_clientes' => 'personales',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->canAccess('cobranza'));
        $this->assertEquals('personales', $user->cobranza_clientes);
        $this->assertEquals('personales', $user->alcanceCobranzaClientes());
        $this->assertFalse($user->puedeCambiarTipoClienteCobranza());
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

    public function test_supervisor_can_download_qpedir_daily_report_for_own_sede(): void
    {
        $this->ensurePedidosSolicitadosTable();

        $supervisor = $this->makeUser(User::ROLE_SUPERVISOR);
        $this->assertTrue($supervisor->canAccess('compras.reporte_sede'));
        $this->assertFalse($supervisor->canAccessComprasTab('qpedir'));

        $this->actingAs($supervisor)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('comprador.pedidos.diario_sede'))
            ->assertOk()
            ->assertHeader('content-disposition');

        $vendedor = $this->makeUser(User::ROLE_VENDEDOR);
        $this->actingAs($vendedor)
            ->get(route('comprador.pedidos.diario_sede'))
            ->assertRedirect('/');
    }

    public function test_compras_notifica_a_sede_cuando_el_producto_tiene_existencia(): void
    {
        $this->ensurePedidosSolicitadosTable();
        $comprador = $this->makeUser(User::ROLE_COMPRADOR);
        $receptor = $this->makeUser(User::ROLE_SUPERVISOR);
        \App\Models\PedidoSolicitado::create([
            'codigo' => 'TEST-EXISTENCIA',
            'producto' => 'Producto con existencia',
            'sede' => 'DORAL',
            'estado' => 'pendiente',
        ]);

        $this->actingAs($comprador)
            ->post(route('comprador.pedidos.tiene_existencia'), [
                'producto' => 'Producto con existencia',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pedidos_solicitados', [
            'producto' => 'Producto con existencia',
            'estado' => 'tiene_existencia',
        ]);
        $this->assertDatabaseHas('notifications', [
            'receiver_id' => $receptor->id,
            'message' => 'El producto "Producto con existencia" tiene existencia. No es necesario comprarlo; la sede DORAL solo debe realizar una requisición.',
        ]);
    }

    public function test_exportar_precios_catalogo_requiere_permiso(): void
    {
        $sinPermiso = $this->makeUser(User::ROLE_VENDEDOR);
        $conPermiso = $this->makeUser(User::ROLE_VENDEDOR);
        $conPermiso->syncExtraPermissions(['catalogo.exportar_precios']);

        $this->assertFalse($sinPermiso->canAccess('catalogo.exportar_precios'));
        $this->assertTrue($conPermiso->canAccess('catalogo.exportar_precios'));

        $this->actingAs($sinPermiso)
            ->get(route('catalogo.precios.export'))
            ->assertRedirect('/');

        $response = $this->actingAs($conPermiso)
            ->get(route('catalogo.precios.export'));

        $this->assertTrue(
            $response->isOk()
            || $response->isRedirect()
            || $response->status() === 302
        );
    }

    private function ensurePedidosSolicitadosTable(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pedidos_solicitados')) {
            \Illuminate\Support\Facades\Schema::create('pedidos_solicitados', function ($table) {
                $table->id();
                $table->unsignedBigInteger('producto_id')->nullable();
                $table->string('codigo', 64);
                $table->string('producto');
                $table->string('categoria')->nullable();
                $table->string('proveedor')->nullable();
                $table->string('solicitante')->nullable();
                $table->string('sede', 50)->nullable();
                $table->text('notas')->nullable();
                $table->string('estado', 32)->default('pendiente');
                $table->timestamp('atendido_at')->nullable();
                $table->timestamps();
            });
        }
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
