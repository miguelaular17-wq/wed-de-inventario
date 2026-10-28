<?php

namespace Tests\Feature;

use App\Jobs\ProcesarImportacionExtracto;
use App\Models\ConciliacionImportacion;
use App\Models\ConciliacionLinea;
use App\Models\FlujoCaja;
use App\Models\User;
use App\Services\Conciliacion\ConciliacionAutoMatcher;
use App\Services\Conciliacion\ExtractoImporter;
use App\Services\Conciliacion\ExtractoParser;
use App\Support\SimpleXlsxWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ConciliacionExtractoImportTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->crearTablasConciliacion();
        Storage::fake('local');
        config(['conciliacion.import_chunk' => 50, 'conciliacion.import_async' => false]);
    }

    public function test_importa_excel_banesco_valido(): void
    {
        $path = $this->xlsxBanesco([
            ['03/08/2026', '1001', 'PAGO PROVEEDOR', '-1.500,25', '0'],
            ['04/08/2026', '1002', 'TRANSFERENCIA RECIBIDA', '300,00', '0'],
            ['05/08/2026', '1003', 'SALDO INICIAL', '999,00', '0'],
        ]);

        $imp = $this->importar($path);
        $this->assertSame(ConciliacionImportacion::COMPLETADA, $imp->estado);
        $this->assertSame(2, $imp->filas_importadas);
        $this->assertSame(0, $imp->filas_rechazadas);

        $lineas = ConciliacionLinea::query()->orderBy('id')->get();
        $this->assertCount(2, $lineas);
        $this->assertSame('cargo', $lineas[0]->tipo);
        $this->assertEquals(1500.25, (float) $lineas[0]->monto);
        $this->assertSame('abono', $lineas[1]->tipo);
        $this->assertEquals(300.0, (float) $lineas[1]->monto);
    }

    public function test_archivo_vacio_queda_fallido(): void
    {
        $path = $this->xlsxBanesco([]);
        $imp = $this->importar($path);

        $this->assertSame(ConciliacionImportacion::FALLIDA, $imp->estado);
        $this->assertSame(0, $imp->filas_importadas);
        $this->assertStringContainsString('No se encontró ninguna fila', (string) $imp->mensaje);
    }

    public function test_cabeceras_incorrectas_no_importan_movimientos(): void
    {
        Storage::disk('local')->put('mal.xlsx', SimpleXlsxWriter::toString(['H' => [
            ['ColA', 'ColB', 'ColC', 'ColD'],
            ['99/99/9999', 'x', 'y', '10,00'],
        ]]));

        $imp = ConciliacionImportacion::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'archivo_nombre' => 'mal.xlsx',
            'archivo_path' => 'mal.xlsx',
            'extension' => 'xlsx',
            'estado' => ConciliacionImportacion::PENDIENTE,
            'user_id' => $this->makeUser(User::ROLE_CONTABILIDAD)->id,
        ]);

        $imp = app(ExtractoImporter::class)->importarArchivo($imp);
        $this->assertTrue($imp->terminada());
        $this->assertSame(0, $imp->filas_importadas);
        $this->assertSame(0, ConciliacionLinea::query()->count());
        $this->assertGreaterThan(0, $imp->filas_rechazadas);
    }

    public function test_duplicados_no_se_vuelven_a_insertar(): void
    {
        $filas = [
            ['03/08/2026', '1001', 'PAGO', '-100,00', '0'],
            ['03/08/2026', '1001', 'PAGO', '-100,00', '0'],
        ];
        $path = $this->xlsxBanesco($filas);

        $imp1 = $this->importar($path);
        $this->assertSame(1, $imp1->filas_importadas);
        $this->assertSame(1, $imp1->filas_duplicadas);

        // Primera importación borra el archivo; regeneramos para la re-subida.
        $path2 = $this->xlsxBanesco($filas, 'dup2.xlsx');
        $imp2 = ConciliacionImportacion::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'archivo_nombre' => 'dup2.xlsx',
            'archivo_path' => $path2,
            'extension' => 'xlsx',
            'estado' => ConciliacionImportacion::PENDIENTE,
        ]);
        $imp2 = app(ExtractoImporter::class)->importarArchivo($imp2);
        $this->assertSame(0, $imp2->filas_importadas);
        $this->assertSame(2, $imp2->filas_duplicadas);
        $this->assertSame(1, ConciliacionLinea::query()->count());
    }

    public function test_rechaza_montos_y_fechas_invalidas(): void
    {
        $path = $this->xlsxBanesco([
            ['99/99/9999', '1', 'MALA FECHA', '10,00', '0'],
            ['03/08/2026', '2', 'SIN MONTO', '0,00', '0'],
            ['03/08/2026', '3', 'MONTO TEXTO', 'N/A', '0'],
            ['04/08/2026', '4', 'OK', '50,00', '0'],
        ]);

        $imp = $this->importar($path);
        $this->assertSame(ConciliacionImportacion::COMPLETADA_CON_PENDIENTES, $imp->estado);
        $this->assertSame(1, $imp->filas_importadas);
        $this->assertGreaterThanOrEqual(3, $imp->filas_rechazadas);
        $this->assertNotEmpty($imp->rechazos);
    }

    public function test_concilia_coincidencia_exacta_y_deja_sin_match_pendiente(): void
    {
        ConciliacionLinea::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-06',
            'descripcion' => 'PAGO PROVEEDOR',
            'referencia' => '5003745290',
            'monto' => 30268,
            'estado' => 'pendiente',
            'tipo' => 'cargo',
            'importacion_id' => 1,
        ]);
        ConciliacionLinea::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-07',
            'descripcion' => 'SIN MATCH',
            'referencia' => '999',
            'monto' => 10,
            'estado' => 'pendiente',
            'tipo' => 'cargo',
            'importacion_id' => 1,
        ]);

        FlujoCaja::create([
            'tipo' => 'egreso',
            'banco' => 'Banesco',
            'titular' => 'Doral',
            'fecha' => '2026-08-06',
            'monto_bs' => 30268,
            'referencia' => '5003745290',
            'concepto' => 'ANTICIPO',
            'motivo' => '073 - ANTICIPO',
            'es_conciliado' => false,
            'oculto' => false,
        ]);

        $n = app(ConciliacionAutoMatcher::class)->conciliar(
            ConciliacionLinea::query()->get(),
            '2026-08-01',
            '2026-08-31',
            'BANESCO',
            'DORAL',
            500
        );

        $this->assertSame(1, $n);
        $this->assertSame('conciliado', ConciliacionLinea::query()->where('referencia', '5003745290')->value('estado'));
        $this->assertSame('pendiente', ConciliacionLinea::query()->where('referencia', '999')->value('estado'));
        $this->assertTrue((bool) FlujoCaja::query()->first()->es_conciliado);
    }

    public function test_abono_del_banco_se_concilia_con_el_ingreso_registrado_y_no_con_un_egreso(): void
    {
        $abono = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-07', 'descripcion' => 'TRANSFERENCIA RECIBIDA',
            'referencia' => '0012345678', 'monto' => 5000, 'estado' => 'pendiente', 'tipo' => 'abono', 'importacion_id' => 1,
        ]);
        $cargo = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-07', 'descripcion' => 'PAGO',
            'referencia' => '777', 'monto' => 5000, 'estado' => 'pendiente', 'tipo' => 'cargo', 'importacion_id' => 1,
        ]);
        $ingreso = FlujoCaja::create([
            'tipo' => 'ingreso', 'categoria_egreso' => 'ingresos', 'banco' => 'Banesco', 'titular' => 'Doral',
            'fecha' => '2026-08-06', 'monto_bs' => 5000, 'referencia' => '12345678', 'motivo' => 'Reintegro proveedor',
            'es_conciliado' => false, 'oculto' => false,
        ]);

        $n = app(ConciliacionAutoMatcher::class)->conciliar(ConciliacionLinea::query()->get(), '2026-08-01', '2026-08-31', 'BANESCO', 'DORAL', 500);

        $this->assertSame(1, $n);
        $this->assertSame($ingreso->id, $abono->fresh()->flujo_caja_id);
        $this->assertSame('conciliado', $abono->fresh()->estado);
        $this->assertTrue((bool) $ingreso->fresh()->es_conciliado);
        // Un cargo del mismo monto nunca se queda con un ingreso.
        $this->assertSame('pendiente', $cargo->fresh()->estado);
    }

    public function test_no_permite_doble_conciliacion_del_mismo_egreso(): void
    {
        $l1 = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-06',
            'descripcion' => 'PAGO A', 'referencia' => '111', 'monto' => 100,
            'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $l2 = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-06',
            'descripcion' => 'PAGO B', 'referencia' => '111', 'monto' => 100,
            'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'DORAL',
            'fecha' => '2026-08-06', 'monto_bs' => 100, 'referencia' => '111',
            'motivo' => '073 - ANTICIPO', 'es_conciliado' => false, 'oculto' => false,
        ]);

        $matcher = app(ConciliacionAutoMatcher::class);
        $matcher->conciliar(collect([$l1]), '2026-08-01', '2026-08-31', 'BANESCO', 'DORAL', 10);
        $matcher->conciliar(collect([$l2->fresh()]), '2026-08-01', '2026-08-31', 'BANESCO', 'DORAL', 10);

        $this->assertSame(1, ConciliacionLinea::query()->where('estado', 'conciliado')->count());
        $this->assertSame(1, ConciliacionLinea::query()->where('estado', 'pendiente')->count());
        $this->assertSame(1, FlujoCaja::query()->where('es_conciliado', true)->count());
    }

    public function test_conciliar_en_transito_a_mano_saca_la_linea_de_movimientos_del_banco(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $linea = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-11',
            'descripcion' => 'PAGO PROVEEDOR', 'referencia' => '777', 'monto' => 2500,
            'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $otraCuenta = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'JRZ', 'fecha' => '2026-08-06',
            'descripcion' => 'PAGO', 'referencia' => '1', 'monto' => 2500,
            'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'DORAL',
            'fecha' => '2026-08-06', 'monto_bs' => 2500, 'referencia' => '999',
            'motivo' => '073 - ANTICIPO', 'oculto' => false,
        ]);

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.transito_conciliar'), [
                'flujo_id' => $flujo->id,
                'fecha_desde' => '2026-08-01',
                'fecha_hasta' => '2026-08-31',
                'banco_filtro' => 'BANESCO',
                'titular_filtro' => 'DORAL',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, '11/08/2026'));

        $linea->refresh();
        $this->assertSame('conciliado', $linea->estado);
        $this->assertSame((int) $flujo->id, (int) $linea->flujo_caja_id);
        $this->assertTrue((bool) $flujo->fresh()->es_conciliado);
        $this->assertSame('pendiente', $otraCuenta->fresh()->estado);
    }

    public function test_conciliar_en_transito_automatico_sin_linea_no_lo_da_por_conciliado(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'DORAL',
            'fecha' => '2026-08-06', 'monto_bs' => 123.45, 'motivo' => 'X', 'oculto' => false,
        ]);

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.transito_conciliar'), ['flujo_id' => $flujo->id])
            ->assertRedirect()
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'No se encontró en el banco') && str_contains($m, 'Justificar'));

        $this->assertFalse((bool) $flujo->fresh()->es_conciliado);
    }

    public function test_justificar_en_transito_sin_linea_exige_nota_y_la_guarda(): void
    {
        Schema::table('flujo_cajas', fn (Blueprint $t) => $t->text('conciliacion_nota')->nullable());
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'DORAL',
            'fecha' => '2026-08-06', 'monto_bs' => 123.45, 'motivo' => 'X', 'oculto' => false,
        ]);
        $ruta = route('finanzas.conciliaciones.transito.justificar');

        $this->actingAs($user)->post($ruta, ['flujo_id' => $flujo->id, 'justificacion' => ''])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Escribe qué es'));
        $this->assertFalse((bool) $flujo->fresh()->es_conciliado);

        $this->actingAs($user)->post($ruta, ['flujo_id' => $flujo->id, 'justificacion' => 'Se pagó en efectivo'])
            ->assertSessionHas('success');
        $this->assertTrue((bool) $flujo->fresh()->es_conciliado);
        $this->assertStringContainsString('Sin movimiento en el banco: Se pagó en efectivo', \Illuminate\Support\Facades\DB::table('flujo_cajas')->where('id', $flujo->id)->value('conciliacion_nota'));
    }

    public function test_buscar_el_error_encuentra_el_pago_en_otra_cuenta_y_lo_mueve_al_conciliar(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BNC', 'titular' => 'LNACEH',
            'fecha' => '2026-09-24', 'monto_bs' => 188890.03, 'referencia' => '2692125415',
            'motivo' => 'DECLARACION', 'oculto' => false,
        ]);
        $enOtraCuenta = ConciliacionLinea::create([
            'banco' => 'BNC', 'titular' => 'JRZ', 'fecha' => '2026-09-25', 'descripcion' => 'PAGO SENIAT',
            'referencia' => '0002692125415', 'monto' => 188890.03, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $coma = ConciliacionLinea::create([
            'banco' => 'BNC', 'titular' => 'LNACEH', 'fecha' => '2026-09-24', 'descripcion' => 'PAGO',
            'referencia' => '55', 'monto' => 1888.90, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-09-24', 'descripcion' => 'OTRA COSA',
            'referencia' => '1', 'monto' => 5, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);

        $r = $this->actingAs($user)->getJson(route('finanzas.conciliaciones.transito.buscar', ['flujo_id' => $flujo->id]))->assertOk()->json();
        $this->assertCount(2, $r['resultados']);
        // Lo que tiene la coma corrida (188.890,03 vs 1.888,90) también sale, pero después.
        $this->assertSame($coma->id, $r['resultados'][1]['id']);
        $this->assertTrue($r['resultados'][1]['x100']);
        $this->assertSame('conciliar', $r['resultados'][1]['accion']);
        $hallado = $r['resultados'][0];
        $this->assertSame($enOtraCuenta->id, $hallado['id']);
        $this->assertTrue($hallado['mismo_monto'] && $hallado['misma_ref']);
        $this->assertFalse($hallado['misma_cuenta']);
        $this->assertSame('mover', $hallado['accion']);
        // En «Justificar» solo salen los cargos pendientes de la cuenta del egreso.
        $this->assertSame([$coma->id], array_column($r['pendientes_cuenta'], 'id'));

        $ruta = route('finanzas.conciliaciones.transito.justificar');
        $this->actingAs($user)->post($ruta, ['flujo_id' => $flujo->id, 'linea_id' => $enOtraCuenta->id])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'otra cuenta'));

        $this->actingAs($user)->post($ruta, ['flujo_id' => $flujo->id, 'linea_id' => $enOtraCuenta->id, 'mover_cuenta' => 1])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '25/09/2026') && str_contains($m, 'JRZ'));
        $this->assertSame('conciliado', $enOtraCuenta->fresh()->estado);
        $this->assertSame((int) $flujo->id, (int) $enOtraCuenta->fresh()->flujo_caja_id);
        $this->assertTrue((bool) $flujo->fresh()->es_conciliado);
        $this->assertSame('JRZ', $flujo->fresh()->titular);
    }

    public function test_justificar_movimiento_del_banco_lo_concilia_con_la_nota(): void
    {
        Schema::table('conciliacion_lineas', fn (Blueprint $t) => $t->text('conciliacion_nota')->nullable());
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $linea = ConciliacionLinea::create([
            'banco' => 'BNC', 'titular' => 'L.S. CASHEA', 'fecha' => '2026-09-01', 'descripcion' => 'PAGO MOVIL',
            'referencia' => '1043941122', 'monto' => 7576.11, 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);
        $ruta = route('finanzas.conciliaciones.linea.justificar');

        $this->actingAs($user)->post($ruta, ['linea_id' => $linea->id, 'justificacion' => 'abc'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'mínimo 5'));
        $this->assertSame('pendiente', $linea->fresh()->estado);

        $this->actingAs($user)->post($ruta, ['linea_id' => $linea->id, 'justificacion' => 'Cobro Cashea de agosto'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Cobro Cashea de agosto'));
        $linea->refresh();
        $this->assertSame('conciliado', $linea->estado);
        $this->assertNull($linea->flujo_caja_id);
        $this->assertStringStartsWith('Cobro Cashea de agosto — Contabilidad, ', $linea->conciliacion_nota);

        // Una línea ya conciliada no se vuelve a justificar.
        $this->actingAs($user)->post($ruta, ['linea_id' => $linea->id, 'justificacion' => 'Otra cosa distinta'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'ya no está pendiente'));
    }

    public function test_recalcular_una_cuenta_concilia_lo_que_se_habia_quedado_pendiente(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $linea = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-06', 'descripcion' => 'PAGO PROVEEDOR',
            'referencia' => '5003745290', 'monto' => 30268, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $otraCuenta = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'JRZ', 'fecha' => '2026-08-06', 'descripcion' => 'PAGO',
            'referencia' => '5003745290', 'monto' => 30268, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'Banesco', 'titular' => 'Doral', 'fecha' => '2026-08-06',
            'monto_bs' => 30268, 'referencia' => '5003745290', 'motivo' => '073 - ANTICIPO', 'oculto' => false,
        ]);

        $this->actingAs($user)->post(route('finanzas.conciliaciones.recalcular'), [
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha_desde' => '2026-08-01', 'fecha_hasta' => '2026-08-31', 'banco_filtro' => 'BANESCO',
        ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Recalculado BANESCO · DORAL') && str_contains($m, '1 movimientos conciliados') && str_contains($m, '1 → 0'));

        $this->assertSame('conciliado', $linea->fresh()->estado);
        $this->assertSame((int) $flujo->id, (int) $linea->fresh()->flujo_caja_id);
        $this->assertSame('pendiente', $otraCuenta->fresh()->estado);

        // Sin nada nuevo, lo dice.
        $this->actingAs($user)->post(route('finanzas.conciliaciones.recalcular'), [
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha_desde' => '2026-08-01', 'fecha_hasta' => '2026-08-31',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'no apareció ninguna coincidencia nueva'));
    }

    public function test_cargo_banesco_despues_del_abono_de_pago_movil_es_comision(): void
    {
        $mov = fn (string $tipo, string $ref, float $monto, string $titular = 'DORAL', string $desc = 'Banesco Pago Movil') => ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => $titular, 'fecha' => '2026-08-04', 'descripcion' => $desc,
            'referencia' => $ref, 'monto' => $monto, 'estado' => 'pendiente', 'tipo' => $tipo,
        ]);
        $mov('abono', '62167593808', 2065.00);
        $comision1 = $mov('cargo', '62167593808', 30.98);
        $mov('abono', '62168051075', 4265.00);
        $comision2 = $mov('cargo', '62168051075', 63.98);
        // Pago móvil pequeño: comisión mínima de Bs 14 (más del 5 %).
        $mov('abono', '62160000001', 100.00);
        $minima = $mov('cargo', '62160000001', 14.00);
        // Sin abono con esa referencia: es un pago móvil enviado, no comisión.
        $enviado = $mov('cargo', '62167549276', 432.21);
        // Mismo monto que el abono: no es comisión (sería una devolución).
        $mov('abono', '700', 500.00);
        $devuelto = $mov('cargo', '700', 500.00);
        // Otra cuenta con la misma referencia: no se cruza.
        $otraCuenta = $mov('cargo', '62168060209', 20.00, 'JRZ');
        $mov('abono', '62168060209', 94295.55);

        $this->assertSame(3, app(\App\Services\Conciliacion\ComisionPagoMovilBanesco::class)->marcar('2026-08-01', '2026-08-31'));
        $this->assertTrue($comision1->fresh()->forzar_comision);
        $this->assertTrue($comision2->fresh()->forzar_comision);
        $this->assertTrue($minima->fresh()->forzar_comision);
        $this->assertFalse($enviado->fresh()->forzar_comision);
        $this->assertFalse($devuelto->fresh()->forzar_comision);
        $this->assertFalse($otraCuenta->fresh()->forzar_comision);
        // Correrlo otra vez no cambia nada.
        $this->assertSame(0, app(\App\Services\Conciliacion\ComisionPagoMovilBanesco::class)->marcar('2026-08-01', '2026-08-31'));
    }

    public function test_buscar_el_error_no_usa_el_monto_en_dolares_ni_comisiones(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-01',
            'monto_bs' => 149758.00, 'monto_usd' => 200, 'referencia' => '79', 'motivo' => 'SUELDO', 'oculto' => false,
        ]);
        foreach (['Com. Banesco Pago Movil', 'OP.CRED.DIRT. CLTE-CLTE PJ -VIA ELECTRON'] as $desc) {
            ConciliacionLinea::create([
                'banco' => 'BANESCO', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-07-31', 'descripcion' => $desc,
                'referencia' => '43700084086', 'monto' => 2.00, 'estado' => 'pendiente', 'tipo' => 'cargo',
            ]);
        }
        $cerca = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-04', 'descripcion' => 'PAGO NOMINA',
            'referencia' => '1', 'monto' => 149758.00, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $mismoDia = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-01', 'descripcion' => 'PAGO NOMINA',
            'referencia' => '2', 'monto' => 149758.00, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);

        $r = $this->actingAs($user)->getJson(route('finanzas.conciliaciones.transito.buscar', ['flujo_id' => $flujo->id]))->assertOk()->json();
        // Solo los de mismo monto, el más cercano a la fecha primero.
        $this->assertSame([$mismoDia->id, $cerca->id], array_column($r['resultados'], 'id'));
        $this->assertSame('mismo día', $r['resultados'][0]['cuando']);
        $this->assertSame('3 días después', $r['resultados'][1]['cuando']);
    }

    public function test_egreso_duplicado_se_anula_desde_buscar_el_error(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $original = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-06',
            'monto_bs' => 5000, 'referencia' => '123456789', 'motivo' => 'PAGO PROVEEDOR', 'oculto' => false,
        ]);
        $original->forceFill(['es_conciliado' => true])->save();
        $linea = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-06', 'descripcion' => 'PAGO',
            'referencia' => '123456789', 'monto' => 5000, 'estado' => 'conciliado', 'tipo' => 'cargo', 'flujo_caja_id' => $original->id,
        ]);
        $copia = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-07',
            'monto_bs' => 5000, 'referencia' => '123456789', 'motivo' => 'PAGO PROVEEDOR', 'oculto' => false,
        ]);

        $r = $this->actingAs($user)->getJson(route('finanzas.conciliaciones.transito.buscar', ['flujo_id' => $copia->id]))->assertOk()->json();
        $this->assertSame($linea->id, $r['resultados'][0]['id']);
        $this->assertSame($original->id, $r['resultados'][0]['duplicado_de']);
        $this->assertSame($original->id, $r['duplicados'][0]['id']);
        $this->assertTrue($r['duplicados'][0]['duplicado']);

        $this->actingAs($user)->post(route('finanzas.conciliaciones.transito.anular_duplicado'), ['flujo_id' => $copia->id, 'duplicado_de' => $original->id])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'anulado por estar duplicado'));
        $copia->refresh();
        $this->assertTrue($copia->anulado);
        $this->assertStringStartsWith('Duplicado del egreso #'.$original->id, $copia->anulado_motivo);
        $this->assertFalse((bool) $original->fresh()->anulado);
        // Ya no sale en Flujo de Caja ni en los totales.
        $this->assertSame([$original->id], FlujoCaja::query()->vigentes()->pluck('id')->all());

        // El original (conciliado) no se puede anular como duplicado.
        $this->actingAs($user)->post(route('finanzas.conciliaciones.transito.anular_duplicado'), ['flujo_id' => $original->id, 'duplicado_de' => $copia->id])
            ->assertSessionHas('error');
        $this->assertFalse((bool) $original->fresh()->anulado);
    }

    public function test_en_transito_marca_el_duplicado_de_un_egreso_ya_conciliado(): void
    {
        $base = ['tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-17', 'monto_bs' => 227.59, 'oculto' => false];
        $conciliado = FlujoCaja::create($base + ['referencia' => '110', 'motivo' => 'COMISIONES DEL PAGO MOVIL']);
        $conciliado->forceFill(['es_conciliado' => true])->save();
        $copia = FlujoCaja::create($base + ['referencia' => '110', 'motivo' => '0,30% DE COMISION DEL PAGO MOVIL']);
        // Mismo monto y día pero otra referencia: dos pagos distintos, no se marca.
        $otro = FlujoCaja::create($base + ['referencia' => '111', 'motivo' => 'OTRO PAGO']);
        // Misma referencia en otra cuenta: no se marca.
        $otraCuenta = FlujoCaja::create(array_merge($base, ['titular' => 'DORAL', 'referencia' => '110', 'motivo' => 'X']));

        $metodo = new \ReflectionMethod(\App\Http\Controllers\FinanzasController::class, 'duplicadosEnTransito');
        $dup = $metodo->invoke(app(\App\Http\Controllers\FinanzasController::class),
            FlujoCaja::query()->whereIn('id', [$copia->id, $otro->id, $otraCuenta->id])->get(), '2026-08-01', '2026-08-31', 'BANESCO', null);

        $this->assertSame([$copia->id], array_keys($dup));
        $this->assertSame($conciliado->id, $dup[$copia->id]['id']);
    }

    public function test_justificar_con_linea_de_otro_monto_pide_nota(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'DORAL',
            'fecha' => '2026-08-06', 'monto_bs' => 1000, 'motivo' => 'X', 'oculto' => false,
        ]);
        $linea = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-07', 'descripcion' => 'PAGO',
            'referencia' => '9', 'monto' => 1003.5, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $ruta = route('finanzas.conciliaciones.transito.justificar');

        $this->actingAs($user)->post($ruta, ['flujo_id' => $flujo->id, 'linea_id' => $linea->id])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'no coincide'));
        $this->assertSame('pendiente', $linea->fresh()->estado);

        $this->actingAs($user)->post($ruta, ['flujo_id' => $flujo->id, 'linea_id' => $linea->id, 'justificacion' => 'Comisión cobrada en el mismo cargo'])
            ->assertSessionHas('success');
        $this->assertSame('conciliado', $linea->fresh()->estado);
        $this->assertTrue((bool) $flujo->fresh()->es_conciliado);
    }

    public function test_pdf_de_conciliacion_no_repite_copias_del_mismo_movimiento(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $base = [
            'banco' => 'VENEZUELA', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-16',
            'descripcion' => 'TRANSFERENCIA RECIBIDA DE TERCEROS', 'referencia' => '1742502415086',
            'tipo' => 'abono',
        ];
        ConciliacionLinea::create($base + ['monto' => 4080.51, 'estado' => 'conciliado']);
        ConciliacionLinea::create($base + ['monto' => 4080.51, 'estado' => 'pendiente']);
        ConciliacionLinea::create($base + ['monto' => 4080.51, 'estado' => 'pendiente']);
        // Misma referencia, otro monto: es otro movimiento y debe seguir apareciendo.
        ConciliacionLinea::create($base + ['monto' => 17688.99, 'estado' => 'pendiente']);

        $capturado = null;
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('pdf'));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')
            ->once()
            ->andReturnUsing(function ($vista, $datos) use (&$capturado, $pdf) {
                $capturado = $datos['data'];

                return $pdf;
            });

        $this->actingAs($user)
            ->get(route('finanzas.conciliaciones.reporte-banco', [
                'banco' => 'VENEZUELA',
                'titular' => 'GRUPO JRZ',
                'fecha_desde' => '2026-08-01',
                'fecha_hasta' => '2026-08-31',
            ]))
            ->assertOk();

        $this->assertCount(1, $capturado['conciliados']);
        $this->assertCount(1, $capturado['sin_registrar']);
        $this->assertEquals(17688.99, (float) $capturado['sin_registrar'][0]['monto']);
    }

    public function test_pdf_no_lista_cancelacion_resumen_de_ventas_como_movimiento_del_banco(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-31',
            'descripcion' => 'CANCELACION RESUMEN DE VENTAS Y RETENCIO', 'referencia' => '14502001242',
            'monto' => 792900.62, 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);
        ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-30',
            'descripcion' => 'CANCELACION RESUMEN DE VENTAS Y RETENCIO', 'referencia' => '14502001241',
            'monto' => 1450449.09, 'estado' => 'conciliado', 'tipo' => 'abono',
        ]);
        ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-31',
            'descripcion' => 'CREDITO INMEDIATO CAMARA DE COMPENSACION', 'referencia' => '20490581436',
            'monto' => 15912.00, 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);

        $capturado = null;
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('pdf'));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()
            ->andReturnUsing(function ($vista, $datos) use (&$capturado, $pdf) {
                $capturado = $datos['data'];

                return $pdf;
            });

        $this->actingAs($user)
            ->get(route('finanzas.conciliaciones.reporte-banco', [
                'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ',
                'fecha_desde' => '2026-08-01', 'fecha_hasta' => '2026-08-31',
            ]))
            ->assertOk();

        $this->assertCount(1, $capturado['sin_registrar']);
        $this->assertSame('20490581436', $capturado['sin_registrar'][0]['referencia']);
        $this->assertCount(0, $capturado['conciliados']);
    }

    public function test_la_comision_del_banco_no_se_queda_con_el_egreso_de_la_transferencia(): void
    {
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'Mercantil', 'titular' => 'JRZ', 'fecha' => '2026-08-05',
            'monto_bs' => 22654.80, 'monto_usd' => 30, 'comision' => 2.26, 'referencia' => '47900003411',
            'motivo' => '2 LIMPIEZAS', 'tipo_gasto' => '036 - MANT. Y REP. LOCALES/CASAS EMPEÑO', 'oculto' => false,
        ]);
        $flujo->forceFill(['es_conciliado' => true])->save();
        $comision = ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-05',
            'descripcion' => 'OP.CRED.DIRT. CLTE-CLTE PJ -VIA ELECTRON', 'referencia' => '43700003411',
            'monto' => 2.26, 'estado' => 'conciliado', 'tipo' => 'cargo', 'flujo_caja_id' => $flujo->id,
        ]);
        $transferencia = ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-05',
            'descripcion' => 'TRANSF ENTRE CTAS BM PERS JURIDICA', 'referencia' => '47900003411',
            'monto' => 22654.80, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);

        $matcher = app(\App\Services\BankReconciliationMatcher::class);
        $this->assertTrue($matcher->referenciasCruzan('43700003411', '47900003411'));
        $this->assertFalse($matcher->coincideEgreso($comision, $flujo));

        $reparar = new \ReflectionMethod(\App\Http\Controllers\FinanzasController::class, 'desvincularComisionEmparejadaConEgreso');
        $reparar->invoke(app(\App\Http\Controllers\FinanzasController::class), $matcher, '2026-08-01', '2026-08-31');

        $this->assertSame('pendiente', $comision->fresh()->estado);
        $this->assertNull($comision->fresh()->flujo_caja_id);
        $this->assertFalse((bool) $flujo->fresh()->es_conciliado);

        $n = app(ConciliacionAutoMatcher::class)->conciliar(
            ConciliacionLinea::query()->get(), '2026-08-01', '2026-08-31', 'MERCANTIL', 'GRUPO JRZ', 500
        );

        $this->assertSame(1, $n);
        $this->assertSame('conciliado', $transferencia->fresh()->estado);
        $this->assertSame((int) $flujo->id, (int) $transferencia->fresh()->flujo_caja_id);
        $this->assertSame('pendiente', $comision->fresh()->estado);
    }

    public function test_comision_op_cred_dirt_se_libera_aunque_el_egreso_no_tenga_comision_registrada(): void
    {
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'Mercantil', 'titular' => 'JRZ', 'fecha' => '2026-08-06',
            'monto_bs' => 15000.00, 'referencia' => '47900005555', 'motivo' => 'PAGO', 'oculto' => false,
        ]);
        $flujo->forceFill(['es_conciliado' => true])->save();
        $comision = ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-06',
            'descripcion' => 'OP.CRED.DIRT. CLTE-CLTE PJ -VIA ELECTRON', 'referencia' => '43700005555',
            'monto' => 4.10, 'estado' => 'conciliado', 'tipo' => 'cargo', 'flujo_caja_id' => $flujo->id,
        ]);
        $transferencia = ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-06',
            'descripcion' => 'TRANSF ENTRE CTAS BM PERS JURIDICA', 'referencia' => '47900005555',
            'monto' => 15000.00, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);

        $reparar = new \ReflectionMethod(\App\Http\Controllers\FinanzasController::class, 'desvincularComisionEmparejadaConEgreso');
        $reparar->invoke(app(\App\Http\Controllers\FinanzasController::class), app(\App\Services\BankReconciliationMatcher::class), '2026-08-01', '2026-08-31');

        $this->assertSame('pendiente', $comision->fresh()->estado);
        $this->assertFalse((bool) $flujo->fresh()->es_conciliado);

        $n = app(ConciliacionAutoMatcher::class)->conciliar(
            ConciliacionLinea::query()->get(), '2026-08-01', '2026-08-31', 'MERCANTIL', 'GRUPO JRZ', 500
        );

        $this->assertSame(1, $n);
        $this->assertSame((int) $flujo->id, (int) $transferencia->fresh()->flujo_caja_id);
        $this->assertNull($comision->fresh()->flujo_caja_id);
    }

    public function test_comision_en_bs_igual_a_los_usd_del_egreso_no_se_queda_con_el(): void
    {
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'Mercantil', 'titular' => 'JRZ', 'fecha' => '2026-08-27',
            'monto_bs' => 11078.48, 'monto_usd' => 14.00, 'comision' => 33.23, 'referencia' => '20407456706',
            'motivo' => 'ROLINERAS DELANTERAS DEL MOTO CARGUERO', 'oculto' => false,
        ]);
        $flujo->forceFill(['es_conciliado' => true])->save();
        $comisionAjena = ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-26',
            'descripcion' => 'COMISION POR TRANSFERENCIA DE FONDOS', 'referencia' => '10707052087',
            'monto' => 14.00, 'estado' => 'conciliado', 'tipo' => 'cargo', 'flujo_caja_id' => $flujo->id,
        ]);
        $transferencia = ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-27',
            'descripcion' => 'CREDITO INMEDIATO CAMARA DE COMPENSACION', 'referencia' => '20407456706',
            'monto' => 11078.48, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);

        $matcher = app(\App\Services\BankReconciliationMatcher::class);
        $controller = app(\App\Http\Controllers\FinanzasController::class);
        foreach (['desvincularComisionEmparejadaConEgreso', 'reasignarEgresoALaReferenciaExacta'] as $metodo) {
            (new \ReflectionMethod($controller, $metodo))->invoke($controller, $matcher, '2026-08-01', '2026-08-31');
        }
        app(ConciliacionAutoMatcher::class)->conciliar(
            ConciliacionLinea::query()->get(), '2026-08-01', '2026-08-31', 'MERCANTIL', 'GRUPO JRZ', 500
        );

        $this->assertNull($comisionAjena->fresh()->flujo_caja_id);
        $this->assertSame((int) $flujo->id, (int) $transferencia->fresh()->flujo_caja_id);
        $this->assertSame('conciliado', $transferencia->fresh()->estado);
    }

    public function test_egreso_pasa_al_reenvio_con_su_referencia_y_el_intento_devuelto_se_une_a_la_devolucion(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $base = ['banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-21', 'monto' => 49205.88];
        $egreso = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'Mercantil', 'titular' => 'JRZ', 'fecha' => '2026-08-21',
            'monto_bs' => 49205.88, 'referencia' => '20403735674', 'motivo' => 'FLETE DE MERCANCIA', 'oculto' => false,
        ]);
        $egreso->forceFill(['es_conciliado' => true])->save();
        $intento = ConciliacionLinea::create($base + [
            'descripcion' => 'CREDITO INMEDIATO CAMARA DE COMPENSACION', 'referencia' => '20403730500',
            'estado' => 'conciliado', 'tipo' => 'cargo', 'flujo_caja_id' => $egreso->id,
        ]);
        $reenvio = ConciliacionLinea::create($base + [
            'descripcion' => 'CREDITO INMEDIATO CAMARA DE COMPENSACION', 'referencia' => '20403735674',
            'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $devolucion = ConciliacionLinea::create($base + [
            'descripcion' => 'DEVOLUCION CREDITO INMEDIATO', 'referencia' => '17103730500',
            'estado' => 'pendiente', 'tipo' => 'abono',
        ]);

        $reparar = new \ReflectionMethod(\App\Http\Controllers\FinanzasController::class, 'reasignarEgresoALaReferenciaExacta');
        $reparar->invoke(app(\App\Http\Controllers\FinanzasController::class), app(\App\Services\BankReconciliationMatcher::class), '2026-08-01', '2026-08-31');

        $this->assertSame((int) $egreso->id, (int) $reenvio->fresh()->flujo_caja_id);
        $this->assertSame('conciliado', $reenvio->fresh()->estado);
        $this->assertSame('pendiente', $intento->fresh()->estado);
        $this->assertNull($intento->fresh()->flujo_caja_id);

        // El flete tiene la referencia del reenvío, no la del intento devuelto: no se ofrece para anular.
        $this->actingAs($user)
            ->getJson(route('finanzas.conciliaciones.devolucion.egresos', ['linea_id' => $devolucion->id, 'contraparte_id' => $intento->id]))
            ->assertOk()
            ->assertJsonCount(0, 'egresos');

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.devolucion'), [
                'linea_id' => $devolucion->id,
                'contraparte_id' => $intento->id,
                'anular' => [$egreso->id],
            ])
            ->assertSessionHasErrors('devolucion');
        $this->assertFalse((bool) $egreso->fresh()->anulado);

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.devolucion'), [
                'linea_id' => $devolucion->id,
                'contraparte_id' => $intento->id,
            ])
            ->assertSessionHas('success');

        $this->assertSame((int) $devolucion->id, (int) $intento->fresh()->devolucion_linea_id);
        $this->assertFalse((bool) $egreso->fresh()->anulado);
        $this->assertSame(0, ConciliacionLinea::query()->where('estado', 'pendiente')->count());
    }

    public function test_el_emparejamiento_prefiere_la_linea_con_la_referencia_del_egreso(): void
    {
        $base = ['banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-21', 'monto' => 49205.88,
            'descripcion' => 'CREDITO INMEDIATO CAMARA DE COMPENSACION', 'estado' => 'pendiente', 'tipo' => 'cargo'];
        $egreso = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'Mercantil', 'titular' => 'JRZ', 'fecha' => '2026-08-21',
            'monto_bs' => 49205.88, 'referencia' => '20403735674', 'motivo' => 'FLETE', 'oculto' => false,
        ]);
        // La del intento devuelto tiene id mayor: antes se procesaba primero y se quedaba con el egreso.
        $reenvio = ConciliacionLinea::create($base + ['referencia' => '20403735674']);
        $intento = ConciliacionLinea::create($base + ['referencia' => '20403730500']);

        app(ConciliacionAutoMatcher::class)->conciliar(
            ConciliacionLinea::query()->get(), '2026-08-01', '2026-08-31', 'MERCANTIL', 'GRUPO JRZ', 500
        );

        $this->assertSame((int) $egreso->id, (int) $reenvio->fresh()->flujo_caja_id);
        $this->assertSame('pendiente', $intento->fresh()->estado);
    }

    public function test_devolucion_une_cargo_y_abono_y_anula_el_egreso(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $cargo = ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-05',
            'descripcion' => 'CREDITO INMEDIATO CAMARA DE COMPENSACION', 'referencia' => '20493920369',
            'monto' => 90619.20, 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $abono = ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-05',
            'descripcion' => 'DEVOLUCION CREDITO INMEDIATO', 'referencia' => '17193858492',
            'monto' => 90619.20, 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);
        $egreso = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'Mercantil', 'titular' => 'JRZ', 'fecha' => '2026-08-05',
            'monto_bs' => 90619.20, 'referencia' => '20493920369', 'motivo' => 'PAGO PROVEEDOR', 'oculto' => false,
        ]);
        $otraCuenta = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'Banesco', 'titular' => 'DORAL', 'fecha' => '2026-08-05',
            'monto_bs' => 90619.20, 'referencia' => '20493920369', 'motivo' => 'OTRA CUENTA', 'oculto' => false,
        ]);
        FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'Mercantil', 'titular' => 'JRZ', 'fecha' => '2026-08-05',
            'monto_bs' => 90619.20, 'referencia' => '20499999999', 'motivo' => 'MISMO MONTO, OTRA REFERENCIA', 'oculto' => false,
        ]);

        // Se puede empezar desde el abono: el sistema identifica cuál es el cargo.
        $this->actingAs($user)
            ->getJson(route('finanzas.conciliaciones.devolucion.egresos', ['linea_id' => $abono->id, 'contraparte_id' => $cargo->id]))
            ->assertOk()
            ->assertJsonCount(1, 'egresos')
            ->assertJsonPath('egresos.0.id', $egreso->id)
            ->assertJsonPath('egresos.0.conciliado_con', null);

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.devolucion'), [
                'linea_id' => $abono->id,
                'contraparte_id' => $cargo->id,
                'anular' => [$egreso->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('conciliado', $cargo->fresh()->estado);
        $this->assertSame('conciliado', $abono->fresh()->estado);
        $this->assertSame((int) $abono->id, (int) $cargo->fresh()->devolucion_linea_id);
        $this->assertSame((int) $cargo->id, (int) $abono->fresh()->devolucion_linea_id);
        $egreso->refresh();
        $this->assertTrue((bool) $egreso->anulado);
        $this->assertTrue((bool) $egreso->oculto);
        $this->assertStringContainsString('20493920369', (string) $egreso->anulado_motivo);
        $this->assertFalse((bool) $otraCuenta->fresh()->anulado);
    }

    public function test_cargo_y_devolucion_no_aparecen_en_el_pdf(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $base = ['banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-12', 'monto' => 83200.00, 'estado' => 'conciliado'];
        $cargo = ConciliacionLinea::create($base + ['descripcion' => 'CREDITO INMEDIATO CAMARA DE COMPENSACION', 'referencia' => '20498030130', 'tipo' => 'cargo']);
        $abono = ConciliacionLinea::create($base + ['descripcion' => 'DEVOLUCION CREDITO INMEDIATO', 'referencia' => '17198030130', 'tipo' => 'abono']);
        $cargo->forceFill(['devolucion_linea_id' => $abono->id])->save();
        $abono->forceFill(['devolucion_linea_id' => $cargo->id])->save();
        ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-12', 'monto' => 500,
            'descripcion' => 'TRANSF ENTRE CTAS BM PERS JURIDICA', 'referencia' => '47900011111', 'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);

        $capturado = null;
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('pdf'));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()
            ->andReturnUsing(function ($vista, $datos) use (&$capturado, $pdf) {
                $capturado = $datos['data'];

                return $pdf;
            });

        $this->actingAs($user)
            ->get(route('finanzas.conciliaciones.reporte-banco', [
                'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha_desde' => '2026-08-01', 'fecha_hasta' => '2026-08-31',
            ]))
            ->assertOk();

        $this->assertCount(0, $capturado['conciliados']);
        $this->assertCount(1, $capturado['sin_registrar']);
        $this->assertSame('47900011111', $capturado['sin_registrar'][0]['referencia']);
    }

    public function test_devolucion_rechaza_montos_distintos_o_mismo_tipo(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $base = ['banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-08-05', 'estado' => 'pendiente'];
        $cargo = ConciliacionLinea::create($base + ['descripcion' => 'CARGO', 'referencia' => '1', 'monto' => 100, 'tipo' => 'cargo']);
        $abonoDistinto = ConciliacionLinea::create($base + ['descripcion' => 'DEV', 'referencia' => '2', 'monto' => 99, 'tipo' => 'abono']);
        $otroCargo = ConciliacionLinea::create($base + ['descripcion' => 'CARGO 2', 'referencia' => '3', 'monto' => 100, 'tipo' => 'cargo']);

        foreach ([$abonoDistinto, $otroCargo] as $contraparte) {
            $this->actingAs($user)
                ->from(route('finanzas.conciliaciones'))
                ->post(route('finanzas.conciliaciones.devolucion'), ['linea_id' => $cargo->id, 'contraparte_id' => $contraparte->id])
                ->assertSessionHasErrors('devolucion');
        }

        $this->assertSame(3, ConciliacionLinea::query()->where('estado', 'pendiente')->count());
    }

    public function test_estado_importacion_solo_visible_al_dueno_o_admin(): void
    {
        $dueno = $this->makeUser(User::ROLE_CONTABILIDAD);
        $otro = $this->makeUser(User::ROLE_CONTABILIDAD);
        $imp = ConciliacionImportacion::create([
            'user_id' => $dueno->id,
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'archivo_nombre' => 'a.xlsx',
            'extension' => 'xlsx',
            'estado' => ConciliacionImportacion::COMPLETADA,
        ]);

        $this->actingAs($dueno)
            ->getJson(route('finanzas.conciliaciones.importaciones.show', $imp))
            ->assertOk()
            ->assertJsonPath('estado', ConciliacionImportacion::COMPLETADA);

        $this->actingAs($otro)
            ->getJson(route('finanzas.conciliaciones.importaciones.show', $imp))
            ->assertForbidden();
    }

    public function test_job_procesa_archivo_y_actualiza_contadores(): void
    {
        $path = $this->xlsxBanesco([
            ['03/08/2026', '7001', 'PAGO X', '-200,00', '0'],
            ['04/08/2026', '7002', 'ABONO Y', '50,00', '0'],
        ]);
        $imp = ConciliacionImportacion::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'archivo_nombre' => 'bench.xlsx',
            'archivo_path' => 'bench.xlsx',
            'extension' => 'xlsx',
            'estado' => ConciliacionImportacion::PENDIENTE,
        ]);

        ProcesarImportacionExtracto::dispatchSync($imp->id);
        $imp->refresh();

        $this->assertTrue($imp->terminada());
        $this->assertSame(2, $imp->filas_importadas);
        $this->assertSame(2, ConciliacionLinea::query()->count());
        $this->assertNull($imp->archivo_path);
    }

    public function test_procesamiento_por_lotes_de_6000_sin_crecimiento_descontrolado(): void
    {
        $rows = [];
        for ($i = 1; $i <= 6000; $i++) {
            $dia = str_pad((string) (1 + ($i % 28)), 2, '0', STR_PAD_LEFT);
            $signo = $i % 2 === 0 ? '-' : '';
            $rows[] = ["{$dia}/08/2026", (string) (800000000 + $i), "MOV {$i}", "{$signo}10,00", '0'];
        }
        $path = $this->xlsxBanesco($rows);
        $imp = $this->importar($path);

        $this->assertSame(6000, $imp->filas_importadas);
        $this->assertSame(0, $imp->filas_duplicadas);
        $this->assertSame(ConciliacionImportacion::COMPLETADA, $imp->estado);
        $this->assertSame(6000, ConciliacionLinea::query()->count());
    }

    /** @param list<list<string|int|float|null>> $filas */
    private function xlsxBanesco(array $filas, string $nombre = 'bench.xlsx'): string
    {
        $sheet = array_merge([['Fecha', 'Referencia', 'Descripción', 'Monto', 'Balance']], $filas);
        Storage::disk('local')->put($nombre, SimpleXlsxWriter::toString(['Hoja1' => $sheet]));

        return $nombre;
    }

    private function importar(string $pathRelativo): ConciliacionImportacion
    {
        $imp = ConciliacionImportacion::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'archivo_nombre' => basename($pathRelativo),
            'archivo_path' => $pathRelativo,
            'extension' => 'xlsx',
            'estado' => ConciliacionImportacion::PENDIENTE,
        ]);

        return app(ExtractoImporter::class)->importarArchivo($imp);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'Contabilidad',
            'email' => 'contab-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => $role,
        ]);
    }

    private function crearTablasConciliacion(): void
    {
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
                $table->boolean('forzar_comision')->default(false);
                $table->string('tipo', 32)->nullable();
                $table->string('session_id')->nullable();
                $table->unsignedBigInteger('flujo_caja_id')->nullable();
                $table->unsignedBigInteger('tesoreria_ingreso_id')->nullable();
                $table->unsignedBigInteger('compra_divisa_id')->nullable();
                $table->unsignedBigInteger('importacion_id')->nullable();
                $table->unsignedInteger('fila_origen')->nullable();
                $table->unsignedBigInteger('devolucion_linea_id')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('conciliacion_lineas', function (Blueprint $table) {
                if (! Schema::hasColumn('conciliacion_lineas', 'importacion_id')) {
                    $table->unsignedBigInteger('importacion_id')->nullable();
                    $table->unsignedInteger('fila_origen')->nullable();
                }
                if (! Schema::hasColumn('conciliacion_lineas', 'forzar_comision')) {
                    $table->boolean('forzar_comision')->default(false);
                }
                if (! Schema::hasColumn('conciliacion_lineas', 'devolucion_linea_id')) {
                    $table->unsignedBigInteger('devolucion_linea_id')->nullable();
                }
            });
        }

        if (! Schema::hasTable('conciliacion_importaciones')) {
            Schema::create('conciliacion_importaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('session_id')->nullable();
                $table->string('banco', 80);
                $table->string('titular', 120);
                $table->string('archivo_nombre', 255);
                $table->string('archivo_path', 500)->nullable();
                $table->string('archivo_hash', 64)->nullable();
                $table->string('extension', 10);
                $table->string('estado', 30)->default('pendiente');
                $table->unsignedInteger('filas_leidas')->default(0);
                $table->unsignedInteger('filas_importadas')->default(0);
                $table->unsignedInteger('filas_duplicadas')->default(0);
                $table->unsignedInteger('filas_rechazadas')->default(0);
                $table->unsignedInteger('filas_conciliadas')->default(0);
                $table->unsignedInteger('filas_pendientes')->default(0);
                $table->unsignedInteger('ultima_fila')->default(0);
                $table->unsignedInteger('intentos')->default(0);
                $table->json('rechazos')->nullable();
                $table->text('mensaje')->nullable();
                $table->timestamp('iniciado_at')->nullable();
                $table->timestamp('finalizado_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('flujo_cajas')) {
            Schema::create('flujo_cajas', function (Blueprint $table) {
                $table->id();
                $table->string('tipo')->nullable();
                $table->date('fecha')->nullable();
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->string('banco_receptor')->nullable();
                $table->string('titular_receptor')->nullable();
                $table->string('categoria_egreso')->nullable();
                $table->string('tipo_gasto')->nullable();
                $table->string('concepto')->nullable();
                $table->string('motivo')->nullable();
                $table->string('referencia')->nullable();
                $table->decimal('monto_bs', 15, 2)->nullable();
                $table->decimal('monto_usd', 15, 2)->nullable();
                $table->decimal('comision', 15, 2)->nullable();
                $table->boolean('es_conciliado')->default(false);
                $table->boolean('oculto')->default(false);
                $table->boolean('anulado')->default(false);
                $table->string('anulado_motivo')->nullable();
                $table->timestamp('anulado_at')->nullable();
                $table->timestamps();
            });
        } else {
            $faltantes = array_values(array_filter([
                'banco', 'titular', 'banco_receptor', 'titular_receptor',
                'categoria_egreso', 'concepto', 'motivo', 'referencia',
                'es_conciliado', 'oculto', 'comision', 'anulado', 'anulado_motivo', 'anulado_at',
            ], fn (string $col) => ! Schema::hasColumn('flujo_cajas', $col)));
            if ($faltantes !== []) {
                Schema::table('flujo_cajas', function (Blueprint $table) use ($faltantes) {
                    foreach ($faltantes as $col) {
                        if (in_array($col, ['es_conciliado', 'oculto', 'anulado'], true)) {
                            $table->boolean($col)->default(false);
                        } elseif ($col === 'comision') {
                            $table->decimal('comision', 15, 2)->nullable();
                        } elseif ($col === 'anulado_at') {
                            $table->timestamp('anulado_at')->nullable();
                        } else {
                            $table->string($col)->nullable();
                        }
                    }
                });
            }
        }

        if (! Schema::hasTable('compra_divisas')) {
            Schema::create('compra_divisas', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->string('referencia')->nullable();
                $table->string('concepto')->nullable();
                $table->string('motivo')->nullable();
                $table->decimal('monto_bs', 18, 2)->default(0);
                $table->boolean('es_conciliado')->default(false);
                $table->timestamps();
            });
        }
    }
}
