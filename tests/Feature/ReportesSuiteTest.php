<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ReportesSuiteTest extends TestCase
{
    use CreatesNominaSchema;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        if (! Schema::hasTable('ventas_documentos')) {
            Schema::create('ventas_documentos', function (Blueprint $t) {
                $t->id();
                $t->string('sede');
                $t->string('tipo_documento');
                $t->string('numero_documento');
                $t->date('fecha');
                $t->timestamps();
            });
        }
        foreach (['estado', 'estacion', 'cliente', 'vendedor', 'factura_origen', 'cajera'] as $c) {
            if (! Schema::hasColumn('ventas_documentos', $c)) {
                Schema::table('ventas_documentos', fn (Blueprint $t) => $t->string($c)->nullable());
            }
        }
        foreach (['total_neto_bs', 'total_neto_usd', 'factor_cambio', 'total_descuento_bs', 'total_descuento_usd', 'total_impuesto_bs', 'total_impuesto_usd'] as $c) {
            if (! Schema::hasColumn('ventas_documentos', $c)) {
                Schema::table('ventas_documentos', fn (Blueprint $t) => $t->decimal($c, 18, 4)->default(0));
            }
        }
        if (! Schema::hasTable('ventas_detalle')) {
            Schema::create('ventas_detalle', function (Blueprint $t) {
                $t->id();
                $t->string('sede');
                $t->string('tipo_documento');
                $t->string('numero_documento');
                $t->unsignedInteger('item_numero')->default(0);
                $t->string('codigo_producto')->nullable();
                $t->string('nombre_producto')->nullable();
                $t->decimal('cantidad', 18, 4)->default(0);
                $t->decimal('precio_neto', 18, 4)->default(0);
            });
        }
        (require database_path('migrations/2026_11_05_090000_create_cuadres_caja_tables.php'))->up();
        (require database_path('migrations/2026_11_05_130000_cuadres_caja_por_dia_de_caja.php'))->up();
        (require database_path('migrations/2026_11_07_120000_reportes_suite_ventas_pagos.php'))->up();

        $this->gerente = User::create(['name' => 'Gerente', 'email' => 'ger-'.uniqid().'@test.local', 'password' => 'password123', 'role' => User::ROLE_GERENTE]);
    }

    public function test_descuentos_muestra_motivo_observacion_y_el_metodo_de_pago(): void
    {
        $this->factura('154538', ['cliente' => 'ESTEFANY MEDINA', 'identificacion' => 'V27961153', 'total_neto_usd' => 1650.47, 'total_descuento_usd' => 614.83,
            'motivo_descuento' => 'AUTORIZADO COMPRAS', 'observacion' => 'REGALIA IPHONE 18 PRO MAX']);
        $this->factura('154539', ['cliente' => 'SIN DESCUENTO', 'total_neto_usd' => 50]);
        $this->pago(1, '154538', 'TRFBS', 'TRANSFERENCIA BS', 1650.47);

        $this->actingAs($this->gerente)
            ->get(route('reportes_suite', ['reporte' => 'descuentos', 'sede' => 'DORAL', 'desde' => '2026-11-02', 'hasta' => '2026-11-02']))
            ->assertOk()
            ->assertSee('ESTEFANY MEDINA')
            ->assertSee('AUTORIZADO COMPRAS')
            ->assertSee('REGALIA IPHONE 18 PRO MAX')
            ->assertSee('2.265,30')
            ->assertSee('614,83')
            ->assertSee('TRANSFERENCIA BS')
            ->assertDontSee('SIN DESCUENTO');
    }

    public function test_credito_calcula_lo_abonado_y_muestra_quien_lo_autorizo(): void
    {
        DB::table('cuadres_caja')->insert(['sede' => 'DORAL', 'fecha_cierre' => '2026-09-01', 'codigo_caja' => 'CJA01', 'usuario' => '28369458',
            'usuario_nombre' => 'ALEJANDRA SALAS', 'factor_cambio' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->factura('144075', ['fecha' => '2026-09-01', 'cliente' => 'ORIANA NARANJO', 'total_neto_usd' => 1787.52, 'credito' => true,
            'total_credito_usd' => 1787.52, 'saldo_credito_usd' => 340, 'autorizado_credito' => '28369458']);

        $this->actingAs($this->gerente)
            ->get(route('reportes_suite', ['reporte' => 'creditos', 'sede' => 'DORAL', 'desde' => '2026-09-01', 'hasta' => '2026-09-30']))
            ->assertOk()
            ->assertSee('ORIANA NARANJO')
            ->assertSee('1.447,52')
            ->assertSee('1.787,52')
            ->assertSee('ALEJANDRA SALAS');
    }

    public function test_facturas_pagos_y_cuadre_responden_y_el_pdf_se_genera(): void
    {
        $this->factura('154526', ['cliente' => 'CARLOS COLINA', 'identificacion' => 'V32365100', 'numero_fiscal' => '8236', 'total_neto_bs' => 4447.90, 'total_neto_usd' => 5]);
        $this->pago(2, '154526', 'TDD', 'TARJ. DÉBITO', 5, 'BANESCO P.V. JRZ');
        DB::table('cuadres_caja')->insert(['sede' => 'DORAL', 'fecha_cierre' => '2026-11-02', 'codigo_caja' => 'CJA09', 'usuario' => '28046742',
            'usuario_nombre' => 'JAIR AVILA', 'factor_cambio' => 889.58, 'observacion_suite' => 'FALTANTE -2,44$ EN EFECTIVO',
            'facturas_suite' => 7, 'total_venta_usd' => 380.86, 'diferencia_suite_usd' => -2.44, 'created_at' => now(), 'updated_at' => now()]);
        $filtros = ['sede' => 'DORAL', 'desde' => '2026-11-02', 'hasta' => '2026-11-02'];

        $this->actingAs($this->gerente)->get(route('reportes_suite', ['reporte' => 'facturas'] + $filtros))
            ->assertOk()->assertSee('CARLOS COLINA')->assertSee('V32365100')->assertSee('8236')->assertSee('4.447,90');
        $this->get(route('reportes_suite', ['reporte' => 'pagos'] + $filtros))
            ->assertOk()->assertSee('TARJ. DÉBITO BANESCO P.V. JRZ')->assertSee('V32365100');
        $this->get(route('reportes_suite', ['reporte' => 'cuadre'] + $filtros))
            ->assertOk()->assertSee('JAIR AVILA')->assertSee('FALTANTE -2,44$ EN EFECTIVO')->assertSee('380,86')->assertSee('-2,44');
        $this->get(route('reportes_suite', ['reporte' => 'cuadre', 'mostrar_total' => '0'] + $filtros))
            ->assertOk()->assertDontSee('380,86');

        $this->getJson(route('reportes_suite.factura', ['DORAL', '154526']))->assertOk()->assertJsonPath('renglones', []);
        $pdf = $this->get(route('reportes_suite', ['reporte' => 'facturas', 'formato' => 'pdf'] + $filtros));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_quien_tiene_sede_fija_solo_ve_su_sede(): void
    {
        $this->factura('1', ['cliente' => 'CLIENTE DORAL']);
        $this->factura('2', ['sede' => 'CENTRO', 'cliente' => 'CLIENTE CENTRO']);
        $supervisor = User::create(['name' => 'Sup', 'email' => 'sup-'.uniqid().'@test.local', 'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR, 'sede' => 'DORAL']);
        \App\Models\UserPermission::create(['user_id' => $supervisor->id, 'permission' => 'tesoreria']);

        $this->actingAs($supervisor->fresh())
            ->get(route('reportes_suite', ['reporte' => 'facturas', 'sede' => 'CENTRO', 'desde' => '2026-11-02', 'hasta' => '2026-11-02']))
            ->assertOk()
            ->assertSee('CLIENTE DORAL')
            ->assertDontSee('CLIENTE CENTRO')
            ->assertSee('Reportes de Suite');
    }

    public function test_sin_permiso_de_tesoreria_no_entra(): void
    {
        $vendedor = User::create(['name' => 'Ven', 'email' => 'ven-'.uniqid().'@test.local', 'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR, 'sede' => 'DORAL']);

        $this->actingAs($vendedor)->get(route('reportes_suite', 'facturas'))->assertRedirect();
    }

    private function factura(string $numero, array $datos = []): void
    {
        DB::table('ventas_documentos')->insert(array_merge([
            'sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => $numero, 'fecha' => '2026-11-02',
            'estado' => 'registrado', 'factor_cambio' => 889.58, 'total_neto_bs' => 0, 'total_neto_usd' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ], $datos));
    }

    private function pago(int $id, string $documento, string $metodo, string $forma, float $usd, ?string $cuenta = null): void
    {
        DB::table('ventas_pagos')->insert([
            'sede' => 'DORAL', 'id_movimiento' => $id, 'fecha' => '2026-11-02 10:00:00', 'origen' => 'FACVEN',
            'numero_documento' => $documento, 'codigo_caja' => 'CJA01', 'usuario' => '25010760', 'tipo_movimiento' => 'INGRESO',
            'codigo_metodo' => $metodo, 'forma_pago' => $forma, 'cuenta' => $cuenta, 'monto_bs' => $usd * 889.58, 'monto_usd' => $usd,
            'tasa' => 889.58, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
