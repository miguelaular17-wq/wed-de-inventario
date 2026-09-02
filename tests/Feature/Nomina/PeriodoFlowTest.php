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
            ->assertSee('Préstamos de esta quincena')
            ->assertDontSee('Marcar todos')
            ->assertDontSee('name="descuentos[', false);

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
            ->assertDontSee('Parcial $')
            ->assertDontSee('name="descuentos['.$cuotaUno->id.'][aplicar]"', false);

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

    public function test_descuenta_mercancia_del_sueldo_al_calcular(): void
    {
        $this->actingAs($this->rrhh);

        $this->post(route('nomina.mercancia.store', $this->empleado), [
            'fecha' => '2026-08-20',
            'monto' => 40,
            'motivo' => 'Celular',
        ])->assertRedirect();

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();
        $this->post(route('nomina.periodos.calcular', $periodo))->assertRedirect();

        $registro = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        $this->assertEquals(40.0, (float) $registro->total_deducciones);
        $this->assertEquals(760.0, (float) $registro->total_pagar);
        $this->assertDatabaseHas('nomina_descuentos_mercancia', [
            'empleado_id' => $this->empleado->id,
            'monto' => 40,
            'estado' => 'DESCONTADO',
            'nomina_periodo_id' => $periodo->id,
        ]);
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

    public function test_escritorio_de_prestamos_programa_descuento_y_alimenta_la_ficha(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20'));
        $this->actingAs($this->rrhh);

        $loan = app(\App\Services\Nomina\LoanService::class);
        $prestamo = $loan->create($this->empleado, [
            'fecha' => '2026-08-16',
            'monto_original' => 110,
            'numero_cuotas' => 4,
            'frecuencia' => 'QUINCENAL',
            'fecha_inicio' => '2026-08-16',
            'motivo' => 'Arreglo vehiculo',
        ], $this->rrhh->id);
        $cuota = $prestamo->cuotas()->orderBy('numero')->firstOrFail();

        $this->get(route('nomina.prestamos.index'))
            ->assertOk()
            ->assertSee('Empleado Quincena')
            ->assertSee('Arreglo vehiculo');

        $this->post(route('nomina.prestamos.programar'), [
            'descuentos' => [
                $cuota->id => [
                    'aplicar' => '1',
                    'cuota_id' => $cuota->id,
                    'monto' => '10.00',
                    'destino' => 'NOMINA',
                ],
            ],
        ])->assertRedirect(route('nomina.prestamos.index'));

        $this->assertDatabaseHas('nomina_prestamo_planes', [
            'cuota_id' => $cuota->id,
            'empleado_id' => $this->empleado->id,
            'monto' => 10,
            'destino' => NominaPrestamoPlan::DESTINO_NOMINA,
            'estado' => NominaPrestamoPlan::PENDIENTE,
        ]);

        $this->get(route('nomina.empleados.show', ['empleado' => $this->empleado, 'tab' => 'prestamos']))
            ->assertOk()
            ->assertSee('Descuento de esta quincena')
            ->assertSee('Nómina')
            ->assertSee('10.00');

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();
        $this->post(route('nomina.periodos.calcular', $periodo))->assertRedirect();

        $this->assertEquals(100.0, (float) $prestamo->fresh()->saldo_pendiente);
        $this->assertSame('APLICADO', NominaPrestamoPlan::query()->where('cuota_id', $cuota->id)->value('estado'));
        $registro = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        $this->assertEquals(10.0, (float) $registro->total_deducciones);

        Carbon::setTestNow();
    }

    public function test_plan_de_prestamo_puede_descontarse_de_comision(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20'));
        $this->actingAs($this->rrhh);

        $this->empleado->update(['modo_comision' => NominaEmpleado::COMISION_VENTAS_PROPIAS]);

        $loan = app(\App\Services\Nomina\LoanService::class);
        $prestamo = $loan->create($this->empleado, [
            'fecha' => '2026-08-16',
            'monto_original' => 110,
            'numero_cuotas' => 4,
            'frecuencia' => 'QUINCENAL',
            'fecha_inicio' => '2026-08-16',
            'motivo' => 'Prestamo personal',
        ], $this->rrhh->id);
        $cuota = $prestamo->cuotas()->orderBy('numero')->firstOrFail();

        $this->post(route('nomina.prestamos.programar'), [
            'descuentos' => [
                $cuota->id => [
                    'aplicar' => '1',
                    'cuota_id' => $cuota->id,
                    'monto' => '27.50',
                    'destino' => 'COMISION',
                ],
            ],
        ])->assertRedirect();

        $this->post(route('nomina.periodos.store'), ['fecha' => '2026-08-20'])->assertRedirect();
        $periodo = NominaPeriodo::query()->firstOrFail();
        $this->post(route('nomina.periodos.calcular', $periodo))->assertRedirect();

        $this->assertEquals(82.5, (float) $prestamo->fresh()->saldo_pendiente);
        $registro = NominaRegistro::query()->where('empleado_id', $this->empleado->id)->firstOrFail();
        $this->assertEquals(0.0, (float) $registro->total_deducciones);
        $liquidacion = NominaLiquidacionComision::query()
            ->where('empleado_id', $this->empleado->id)
            ->where('periodo_id', $periodo->id)
            ->firstOrFail();
        $this->assertEquals(27.5, (float) $liquidacion->prestamos);
        $this->assertDatabaseHas('nomina_prestamo_planes', [
            'cuota_id' => $cuota->id,
            'destino' => NominaPrestamoPlan::DESTINO_COMISION,
            'estado' => NominaPrestamoPlan::APLICADO,
        ]);

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
