<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\GerencialDashboardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class GerencialDashboardServiceTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        Carbon::setTestNow(Carbon::parse('2026-08-22', 'America/Caracas'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ventas_por_sede_usan_cabecera_fac_menos_dev(): void
    {
        DB::table('ventas_documentos')->insert([
            ['sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => '1', 'fecha' => '2026-08-10', 'estado' => 'registrado', 'total_neto_usd' => 151645.02, 'total_neto_bs' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['sede' => 'DORAL', 'tipo_documento' => 'DEV', 'numero_documento' => '2', 'fecha' => '2026-08-11', 'estado' => 'registrado', 'total_neto_usd' => 6529.98, 'total_neto_bs' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['sede' => 'CENTRO', 'tipo_documento' => 'FAC', 'numero_documento' => '3', 'fecha' => '2026-08-10', 'estado' => 'registrado', 'total_neto_usd' => 50, 'total_neto_bs' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => '4', 'fecha' => '2026-08-10', 'estado' => 'guardado', 'total_neto_usd' => 9999, 'total_neto_bs' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = app(GerencialDashboardService::class);
        $periodo = $service->resolverPeriodo('mes', null, null);
        $resumen = $service->resumen($periodo, 'todas', null, null, null);
        $doral = collect($resumen['por_sede'])->firstWhere('sede', 'DORAL');

        $this->assertSame(145115.04, $doral['ventas_usd']);
        $this->assertSame(1, $doral['facturas']);
        $this->assertSame(1, $doral['devoluciones']);
        $this->assertSame(6529.98, $doral['devoluciones_usd']);
        $this->assertEquals(145165.04, $resumen['total']['ventas_usd']);
        $this->assertContains('NUNES', array_column($resumen['por_sede'], 'sede'));
        $this->assertContains('JRZ', array_column($resumen['por_sede'], 'sede'));
    }

    public function test_gerente_ve_el_dashboard_y_supervisor_no(): void
    {
        $gerente = User::create([
            'name' => 'Gerente',
            'email' => 'gerente-dash@test.local',
            'password' => 'password123',
            'role' => User::ROLE_GERENTE,
        ]);
        $this->actingAs($gerente)
            ->get(route('gerencial.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard gerencial')
            ->assertSee('DORAL');

        $supervisor = User::create([
            'name' => 'Supervisor',
            'email' => 'sup-dash@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);
        $this->actingAs($supervisor)
            ->get(route('gerencial.dashboard'))
            ->assertRedirect();
    }

    public function test_gerente_ve_modulos_de_devoluciones_valorizados_y_ajustes(): void
    {
        $productoId = DB::table('productos')->insertGetId([
            'codigo' => 'ABC',
            'nombre' => 'Producto test',
            'categoria' => 'HOGAR',
            'costo_actual' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_actual')->insert([
            'producto_id' => $productoId,
            'sede' => 'DORAL',
            'existencia' => 4,
        ]);
        DB::table('clientes')->insert([
            'cedula' => '30657986',
            'nombre' => 'RICARDO GIMENEZ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ajustes_inventario')->insert([
            [
                'sede' => 'DORAL',
                'tipo_movimiento' => 'AJU',
                'numero_documento' => 'A-1',
                'fecha' => '2026-08-12',
                'codigo_producto' => 'ABC',
                'nombre_producto' => 'Producto test',
                'cantidad' => 2,
                'costo_unitario' => 10,
                'motivo' => 'Conteo',
                'usuario' => 'V30657986',
            ],
            [
                'sede' => 'DORAL',
                'tipo_movimiento' => 'AJU',
                'numero_documento' => 'A-2',
                'fecha' => '2026-08-13',
                'codigo_producto' => 'ABC',
                'nombre_producto' => 'Producto test',
                'cantidad' => 1,
                'costo_unitario' => 10,
                'motivo' => 'Conteo',
                'usuario' => '30657986',
            ],
            [
                'sede' => 'DORAL',
                'tipo_movimiento' => 'SAL',
                'numero_documento' => 'A-3',
                'fecha' => '2026-08-14',
                'codigo_producto' => 'ABC',
                'nombre_producto' => 'Producto test',
                'cantidad' => 1,
                'costo_unitario' => 10,
                'motivo' => 'Merma',
                'usuario' => '30657986',
            ],
            [
                'sede' => 'CENTRO',
                'tipo_movimiento' => 'AJU',
                'numero_documento' => 'A-4',
                'fecha' => '2026-08-14',
                'codigo_producto' => 'ABC',
                'nombre_producto' => 'Producto test',
                'cantidad' => 1,
                'costo_unitario' => 10,
                'motivo' => 'Conteo',
                'usuario' => 'V3065986',
            ],
        ]);
        DB::table('ventas_detalle')->insert([
            [
                'sede' => 'DORAL',
                'tipo_documento' => 'DEV',
                'numero_documento' => 'D-9',
                'item_numero' => 1,
                'fecha' => '2026-08-11',
                'producto_id' => $productoId,
                'codigo_producto' => 'ABC',
                'nombre_producto' => 'Producto test',
                'cantidad' => 1,
                'precio_venta' => 25,
                'precio_neto' => 25,
                'vendedor' => 'Ana',
                'motivo_devolucion' => 'Defecto de fábrica',
                'anulado' => false,
            ],
            [
                'sede' => 'DORAL',
                'tipo_documento' => 'DEV',
                'numero_documento' => 'D-10',
                'item_numero' => 1,
                'fecha' => '2026-08-12',
                'producto_id' => $productoId,
                'codigo_producto' => 'ABC',
                'nombre_producto' => 'Producto test',
                'cantidad' => 1,
                'precio_venta' => 25,
                'precio_neto' => 25,
                'vendedor' => 'Ana',
                'motivo_devolucion' => 'Defecto de fábrica',
                'anulado' => false,
            ],
            [
                'sede' => 'DORAL',
                'tipo_documento' => 'DEV',
                'numero_documento' => 'D-11',
                'item_numero' => 1,
                'fecha' => '2026-08-13',
                'producto_id' => $productoId,
                'codigo_producto' => 'ABC',
                'nombre_producto' => 'Producto test',
                'cantidad' => 1,
                'precio_venta' => 10,
                'precio_neto' => 10,
                'vendedor' => 'Ana',
                'motivo_devolucion' => 'Cambio de talla',
                'anulado' => false,
            ],
        ]);

        $gerente = User::create([
            'name' => 'Gerente Modulos',
            'email' => 'gerente-modulos@test.local',
            'password' => 'password123',
            'role' => User::ROLE_GERENTE,
        ]);

        $this->actingAs($gerente)
            ->get(route('gerencial.devoluciones'))
            ->assertOk()
            ->assertSee('Devoluciones en ventas')
            ->assertSee('Producto test')
            ->assertSee('Defecto de fábrica')
            ->assertSee('Ver devoluciones');

        $this->actingAs($gerente)
            ->get(route('gerencial.devoluciones', ['ver_detalle' => 1]))
            ->assertOk()
            ->assertSee('D-9')
            ->assertSee('Detalle de devoluciones');

        $this->actingAs($gerente)
            ->get(route('gerencial.valorizados'))
            ->assertOk()
            ->assertSee('Valorizados de inventarios')
            ->assertSee('HOGAR')
            ->assertSee('Clasificación de inventario');

        $this->actingAs($gerente)
            ->get(route('gerencial.ajustes'))
            ->assertOk()
            ->assertSee('Consolidados de ajustes')
            ->assertSee('AJU')
            ->assertSee('Conteo')
            ->assertSee('Entradas vs salidas')
            ->assertSee('RICARDO GIMENEZ')
            ->assertDontSee('V3065986');

        $this->actingAs($gerente)
            ->get(route('gerencial.rentabilidad'))
            ->assertOk()
            ->assertSee('Rentabilidad')
            ->assertSee('Utilidad bruta');
    }

    public function test_supervisor_no_entra_a_valorizados_ni_ajustes(): void
    {
        $supervisor = User::create([
            'name' => 'Supervisor 2',
            'email' => 'sup-modulos@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($supervisor)->get(route('gerencial.valorizados'))->assertRedirect();
        $this->actingAs($supervisor)->get(route('gerencial.ajustes'))->assertRedirect();
        $this->actingAs($supervisor)->get(route('gerencial.rentabilidad'))->assertRedirect();
    }

    public function test_analytics_calcula_margen_y_porcentaje_de_devolucion(): void
    {
        DB::table('ventas_documentos')->insert([
            ['sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => 'F-1', 'fecha' => '2026-08-10', 'estado' => 'registrado', 'total_neto_usd' => 100, 'total_neto_bs' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['sede' => 'DORAL', 'tipo_documento' => 'DEV', 'numero_documento' => 'D-1', 'fecha' => '2026-08-11', 'estado' => 'registrado', 'total_neto_usd' => 10, 'total_neto_bs' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('ventas_detalle')->insert([
            [
                'sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => 'F-1', 'item_numero' => 1,
                'fecha' => '2026-08-10', 'codigo_producto' => 'X1', 'nombre_producto' => 'Aro',
                'cantidad' => 2, 'precio_venta' => 50, 'precio_neto' => 50, 'costo_unitario' => 20,
                'vendedor' => 'Ana', 'motivo_devolucion' => null, 'anulado' => false,
            ],
            [
                'sede' => 'DORAL', 'tipo_documento' => 'DEV', 'numero_documento' => 'D-1', 'item_numero' => 1,
                'fecha' => '2026-08-11', 'codigo_producto' => 'X1', 'nombre_producto' => 'Aro',
                'cantidad' => 1, 'precio_venta' => 10, 'precio_neto' => 10, 'costo_unitario' => 4,
                'vendedor' => 'Ana', 'motivo_devolucion' => 'Cliente no conforme', 'anulado' => false,
            ],
        ]);

        $base = app(GerencialDashboardService::class);
        $analytics = app(\App\Services\GerencialAnalyticsService::class);
        $periodo = $base->resolverPeriodo('mes', null, null);

        $dev = $analytics->devoluciones($periodo, 'DORAL', null, null, false);
        $this->assertSame(1, $dev['kpis']['documentos']);
        $this->assertEquals(10.0, $dev['kpis']['usd']);
        $this->assertEquals(10.0, $dev['kpis']['pct_ventas']);
        $this->assertSame('Cliente no conforme', $dev['porMotivo']->first()->motivo);

        $rent = $analytics->rentabilidad($periodo, 'DORAL', null, null, null);
        $this->assertEquals(90.0, $rent['kpis']['ventas']);
        $this->assertEquals(36.0, $rent['kpis']['costo']);
        $this->assertEquals(54.0, $rent['kpis']['utilidad']);
        $this->assertEquals(60.0, $rent['kpis']['margen_pct']);
    }

    public function test_motivos_de_profit_traducen_codigos_y_placeholders(): void
    {
        $this->assertSame('DIFERENCIAS POR VALIDAR', \App\Support\ProfitMotivos::ajuste('19', 'AJU'));
        $this->assertSame('ERROR EN VENTA', \App\Support\ProfitMotivos::ajuste('09', 'AJU'));
        $this->assertSame('TRASLADO ALMACENES', \App\Support\ProfitMotivos::ajuste('001', 'TRA'));
        $this->assertSame('PRODUCTO DEFECTUOSO', \App\Support\ProfitMotivos::devolucion('02'));
        $this->assertSame('CAMBIO DE PRODUCTO', \App\Support\ProfitMotivos::devolucion('CAMBIO DE PRODUCTO'));
        $this->assertSame('NO ESPECIFICADO', \App\Support\ProfitMotivos::devolucion('SELECCIONAR...'));
        $this->assertSame('GARANTÍA', \App\Support\ProfitMotivos::devolucion('GARANTÍA'));
        $this->assertSame('Sin motivo', \App\Support\ProfitMotivos::ajuste('Seleccione un motivo...'));
        $this->assertSame('Sin motivo', \App\Support\ProfitMotivos::devolucion(''));
        $this->assertSame('Conteo', \App\Support\ProfitMotivos::ajuste('Conteo', 'AJU'));
    }
}
