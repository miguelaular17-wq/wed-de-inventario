<?php

namespace Tests\Feature\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaHoraExtra;
use App\Models\Nomina\NominaInasistencia;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaPrestamoPlan;
use App\Models\Nomina\NominaRegistro;
use App\Models\Nomina\NominaSede;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class PeriodoFlowTest extends TestCase
{
    use CreatesNominaSchema;

    private User $rrhh;
    private NominaEmpleado $empleado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        $this->rrhh = User::create([
            'name' => 'RRHH Períodos',
            'email' => 'rrhh-periodos@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);

        $cliente = Cliente::create([
            'cedula' => '27000001',
            'nombre' => 'Empleado Quincena',
        ]);

        $this->empleado = NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'salario_base' => 800,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'fecha_ingreso' => '2025-01-01',
        ]);
    }

    public function test_completa_el_ciclo_de_quincena_y_congela_los_totales(): void
    {
        $this->actingAs($this->rrhh);
        $this->crearMovimientos();

        $this->post(route('nomina.periodos.store'), [
            'fecha' => '2026-08-20',
        ])->assertRedirect();

        $periodo = NominaPeriodo::query()->firstOrFail();
        $this->assertSame('16/08/2026 al 31/08/2026', $periodo->etiqueta);
        $this->assertSame(NominaPeriodo::ABIERTO, $periodo->estado);
        $this->assertSame('2026-09-03', $periodo->fecha_pago_comision?->toDateString());

        $this->post(route('nomina.periodos.calcular', $periodo))
            ->assertRedirect(route('nomina.periodos.show', $periodo));

        $periodo->refresh();
        $registro = NominaRegistro::query()->firstOrFail();

        $this->assertSame(NominaPeriodo::CALCULADO, $periodo->estado);
        $this->assertEquals(800, $registro->salario_base);
        $this->assertEquals(30, $registro->total_otros_ingresos);
        $this->assertEquals(70, $registro->total_deducciones);
        $this->assertEquals(760, $registro->total_pagar);
        $this->assertDatabaseHas('nomina_abonos_sueldo', [
            'estado' => 'DESCONTADO',
            'nomina_periodo_id' => $periodo->id,
        ]);
        $this->assertDatabaseHas('nomina_inasistencias', [
            'estado' => 'APLICADO',
            'nomina_periodo_id' => $periodo->id,
        ]);
        $this->assertDatabaseHas('nomina_horas_extras', [
            'estado' => 'APLICADO',
            'nomina_periodo_id' => $periodo->id,
        ]);

        $this->get(route('nomina.periodos.show', $periodo))
            ->assertOk()
            ->assertSee('Descargar relación PDF')
            ->assertSee('Descargar Excel');

        $pdf = $this->get(route('nomina.periodos.relacion', $periodo));
        $pdf->assertOk();
        $pdf->assertHeader('content-disposition');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->get(route('nomina.periodos.relacion', ['periodo' => $periodo, 'formato' => 'xlsx']));
        $xlsx->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $xlsx->getContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);
        $this->assertStringContainsString('Empleado Quincena', $sheet);
        $this->assertStringContainsString('Cargo', $sheet);
        $this->assertStringContainsString('Total asignaciones', $sheet);
        $this->assertStringContainsString('Total Pagar USD', $sheet);
        $this->assertStringContainsString('Ausencias', $sheet);
        $this->assertStringContainsString('Total a Pagar BCV', $sheet);
        $this->assertStringNotContainsString('Comisión', $sheet);
        $this->assertStringNotContainsString('Empresa', $sheet);

        $this->post(route('nomina.periodos.aprobar', $periodo))->assertRedirect();
        $this->assertSame(NominaPeriodo::APROBADO, $periodo->fresh()->estado);

        $this->post(route('nomina.periodos.pagar', $periodo))->assertRedirect();
        $this->assertSame(NominaPeriodo::PAGADO, $periodo->fresh()->estado);

        $this->post(route('nomina.periodos.cerrar', $periodo))->assertRedirect();
        $this->assertSame(NominaPeriodo::CERRADO, $periodo->fresh()->estado);

        $this->get(route('nomina.periodos.show', $periodo))
            ->assertOk()
            ->assertSee('Empleado Quincena')
            ->assertSee('$760.00')
            ->assertSee('CERRADO');
    }

    public function test_rechaza_transiciones_fuera_de_orden_y_quincenas_duplicadas(): void
    {
        $this->actingAs($this->rrhh);

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-03'])
            ->assertRedirect();

        $periodo = NominaPeriodo::query()->firstOrFail();

        $this->post(route('nomina.periodos.aprobar', $periodo))
            ->assertSessionHasErrors('periodo');
        $this->assertSame(NominaPeriodo::ABIERTO, $periodo->fresh()->estado);

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-12'])
            ->assertSessionHasErrors('fecha');
        $this->assertSame(1, NominaPeriodo::query()->count());
    }

    public function test_al_calcular_no_descuenta_prestamos_libres_automaticamente(): void
    {
        $this->actingAs($this->rrhh);

        $otroCliente = Cliente::create(['cedula' => '27000002', 'nombre' => 'Otro Con Prestamo']);
        $otro = NominaEmpleado::create([
            'cliente_id' => $otroCliente->id,
            'salario_base' => 800,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'fecha_ingreso' => '2025-01-01',
        ]);

        $loan = app(\App\Services\Nomina\LoanService::class);
        $prestamoElegido = $loan->create($this->empleado, [
            'fecha' => '2026-08-16',
            'monto_original' => 110,
            'motivo' => 'Arreglo vehiculo',
        ], $this->rrhh->id);
        $prestamoOtro = $loan->create($otro, [
            'fecha' => '2026-08-16',
            'monto_original' => 200,
            'motivo' => 'Otro prestamo',
        ], $this->rrhh->id);

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();

        $this->get(route('nomina.periodos.calcular.form', $periodo))
            ->assertOk()
            ->assertSee('Préstamos de esta quincena');

        $this->post(route('nomina.periodos.calcular', $periodo), [
            'descontar_empleado_ids' => [$this->empleado->id],
        ])->assertRedirect(route('nomina.periodos.show', $periodo));

        $this->assertEquals(110.0, (float) $prestamoElegido->fresh()->saldo_pendiente);
        $this->assertEquals(200.0, (float) $prestamoOtro->fresh()->saldo_pendiente);
        $this->assertCount(0, $prestamoElegido->cuotas);
        $this->assertCount(0, $prestamoOtro->abonos);

        $registro = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        $this->assertEquals(0.0, (float) $registro->total_deducciones);
    }

    public function test_pago_manual_a_prestamo_libre_reduce_saldo(): void
    {
        $this->actingAs($this->rrhh);

        $loan = app(\App\Services\Nomina\LoanService::class);
        $prestamoUno = $loan->create($this->empleado, [
            'fecha' => '2026-08-16',
            'monto_original' => 110,
            'motivo' => 'Arreglo vehiculo',
        ], $this->rrhh->id);
        $prestamoDos = $loan->create($this->empleado, [
            'fecha' => '2026-08-16',
            'monto_original' => 80,
            'motivo' => 'Prestamo personal',
        ], $this->rrhh->id);

        $this->post(route('nomina.prestamos.abonar', $prestamoUno), [
            'fecha' => '2026-08-20',
            'monto' => 10,
            'tipo' => 'DESCUENTO_NOMINA',
            'observacion' => 'Abono quincena',
        ])->assertRedirect();

        $this->assertEquals(100.0, (float) $prestamoUno->fresh()->saldo_pendiente);
        $this->assertEquals(80.0, (float) $prestamoDos->fresh()->saldo_pendiente);
        $this->assertDatabaseHas('nomina_prestamo_abonos', [
            'prestamo_id' => $prestamoUno->id,
            'monto' => 10,
            'tipo' => 'DESCUENTO_NOMINA',
        ]);
        $this->assertCount(0, $prestamoUno->cuotas);
    }

    public function test_descuenta_faltante_caja_del_sueldo_si_no_tiene_comision(): void
    {
        $this->actingAs($this->rrhh);

        $cargoId = DB::table('nomina_cargos')->insertGetId([
            'nombre' => 'Cajero',
            'descripcion' => 'Caja',
            'estado' => 'ACTIVO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->empleado->update([
            'cargo_id' => $cargoId,
            'modo_comision' => NominaEmpleado::COMISION_NINGUNA,
        ]);

        $this->post(route('nomina.faltante_caja.store'), [
            'empleado_id' => $this->empleado->id,
            'fecha' => '2026-08-20',
            'monto' => 40,
            'motivo' => 'Cierre corto',
        ])->assertRedirect();

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();
        $this->post(route('nomina.periodos.calcular', $periodo))->assertRedirect();

        $registro = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        $this->assertEquals(40.0, (float) $registro->total_deducciones);
        $this->assertEquals(760.0, (float) $registro->total_pagar);
        $this->assertDatabaseHas('nomina_comision_descuentos', [
            'empleado_id' => $this->empleado->id,
            'tipo' => 'FALTANTE',
            'monto' => 40,
            'estado' => 'APLICADO',
            'periodo_id' => $periodo->id,
        ]);
        $desglose = json_decode((string) $registro->observaciones, true);
        $this->assertEquals(40, $desglose['faltante_caja'] ?? null);

        $this->get(route('nomina.periodos.show', $periodo))
            ->assertOk()
            ->assertSee('Faltante de caja')
            ->assertSee('nomina-desc-link');
    }

    public function test_deduccion_por_ajuste_no_se_duplica_en_columna_ni_total(): void
    {
        $this->actingAs($this->rrhh);

        $this->post(route('nomina.ajustes.store', $this->empleado), [
            'fecha' => '2026-08-20',
            'tipo' => 'DEDUCCION',
            'destino' => 'NOMINA',
            'monto' => 6.66,
            'motivo' => 'Inasistencia',
        ])->assertRedirect();

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();
        $this->post(route('nomina.periodos.calcular', $periodo))->assertRedirect();

        $registro = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        $desglose = json_decode((string) $registro->observaciones, true);

        $this->assertEquals(6.66, (float) ($desglose['deducciones_ajuste_nomina'] ?? 0));
        $this->assertEquals(0.0, (float) ($desglose['otras_deducciones'] ?? 0));
        $this->assertEquals(6.66, (float) $registro->total_deducciones);
        $this->assertEquals(6.66, $registro->montoDeduccionesAjuste());
        $this->assertEquals(793.34, (float) $registro->total_pagar);
    }

    public function test_excel_no_duplica_deducciones_aunque_el_snapshot_este_duplicado(): void
    {
        $this->actingAs($this->rrhh);

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();
        $this->post(route('nomina.periodos.calcular', $periodo))->assertRedirect();

        $registro = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        // Snapshot legado: el mismo ajuste aparece en otras y en deducciones_ajuste_nomina.
        $desglose = json_decode((string) $registro->observaciones, true) ?: [];
        $desglose['inasistencias'] = 10.0;
        $desglose['abonos_sueldo'] = 20.0;
        $desglose['prestamos'] = 5.0;
        $desglose['deducciones_ajuste_nomina'] = 6.66;
        $desglose['otras_deducciones'] = 6.66;
        $desglose['mercancia'] = 0;
        $desglose['faltante_caja'] = 0;
        $registro->update([
            'observaciones' => json_encode($desglose, JSON_UNESCAPED_UNICODE),
            'total_deducciones' => 41.66, // 10+20+5+6.66 (una sola vez)
        ]);
        $registro->refresh();

        $this->assertEquals(6.66, $registro->montoDeduccionesAjuste());
        $this->assertEquals(
            41.66,
            round(
                (float) ($registro->desglose()['inasistencias'] ?? 0)
                + (float) ($registro->desglose()['abonos_sueldo'] ?? 0)
                + $registro->montoDeduccionesAjuste()
                + (float) ($registro->desglose()['prestamos'] ?? 0),
                2
            )
        );
    }

    public function test_empleado_que_entra_a_mitad_de_quincena_cobra_solo_dias_trabajados(): void
    {
        $this->actingAs($this->rrhh);

        $this->empleado->update([
            'salario_base' => 170,
            'tipo_salario' => 'MENSUAL',
            'fecha_ingreso' => '2026-08-20',
        ]);

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();
        $this->post(route('nomina.periodos.calcular', $periodo))->assertRedirect();

        // Valor día = 170/30 = 5.67; días 20..31 agosto = 12 → 68.04
        $registro = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        $desglose = json_decode((string) $registro->observaciones, true);

        $this->assertTrue((bool) ($desglose['salario_prorrateado'] ?? false));
        $this->assertSame(12, (int) ($desglose['dias_trabajados'] ?? 0));
        $this->assertEquals(5.67, (float) ($desglose['valor_dia'] ?? 0));
        $this->assertEquals(68.04, (float) $registro->salario_base);
        $this->assertEquals(68.04, (float) $registro->total_pagar);
    }

    public function test_se_puede_deshacer_un_calculo_accidental(): void
    {
        $this->actingAs($this->rrhh);
        $this->crearMovimientos();

        $loan = app(\App\Services\Nomina\LoanService::class);
        $prestamo = $loan->create($this->empleado, [
            'fecha' => '2026-08-16',
            'monto_original' => 110,
            'motivo' => 'Arreglo vehiculo',
        ], $this->rrhh->id);

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();

        $this->post(route('nomina.periodos.calcular', $periodo), [
            'descontar_empleado_ids' => [$this->empleado->id],
        ])->assertRedirect();

        $this->assertSame(NominaPeriodo::CALCULADO, $periodo->fresh()->estado);
        $this->assertEquals(110.0, (float) $prestamo->fresh()->saldo_pendiente);

        $this->post(route('nomina.periodos.revertir', $periodo))->assertRedirect(route('nomina.periodos.show', $periodo));

        $periodo->refresh();
        $this->assertSame(NominaPeriodo::ABIERTO, $periodo->estado);
        $this->assertSame(0, NominaRegistro::query()->count());
        $this->assertEquals(110.0, (float) $prestamo->fresh()->saldo_pendiente);
        $this->assertCount(0, $prestamo->cuotas);
        $this->assertDatabaseHas('nomina_abonos_sueldo', [
            'estado' => 'PENDIENTE',
            'nomina_periodo_id' => null,
        ]);
        $this->assertDatabaseHas('nomina_inasistencias', [
            'estado' => 'PENDIENTE',
            'nomina_periodo_id' => null,
        ]);
        $this->assertDatabaseHas('nomina_horas_extras', [
            'estado' => 'PENDIENTE',
            'nomina_periodo_id' => null,
        ]);
    }

    public function test_exporta_txt_banco_por_empresa_en_bolivares(): void
    {
        \Illuminate\Support\Facades\Cache::put('tasa_bcv_'.date('Y-m-d'), 40.0, 3600);

        $empresa = \App\Models\Nomina\NominaEmpresa::create([
            'codigo' => 'J401722296',
            'nombre' => 'INVERSIONES DORAL PARAGUANÁ, C.A.',
            'estado' => 'ACTIVO',
        ]);
        $this->empleado->update(['empresa_id' => $empresa->id]);
        $this->actingAs($this->rrhh);
        $this->crearMovimientos();
        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();
        $this->post(route('nomina.periodos.calcular', $periodo))->assertRedirect();

        $this->get(route('nomina.periodos.show', $periodo))
            ->assertOk()
            ->assertSee('Archivo para el banco')
            ->assertSee('J401722296');

        $txt = $this->get(route('nomina.periodos.banco', [$periodo, $empresa]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('30400', $txt);
        $this->assertStringContainsString(now()->format('dmY'), $txt);
    }

    private function crearMovimientos(): void
    {
        $quincena = [
            'inicio' => '2026-08-16',
            'fin' => '2026-08-31',
            'etiqueta' => '16/08/2026 al 31/08/2026',
        ];

        NominaAbonoSueldo::create([
            'empleado_id' => $this->empleado->id,
            'fecha' => '2026-08-18',
            'monto' => 50,
            'quincena_inicio' => $quincena['inicio'],
            'quincena_fin' => $quincena['fin'],
            'etiqueta' => $quincena['etiqueta'],
            'estado' => 'PENDIENTE',
            'created_by' => $this->rrhh->id,
        ]);

        NominaInasistencia::create([
            'empleado_id' => $this->empleado->id,
            'fecha' => '2026-08-19',
            'cantidad' => 1,
            'valor_unitario' => 20,
            'monto' => 20,
            'quincena_inicio' => $quincena['inicio'],
            'quincena_fin' => $quincena['fin'],
            'etiqueta' => $quincena['etiqueta'],
            'estado' => 'PENDIENTE',
            'created_by' => $this->rrhh->id,
        ]);

        NominaHoraExtra::create([
            'empleado_id' => $this->empleado->id,
            'fecha' => '2026-08-20',
            'horas' => 2,
            'valor_unitario' => 15,
            'monto' => 30,
            'quincena_inicio' => $quincena['inicio'],
            'quincena_fin' => $quincena['fin'],
            'etiqueta' => $quincena['etiqueta'],
            'estado' => 'PENDIENTE',
            'created_by' => $this->rrhh->id,
        ]);
    }

    public function test_escritorio_de_prestamos_en_modo_libre_no_lista_cuotas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20'));
        $this->actingAs($this->rrhh);

        $loan = app(\App\Services\Nomina\LoanService::class);
        $prestamo = $loan->create($this->empleado, [
            'fecha' => '2026-08-16',
            'monto_original' => 110,
            'motivo' => 'Arreglo vehiculo',
        ], $this->rrhh->id);

        $this->get(route('nomina.prestamos.index'))
            ->assertOk()
            ->assertSee('Empleado Quincena')
            ->assertSee('Cobrar / descontar')
            ->assertDontSee('Marcar todos');

        $this->get(route('nomina.empleados.show', ['empleado' => $this->empleado, 'tab' => 'prestamos']))
            ->assertOk()
            ->assertSee('Historial de pagos')
            ->assertSee('Arreglo vehiculo')
            ->assertSee('Saldo libre');

        $this->assertEquals(110.0, (float) $prestamo->fresh()->saldo_pendiente);
        $this->assertCount(0, $prestamo->cuotas);

        Carbon::setTestNow();
    }

    public function test_pago_a_prestamo_libre_desde_ficha_no_afecta_comision_automatica(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20'));
        $this->actingAs($this->rrhh);

        $this->empleado->update(['modo_comision' => NominaEmpleado::COMISION_VENTAS_PROPIAS]);

        $loan = app(\App\Services\Nomina\LoanService::class);
        $prestamo = $loan->create($this->empleado, [
            'fecha' => '2026-08-16',
            'monto_original' => 110,
            'motivo' => 'Prestamo personal',
        ], $this->rrhh->id);

        $this->post(route('nomina.prestamos.abonar', $prestamo), [
            'fecha' => '2026-08-20',
            'monto' => 27.50,
            'tipo' => 'DESCUENTO_NOMINA',
        ])->assertRedirect();

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();
        $this->post(route('nomina.periodos.calcular', $periodo))->assertRedirect();

        $this->assertEquals(82.5, (float) $prestamo->fresh()->saldo_pendiente);
        $registro = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        $this->assertEquals(0.0, (float) $registro->total_deducciones);

        Carbon::setTestNow();
    }

    public function test_zip_de_relacion_separa_sede_y_area(): void
    {
        $this->actingAs($this->rrhh);

        $sede = NominaSede::create([
            'nombre' => 'Doral',
            'codigo' => 'DORAL',
            'tipo' => 'SEDE',
            'estado' => 'ACTIVO',
        ]);
        $area = NominaSede::create([
            'nombre' => 'Marketing',
            'codigo' => 'MARKETING',
            'tipo' => 'AREA',
            'estado' => 'ACTIVO',
        ]);

        $this->empleado->update(['sede_id' => $sede->id]);
        $otro = NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '27000002', 'nombre' => 'Persona Area'])->id,
            'salario_base' => 100,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'sede_id' => $area->id,
        ]);

        $periodo = NominaPeriodo::create([
            'fecha_inicio' => '2026-07-16',
            'fecha_fin' => '2026-07-31',
            'etiqueta' => '16/07/2026 al 31/07/2026',
            'estado' => NominaPeriodo::CALCULADO,
        ]);
        NominaRegistro::create([
            'periodo_id' => $periodo->id,
            'empleado_id' => $this->empleado->id,
            'salario_base' => 800,
            'total_pagar' => 800,
        ]);
        NominaRegistro::create([
            'periodo_id' => $periodo->id,
            'empleado_id' => $otro->id,
            'salario_base' => 100,
            'total_pagar' => 100,
        ]);

        $response = $this->get(route('nomina.periodos.relacion', ['periodo' => $periodo, 'formato' => 'zip']));
        $response->assertOk();
        $this->assertStringContainsString('zip', (string) $response->headers->get('content-disposition'));

        $tmp = tempnam(sys_get_temp_dir(), 'ziprel');
        file_put_contents($tmp, $response->getContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $nombres = [];
        $pdfSede = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }
        $pdfSede = (string) $zip->getFromName('Sede_Doral.pdf');
        $zip->close();
        @unlink($tmp);

        $this->assertContains('Sede_Doral.pdf', $nombres);
        $this->assertContains('Area_Marketing.pdf', $nombres);
        $this->assertStringStartsWith('%PDF', $pdfSede);

        $this->get(route('nomina.periodos.show', $periodo))
            ->assertOk()
            ->assertSee('Totales por sede y área')
            ->assertSee('Doral')
            ->assertSee('Marketing')
            ->assertSee('Asignaciones')
            ->assertSee('Total pagado USD')
            ->assertSee('Total pagado Bs');
    }

    public function test_zip_de_relacion_comisiones_separa_sede_y_area(): void
    {
        $this->actingAs($this->rrhh);

        $sede = NominaSede::create([
            'nombre' => 'Doral',
            'codigo' => 'DORAL',
            'tipo' => 'SEDE',
            'estado' => 'ACTIVO',
        ]);
        $area = NominaSede::create([
            'nombre' => 'Marketing',
            'codigo' => 'MARKETING',
            'tipo' => 'AREA',
            'estado' => 'ACTIVO',
        ]);

        $this->empleado->update(['sede_id' => $sede->id, 'modo_comision' => NominaEmpleado::COMISION_VENTAS_PROPIAS]);
        $tecnico = NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '27000003', 'nombre' => 'Tecnico Area'])->id,
            'salario_base' => 0,
            'tipo_salario' => 'SOLO_COMISION',
            'estado' => 'ACTIVO',
            'sede_id' => $area->id,
            'modo_comision' => NominaEmpleado::COMISION_SERVICIO_TECNICO,
            'es_servicio_tecnico' => true,
        ]);

        $periodo = NominaPeriodo::create([
            'fecha_inicio' => '2026-07-16',
            'fecha_fin' => '2026-07-31',
            'etiqueta' => '16/07/2026 al 31/07/2026',
            'estado' => NominaPeriodo::CALCULADO,
        ]);

        NominaLiquidacionComision::create([
            'periodo_id' => $periodo->id,
            'empleado_id' => $this->empleado->id,
            'modo' => NominaEmpleado::COMISION_VENTAS_PROPIAS,
            'base_total' => 1000,
            'base_telefonia' => 200,
            'base_otros' => 800,
            'comision_total' => 10,
            'total_pagar' => 9,
        ]);
        NominaLiquidacionComision::create([
            'periodo_id' => $periodo->id,
            'empleado_id' => $tecnico->id,
            'modo' => NominaEmpleado::COMISION_SERVICIO_TECNICO,
            'base_total' => 300,
            'comision_total' => 150,
            'total_pagar' => 135,
            'snapshot' => ['ventas_st' => 300, 'gastos' => 0],
        ]);

        $response = $this->get(route('nomina.comisiones.relacion', ['periodo' => $periodo, 'formato' => 'zip']));
        $response->assertOk();
        $this->assertStringContainsString('zip', (string) $response->headers->get('content-disposition'));

        $tmp = tempnam(sys_get_temp_dir(), 'zipcom');
        file_put_contents($tmp, $response->getContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }
        $pdfSede = (string) $zip->getFromName('Sede_Doral.pdf');
        $zip->close();
        @unlink($tmp);

        $this->assertContains('Sede_Doral.pdf', $nombres);
        $this->assertContains('Area_Marketing.pdf', $nombres);
        $this->assertStringStartsWith('%PDF', $pdfSede);

        $this->get(route('nomina.comisiones.show', $periodo))
            ->assertOk()
            ->assertSee('Totales por sede y área')
            ->assertSee('Doral')
            ->assertSee('Marketing')
            ->assertSee('$9.00')
            ->assertSee('$135.00');
    }

    public function test_recalcula_solo_comisiones_sin_tocar_nomina(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20'));
        $this->actingAs($this->rrhh);

        $this->empleado->update([
            'modo_comision' => NominaEmpleado::COMISION_VENTAS_PROPIAS,
            'codigo_vendedor' => 'VEND-RECALC',
        ]);
        $this->crearMovimientos();

        DB::table('ventas_detalle')->insert([
            'sede' => 'CENTRO',
            'tipo_documento' => 'FAC',
            'numero_documento' => '88888',
            'fecha' => '2026-08-18',
            'cantidad' => 1,
            'precio_venta' => 1000,
            'costo_unitario' => 0,
            'ganancia' => 1000,
            'vendedor' => 'VEND-RECALC',
            'anulado' => false,
        ]);

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();
        $this->post(route('nomina.periodos.calcular', $periodo))->assertRedirect();

        $registroAntes = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        $salarioAntes = (float) $registroAntes->total_pagar;
        $deduccionesAntes = (float) $registroAntes->total_deducciones;
        $comisionAntes = (float) $registroAntes->total_comisiones;

        DB::table('ventas_detalle')->insert([
            'sede' => 'CENTRO',
            'tipo_documento' => 'FAC',
            'numero_documento' => '99999',
            'fecha' => '2026-08-18',
            'cantidad' => 1,
            'precio_venta' => 5000,
            'costo_unitario' => 0,
            'ganancia' => 5000,
            'vendedor' => 'VEND-RECALC',
            'anulado' => false,
        ]);

        $this->post(route('nomina.comisiones.recalcular', $periodo))
            ->assertRedirect(route('nomina.comisiones.show', $periodo));

        $registroDespues = $registroAntes->fresh();
        $this->assertSame($salarioAntes, (float) $registroDespues->total_pagar);
        $this->assertSame($deduccionesAntes, (float) $registroDespues->total_deducciones);
        $this->assertGreaterThan($comisionAntes, (float) $registroDespues->total_comisiones);

        Carbon::setTestNow();
    }

    public function test_no_recalcula_comisiones_de_quincena_cerrada(): void
    {
        $this->actingAs($this->rrhh);

        $periodo = NominaPeriodo::create([
            'fecha_inicio' => '2026-07-16',
            'fecha_fin' => '2026-07-31',
            'etiqueta' => '16/07/2026 al 31/07/2026',
            'estado' => NominaPeriodo::CERRADO,
        ]);

        $this->post(route('nomina.comisiones.recalcular', $periodo))
            ->assertSessionHasErrors('estado');
    }
}
