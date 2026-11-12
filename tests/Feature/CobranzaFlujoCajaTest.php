<?php

namespace Tests\Feature;

use App\Models\CobranzaMovimiento;
use App\Models\ConciliacionLinea;
use App\Models\FlujoCaja;
use App\Models\User;
use App\Services\BcvRateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class CobranzaFlujoCajaTest extends TestCase
{
    use CreatesNominaSchema;

    private const CUENTA_BANCO = 'Banesco|Grupo JRZ|BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO';

    private const CUENTA_CRUCE = 'BANCO CRUCE COBRANZA|COBRANZA|CUENTAS DE CRUCE';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->crearTablas();
        $this->mock(BcvRateService::class, fn ($m) => $m->shouldReceive('getRateForToday')->andReturn(40.0));
    }

    public function test_ingreso_en_bs_calcula_divisas_y_va_oculto_a_la_conciliacion(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('cobranza.flujo.index'))->assertOk()->assertSee('Flujo de caja · Cobranza');

        $this->post(route('cobranza.flujo.store'), [
            'tipo' => 'ingreso', 'fecha' => '2026-11-10', 'ambito' => 'contratos', 'cuenta' => self::CUENTA_BANCO,
            'monto_bs' => 4000, 'tasa_cambio' => 40, 'referencia' => '123456',
            'concepto' => 'Cuota contrato C-010', 'contraparte' => 'Juan Pérez',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $mov = CobranzaMovimiento::query()->firstOrFail();
        $this->assertSame('100.00', $mov->monto_usd);
        $this->assertSame('contratos', $mov->ambito);
        $this->assertNotNull($mov->flujo_caja_id);

        $espejo = FlujoCaja::query()->findOrFail($mov->flujo_caja_id);
        $this->assertSame('ingreso', $espejo->tipo);
        $this->assertTrue((bool) $espejo->oculto);
        $this->assertSame(FlujoCaja::ORIGEN_COBRANZA, $espejo->origen);
        $this->assertSame('4000.00', number_format((float) $espejo->monto_bs, 2, '.', ''));
        $this->assertStringContainsString('Cobranza · Contratos', (string) $espejo->motivo);

        // Editar el monto actualiza la copia.
        $this->put(route('cobranza.flujo.update', $mov), [
            'fecha' => '2026-11-10', 'ambito' => 'contratos', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 120, 'tasa_cambio' => 40, 'concepto' => 'Cuota contrato C-010',
        ])->assertSessionHasNoErrors();
        $this->assertSame('4800.00', number_format((float) $espejo->fresh()->monto_bs, 2, '.', ''));

        // Conciliado: ya no se cambia el monto ni se elimina.
        $espejo->forceFill(['es_conciliado' => true])->save();
        $this->put(route('cobranza.flujo.update', $mov), [
            'fecha' => '2026-11-10', 'ambito' => 'contratos', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 150, 'tasa_cambio' => 40, 'concepto' => 'Cuota contrato C-010',
        ])->assertSessionHasErrors('movimiento');
        $this->put(route('cobranza.flujo.update', $mov), [
            'fecha' => '2026-11-10', 'ambito' => 'patrimonial', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 120, 'tasa_cambio' => 40, 'concepto' => 'Concepto corregido',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Concepto corregido', $mov->fresh()->concepto);
        $this->delete(route('cobranza.flujo.destroy', $mov))->assertSessionHasErrors('movimiento');
        $this->assertNotNull($mov->fresh());
    }

    public function test_cruce_registra_ingreso_y_egreso_enlazados_sin_pasar_por_el_banco(): void
    {
        $user = $this->makeUser();

        // Un cruce en un banco real se rechaza.
        $this->actingAs($user)->post(route('cobranza.flujo.store'), [
            'tipo' => 'ingreso', 'fecha' => '2026-11-10', 'ambito' => 'cobranza', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 50, 'tasa_cambio' => 40, 'concepto' => 'Abono factura 777',
            'cruce' => 1, 'cruce_tipo_gasto' => '087 - PAGO PROVEEDORES', 'cruce_destino' => 'Pago proveedor X',
        ])->assertSessionHasErrors('cuenta');

        $this->post(route('cobranza.flujo.store'), [
            'tipo' => 'ingreso', 'fecha' => '2026-11-10', 'ambito' => 'cobranza', 'cuenta' => self::CUENTA_CRUCE,
            'monto_usd' => 50, 'tasa_cambio' => 40, 'concepto' => 'Abono factura 777', 'contraparte' => 'Cliente A',
            'cruce' => 1, 'cruce_tipo_gasto' => '087 - PAGO PROVEEDORES', 'cruce_destino' => 'Pago proveedor X', 'cruce_ambito' => 'general', 'cruce_beneficiario' => 'Proveedor X',
        ])->assertSessionHasNoErrors();

        $movs = CobranzaMovimiento::query()->orderBy('id')->get();
        $this->assertCount(2, $movs);
        [$ingreso, $egreso] = [$movs[0], $movs[1]];
        $this->assertSame('ingreso', $ingreso->tipo);
        $this->assertSame('egreso', $egreso->tipo);
        $this->assertSame($ingreso->cruce_id, $egreso->cruce_id);
        $this->assertNotNull($ingreso->cruce_id);
        $this->assertSame('Pago proveedor X', $egreso->concepto);
        $this->assertSame('general', $egreso->ambito);
        $this->assertSame('2000.00', $egreso->monto_bs);
        $this->assertNull($ingreso->flujo_caja_id);
        $this->assertSame(0, FlujoCaja::query()->count());

        $this->get(route('cobranza.flujo.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertOk()->assertSee('Se fue en: Pago proveedor X')->assertSee('Vino de: Abono factura 777');

        // Editar una mitad cambia el monto de las dos.
        $this->put(route('cobranza.flujo.update', $ingreso), [
            'fecha' => '2026-11-11', 'ambito' => 'cobranza', 'cuenta' => self::CUENTA_CRUCE,
            'monto_usd' => 60, 'tasa_cambio' => 40, 'concepto' => 'Abono factura 777',
        ])->assertSessionHasNoErrors();
        $this->assertSame('60.00', $egreso->fresh()->monto_usd);
        $this->assertSame('2026-11-11', $egreso->fresh()->fecha->toDateString());

        // Eliminar una mitad elimina el cruce completo.
        $this->delete(route('cobranza.flujo.destroy', $egreso))->assertSessionHasNoErrors();
        $this->assertSame(0, CobranzaMovimiento::query()->count());
    }

    public function test_eliminar_movimiento_borra_su_copia_y_libera_la_linea_del_banco(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('cobranza.flujo.store'), [
            'tipo' => 'egreso', 'tipo_gasto' => '036 - MANT. Y REP.', 'fecha' => '2026-11-10', 'ambito' => 'patrimonial', 'cuenta' => self::CUENTA_BANCO,
            'monto_bs' => 800, 'tasa_cambio' => 40, 'concepto' => 'Reparación local 3',
        ])->assertSessionHasNoErrors();
        $mov = CobranzaMovimiento::query()->firstOrFail();
        $this->assertSame('egreso_realizado', FlujoCaja::query()->find($mov->flujo_caja_id)->categoria_egreso);

        $this->delete(route('cobranza.flujo.destroy', $mov))->assertSessionHasNoErrors();
        $this->assertSame(0, FlujoCaja::query()->count());
        $this->assertSame(0, CobranzaMovimiento::query()->count());
    }

    public function test_abonos_desde_la_web_bajan_la_deuda_y_la_marcan_pagada(): void
    {
        $user = $this->makeUser();
        $this->crearTablasCobranza();
        $this->filaHistorial(['id_documento' => '555', 'numero_documento' => 'F-555', 'saldo' => 100, 'monto_neto' => 100]);

        $this->actingAs($user)->get(route('cobranza.flujo.index', ['factura_sede' => 'DORAL', 'factura_doc' => '555']))
            ->assertOk()->assertSee('FAC F-555')->assertSee('Cliente Deudor');

        // Buscador: encuentra la factura por cliente o número, con su deuda.
        $this->getJson(route('cobranza.flujo.facturas', ['q' => 'deudor']))
            ->assertOk()
            ->assertJsonPath('facturas.0.numero', 'FAC F-555')
            ->assertJsonPath('facturas.0.saldo', 100);

        $abonar = fn (float $usd) => $this->post(route('cobranza.flujo.store'), [
            'tipo' => 'ingreso', 'fecha' => '2026-11-10', 'ambito' => 'cobranza', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => $usd, 'tasa_cambio' => 40, 'concepto' => 'Abono F-555',
            'factura_sede' => 'DORAL', 'factura_doc' => '555',
        ]);

        $abonar(40)->assertSessionHasNoErrors();
        $factura = \App\Models\CobranzaFactura::query()->firstOrFail();
        $this->getJson(route('cobranza.flujo.facturas', ['q' => 'F-555']))->assertJsonPath('facturas.0.saldo', 60);
        $this->get(route('cobranza.flujo.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertSee('Cliente Deudor (C-1)')->assertSee('FAC F-555 · DORAL');
        $this->assertSame('100.00', $factura->saldo_inicial);
        $servicio = app(\App\Services\Cobranza\CobranzaFacturaService::class);
        $this->assertSame(60.0, $servicio->saldo($factura));

        // La lista de Cobranza ve el saldo bajado.
        $fila = (object) ['sede_nombre' => 'DORAL', 'id_documento' => '555', 'saldo' => 100.0];
        $servicio->aplicar([$fila]);
        $this->assertSame(60.0, $fila->saldo);
        $this->assertSame(40.0, $fila->abonado_web);

        $abonar(70)->assertSessionHasErrors('monto_usd');
        $abonar(60)->assertSessionHasNoErrors();
        $this->assertTrue($factura->fresh()->pagada_web);
        $this->assertTrue(\DB::connection('pgsql')->table('cobranzas_pagadas_manualmente')->where('id_documento', 'F-555')->exists());
        $abonar(1)->assertSessionHasErrors('vinculo');

        // Borrar un abono la vuelve a poner en la lista.
        $ultimo = CobranzaMovimiento::query()->latest('id')->firstOrFail();
        $this->delete(route('cobranza.flujo.destroy', $ultimo))->assertSessionHasNoErrors();
        $this->assertFalse($factura->fresh()->pagada_web);
        $this->assertFalse(\DB::connection('pgsql')->table('cobranzas_pagadas_manualmente')->where('id_documento', 'F-555')->exists());
    }

    public function test_sube_el_comprobante_al_registrar_y_al_editar(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([], 200)]);
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('cobranza.flujo.store'), [
            'tipo' => 'egreso', 'tipo_gasto' => '087 - PAGO PROVEEDORES', 'fecha' => '2026-11-10', 'ambito' => 'general', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 20, 'tasa_cambio' => 40, 'concepto' => 'Pago proveedor',
            'comprobantes' => [\Illuminate\Http\UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertSessionHasErrors('comprobantes.0');
        $this->assertSame(0, CobranzaMovimiento::query()->count());

        $this->post(route('cobranza.flujo.store'), [
            'tipo' => 'egreso', 'tipo_gasto' => '087 - PAGO PROVEEDORES', 'fecha' => '2026-11-10', 'ambito' => 'general', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 20, 'tasa_cambio' => 40, 'concepto' => 'Pago proveedor',
            'comprobantes' => [\Illuminate\Http\UploadedFile::fake()->image('pago.jpg')],
        ])->assertSessionHasNoErrors();
        $mov = CobranzaMovimiento::query()->firstOrFail();
        $this->assertCount(1, $mov->comprobantes);
        $primero = $mov->comprobantes[0];
        $this->get(route('cobranza.flujo.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertOk()->assertSee('📎 Comprobante');

        // Al editar: se quita el que había y se agrega otro.
        $this->post(route('cobranza.flujo.update', $mov), [
            '_method' => 'PUT', 'fecha' => '2026-11-10', 'ambito' => 'general', 'cuenta' => self::CUENTA_BANCO, 'tipo_gasto' => '087 - PAGO PROVEEDORES',
            'monto_usd' => 20, 'tasa_cambio' => 40, 'concepto' => 'Pago proveedor',
            'comprobantes_quitar' => [$primero],
            'comprobantes' => [\Illuminate\Http\UploadedFile::fake()->create('transferencia.pdf', 30, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $comprobantes = $mov->fresh()->comprobantes;
        $this->assertCount(1, $comprobantes);
        $this->assertNotSame($primero, $comprobantes[0]);
        $this->assertStringEndsWith('.pdf', $comprobantes[0]);
    }

    public function test_cada_ingreso_genera_su_recibo_de_pago(): void
    {
        $user = $this->makeUser();
        $this->crearTablasCobranza();
        $this->filaHistorial(['id_documento' => '555', 'numero_documento' => '155564', 'saldo' => 100, 'monto_neto' => 100]);

        $this->actingAs($user)->post(route('cobranza.flujo.store'), [
            'tipo' => 'ingreso', 'fecha' => '2026-11-07', 'ambito' => 'cobranza', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 50, 'tasa_cambio' => 896.12, 'concepto' => 'Abono 155564', 'forma_pago' => 'EFECTIVO',
            'factura_sede' => 'DORAL', 'factura_doc' => '555',
        ])->assertSessionHasNoErrors()->assertSessionHas('recibo_id');
        $mov = CobranzaMovimiento::query()->firstOrFail();
        $this->assertSame('EFECTIVO', $mov->forma_pago);

        $datos = app(\App\Services\Cobranza\CobranzaReciboService::class)->datos($mov->load('usuario'));
        $this->assertSame('J-40172229-6', $datos['empresa']['rif']);
        $this->assertSame('Cliente Deudor', $datos['cliente']);
        $this->assertSame('155564', $datos['afecta']);
        $this->assertSame('EFECTIVO', $datos['metodo']);
        $this->assertSame(50.0, $datos['abonado_usd']);
        $this->assertSame(44806.0, $datos['abonado_bs']);
        $this->assertSame(50.0, $datos['por_pagar_usd']);

        $this->get(route('cobranza.flujo.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertOk()->assertSee(route('cobranza.flujo.recibo', $mov), false);
        $pdf = $this->get(route('cobranza.flujo.recibo', $mov));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $excel = $this->get(route('cobranza.flujo.excel', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']));
        $excel->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="flujo_caja_cobranza_2026-11-01_2026-11-30.xlsx"');
        $hojas = \Shuchkin\SimpleXLSX::parseData($excel->getContent());
        $this->assertNotFalse($hojas);
        $movs = $hojas->rows(0);
        $this->assertSame('Ingreso', $movs[1][1]);
        $this->assertSame('Cliente Deudor (C-1)', $movs[1][6]);
        $this->assertStringContainsString('155564', $movs[1][7]);
        $this->assertSame('Abono 155564', $movs[1][8]);
        $this->assertEquals(50, $movs[1][14]);
        $this->assertSame('Ingresos', $hojas->rows(1)[6][0]);
        $this->assertEquals(50, $hojas->rows(1)[6][1]);
    }

    public function test_pago_de_cuota_desde_el_flujo_y_desde_contratos(): void
    {
        $user = $this->makeUser();
        foreach ([
            '2026_07_21_200000_create_contratos_tables', '2026_07_22_151314_add_abono_capital_to_contrato_cuotas_table',
            '2026_07_25_162758_add_liquidado_columns_to_contratos_table', '2026_08_07_205900_add_pago_fields_to_contrato_cuotas_and_seguimientos',
        ] as $m) {
            (require database_path("migrations/{$m}.php"))->up();
        }
        $contrato = \App\Models\Contrato::create([
            'numero_contrato' => 'C-010', 'cliente' => 'Juan Pérez', 'capital' => 1000, 'interes_porcentaje' => 0.1,
            'cuota_fija' => 100, 'total_a_pagar' => 1000, 'fecha_inicio' => '2026-10-01', 'activo' => true,
        ]);
        $cuota = \App\Models\ContratoCuota::create([
            'contrato_id' => $contrato->id, 'numero_cuota' => 1, 'fecha_vencimiento' => '2026-11-01', 'monto' => 100, 'saldo' => 100, 'estatus' => 'vencido',
        ]);

        $this->actingAs($user)->get(route('cobranza.flujo.index'))->assertOk()->assertSee('C-010')->assertSee('Juan P');

        // Desde el flujo: paga la cuota en Contratos.
        $this->post(route('cobranza.flujo.store'), [
            'tipo' => 'ingreso', 'fecha' => '2026-11-10', 'ambito' => 'contratos', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 150, 'tasa_cambio' => 40, 'concepto' => 'Cuota 1 C-010',
            'vinculo' => CobranzaMovimiento::VINCULO_CUOTA.':'.$cuota->id, 'abono_capital' => 50,
        ])->assertSessionHasNoErrors();
        $cuota->refresh();
        $this->assertSame('pagado', $cuota->estatus);
        $this->assertSame(100.0, (float) $cuota->monto_pagado);
        $this->assertSame(50.0, (float) $cuota->abono_capital);
        $this->assertSame(950.0, (float) $contrato->fresh()->total_a_pagar);
        $mov = CobranzaMovimiento::query()->firstOrFail();
        $this->assertSame(CobranzaMovimiento::VINCULO_CUOTA, $mov->vinculo_tipo);
        $this->assertNotNull($mov->flujo_caja_id);
        $this->get(route('cobranza.flujo.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertSee('Contrato C-010 · Juan Pérez')->assertSee('Cuota #1 · vence 01/11/2026');

        // El monto se corrige en Contratos, no en el flujo.
        $this->delete(route('cobranza.flujo.destroy', $mov))->assertSessionHasErrors('movimiento');
        $this->put(route('cobranza.flujo.update', $mov), [
            'fecha' => '2026-11-10', 'ambito' => 'contratos', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 120, 'tasa_cambio' => 40, 'concepto' => 'Cuota 1 C-010',
        ])->assertSessionHasErrors('movimiento');

        // Desde Contratos: el pago aparece en el flujo (cruce → cuenta de cruce, sin conciliación).
        $cuota2 = \App\Models\ContratoCuota::create([
            'contrato_id' => $contrato->id, 'numero_cuota' => 2, 'fecha_vencimiento' => '2026-12-01', 'monto' => 95, 'saldo' => 95, 'estatus' => 'pendiente',
        ]);
        $this->post(route('contratos.pagar', $cuota2->id), [
            'forma_pago' => 'CRUCE', 'monto_pagado' => 95, 'fecha_pago' => '2026-11-11',
        ])->assertSessionHasNoErrors();
        $this->assertSame('pagado', $cuota2->fresh()->estatus);
        $mov2 = CobranzaMovimiento::query()->where('vinculo_id', $cuota2->id)->where('vinculo_tipo', CobranzaMovimiento::VINCULO_CUOTA)->firstOrFail();
        $this->assertSame('95.00', $mov2->monto_usd);
        $this->assertSame(CobranzaMovimiento::BANCO_CRUCE_POR_DEFECTO, $mov2->banco);
        $this->assertNull($mov2->flujo_caja_id);
    }

    public function test_patrimonial_gasto_de_propiedad_y_pago_de_alquiler_desde_el_flujo(): void
    {
        $user = $this->makeUser();
        $this->crearTablasPatrimonial();
        $propiedad = \App\Models\Patrimonial\Propiedad::create(['codigo' => 'L-3', 'nombre' => 'Local 3', 'tipo' => 'local', 'estado' => 'alquilado']);
        $alquiler = \App\Models\Patrimonial\Alquiler::create([
            'propiedad_id' => $propiedad->id, 'inquilino_nombre' => 'Inquilino Uno', 'fecha_inicio' => '2026-10-01',
            'canon_mensual' => 300, 'estado' => 'activo',
        ]);
        $pago = \App\Models\Patrimonial\AlquilerPago::create([
            'alquiler_id' => $alquiler->id, 'periodo' => '2026-11', 'fecha_vencimiento' => '2026-11-05', 'monto' => 300, 'monto_pagado' => 0, 'estado' => 'pendiente',
        ]);

        $this->actingAs($user)->get(route('cobranza.flujo.index'))->assertOk()->assertSee('Local 3')->assertSee('Inquilino Uno');

        // Gasto de la propiedad: crea la transacción en Patrimonial.
        $this->post(route('cobranza.flujo.store'), [
            'tipo' => 'egreso', 'tipo_gasto' => '036 - MANT. Y REP.', 'fecha' => '2026-11-10', 'ambito' => 'patrimonial', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 80, 'tasa_cambio' => 40, 'concepto' => 'Pintura del local',
            'vinculo' => 'pat_propiedad:'.$propiedad->id, 'pat_categoria' => 'Mantenimiento',
        ])->assertSessionHasNoErrors();
        $gasto = CobranzaMovimiento::query()->firstOrFail();
        $tx = \App\Models\Patrimonial\PatTransaccion::query()->findOrFail($gasto->vinculo_id);
        $this->assertSame('gasto', $tx->tipo);
        $this->assertSame('Mantenimiento', $tx->categoria);
        $this->assertSame('80.00', $tx->monto);

        // Editar el monto corrige la transacción; borrar el movimiento la borra.
        $this->put(route('cobranza.flujo.update', $gasto), [
            'fecha' => '2026-11-10', 'ambito' => 'patrimonial', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 90, 'tasa_cambio' => 40, 'concepto' => 'Pintura del local',
        ])->assertSessionHasNoErrors();
        $this->assertSame('90.00', $tx->fresh()->monto);
        $this->get(route('cobranza.flujo.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))->assertSee('Local 3 · Gasto');
        $this->delete(route('cobranza.flujo.destroy', $gasto))->assertSessionHasNoErrors();
        $this->assertNull($tx->fresh());

        // Una categoría que no es de gasto se rechaza.
        $this->post(route('cobranza.flujo.store'), [
            'tipo' => 'egreso', 'tipo_gasto' => '036 - MANT. Y REP.', 'fecha' => '2026-11-10', 'ambito' => 'patrimonial', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 10, 'concepto' => 'x', 'vinculo' => 'pat_propiedad:'.$propiedad->id, 'pat_categoria' => 'Alquiler',
        ])->assertSessionHasErrors('pat_categoria');

        // Pago del alquiler desde el flujo.
        $this->post(route('cobranza.flujo.store'), [
            'tipo' => 'ingreso', 'fecha' => '2026-11-10', 'ambito' => 'patrimonial', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 300, 'tasa_cambio' => 40, 'concepto' => 'Alquiler noviembre',
            'vinculo' => CobranzaMovimiento::VINCULO_ALQUILER.':'.$pago->id, 'forma_pago' => 'TRANSFERENCIA_BCV',
        ])->assertSessionHasNoErrors();
        $this->assertSame('pagado', $pago->fresh()->estado);
        $this->assertSame(300.0, (float) $pago->fresh()->monto_pagado);
        $this->get(route('cobranza.flujo.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertSee('Local 3 · Alquiler')->assertSee('Período 2026-11 · inquilino Inquilino Uno');
    }

    public function test_asiento_contable_debe_cuadrar_y_suma_a_los_saldos_por_cuenta(): void
    {
        $user = $this->makeUser();
        $asiento = fn (array $haber) => [
            'fecha' => '2026-11-10', 'descripcion' => 'Traspaso del cruce', 'ambito' => 'general',
            'debe_cuenta' => 'Banesco · Grupo JRZ', 'debe_monto' => 100, 'haber' => $haber,
        ];

        // Los haberes tienen que sumar el debe.
        $this->actingAs($user)->post(route('cobranza.flujo.asientos.store'), $asiento([
            ['cuenta' => 'BANCO CRUCE COBRANZA · COBRANZA', 'monto' => 90],
        ]))->assertSessionHasErrors('lineas');

        $this->post(route('cobranza.flujo.asientos.store'), $asiento([
            ['cuenta' => 'BANCO CRUCE COBRANZA · COBRANZA', 'monto' => 100],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\CobranzaAsiento::query()->count());
        $this->assertSame(2, \App\Models\CobranzaAsientoLinea::query()->count());
        $this->assertSame(0.0, (float) \App\Models\CobranzaAsientoLinea::query()->sum('debe_bs'));

        // Un debe y varios haberes.
        $this->post(route('cobranza.flujo.asientos.store'), $asiento([
            ['cuenta' => 'Gastos · Cobranza', 'detalle' => 'Comisión', 'monto' => 30],
            ['cuenta' => 'Ingresos · Cobranza', 'monto' => 70],
        ]))->assertSessionHasNoErrors();
        $ultimo = \App\Models\CobranzaAsiento::query()->latest('id')->firstOrFail()->lineas()->orderBy('id')->get();
        $this->assertSame(['100.00', '0.00', '0.00'], $ultimo->pluck('debe_usd')->all());
        $this->assertSame(['0.00', '30.00', '70.00'], $ultimo->pluck('haber_usd')->all());
        \App\Models\CobranzaAsiento::query()->latest('id')->first()->lineas()->delete();
        \App\Models\CobranzaAsiento::query()->latest('id')->first()->delete();

        $this->post(route('cobranza.flujo.store'), [
            'tipo' => 'ingreso', 'fecha' => '2026-11-10', 'ambito' => 'cobranza', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 50, 'tasa_cambio' => 40, 'concepto' => 'Cobro',
        ])->assertSessionHasNoErrors();

        $this->get(route('cobranza.flujo.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertOk()
            ->assertViewHas('saldosCuentas', function (array $saldos) {
                $banesco = collect($saldos)->firstWhere('cuenta', 'BANESCO · GRUPO JRZ');
                $cruce = collect($saldos)->firstWhere('cuenta', 'BANCO CRUCE COBRANZA · COBRANZA');

                return $banesco && $banesco['saldo_usd'] === 150.0 && $cruce && $cruce['saldo_usd'] === -100.0;
            })
            ->assertSee('Traspaso del cruce');
    }

    public function test_ingreso_crea_reserva_temporal_con_sus_fechas(): void
    {
        $user = $this->makeUser();
        $this->crearTablasPatrimonial();
        $apto = \App\Models\Patrimonial\Propiedad::create(['codigo' => 'PAT-012', 'nombre' => 'Apartamento Salamar', 'tipo' => 'apartamento', 'estado' => 'disponible']);
        $reserva = fn (string $entrada, string $salida, float $monto) => [
            'tipo' => 'ingreso', 'fecha' => '2026-11-10', 'ambito' => 'patrimonial', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => $monto, 'tasa_cambio' => 40, 'concepto' => 'Reserva Salamar',
            'vinculo' => 'pat_reserva_nueva:'.$apto->id, 'reserva_cliente' => 'María Pérez', 'reserva_contacto' => '0414',
            'reserva_entrada' => $entrada, 'reserva_salida' => $salida, 'reserva_precio_noche' => 60, 'forma_pago' => 'ZELLE',
        ];

        $this->actingAs($user)->post(route('cobranza.flujo.store'), $reserva('2026-11-20', '2026-11-23', 100))->assertSessionHasNoErrors();

        $r = \App\Models\Patrimonial\Reserva::query()->firstOrFail();
        $this->assertSame('María Pérez', $r->cliente_nombre);
        $this->assertSame('2026-11-20', $r->fecha_entrada->toDateString());
        $this->assertSame('2026-11-23', $r->fecha_salida->toDateString());
        $this->assertSame(180.0, $r->getTotal());
        $this->assertSame(100.0, (float) $r->getTotalPagado());
        $this->assertSame('reservado', $apto->fresh()->estado);
        $mov = CobranzaMovimiento::query()->firstOrFail();
        $this->assertSame(CobranzaMovimiento::VINCULO_RESERVA, $mov->vinculo_tipo);
        $this->assertSame($r->id, (int) $mov->vinculo_id);
        $this->assertSame('María Pérez', $mov->contraparte);

        // Fechas que chocan con otra reserva, o salida antes de la entrada: no se guarda.
        $this->post(route('cobranza.flujo.store'), $reserva('2026-11-22', '2026-11-25', 50))->assertSessionHasErrors('reserva_entrada');
        $this->post(route('cobranza.flujo.store'), $reserva('2026-11-28', '2026-11-27', 50))->assertSessionHasErrors('reserva_salida');
        $this->assertSame(1, \App\Models\Patrimonial\Reserva::query()->count());
    }

    public function test_gasto_repartido_entre_propiedades_con_su_asiento(): void
    {
        $user = $this->makeUser();
        $this->crearTablasPatrimonial();
        (require database_path('migrations/2026_11_12_100000_add_cobranza_movimiento_id_to_pat_transacciones.php'))->up();
        $local = \App\Models\Patrimonial\Propiedad::create(['codigo' => 'L-3', 'nombre' => 'Local 3', 'tipo' => 'local', 'estado' => 'disponible']);
        $casa = \App\Models\Patrimonial\Propiedad::create(['codigo' => 'C-1', 'nombre' => 'Casa playa', 'tipo' => 'casa', 'estado' => 'disponible']);
        $reparto = fn (float $a, float $b) => [
            ['propiedad_id' => $local->id, 'categoria' => 'Electricidad', 'monto_usd' => $a],
            ['propiedad_id' => $casa->id, 'categoria' => 'Electricidad', 'monto_usd' => $b],
        ];

        // Si no suma el total, no se guarda.
        $this->actingAs($user)->post(route('cobranza.flujo.store'), [
            'tipo' => 'egreso', 'tipo_gasto' => '036 - MANT. Y REP.', 'fecha' => '2026-11-10', 'ambito' => 'patrimonial', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 300, 'tasa_cambio' => 40, 'concepto' => 'Luz noviembre',
            'vinculo' => 'pat_reparto:0', 'reparto' => $reparto(100, 150),
        ])->assertSessionHasErrors('reparto');

        $this->post(route('cobranza.flujo.store'), [
            'tipo' => 'egreso', 'tipo_gasto' => '036 - MANT. Y REP.', 'fecha' => '2026-11-10', 'ambito' => 'patrimonial', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 300, 'tasa_cambio' => 40, 'concepto' => 'Luz noviembre',
            'vinculo' => 'pat_reparto:0', 'reparto' => $reparto(100, 200),
            'asiento' => 1, 'asiento_cuenta' => 'Gastos · Patrimonial',
        ])->assertSessionHasNoErrors();

        $mov = CobranzaMovimiento::query()->firstOrFail();
        $this->assertSame(CobranzaMovimiento::VINCULO_PAT_REPARTO, $mov->vinculo_tipo);
        $partes = \App\Models\Patrimonial\PatTransaccion::query()->where('cobranza_movimiento_id', $mov->id)->orderBy('id')->get();
        $this->assertSame(['100.00', '200.00'], $partes->pluck('monto')->all());
        $this->assertSame('gasto', $partes[0]->tipo);

        // El asiento lleva una línea de contrapartida por propiedad, con su parte en $ y en Bs.
        $lineas = \App\Models\CobranzaAsiento::query()->firstOrFail()->lineas()->orderBy('id')->get();
        $this->assertCount(3, $lineas);
        $this->assertSame('Local 3 · Electricidad', $lineas[0]->detalle);
        $this->assertSame('100.00', $lineas[0]->debe_usd);
        $this->assertSame('4000.00', $lineas[0]->debe_bs);
        $this->assertSame('8000.00', $lineas[1]->debe_bs);
        $this->assertSame('300.00', $lineas[2]->haber_usd);

        $this->get(route('cobranza.flujo.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertSee('Gasto repartido entre 2 propiedades')
            ->assertSee('Local 3 $100.00 (Electricidad) · Casa playa $200.00 (Electricidad)');

        // Cambiar el total reparte en la misma proporción.
        $this->put(route('cobranza.flujo.update', $mov), [
            'fecha' => '2026-11-10', 'ambito' => 'patrimonial', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 330, 'tasa_cambio' => 40, 'concepto' => 'Luz noviembre',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['110.00', '220.00'], $partes->map(fn ($p) => $p->fresh()->monto)->all());
        $this->assertSame('220.00', \App\Models\CobranzaAsientoLinea::query()->where('detalle', 'Casa playa · Electricidad')->value('debe_usd'));

        $this->delete(route('cobranza.flujo.destroy', $mov))->assertSessionHasNoErrors();
        $this->assertSame(0, \App\Models\Patrimonial\PatTransaccion::query()->count());
        $this->assertSame(0, \App\Models\CobranzaAsiento::query()->count());
    }

    public function test_asiento_desde_el_ingreso_usa_su_monto_y_lo_sigue(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('cobranza.flujo.store'), [
            'tipo' => 'ingreso', 'fecha' => '2026-11-10', 'ambito' => 'cobranza', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 100, 'tasa_cambio' => 40, 'concepto' => 'Cobro cliente A',
            'asiento' => 1, 'asiento_cuenta' => 'Cuentas por cobrar · Cobranza',
        ])->assertSessionHasNoErrors();

        $mov = CobranzaMovimiento::query()->firstOrFail();
        $asiento = \App\Models\CobranzaAsiento::query()->with('lineas')->firstOrFail();
        $this->assertSame($mov->id, (int) $asiento->movimiento_id);
        $debe = $asiento->lineas->firstWhere('cuenta', 'BANESCO · GRUPO JRZ');
        $haber = $asiento->lineas->firstWhere('cuenta', 'CUENTAS POR COBRAR · COBRANZA');
        $this->assertSame('100.00', $debe->debe_usd);
        $this->assertSame('4000.00', $debe->debe_bs);
        $this->assertSame('100.00', $haber->haber_usd);

        // El banco no se cuenta dos veces; la contrapartida baja.
        $this->get(route('cobranza.flujo.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertSee('Asiento contra CUENTAS POR COBRAR · COBRANZA')
            ->assertViewHas('saldosCuentas', function (array $saldos) {
                $banco = collect($saldos)->firstWhere('cuenta', 'BANESCO · GRUPO JRZ');
                $cxc = collect($saldos)->firstWhere('cuenta', 'CUENTAS POR COBRAR · COBRANZA');

                return $banco['saldo_usd'] === 100.0 && $cxc['saldo_usd'] === -100.0;
            });

        // Editar el monto ajusta el asiento; eliminar el movimiento lo elimina.
        $this->put(route('cobranza.flujo.update', $mov), [
            'fecha' => '2026-11-10', 'ambito' => 'cobranza', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 120, 'tasa_cambio' => 40, 'concepto' => 'Cobro cliente A',
        ])->assertSessionHasNoErrors();
        $this->assertSame('120.00', $asiento->fresh()->lineas->firstWhere('cuenta', 'CUENTAS POR COBRAR · COBRANZA')->haber_usd);
        $this->delete(route('cobranza.flujo.destroy', $mov))->assertSessionHasNoErrors();
        $this->assertSame(0, \App\Models\CobranzaAsiento::query()->count());
        $this->assertSame(0, \App\Models\CobranzaAsientoLinea::query()->count());

        // Sin contrapartida no se guarda.
        $this->post(route('cobranza.flujo.store'), [
            'tipo' => 'egreso', 'tipo_gasto' => '036 - MANT. Y REP.', 'fecha' => '2026-11-10', 'ambito' => 'general', 'cuenta' => self::CUENTA_BANCO,
            'monto_usd' => 10, 'concepto' => 'Gasto', 'asiento' => 1, 'asiento_cuenta' => '',
        ])->assertSessionHasErrors('asiento_cuenta');
    }

    public function test_sin_permiso_de_cobranza_no_entra(): void
    {
        $user = User::create([
            'name' => 'Vendedor', 'email' => 'v-'.uniqid().'@test.local', 'password' => 'password123', 'role' => 'vendedor',
        ]);
        $this->actingAs($user)->get(route('cobranza.flujo.index'))->assertRedirect();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Cobranza', 'email' => 'cob-'.uniqid().'@test.local', 'password' => 'password123', 'role' => 'cobranza',
        ]);
    }

    private function crearTablas(): void
    {
        $columnas = [
            'banco' => 'string', 'titular' => 'string', 'categoria_cuenta' => 'string', 'titular_receptor' => 'string',
            'banco_receptor' => 'string', 'categoria_egreso' => 'string', 'referencia' => 'string', 'motivo' => 'string',
            'oculto' => 'bool', 'es_conciliado' => 'bool', 'anulado' => 'bool',
            'comision' => 'decimal', 'diferencial_cambiario' => 'decimal',
        ];
        $faltan = array_filter($columnas, fn ($tipo, $col) => ! Schema::hasColumn('flujo_cajas', $col), ARRAY_FILTER_USE_BOTH);
        if ($faltan !== []) {
            Schema::table('flujo_cajas', function (Blueprint $table) use ($faltan) {
                foreach ($faltan as $col => $tipo) {
                    match ($tipo) {
                        'bool' => $table->boolean($col)->default(false),
                        'decimal' => $table->decimal($col, 15, 2)->default(0),
                        default => $table->string($col)->nullable(),
                    };
                }
            });
        }
        if (! Schema::hasTable('conciliacion_lineas')) {
            Schema::create('conciliacion_lineas', function (Blueprint $table) {
                $table->id();
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->date('fecha')->nullable();
                $table->decimal('monto', 15, 2)->default(0);
                $table->string('estado', 32)->default('pendiente');
                $table->unsignedBigInteger('flujo_caja_id')->nullable();
                $table->timestamps();
            });
        }
        (require database_path('migrations/2026_11_11_210000_create_cobranza_movimientos_table.php'))->up();
        (require database_path('migrations/2026_11_11_220000_create_cobranza_facturas_y_asientos.php'))->up();
        (require database_path('migrations/2026_11_12_090000_add_movimiento_id_to_cobranza_asientos.php'))->up();
        (require database_path('migrations/2026_11_12_170000_add_forma_pago_to_cobranza_movimientos.php'))->up();
        (require database_path('migrations/2026_11_12_180000_add_tipo_gasto_to_cobranza_movimientos.php'))->up();
        (require database_path('migrations/2026_11_12_190000_add_comprobantes_to_cobranza_movimientos.php'))->up();
    }

    private function crearTablasPatrimonial(): void
    {
        foreach ([
            '2026_08_08_200000_create_patrimonial_tables', '2026_08_09_210922_create_pat_reserva_pagos_table',
            '2026_08_09_221619_add_payment_fields_to_pat_alquiler_pagos', '2026_08_09_223053_add_monto_pagado_to_pat_alquiler_pagos',
            '2026_09_08_150000_add_comision_to_pat_reservas', '2026_09_08_162000_add_comision_to_pat_alquileres',
            '2026_09_09_140000_add_comision_pagada_to_pat_alquiler_pagos',
        ] as $m) {
            (require database_path("migrations/{$m}.php"))->up();
        }
    }

    /** historial_cobranzas y la tabla de «Pagado» viven en la conexión pgsql: en la prueba, otra base SQLite. */
    private function crearTablasCobranza(): void
    {
        config(['database.connections.pgsql' => config('database.connections.sqlite')]);
        \DB::purge('pgsql');
        $pdo = \DB::connection('pgsql')->getPdo();
        $btrim = fn ($s) => trim((string) $s);
        method_exists($pdo, 'createFunction') ? $pdo->createFunction('BTRIM', $btrim, 1) : $pdo->sqliteCreateFunction('BTRIM', $btrim, 1);
        $esquema = Schema::connection('pgsql');
        $esquema->create('historial_cobranzas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_registro');
            $table->string('sede_nombre')->nullable();
            $table->string('codigo_cliente')->nullable();
            $table->string('nombre_cliente')->nullable();
            $table->string('id_documento')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->string('tipo_cxc')->nullable();
            $table->string('numero_documento')->nullable();
            $table->decimal('monto_neto', 15, 2)->default(0);
            $table->decimal('saldo', 15, 2)->default(0);
            $table->integer('tipo_fila')->nullable();
            $table->string('factura_padre')->nullable();
            $table->string('estatus')->nullable();
        });
        $esquema->create('cobranzas_pagadas_manualmente', function (Blueprint $table) {
            $table->id();
            $table->string('id_documento')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    private function filaHistorial(array $datos): void
    {
        \DB::connection('pgsql')->table('historial_cobranzas')->insert($datos + [
            'fecha_registro' => '2026-11-10', 'sede_nombre' => 'DORAL', 'codigo_cliente' => 'C-1', 'nombre_cliente' => 'Cliente Deudor',
            'fecha_emision' => '2026-10-01', 'tipo_cxc' => 'FAC', 'tipo_fila' => 1, 'estatus' => 'RECIENTE',
        ]);
    }
}
