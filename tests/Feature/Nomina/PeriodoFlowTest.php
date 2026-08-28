<?php

namespace Tests\Feature\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaHoraExtra;
use App\Models\Nomina\NominaInasistencia;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaRegistro;
use App\Models\User;
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
        $this->assertStringContainsString('A pagar USD', $sheet);
        $this->assertStringNotContainsString('Comisión', $sheet);

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

    public function test_al_calcular_pregunta_a_quien_descontar_cuotas_y_no_las_aplica_solas(): void
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
            'numero_cuotas' => 4,
            'frecuencia' => 'QUINCENAL',
            'fecha_inicio' => '2026-08-16',
            'motivo' => 'Arreglo vehiculo',
        ], $this->rrhh->id);
        $prestamoOtro = $loan->create($otro, [
            'fecha' => '2026-08-16',
            'monto_original' => 200,
            'numero_cuotas' => 4,
            'frecuencia' => 'QUINCENAL',
            'fecha_inicio' => '2026-08-16',
            'motivo' => 'Otro prestamo',
        ], $this->rrhh->id);

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();

        $this->get(route('nomina.periodos.calcular.form', $periodo))
            ->assertOk()
            ->assertSee('Empleado Quincena')
            ->assertSee('Otro Con Prestamo')
            ->assertSee('Arreglo vehiculo');

        $this->post(route('nomina.periodos.calcular', $periodo), [
            'descontar_empleado_ids' => [$this->empleado->id],
        ])->assertRedirect(route('nomina.periodos.show', $periodo));

        $this->assertEquals(82.5, (float) $prestamoElegido->fresh()->saldo_pendiente);
        $this->assertEquals(200.0, (float) $prestamoOtro->fresh()->saldo_pendiente);
        $this->assertSame(1, $prestamoElegido->cuotas()->whereNotNull('nomina_periodo_id')->count());
        $this->assertSame(0, $prestamoOtro->cuotas()->whereNotNull('nomina_periodo_id')->count());

        $registro = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        $this->assertEquals(27.5, (float) $registro->total_deducciones);
    }

    public function test_permite_descontar_una_cuota_o_un_parcial_y_lo_registra(): void
    {
        $this->actingAs($this->rrhh);

        $loan = app(\App\Services\Nomina\LoanService::class);
        $prestamoUno = $loan->create($this->empleado, [
            'fecha' => '2026-08-16',
            'monto_original' => 110,
            'numero_cuotas' => 4,
            'frecuencia' => 'QUINCENAL',
            'fecha_inicio' => '2026-08-16',
            'motivo' => 'Arreglo vehiculo',
        ], $this->rrhh->id);
        $prestamoDos = $loan->create($this->empleado, [
            'fecha' => '2026-08-16',
            'monto_original' => 80,
            'numero_cuotas' => 4,
            'frecuencia' => 'QUINCENAL',
            'fecha_inicio' => '2026-08-16',
            'motivo' => 'Prestamo personal',
        ], $this->rrhh->id);

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();

        $cuotaUno = $prestamoUno->cuotas()->orderBy('numero')->firstOrFail();
        $cuotaDos = $prestamoDos->cuotas()->orderBy('numero')->firstOrFail();

        $this->get(route('nomina.periodos.calcular.form', $periodo))
            ->assertOk()
            ->assertSee('name="descuentos['.$cuotaUno->id.'][aplicar]"', false)
            ->assertSee('Parcial $');

        $this->post(route('nomina.periodos.calcular', $periodo), [
            'descuentos' => [
                $cuotaUno->id => [
                    'aplicar' => '1',
                    'cuota_id' => $cuotaUno->id,
                    'monto' => '10.00',
                ],
                $cuotaDos->id => [
                    'cuota_id' => $cuotaDos->id,
                    'monto' => '20.00',
                ],
            ],
        ])->assertRedirect(route('nomina.periodos.show', $periodo));

        $this->assertEquals(100.0, (float) $prestamoUno->fresh()->saldo_pendiente);
        $this->assertEquals(80.0, (float) $prestamoDos->fresh()->saldo_pendiente);
        $this->assertSame('PARCIAL', $cuotaUno->fresh()->estado);
        $this->assertEquals(10.0, (float) $cuotaUno->fresh()->monto_pagado);
        $this->assertDatabaseHas('nomina_prestamo_abonos', [
            'prestamo_id' => $prestamoUno->id,
            'cuota_id' => $cuotaUno->id,
            'monto' => 10,
            'tipo' => 'DESCUENTO_NOMINA',
        ]);
        $this->assertSame(0, $prestamoDos->cuotas()->whereNotNull('nomina_periodo_id')->count());

        $registro = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        $this->assertEquals(10.0, (float) $registro->total_deducciones);
    }

    public function test_se_puede_deshacer_un_calculo_accidental(): void
    {
        $this->actingAs($this->rrhh);
        $this->crearMovimientos();

        $loan = app(\App\Services\Nomina\LoanService::class);
        $prestamo = $loan->create($this->empleado, [
            'fecha' => '2026-08-16',
            'monto_original' => 110,
            'numero_cuotas' => 4,
            'frecuencia' => 'QUINCENAL',
            'fecha_inicio' => '2026-08-16',
            'motivo' => 'Arreglo vehiculo',
        ], $this->rrhh->id);

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();

        $this->post(route('nomina.periodos.calcular', $periodo), [
            'descontar_empleado_ids' => [$this->empleado->id],
        ])->assertRedirect();

        $this->assertSame(NominaPeriodo::CALCULADO, $periodo->fresh()->estado);
        $this->assertEquals(82.5, (float) $prestamo->fresh()->saldo_pendiente);

        $this->post(route('nomina.periodos.revertir', $periodo))->assertRedirect(route('nomina.periodos.show', $periodo));

        $periodo->refresh();
        $this->assertSame(NominaPeriodo::ABIERTO, $periodo->estado);
        $this->assertSame(0, NominaRegistro::query()->count());
        $this->assertEquals(110.0, (float) $prestamo->fresh()->saldo_pendiente);
        $this->assertSame(0, $prestamo->cuotas()->whereNotNull('nomina_periodo_id')->count());
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

        $this->assertStringContainsString('27000001;30400.00;'.now()->format('d/m/Y'), $txt);
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
}
