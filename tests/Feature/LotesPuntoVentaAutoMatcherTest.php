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
        $this->lote('NUNES', '2026-08-01', '000683', 5000);
        $this->lote('NUNES', '2026-08-05', '000683', 5000);
        $linea = $this->linea('NUNES', '2026-08-06', 'TMD 0037638504 L683 20260801 683', 4925);

        $this->assertSame(0, $this->servicio()->conciliar('2026-08-01', '2026-08-31'));
        $this->assertSame('pendiente', $linea->fresh()->estado);
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
