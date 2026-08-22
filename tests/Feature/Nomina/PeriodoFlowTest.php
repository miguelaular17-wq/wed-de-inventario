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
