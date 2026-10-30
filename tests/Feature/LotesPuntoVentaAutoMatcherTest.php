<?php

namespace Tests\Feature;

use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use App\Models\User;
use App\Services\Conciliacion\LotesPuntoVentaAutoMatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class LotesPuntoVentaAutoMatcherTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        if (! Schema::hasTable('tesoreria_ingresos')) {
            Schema::create('tesoreria_ingresos', function (Blueprint $table) {
                $table->id();
                $table->string('tipo', 32);
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->date('fecha');
                $table->decimal('monto', 15, 2);
                $table->string('lote_referencia')->nullable();
                $table->boolean('es_conciliado')->default(false);
                $table->text('descripcion')->nullable();
                $table->string('comprobante_path')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('conciliacion_lineas')) {
            Schema::create('conciliacion_lineas', function (Blueprint $table) {
                $table->id();
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->date('fecha')->nullable();
                $table->text('descripcion')->nullable();
                $table->string('referencia')->nullable();
                $table->decimal('monto', 15, 2)->default(0);
                $table->string('estado', 32)->default('pendiente');
                $table->string('tipo', 32)->nullable();
                $table->string('session_id')->nullable();
                $table->unsignedBigInteger('flujo_caja_id')->nullable();
                $table->unsignedBigInteger('tesoreria_ingreso_id')->nullable();
                $table->unsignedBigInteger('compra_divisa_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_cruza_el_lote_con_el_numero_en_la_descripcion_y_la_comision_descontada(): void
    {
        $lote = $this->lote('NUNES', '2026-08-01', '000683', 11366.63);
        $linea = $this->linea('NUNES', '2026-08-03', 'TMD 0037638504 L683 20260801 683', 11196.14);

        $this->assertSame(1, $this->servicio()->conciliar('2026-08-01', '2026-08-31'));

        $this->assertTrue((bool) $lote->fresh()->es_conciliado);
        $this->assertSame('conciliado', $linea->fresh()->estado);
        $this->assertSame((int) $lote->id, (int) $linea->fresh()->tesoreria_ingreso_id);
    }

    public function test_debito_y_credito_del_mismo_lote_se_emparejan_por_monto(): void
    {
        $debito = $this->lote('LNACEH', '2026-08-01', '000956', 5391.29);
        $credito = $this->lote('LNACEH', '2026-08-01', '000956', 42611.40);
        $tmd = $this->linea('LNACEH', '2026-08-03', 'TMD 0037638416 L956 20260801 956', 5350.86);
        $tdb = $this->linea('LNACEH', '2026-08-03', 'TDB CAPIT 0037638416 L000956 30687', 42291.81);

        $this->assertSame(1, $this->servicio()->conciliar('2026-08-01', '2026-08-31'));

        $this->assertSame((int) $debito->id, (int) $tmd->fresh()->tesoreria_ingreso_id);
        $this->assertSame((int) $credito->id, (int) $tdb->fresh()->tesoreria_ingreso_id);
        $this->assertTrue((bool) $debito->fresh()->es_conciliado);
        $this->assertTrue((bool) $credito->fresh()->es_conciliado);
    }

    public function test_no_cruza_si_el_monto_otra_cuenta_o_la_fecha_no_cuadran(): void
    {
        $this->lote('NUNES', '2026-08-01', '000700', 10000);
        $muyBajo = $this->linea('NUNES', '2026-08-02', 'TDB CAPIT 0037638504 L000700 30687', 9500);

        $this->lote('NUNES', '2026-08-01', '000701', 10000);
        $otraCuenta = $this->linea('DORAL', '2026-08-02', 'TDB CAPIT 0052320997 L000701 30687', 9850);

        $this->lote('NUNES', '2026-08-01', '000702', 10000);
        $tarde = $this->linea('NUNES', '2026-08-20', 'TDB CAPIT 0037638504 L000702 30687', 9850);

        $this->lote('NUNES', '2026-08-01', '000703', 10000);
        $mayor = $this->linea('NUNES', '2026-08-02', 'TDB CAPIT 0037638504 L000703 30687', 10200);

        $this->assertSame(0, $this->servicio()->conciliar('2026-08-01', '2026-08-31'));

        foreach ([$muyBajo, $otraCuenta, $tarde, $mayor] as $linea) {
            $this->assertSame('pendiente', $linea->fresh()->estado);
            $this->assertNull($linea->fresh()->tesoreria_ingreso_id);
        }
        $this->assertSame(0, TesoreriaIngreso::where('es_conciliado', true)->count());
    }

    public function test_no_cruza_si_hay_dos_lotes_posibles(): void
    {
        $del01 = $this->lote('NUNES', '2026-08-01', '000683', 5000);
        $this->lote('NUNES', '2026-08-05', '000683', 5000);
        // Sin fecha de cierre en la descripción no se sabe cuál es.
        $linea = $this->linea('NUNES', '2026-08-06', 'TDB CAPIT 0037638504 L000683 30687', 4925);

        $this->assertSame(0, $this->servicio()->conciliar('2026-08-01', '2026-08-31'));
        $this->assertSame('pendiente', $linea->fresh()->estado);

        // Con la fecha del cierre (20260801) sí: es el del 01/08.
        $linea->delete();
        $conFecha = $this->linea('NUNES', '2026-08-06', 'TMD 0037638504 L683 20260801 683', 4925);
        $this->assertSame(1, $this->servicio()->conciliar('2026-08-01', '2026-08-31'));
        $this->assertSame((int) $del01->id, (int) $conFecha->fresh()->tesoreria_ingreso_id);
    }

    public function test_la_pantalla_de_lotes_concilia_al_abrirla(): void
    {
        if (! Schema::hasTable('cuentas_bancarias')) {
            Schema::create('cuentas_bancarias', function (Blueprint $table) {
                $table->id();
                $table->string('banco');
                $table->string('titular');
                $table->string('numero_cuenta', 32)->nullable();
                $table->string('categoria_reporte')->nullable();
                $table->boolean('mostrar_en_principal')->default(true);
                $table->integer('orden')->default(0);
                $table->timestamps();
            });
        }
        $user = User::create([
            'name' => 'Contabilidad',
            'email' => 'contab-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_CONTABILIDAD,
        ]);
        $lote = $this->lote('NUNES', '2026-08-05', '000686', 5391.85);
        $this->linea('NUNES', '2026-08-06', 'TDB CAPIT 0037638504 L000686 30687', 5310.97);

        $this->actingAs($user)
            ->get(route('finanzas.conciliaciones.lotes', ['fecha_desde' => '2026-08-01', 'fecha_hasta' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Se conciliaron automáticamente 1 lote(s) Banesco');

        $this->assertTrue((bool) $lote->fresh()->es_conciliado);
    }

    public function test_lote_del_cierre_de_mes_se_pasa_al_mes_siguiente_y_se_puede_devolver(): void
    {
        if (! Schema::hasTable('cuentas_bancarias')) {
            Schema::create('cuentas_bancarias', function (Blueprint $table) {
                $table->id();
                $table->string('banco');
                $table->string('titular');
                $table->string('categoria_reporte')->nullable();
                $table->boolean('mostrar_en_principal')->default(true);
                $table->integer('orden')->default(0);
                $table->timestamps();
            });
        }
        (require database_path('migrations/2026_10_29_150000_add_conciliar_en_to_tesoreria_ingresos.php'))->up();
        $user = User::create(['name' => 'Contabilidad', 'email' => 'contab-'.uniqid().'@test.local', 'password' => 'password123', 'role' => User::ROLE_CONTABILIDAD]);
        $cierre = $this->lote('DORAL', '2026-08-31', '777', 4500.00, 'TESORO');
        $otro = $this->lote('DORAL', '2026-08-30', '776', 1200.00, 'TESORO');
        $agosto = ['fecha_desde' => '2026-08-01', 'fecha_hasta' => '2026-08-31'];
        $septiembre = ['fecha_desde' => '2026-09-01', 'fecha_hasta' => '2026-09-30'];

        $this->actingAs($user)->post(route('finanzas.conciliaciones.lotes.diferir'), ['diferir_id' => $cierre->id])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'septiembre 2026'));
        $this->assertSame('2026-09-01', substr((string) $cierre->fresh()->conciliar_en, 0, 10));

        // Agosto: ya no está entre los pendientes, sale en «Pasan al mes siguiente».
        $vista = $this->actingAs($user)->get(route('finanzas.conciliaciones.lotes', $agosto))->assertOk();
        $vista->assertSee('Pasan al mes siguiente (1)');
        $tarjeta = collect($vista->viewData('tarjetas'))->first();
        $this->assertSame([$otro->id], $tarjeta['pendientes']->pluck('id')->all());
        $this->assertSame(1, $vista->viewData('totalPendientes'));

        // Septiembre: aparece para conciliarlo con la LIQ del día 1.
        $sep = $this->actingAs($user)->get(route('finanzas.conciliaciones.lotes', $septiembre))->assertOk()->assertSee('Del mes anterior');
        $this->assertSame([$cierre->id], collect($sep->viewData('tarjetas'))->first()['pendientes']->pluck('id')->all());

        $this->actingAs($user)->post(route('finanzas.conciliaciones.lotes.diferir'), ['volver_id' => $cierre->id])->assertSessionHas('success');
        $this->assertNull($cierre->fresh()->conciliar_en);
        $this->assertSame(2, $this->actingAs($user)->get(route('finanzas.conciliaciones.lotes', $agosto))->viewData('totalPendientes'));
    }

    public function test_bnc_cruza_el_lote_abonado_por_tipo_de_tarjeta(): void
    {
        $lote = $this->lote('DORAL', '2026-08-03', '69', 126391.49, 'BNC');
        $debito = $this->linea('DORAL', '2026-08-04', 'POS: 860982552 INVERSIONES DORAL PARAGUA FECHA:04/08/2026 HORA:03:34:00 LOTE:000069 TERM:17213012 TIPO:TRJ. DEBITO', 123426.29, 'BNC');
        $electron = $this->linea('DORAL', '2026-08-04', 'POS: 860982552 INVERSIONES DORAL PARAGUA FECHA:04/08/2026 HORA:03:34:00 LOTE:000069 TERM:17213012 TIPO:TRJ. DEB.ELC', 2965.20, 'BNC');

        $this->assertSame(1, $this->servicio()->conciliar('2026-08-01', '2026-08-31', 'BNC', 'DORAL'));

        $this->assertTrue((bool) $lote->fresh()->es_conciliado);
        $this->assertSame((int) $lote->id, (int) $debito->fresh()->tesoreria_ingreso_id);
        $this->assertSame((int) $lote->id, (int) $electron->fresh()->tesoreria_ingreso_id);
    }

    public function test_bnc_cada_punto_tiene_su_numeracion_de_lotes(): void
    {
        // Lote 439 del punto 860963077 y lote 439 del punto 860982552 son lotes distintos.
        $loteA = $this->lote('DORAL', '2026-08-01', '000439', 29903.30, 'BNC');
        $a1 = $this->linea('DORAL', '2026-08-01', 'POS: 860963077 INVERSIONES DORAL FECHA:01/08/2026 LOTE:000439 TERM:17183717 TIPO:TRJ. DEB.ELC', 16799.18, 'BNC');
        $a2 = $this->linea('DORAL', '2026-08-01', 'POS: 860963077 INVERSIONES DORAL FECHA:01/08/2026 LOTE:000439 TERM:17183717 TIPO:TRJ. DEBITO', 13104.12, 'BNC');
        $otroPunto = $this->linea('DORAL', '2026-08-02', 'POS: 860982552 INVERSIONES DORAL FECHA:02/08/2026 LOTE:000439 TERM:17213012 TIPO:TRJ. DEBITO', 5000.00, 'BNC');

        $this->assertSame(1, $this->servicio()->conciliar('2026-08-01', '2026-08-31'));

        $this->assertSame((int) $loteA->id, (int) $a1->fresh()->tesoreria_ingreso_id);
        $this->assertSame((int) $loteA->id, (int) $a2->fresh()->tesoreria_ingreso_id);
        $this->assertSame('pendiente', $otroPunto->fresh()->estado);
    }

    public function test_banesco_td_y_tt_cruzan_por_el_lote_de_su_terminal(): void
    {
        $matcher = app(\App\Services\BankReconciliationMatcher::class);
        $this->assertSame(
            ['afiliado' => '0001298250', 'lote' => '114', 'fecha' => '2026-10-01', 'terminal' => '0052320997'],
            $matcher->liquidacionBanescoTd('TD 0001298250 000114 011026 0052320997'),
        );
        $this->assertTrue($matcher->esAbonoLotePuntoVenta('TT 0001298250 000084 300826 0052320997'));
        $this->assertFalse($matcher->esAbonoLotePuntoVenta('TD 0001298250 000114 321326 0052320997'));

        // Débito y crédito del lote 114 de la terminal 0052320997; el lote 114 de otra terminal es otro cierre.
        $debito = $this->lote('DORAL', '2026-10-01', '000114', 50000.00);
        $credito = $this->lote('DORAL', '2026-10-01', '000114', 76873.95);
        $td = $this->linea('DORAL', '2026-10-02', 'TD 0001298250 000114 011026 0052320997', 49250.00);
        $tt = $this->linea('DORAL', '2026-10-02', 'TT 0001298250 000114 011026 0052320997', 75720.84);
        $otraTerminal = $this->linea('DORAL', '2026-10-02', 'TD 0001298250 000114 011026 0037623987', 1000.00);

        $this->assertSame(1, $this->servicio()->conciliar('2026-10-01', '2026-10-31'));

        $this->assertSame((int) $debito->id, (int) $td->fresh()->tesoreria_ingreso_id);
        $this->assertSame((int) $credito->id, (int) $tt->fresh()->tesoreria_ingreso_id);
        $this->assertTrue((bool) $debito->fresh()->es_conciliado);
        $this->assertTrue((bool) $credito->fresh()->es_conciliado);
        $this->assertSame('pendiente', $otraTerminal->fresh()->estado);
    }

    public function test_banesco_lote_en_varios_formatos_se_junta_por_terminal_y_toma_el_del_cierre(): void
    {
        // Caso DORAL del 02/10: el lote 114 de la terminal 0052320997 llega en cuatro abonos y hay otro «114» el día antes.
        $otro114 = $this->lote('DORAL', '2026-09-30', '000114', 79097.77);
        $lote = $this->lote('DORAL', '2026-10-01', '000114', 126873.95);
        $l148 = $this->lote('DORAL', '2026-10-01', '000148', 500.00);
        $lineas = [
            $this->linea('DORAL', '2026-10-02', 'TD 0001298250 000114 011026 0052320997', 33788.20),
            $this->linea('DORAL', '2026-10-02', 'TMD 0052320997 L114 20261001 114', 2528.93),
            $this->linea('DORAL', '2026-10-02', 'TDC 0052320997 L000114 20261001 000114', 6973.06),
            $this->linea('DORAL', '2026-10-02', 'TDB CAPIT 0052320997 L000114 30687', 80442.45),
        ];
        // Otra terminal con su propio lote 148: no se mezcla con el 114.
        $otraTerminal = $this->linea('DORAL', '2026-10-02', 'TMD 0052020582 L148 20261001 148', 490.00);

        $this->assertSame(2, $this->servicio()->conciliar('2026-10-01', '2026-10-31'));

        foreach ($lineas as $l) {
            $this->assertSame((int) $lote->id, (int) $l->fresh()->tesoreria_ingreso_id);
        }
        $this->assertTrue((bool) $lote->fresh()->es_conciliado);
        $this->assertFalse((bool) $otro114->fresh()->es_conciliado);
        $this->assertSame((int) $l148->id, (int) $otraTerminal->fresh()->tesoreria_ingreso_id);
    }

    public function test_no_cruza_si_el_extracto_trae_la_misma_liquidacion_dos_veces(): void
    {
        $this->lote('LNACEH', '2026-08-08', '499', 118405.95, 'BNC');
        $this->lote('LNACEH', '2026-08-09', '0499', 118405.95, 'BNC');
        $desc = 'POS: 860963149 LNACEH SPORT CA FECHA:09/08/2026 HORA:03:38:46 LOTE:000499 TERM:17183807 TIPO:TRJ. DEBITO';
        $l1 = $this->linea('LNACEH', '2026-08-09', $desc, 118405.95, 'BNC');
        $l2 = $this->linea('LNACEH', '2026-08-09', $desc, 118405.95, 'BNC');

        $this->assertSame(0, $this->servicio()->conciliar('2026-08-01', '2026-08-31'));
        $this->assertSame('pendiente', $l1->fresh()->estado);
        $this->assertSame('pendiente', $l2->fresh()->estado);
    }

    private function servicio(): LotesPuntoVentaAutoMatcher
    {
        return app(LotesPuntoVentaAutoMatcher::class);
    }

    private function lote(string $titular, string $fecha, string $numero, float $monto, string $banco = 'BANESCO'): TesoreriaIngreso
    {
        return TesoreriaIngreso::create([
            'tipo' => 'punto_venta',
            'banco' => $banco,
            'titular' => $titular,
            'fecha' => $fecha,
            'monto' => $monto,
            'lote_referencia' => $numero,
            'es_conciliado' => false,
        ]);
    }

    private function linea(string $titular, string $fecha, string $descripcion, float $monto, string $banco = 'BANESCO'): ConciliacionLinea
    {
        return ConciliacionLinea::create([
            'banco' => $banco,
            'titular' => $titular,
            'fecha' => $fecha,
            'descripcion' => $descripcion,
            'referencia' => (string) ltrim((string) preg_replace('/.*L(?:OTE:)?\.?0*(\d+).*/', '$1', $descripcion), '0'),
            'monto' => $monto,
            'estado' => 'pendiente',
            'tipo' => 'abono',
        ]);
    }
}
