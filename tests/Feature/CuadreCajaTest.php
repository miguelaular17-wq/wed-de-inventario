<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CuadreCaja;
use App\Models\CuadreCajaLote;
use App\Models\CuadreCajaPago;
use App\Models\TesoreriaIngreso;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\CuadreCajaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class CuadreCajaTest extends TestCase
{
    use CreatesNominaSchema;

    private CuadreCaja $cierre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        (require database_path('migrations/2026_11_05_090000_create_cuadres_caja_tables.php'))->up();
        (require database_path('migrations/2026_11_05_130000_cuadres_caja_por_dia_de_caja.php'))->up();
        (require database_path('migrations/2026_11_06_100000_add_estado_tesoreria_to_cuadres_caja.php'))->up();
        (require database_path('migrations/2026_11_06_120000_create_cuadre_caja_lotes_table.php'))->up();
        (require database_path('migrations/2026_11_06_140000_add_banco_to_cuadre_caja_lotes.php'))->up();
        (require database_path('migrations/2026_11_07_100000_add_titular_to_cuadre_caja_lotes.php'))->up();
        if (! Schema::hasTable('tesoreria_ingresos')) {
            Schema::create('tesoreria_ingresos', function (Blueprint $t) {
                $t->id();
                $t->string('tipo');
                $t->string('banco')->nullable();
                $t->string('titular')->nullable();
                $t->date('fecha');
                $t->decimal('monto', 15, 2);
                $t->string('lote_referencia')->nullable();
                $t->text('descripcion')->nullable();
                $t->string('comprobante_path')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->boolean('es_conciliado')->default(false);
                $t->date('conciliar_en')->nullable();
                $t->timestamps();
            });
        }

        $this->cierre = CuadreCaja::create([
            'sede' => 'DORAL', 'id_cierre' => 49929, 'fecha_cierre' => '2026-10-30', 'codigo_caja' => 'CJA02',
            'descripcion_caja' => 'CAJA 02', 'usuario' => '28738920', 'usuario_nombre' => 'ARIAMNA REYES',
            'estado' => 2, 'factor_cambio' => 889.26,
        ]);
        $this->pagosDeSuite($this->cierre);

        // Otro cierre de la sede donde sí se declaró Binance: queda disponible para agregarlo.
        $otro = CuadreCaja::create([
            'sede' => 'DORAL', 'id_cierre' => 49928, 'fecha_cierre' => '2026-10-30', 'codigo_caja' => 'CJA01',
            'usuario' => '28369458', 'factor_cambio' => 889.26,
        ]);
        CuadreCajaPago::create([
            'cuadre_caja_id' => $otro->id, 'codigo_metodo' => 'TRF', 'forma_pago' => 'TRANSF. $', 'codigo_cuenta' => 'BINANCE',
            'cuenta' => 'BINANCE', 'en_dolares' => true, 'ingresos' => 20, 'ingresos_usd' => 20,
        ]);
    }

    public function test_la_diferencia_es_lo_contado_por_tesoreria_menos_lo_declarado_en_suite(): void
    {
        $tesoreria = $this->usuario('tesoreria');
        $this->actingAs($tesoreria)
            ->post(route('cuadre_caja.conciliar', $this->cierre), [
                'accion' => 'procesar',
                'montos' => ['02|' => '810', 'TDD|PUNTOS' => '', 'TRF|BINANCE' => '22.50'],
                'lotes' => ['TDD|PUNTOS' => $this->lotesCompletos()],
                'observacion' => 'Faltan 5 en efectivo',
            ])
            ->assertRedirect();

        $resumen = app(CuadreCajaService::class)->resumen($this->cierre->fresh()->load(['pagos', 'conteos']));

        $this->assertSame(1637.64, $resumen['ingresos_usd']);
        $this->assertSame(1655.14, $resumen['tesoreria_usd']);
        $this->assertSame(17.5, $resumen['diferencia_usd']);
        $lineas = collect($resumen['lineas'])->keyBy('clave');
        $this->assertSame(-5.0, $lineas['02|']['diferencia_usd']);
        $this->assertSame(0.0, $lineas['TDD|PUNTOS']['diferencia_usd']);
        $this->assertSame(22.5, $lineas['TRF|BINANCE']['diferencia_usd']);
        $this->assertSame('Faltan 5 en efectivo', $this->cierre->fresh()->observacion_tesoreria);
        $this->assertSame($tesoreria->id, $this->cierre->fresh()->conciliado_por);

        $this->actingAs($tesoreria)
            ->get(route('cuadre_caja.index', ['desde' => '2026-10-30', 'hasta' => '2026-10-30']))
            ->assertOk()
            ->assertSee('ARIAMNA REYES')
            ->assertSee('$17,50');

        $this->actingAs($tesoreria)
            ->get(route('cuadre_caja.show', $this->cierre))
            ->assertOk()
            ->assertSee('-$5,00', false)
            ->assertSee('Caja procesada')
            ->assertSee('Reabrir caja');
    }

    public function test_procesar_se_puede_reabrir_y_volver_a_corregir(): void
    {
        $tesoreria = $this->usuario('tesoreria');
        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'procesar', 'montos' => ['02|' => '810'], 'observacion' => 'Faltan 5',
        ]);
        $this->assertTrue($this->cierre->fresh()->procesado());

        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'procesar', 'montos' => ['02|' => '1'], 'observacion' => 'Otro',
        ])->assertSessionHas('error');
        $this->assertSame(810.0, (float) $this->cierre->fresh()->conteos()->value('monto'));

        $this->actingAs($tesoreria)->post(route('cuadre_caja.reabrir', $this->cierre))->assertRedirect();
        $reabierta = $this->cierre->fresh();
        $this->assertTrue($reabierta->pendiente());
        $this->assertSame($tesoreria->id, (int) $reabierta->reabierto_por);
        $this->assertSame('Faltan 5', $reabierta->observacion_tesoreria);

        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'procesar', 'montos' => ['02|' => '815'], 'observacion' => 'Ya repuso',
        ]);
        $this->assertTrue($this->cierre->fresh()->procesado());
        $this->assertSame(815.0, (float) $this->cierre->fresh()->conteos()->value('monto'));
    }

    public function test_cerrar_pide_motivo_y_observacion_y_no_se_reabre(): void
    {
        $tesoreria = $this->usuario('tesoreria');

        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'cerrar', 'montos' => ['02|' => '815'], 'observacion' => 'Todo bien',
        ])->assertSessionHasErrors('motivo');
        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'cerrar', 'montos' => ['02|' => '815'], 'motivo' => 'faltante',
        ])->assertSessionHasErrors('observacion');
        $this->assertTrue($this->cierre->fresh()->pendiente());

        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'cerrar', 'montos' => ['02|' => '815', 'TDD|PUNTOS' => ''],
            'lotes' => ['TDD|PUNTOS' => $this->lotesCompletos()],
            'motivo' => 'cuadrada', 'observacion' => 'Cuadró exacto',
        ])->assertSessionHasNoErrors();

        $cerrada = $this->cierre->fresh();
        $this->assertTrue($cerrada->cerrado());
        $this->assertSame('cuadrada', $cerrada->motivo_cierre);
        $this->assertSame('Cuadró exacto', $cerrada->observacion_tesoreria);

        $this->actingAs($tesoreria)->post(route('cuadre_caja.reabrir', $this->cierre))->assertSessionHas('error');
        $this->assertTrue($this->cierre->fresh()->cerrado());

        $this->actingAs($tesoreria)->get(route('cuadre_caja.show', $this->cierre))
            ->assertOk()
            ->assertSee('Caja cerrada')
            ->assertSee('Caja cuadrada, sin diferencia')
            ->assertDontSee('Reabrir caja')
            ->assertDontSee('name="montos[02|]"', false);

        $this->actingAs($tesoreria)
            ->get(route('cuadre_caja.index', ['desde' => '2026-10-30', 'hasta' => '2026-10-30', 'estado' => 'cerrado']))
            ->assertOk()
            ->assertSee('ARIAMNA REYES');
    }

    public function test_una_caja_procesada_se_puede_cerrar_sin_cambiar_los_montos(): void
    {
        $tesoreria = $this->usuario('tesoreria');
        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'procesar', 'montos' => ['02|' => '810'], 'observacion' => 'Faltan 5',
        ]);

        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'cerrar', 'motivo' => 'faltante', 'observacion' => 'Se descuenta por nómina',
        ])->assertSessionHasNoErrors();

        $cerrada = $this->cierre->fresh();
        $this->assertTrue($cerrada->cerrado());
        $this->assertSame(810.0, (float) $cerrada->conteos()->value('monto'));
    }

    public function test_una_forma_de_pago_sin_contar_cuenta_como_cero_al_conciliar(): void
    {
        $this->actingAs($this->usuario('tesoreria'))
            ->post(route('cuadre_caja.conciliar', $this->cierre), [
                'accion' => 'procesar', 'montos' => ['02|' => '815', 'TDD|PUNTOS' => ''], 'observacion' => 'Sin punto',
            ]);

        $resumen = app(CuadreCajaService::class)->resumen($this->cierre->fresh()->load(['pagos', 'conteos']));

        $this->assertSame(-822.64, $resumen['diferencia_usd']);
    }

    public function test_lo_contado_no_se_pierde_cuando_el_sincronizador_vuelve_a_subir_el_cierre(): void
    {
        $this->actingAs($this->usuario('tesoreria'))
            ->post(route('cuadre_caja.conciliar', $this->cierre), [
                'accion' => 'procesar', 'montos' => ['02|' => '810', 'TDD|PUNTOS' => ''],
                'lotes' => ['TDD|PUNTOS' => $this->lotesCompletos()], 'observacion' => 'Faltan 5',
            ]);

        CuadreCajaPago::query()->where('cuadre_caja_id', $this->cierre->id)->delete();
        $this->pagosDeSuite($this->cierre);

        $resumen = app(CuadreCajaService::class)->resumen($this->cierre->fresh()->load(['pagos', 'conteos']));
        $this->assertSame(-5.0, $resumen['diferencia_usd']);
    }

    public function test_sin_nombre_de_suite_muestra_el_de_la_ficha_del_empleado_por_cedula(): void
    {
        Cliente::create(['cedula' => '28369458', 'nombre' => 'MARIA PEREZ']);
        $sinNombre = CuadreCaja::create([
            'sede' => 'DORAL', 'fecha_cierre' => '2026-10-31', 'codigo_caja' => 'CJA03',
            'usuario' => 'V28369458', 'factor_cambio' => 889.26,
        ]);
        $tesoreria = $this->usuario('tesoreria');

        $this->actingAs($tesoreria)
            ->get(route('cuadre_caja.index', ['desde' => '2026-10-31', 'hasta' => '2026-10-31', 'cajera' => 'maria']))
            ->assertOk()
            ->assertSee('MARIA PEREZ')
            ->assertSee('V28369458');

        $this->actingAs($tesoreria)->get(route('cuadre_caja.show', $sinNombre))
            ->assertOk()
            ->assertSee('MARIA PEREZ')
            ->assertSee('sin cierre en Suite todavía');
        $this->assertNull($sinNombre->fresh()->usuario_nombre);
    }

    public function test_ver_no_deja_conciliar_y_sin_permiso_no_entra(): void
    {
        $sinPermiso = $this->usuario(User::ROLE_SUPERVISOR);
        $this->assertNotSame(200, $this->actingAs($sinPermiso)->get(route('cuadre_caja.index'))->status());

        $soloVer = $this->usuario(User::ROLE_SUPERVISOR);
        UserPermission::create(['user_id' => $soloVer->id, 'permission' => 'cuadre_caja']);
        $soloVer = $soloVer->fresh();

        $this->actingAs($soloVer)->get(route('cuadre_caja.show', $this->cierre))->assertOk()
            ->assertDontSee('Procesar caja')->assertDontSee('Cerrar caja');
        $this->actingAs($soloVer)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'procesar', 'montos' => ['02|' => '1'], 'observacion' => 'x',
        ]);
        $this->actingAs($soloVer)->post(route('cuadre_caja.reabrir', $this->cierre));
        $this->assertFalse($this->cierre->fresh()->conciliado());
    }

    public function test_los_lotes_del_punto_de_venta_suman_su_monto_y_quedan_en_tesoreria(): void
    {
        $tesoreria = $this->usuario('tesoreria');
        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'procesar', 'montos' => ['02|' => '815', 'TDD|PUNTOS' => '1'],
            'lotes' => ['TDD|PUNTOS' => [['lote' => '101', 'monto' => '500000'], ['lote' => '102', 'monto' => '200000']]],
            'observacion' => 'Falta un lote',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $resumen = app(CuadreCajaService::class)->resumen($this->cierre->fresh()->load(['pagos', 'conteos', 'lotes']));
        $pos = collect($resumen['lineas'])->keyBy('clave')['TDD|PUNTOS'];
        $this->assertSame(700000.0, (float) $pos['tesoreria']);
        $this->assertSame(['101', '102'], array_column($pos['lotes'], 'lote'));

        $ingresos = TesoreriaIngreso::query()->orderBy('lote_referencia')->get();
        $this->assertSame(['101', '102'], $ingresos->pluck('lote_referencia')->all());
        $this->assertSame(['BANESCO'], $ingresos->pluck('banco')->unique()->values()->all());
        $this->assertSame(['DORAL'], $ingresos->pluck('titular')->unique()->values()->all());
        $this->assertSame('punto_venta', $ingresos->first()->tipo);
        $this->assertSame('2026-10-30', substr((string) $ingresos->first()->fecha, 0, 10));

        $this->actingAs($tesoreria)->get(route('cuadre_caja.show', $this->cierre))
            ->assertOk()
            ->assertSee('Total puntos de venta')
            ->assertSee('Lote <b>101</b>', false);
    }

    public function test_reprocesar_actualiza_los_lotes_sin_duplicarlos_y_un_lote_conciliado_no_se_quita(): void
    {
        $tesoreria = $this->usuario('tesoreria');
        $procesar = fn (array $lotes) => $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'procesar', 'montos' => ['02|' => '815'], 'lotes' => ['TDD|PUNTOS' => $lotes], 'observacion' => 'ok',
        ]);

        $procesar([['lote' => '101', 'monto' => '500000'], ['lote' => '102', 'monto' => '200000']]);
        $this->actingAs($tesoreria)->post(route('cuadre_caja.reabrir', $this->cierre));
        $procesar([['lote' => '101', 'monto' => '500000'], ['lote' => '102', 'monto' => '231536.94']]);

        $this->assertSame(2, TesoreriaIngreso::query()->count());
        $this->assertSame(231536.94, (float) TesoreriaIngreso::query()->where('lote_referencia', '102')->value('monto'));

        TesoreriaIngreso::query()->where('lote_referencia', '101')->update(['es_conciliado' => true]);
        $this->actingAs($tesoreria)->post(route('cuadre_caja.reabrir', $this->cierre));
        $procesar([['lote' => '102', 'monto' => '231536.94']])->assertSessionHas('error');

        $this->assertTrue($this->cierre->fresh()->pendiente());
        $this->assertSame(2, CuadreCajaLote::query()->count());
        $this->assertSame(2, TesoreriaIngreso::query()->count());
    }

    public function test_un_lote_que_tesoreria_ya_cargo_a_mano_se_enlaza_en_vez_de_duplicarse(): void
    {
        $manual = TesoreriaIngreso::create([
            'tipo' => 'punto_venta', 'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-10-30',
            'monto' => 731536.94, 'lote_referencia' => '777',
        ]);

        $this->actingAs($this->usuario('tesoreria'))->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'procesar', 'montos' => ['02|' => '815'],
            'lotes' => ['TDD|PUNTOS' => [['lote' => '777', 'monto' => '731536.94']]], 'observacion' => 'ok',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, TesoreriaIngreso::query()->count());
        $this->assertSame($manual->id, (int) CuadreCajaLote::query()->value('tesoreria_ingreso_id'));
    }

    public function test_cada_lote_va_a_tesoreria_con_el_banco_elegido(): void
    {
        $tesoreria = $this->usuario('tesoreria');
        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'procesar', 'montos' => ['02|' => '815'],
            'lotes' => ['TDD|PUNTOS' => [
                ['banco' => 'BANESCO', 'lote' => '9', 'monto' => '500000'],
                ['banco' => 'BNC', 'titular' => 'LNACEH', 'lote' => '9', 'monto' => '231536.94'],
            ]],
            'observacion' => 'Dos puntos',
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(
            ['BANESCO' => 500000.0, 'BNC' => 231536.94],
            TesoreriaIngreso::query()->orderBy('banco')->get()->mapWithKeys(fn ($i) => [$i->banco => (float) $i->monto])->all()
        );
        $this->assertSame(
            ['BANESCO' => 'DORAL', 'BNC' => 'LNACEH'],
            TesoreriaIngreso::query()->orderBy('banco')->pluck('titular', 'banco')->all()
        );

        $this->actingAs($tesoreria)->get(route('cuadre_caja.show', $this->cierre))
            ->assertOk()
            ->assertSee('BNC · LNACEH · Lote <b>9</b>', false);

        $this->actingAs($tesoreria)->post(route('cuadre_caja.reabrir', $this->cierre));
        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'procesar', 'montos' => ['02|' => '815'],
            'lotes' => ['TDD|PUNTOS' => [['banco' => 'BOGUS', 'lote' => '1', 'monto' => '5']]], 'observacion' => 'x',
        ])->assertSessionHas('error');
    }

    public function test_nota_de_credito_no_suma_y_el_efectivo_devuelto_compensa_la_diferencia(): void
    {
        if (! Schema::hasColumn('ventas_documentos', 'usuario')) {
            Schema::table('ventas_documentos', fn (Blueprint $t) => $t->string('usuario')->nullable());
        }
        // Caja 04 de Jorgelis (10/11): dos devoluciones, una en efectivo (91.949,82) y otra con nota de crédito (6.717,55).
        $caja = CuadreCaja::create([
            'sede' => 'DORAL', 'fecha_cierre' => '2026-11-10', 'codigo_caja' => 'CJA04', 'descripcion_caja' => 'CAJA 04',
            'usuario' => '31234681', 'usuario_nombre' => 'JORGELIS VELAZQUEZ', 'factor_cambio' => 899.27,
        ]);
        foreach ([['02', 'EFECTIVO $', true, 535, 535], ['CASHEA', 'CASHEA', true, 1084.04, 1084.04], ['NDC', 'NOTA DE CRÉDITO', false, 6717.55, 7.47]] as [$m, $f, $usd, $bs, $u]) {
            CuadreCajaPago::create(['cuadre_caja_id' => $caja->id, 'codigo_metodo' => $m, 'forma_pago' => $f, 'en_dolares' => $usd, 'ingresos' => $bs, 'ingresos_usd' => $u]);
        }
        foreach ([['1699', 91949.82], ['1700', 6717.55]] as [$doc, $bs]) {
            \Illuminate\Support\Facades\DB::table('ventas_documentos')->insert([
                'sede' => 'DORAL', 'tipo_documento' => 'DEV', 'numero_documento' => $doc, 'fecha' => '2026-11-10', 'estado' => 'registrado',
                'total_neto_bs' => $bs, 'total_neto_usd' => 0, 'usuario' => '31234681', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $lineas = collect(app(CuadreCajaService::class)->lineas($caja->load(['pagos', 'conteos', 'lotes'])))->keyBy('clave');
        $this->assertSame('no_suma', $lineas['NDC|']['regla']);
        $this->assertSame('devolucion', $lineas['NDC1|']['regla']);
        $this->assertSame(91949.82, $lineas['NDC1|']['suite']);
        $this->assertSame(102.25, $lineas['NDC1|']['suite_usd']);

        // La cajera le dio $102,25 al cliente del efectivo y le faltan 2,75: contó 430.
        $this->actingAs($this->usuario('tesoreria'))->post(route('cuadre_caja.conciliar', $caja), [
            'accion' => 'procesar', 'montos' => ['02|' => '430', 'CASHEA|' => '1084.04'], 'observacion' => 'Faltante por tasa en devolución',
        ])->assertSessionHasNoErrors();
        $resumen = app(CuadreCajaService::class)->resumen($caja->fresh()->load(['pagos', 'conteos', 'lotes']));
        $this->assertSame(1619.04, $resumen['ingresos_usd']);
        $this->assertSame(1616.29, $resumen['tesoreria_usd']);
        $this->assertSame(-2.75, $resumen['diferencia_usd']);

        $this->get(route('cuadre_caja.show', $caja))->assertOk()
            ->assertSee('No suma al cuadre')->assertSee('Devuelto en efectivo')->assertSee('-$2,75', false);
    }

    public function test_lo_contado_antes_punto_por_punto_se_junta_en_una_linea_y_al_reprocesar_no_se_duplica(): void
    {
        $tesoreria = $this->usuario('tesoreria');
        foreach (['BAN02' => 500000, 'BNC02' => 231536.94] as $cuenta => $monto) {
            \App\Models\CuadreCajaConteo::create(['cuadre_caja_id' => $this->cierre->id, 'codigo_metodo' => 'TDD', 'codigo_cuenta' => $cuenta,
                'forma_pago' => 'TARJ. DÉBITO', 'cuenta' => config('cuadre_caja.formas_cuadre.DORAL')['TDD|'.$cuenta][1],
                'en_dolares' => false, 'monto' => $monto, 'user_id' => $tesoreria->id]);
        }
        $ingreso = TesoreriaIngreso::create(['tipo' => 'punto_venta', 'banco' => 'BNC', 'titular' => 'DORAL', 'fecha' => '2026-10-30', 'monto' => 231536.94, 'lote_referencia' => '55']);
        CuadreCajaLote::create(['cuadre_caja_id' => $this->cierre->id, 'codigo_metodo' => 'TDD', 'codigo_cuenta' => 'BNC02', 'banco' => 'BNC',
            'titular' => 'DORAL', 'lote' => '55', 'monto' => 231536.94, 'tesoreria_ingreso_id' => $ingreso->id, 'user_id' => $tesoreria->id]);

        $pos = collect(app(CuadreCajaService::class)->lineas($this->cierre->fresh()->load(['pagos', 'conteos', 'lotes'])))->keyBy('clave')['TDD|PUNTOS'];
        $this->assertSame(731536.94, round((float) $pos['tesoreria'], 2));
        $this->assertSame(['55'], array_column($pos['lotes'], 'lote'));

        $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
            'accion' => 'procesar', 'montos' => ['02|' => '815'],
            'lotes' => ['TDD|PUNTOS' => [['banco' => 'BNC', 'titular' => 'DORAL', 'lote' => '55', 'monto' => '231536.94'], ['banco' => 'BANESCO', 'lote' => '9', 'monto' => '500000']]],
            'observacion' => 'ok',
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(['PUNTOS'], \App\Models\CuadreCajaConteo::query()->where('codigo_metodo', 'TDD')->pluck('codigo_cuenta')->all());
        $this->assertSame(731536.94, (float) \App\Models\CuadreCajaConteo::query()->where('codigo_metodo', 'TDD')->value('monto'));
        $this->assertSame(2, CuadreCajaLote::query()->count());
        $this->assertSame(['PUNTOS'], CuadreCajaLote::query()->distinct()->pluck('codigo_cuenta')->all());
        $this->assertSame(2, TesoreriaIngreso::query()->count());
    }

    public function test_un_lote_sin_numero_o_repetido_no_se_guarda(): void
    {
        $tesoreria = $this->usuario('tesoreria');
        foreach ([
            [['lote' => '', 'monto' => '10']],
            [['lote' => '5', 'monto' => '10'], ['lote' => '5', 'monto' => '20']],
        ] as $lotes) {
            $this->actingAs($tesoreria)->post(route('cuadre_caja.conciliar', $this->cierre), [
                'accion' => 'procesar', 'montos' => ['02|' => '815'], 'lotes' => ['TDD|PUNTOS' => $lotes], 'observacion' => 'ok',
            ])->assertSessionHas('error');
        }

        $this->assertTrue($this->cierre->fresh()->pendiente());
        $this->assertSame(0, TesoreriaIngreso::query()->count());
    }

    public function test_solo_las_tarjetas_en_cuentas_pv_son_punto_de_venta(): void
    {
        $s = app(CuadreCajaService::class);

        $this->assertTrue($s->esPuntoDeVenta('TDD', 'TARJ. DÉBITO', 'BANESCO P.V. JRZ'));
        $this->assertTrue($s->esPuntoDeVenta('TDD', 'TARJ. DÉBITO', 'BNC P.V. JRZ'));
        $this->assertFalse($s->esPuntoDeVenta('TRFBS', 'TRANSFERENCIA BS', 'BANESCO P.V. JRZ'));
        $this->assertTrue($s->esPuntoDeVenta('TDD', 'TARJ. DÉBITO', 'BANESCO INV. DORAL (4025)'));
        $this->assertFalse($s->esPuntoDeVenta('TRFBS', 'TRANSFERENCIA BS', 'BANESCO INV. DORAL (4025)'));
        $this->assertFalse($s->esPuntoDeVenta('TDD', 'TARJ. DÉBITO', 'BANESCO DORAL'));
        $this->assertSame(['BNC', 'JRZ'], $s->bancoTitularPuntoVenta('BNC P.V. JRZ', 'DORAL'));
        $this->assertSame(['BANESCO', 'DORAL'], $s->bancoTitularPuntoVenta('BANESCO P.V. DORAL', 'JRZ'));
    }

    /** @return list<array{lote: string, monto: string}> */
    public function test_muestra_las_formas_de_pago_de_la_pantalla_de_suite_aunque_esten_en_cero(): void
    {
        $otro = CuadreCaja::query()->where('codigo_caja', 'CJA01')->firstOrFail();
        CuadreCajaPago::create([
            'cuadre_caja_id' => $otro->id, 'codigo_metodo' => '03', 'forma_pago' => 'NOMINA',
            'en_dolares' => true, 'ingresos' => 10, 'ingresos_usd' => 10,
        ]);

        $lineas = collect(app(CuadreCajaService::class)->lineas($this->cierre->load(['pagos', 'conteos', 'lotes'])))->keyBy('clave');

        // Los puntos de venta (Banesco, BNC, Inv. Doral…) van en una sola línea TARJ. DÉBITO.
        $s = app(CuadreCajaService::class);
        $sinPuntos = array_values(array_filter(array_keys(config('cuadre_caja.formas_cuadre.DORAL')), function ($clave) use ($s) {
            [$forma, $cuenta, $usd] = config('cuadre_caja.formas_cuadre.DORAL')[$clave];

            return $usd || ! $s->esPuntoDeVenta(explode('|', $clave)[0], $forma, $cuenta);
        }));
        $this->assertSame($sinPuntos, $lineas->keys()->reject(fn ($k) => $k === 'TDD|PUNTOS')->values()->all());
        $this->assertSame(815.0, $lineas['02|']['suite']);
        $this->assertSame(0.0, $lineas['TRF|BINANCE']['suite']);
        $this->assertTrue($lineas['TDD|PUNTOS']['punto_venta']);
        $this->assertSame('TARJ. DÉBITO', $lineas['TDD|PUNTOS']['forma_pago']);
        $this->assertFalse($lineas->has('TDD|BNC02'));
        $this->assertFalse($lineas->has('TDD|4025'));
        $this->assertFalse($lineas['TRFBS|4025']['punto_venta']);
        $this->assertSame(['NDC|', 'NDC1|', 'IGTF|'], $lineas->keys()->slice(-3)->values()->all());
        $this->assertSame('EFECTIVO DEVOLUCION', $lineas['NDC1|']['forma_pago']);
        $this->assertFalse($lineas->has('03|'));
        $this->assertSame('BANESCO', app(CuadreCajaService::class)->bancoTitularPuntoVenta('BANESCO INV. DORAL (4025)', 'DORAL')[0]);

        $this->actingAs($this->usuario('tesoreria'))
            ->get(route('cuadre_caja.show', $this->cierre))
            ->assertOk()
            ->assertSee('TRANSF. $')
            ->assertSee('BINANCE');
    }

    private function lotesCompletos(): array
    {
        return [['lote' => '101', 'monto' => '500000'], ['lote' => '102', 'monto' => '231536.94']];
    }

    private function pagosDeSuite(CuadreCaja $cierre): void
    {
        CuadreCajaPago::create([
            'cuadre_caja_id' => $cierre->id, 'codigo_metodo' => '02', 'forma_pago' => 'EFECTIVO $',
            'en_dolares' => true, 'ingresos' => 815, 'ingresos_usd' => 815,
        ]);
        CuadreCajaPago::create([
            'cuadre_caja_id' => $cierre->id, 'codigo_metodo' => 'TDD', 'forma_pago' => 'TARJ. DÉBITO', 'codigo_cuenta' => 'BAN02',
            'cuenta' => 'BANESCO P.V. DORAL', 'en_dolares' => false, 'ingresos' => 731536.94, 'ingresos_usd' => 822.6356,
        ]);
    }

    private function usuario(string $rol): User
    {
        return User::create([
            'name' => 'Usuario '.$rol,
            'email' => 'u-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => $rol,
            'sede' => 'DORAL',
        ]);
    }
}
