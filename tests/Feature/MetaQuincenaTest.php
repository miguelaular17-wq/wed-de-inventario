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
        if (! Schema::hasColumn('meta_quincena_productos', 'valor_unitario')) {
            (require database_path('migrations/2026_11_19_160000_add_valor_unitario_to_meta_quincena_productos_table.php'))->up();
        }
        if (! Schema::hasColumn('meta_quincena_productos', 'cantidad_arrastre')) {
            (require database_path('migrations/2026_11_24_140000_add_arrastre_to_meta_quincena_productos.php'))->up();
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

    public function test_sin_metas_asignadas_el_personal_de_tienda_igual_entra_y_no_ve_las_de_otros(): void
    {
        foreach ([User::ROLE_TELEFONIA, User::ROLE_VENDEDOR] as $rol) {
            $this->actingAs($this->usuario($rol, 'ZAMORA'))->get(route('metas.index'))
                ->assertOk()
                ->assertDontSee('PERFUME DE ANA')
                ->assertDontSee('RELOJ DE LUIS')
                ->assertSee('Aún no tienes productos meta asignados');
        }
    }

    public function test_sin_metas_asignadas_otro_rol_sin_permiso_no_entra(): void
    {
        $this->actingAs($this->usuario(User::ROLE_TECNICO, 'ZAMORA'))
            ->get(route('metas.index'))
            ->assertForbidden();
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

    public function test_gerente_puede_definir_valor_unitario_de_la_meta(): void
    {
        $gerente = $this->usuario(User::ROLE_GERENTE, null);

        $this->actingAs($gerente)
            ->patchJson(route('metas.valor', $this->deAna), ['valor_unitario' => 2.5])
            ->assertOk()
            ->assertJsonPath('valor_unitario', 2.5);

        $this->assertEquals(2.5, (float) $this->deAna->fresh()->valor_unitario);

        $this->actingAs($this->usuario(User::ROLE_TELEFONIA, 'ZAMORA'))
            ->patchJson(route('metas.valor', $this->deAna), ['valor_unitario' => 9])
            ->assertForbidden();
        $this->assertEquals(2.5, (float) $this->deAna->fresh()->valor_unitario);
    }

    public function test_el_bono_de_meta_usa_valor_por_unidades_vendidas_hasta_la_meta(): void
    {
        $this->deAna->update(['valor_unitario' => 2, 'cantidad_inicial' => 3]);
        $q = app(MetaQuincenaService::class)->quincenaActual();

        if (Schema::hasTable('ventas_detalle')) {
            DB::table('ventas_detalle')->insert([
                'producto_id' => $this->deAna->producto_id,
                'sede' => 'ZAMORA',
                'fecha' => $q['inicio']->toDateString(),
                'cantidad' => 5,
                'tipo_documento' => 'FAC',
                'numero_documento' => 'F-META-1',
                'anulado' => false,
            ]);
        }

        $bono = app(MetaQuincenaService::class)->bonoComisionResponsable(
            $this->ana->id,
            $q['inicio'],
            $q['fin']
        );

        // 5 vendidas, meta 3 → paga 3 × $2 = $6
        $this->assertEquals(6.0, $bono['total']);
        $this->assertCount(1, $bono['lineas']);
        $this->assertEquals(3.0, $bono['lineas'][0]['unidades_pago']);
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

        // Gerente con ficha de nómina y su propio equipo: igual ve a todo el personal de la sede.
        $gerente = $this->usuario(User::ROLE_GERENTE, null);
        $admon = DB::table('nomina_sedes')->insertGetId(['nombre' => 'Administración', 'codigo' => 'ADMINISTRACION']);
        $fichaGerente = $this->empleado('MIGUEL GERENTE', '44444444', $admon);
        $fichaGerente->update(['user_id' => $gerente->id]);
        $this->empleado('ASISTENTE ADMON', '55555555', $admon)->update(['supervisor_id' => $fichaGerente->id]);

        $this->actingAs($gerente)
            ->get(route('metas.index', ['sede' => 'TECNOHOUSE']))
            ->assertOk()
            ->assertSee('<option value="'.$kleiver->id.'"', false);
    }

    public function test_buscar_producto_o_responsable_mira_todas_las_quincenas_y_muestra_fecha_asignacion(): void
    {
        $servicio = app(MetaQuincenaService::class);
        $actual = $servicio->quincenaActual();
        $anterior = $servicio->quincenaAnterior($actual['inicio']);

        $productoViejo = DB::table('productos')->insertGetId([
            'codigo' => 'BUSQ-OLD',
            'nombre' => 'LICUADORA HISTORICA',
        ]);
        $metaVieja = MetaQuincenaProducto::create([
            'producto_id' => $productoViejo,
            'sede' => 'ZAMORA',
            'quincena_inicio' => $anterior['inicio']->toDateString(),
            'quincena_fin' => $anterior['fin']->toDateString(),
            'cantidad_inicial' => 2,
            'responsable_empleado_id' => $this->ana->id,
        ]);
        $metaVieja->forceFill([
            'created_at' => '2026-10-02 09:15:00',
            'updated_at' => '2026-10-02 09:15:00',
        ])->saveQuietly();

        $user = $this->usuario(User::ROLE_GERENTE, null);

        $this->actingAs($user)->get(route('metas.index', ['q' => 'LICUADORA']))
            ->assertOk()
            ->assertSee('LICUADORA HISTORICA')
            ->assertSee('todas las quincenas')
            ->assertSee('02/10/2026')
            ->assertSee($anterior['inicio']->format('d/m/Y'))
            ->assertDontSee('VENTILADOR CENTRO');

        $this->actingAs($user)->get(route('metas.index', ['q' => 'ANA TELEFONIA']))
            ->assertOk()
            ->assertSee('LICUADORA HISTORICA')
            ->assertSee('PERFUME DE ANA')
            ->assertSee('Asignada')
            ->assertDontSee('RELOJ DE LUIS');
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
