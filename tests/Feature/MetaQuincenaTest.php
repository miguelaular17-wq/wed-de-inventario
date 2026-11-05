<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MetaQuincenaProducto;
use App\Models\Nomina\NominaEmpleado;
use App\Models\User;
use App\Services\MetaQuincenaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class MetaQuincenaTest extends TestCase
{
    use CreatesNominaSchema;

    private MetaQuincenaProducto $deAna;

    private MetaQuincenaProducto $deLuis;

    private MetaQuincenaProducto $deCentro;

    private NominaEmpleado $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        if (! Schema::hasTable('meta_quincena_productos')) {
            (require database_path('migrations/2026_09_03_120000_create_meta_quincena_productos_table.php'))->up();
        }

        $zamora = DB::table('nomina_sedes')->insertGetId(['nombre' => 'Zamora', 'codigo' => 'ZAMORA']);
        $this->ana = $this->empleado('ANA TELEFONIA', '11111111', $zamora);
        $luis = $this->empleado('LUIS VENDEDOR', '22222222', $zamora);

        $q = app(MetaQuincenaService::class)->quincenaActual();
        $meta = fn (string $producto, string $sede, ?int $responsable) => MetaQuincenaProducto::create([
            'producto_id' => DB::table('productos')->insertGetId(['codigo' => $producto, 'nombre' => $producto]),
            'sede' => $sede,
            'quincena_inicio' => $q['inicio']->toDateString(),
            'quincena_fin' => $q['fin']->toDateString(),
            'cantidad_inicial' => 3,
            'responsable_empleado_id' => $responsable,
        ]);
        $this->deAna = $meta('PERFUME DE ANA', 'ZAMORA', $this->ana->id);
        $this->deLuis = $meta('RELOJ DE LUIS', 'ZAMORA', $luis->id);
        $this->deCentro = $meta('VENTILADOR CENTRO', 'CENTRO', null);
    }

    public function test_telefonia_solo_ve_las_metas_donde_es_responsable_y_no_las_reasigna(): void
    {
        $user = $this->usuario(User::ROLE_TELEFONIA, 'ZAMORA');
        $this->ana->update(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('metas.index'))
            ->assertOk()
            ->assertSee('PERFUME DE ANA')
            ->assertDontSee('RELOJ DE LUIS')
            ->assertDontSee('VENTILADOR CENTRO')
            ->assertSee('donde eres el responsable');

        $this->actingAs($user)
            ->patchJson(route('metas.responsable', $this->deAna), ['responsable_empleado_id' => null])
            ->assertForbidden();
        $this->assertSame($this->ana->id, (int) $this->deAna->fresh()->responsable_empleado_id);
    }

    public function test_sin_metas_asignadas_telefonia_no_entra(): void
    {
        $user = $this->usuario(User::ROLE_TELEFONIA, 'ZAMORA');

        $this->actingAs($user)->get(route('metas.index'))->assertForbidden();
    }

    public function test_el_supervisor_ve_todas_las_metas_de_su_sede(): void
    {
        $user = $this->usuario(User::ROLE_SUPERVISOR, 'ZAMORA');

        $this->actingAs($user)->get(route('metas.index'))
            ->assertOk()
            ->assertSee('PERFUME DE ANA')
            ->assertSee('RELOJ DE LUIS')
            ->assertDontSee('VENTILADOR CENTRO');
    }

    public function test_el_filtro_de_sede_deja_solo_las_metas_de_esa_sede(): void
    {
        $user = $this->usuario(User::ROLE_GERENTE, null);

        $this->actingAs($user)->get(route('metas.index', ['sede' => 'centro']))
            ->assertOk()
            ->assertSee('VENTILADOR CENTRO')
            ->assertDontSee('PERFUME DE ANA')
            ->assertDontSee('RELOJ DE LUIS');

        $this->actingAs($user)->get(route('metas.index'))
            ->assertOk()
            ->assertSee('VENTILADOR CENTRO')
            ->assertSee('PERFUME DE ANA');
    }

    public function test_con_responsable_asignado_se_ve_el_nombre_y_el_selector_queda_oculto(): void
    {
        $html = $this->actingAs($this->usuario(User::ROLE_GERENTE, null))
            ->get(route('metas.index', ['sede' => 'ZAMORA']))
            ->assertOk()
            ->assertSee('ANA TELEFONIA')
            ->assertSee('Cambiar')
            ->getContent();

        $this->assertSame(2, substr_count($html, 'class="meta-responsable"'));
        $this->assertSame(2, substr_count($html, 'style="display:none;min-width:180px'));
    }

    public function test_al_marcar_la_meta_se_elige_el_vendedor_de_esa_sede(): void
    {
        $gerente = $this->usuario(User::ROLE_GERENTE, null);
        $producto = DB::table('productos')->insertGetId(['codigo' => 'R001', 'nombre' => 'RELOJ NUEVO']);
        DB::table('stock_actual')->insert(['producto_id' => $producto, 'sede' => 'ZAMORA', 'existencia' => 7]);
        $centro = DB::table('nomina_sedes')->insertGetId(['nombre' => 'Centro', 'codigo' => 'CENTRO']);
        $deCentro = $this->empleado('PEDRO CENTRO', '33333333', $centro);

        $vendedores = $this->actingAs($gerente)->getJson(route('metas.stock', $producto))
            ->assertOk()
            ->json('vendedores');
        $this->assertSame(['ANA TELEFONIA', 'LUIS VENDEDOR'], array_column($vendedores['ZAMORA'], 'nombre'));
        $this->assertSame(['PEDRO CENTRO'], array_column($vendedores['CENTRO'], 'nombre'));

        $this->actingAs($gerente)
            ->postJson(route('metas.store'), ['producto_id' => $producto, 'sede' => 'ZAMORA', 'responsable_empleado_id' => $deCentro->id])
            ->assertStatus(422);
        $this->assertFalse(MetaQuincenaProducto::query()->where('producto_id', $producto)->exists());

        $this->actingAs($gerente)
            ->postJson(route('metas.store'), ['producto_id' => $producto, 'sede' => 'ZAMORA', 'responsable_empleado_id' => $this->ana->id])
            ->assertOk()
            ->assertJsonPath('responsable_nombre', 'ANA TELEFONIA');
        $this->assertSame($this->ana->id, (int) MetaQuincenaProducto::query()->where('producto_id', $producto)->value('responsable_empleado_id'));

        $this->actingAs($gerente)->getJson(route('metas.stock', $producto))
            ->assertJsonPath('responsables.ZAMORA', 'ANA TELEFONIA');
    }

    public function test_una_sede_escrita_distinto_en_nomina_igual_trae_sus_vendedores(): void
    {
        $tecno = DB::table('nomina_sedes')->insertGetId(['nombre' => 'Tecno House', 'codigo' => 'Tecno House']);
        $kleiver = $this->empleado('KLEIVER SIBADA', '27924188', $tecno);
        $q = app(MetaQuincenaService::class)->quincenaActual();
        MetaQuincenaProducto::create([
            'producto_id' => DB::table('productos')->insertGetId(['codigo' => 'S26', 'nombre' => 'SAMSUNG S26']),
            'sede' => 'TECNOHOUSE',
            'quincena_inicio' => $q['inicio']->toDateString(),
            'quincena_fin' => $q['fin']->toDateString(),
            'cantidad_inicial' => 1,
        ]);

        $vendedores = app(MetaQuincenaService::class)->vendedoresPorSede(['TECNOHOUSE']);
        $this->assertSame([$kleiver->id], array_column($vendedores['TECNOHOUSE'], 'id'));

        $this->actingAs($this->usuario(User::ROLE_GERENTE, null))
            ->get(route('metas.index', ['sede' => 'TECNOHOUSE']))
            ->assertOk()
            ->assertSee('<option value="'.$kleiver->id.'"', false);
    }

    private function empleado(string $nombre, string $cedula, int $sedeId): NominaEmpleado
    {
        return NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => $cedula, 'nombre' => $nombre])->id,
            'sede_id' => $sedeId,
            'estado' => 'ACTIVO',
        ]);
    }

    private function usuario(string $rol, ?string $sede): User
    {
        return User::create([
            'name' => 'Usuario '.$rol,
            'email' => 'u-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => $rol,
            'sede' => $sede,
        ]);
    }
}
