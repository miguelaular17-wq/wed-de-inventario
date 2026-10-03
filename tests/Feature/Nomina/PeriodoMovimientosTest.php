<?php

namespace Tests\Feature\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaEmpleadoAjuste;
use App\Models\Nomina\NominaHoraExtra;
use App\Models\Nomina\NominaInasistencia;
use App\Models\Nomina\NominaPeriodo;
use App\Models\User;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class PeriodoMovimientosTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
    }

    public function test_registra_horas_ias_adelanto_y_bono_en_la_quincena(): void
    {
        [$user, $empleado, $periodo] = $this->escena();

        $this->actingAs($user)->post(route('nomina.periodos.movimientos.store', $periodo), [
            'concepto' => 'extras',
            'empleado_id' => $empleado->id,
            'unidad' => 'DIAS',
            'horas' => 1,
            'motivo' => 'Feriado',
        ])->assertRedirect(route('nomina.periodos.show', ['periodo' => $periodo, 'ajuste' => 'extras']));

        $this->actingAs($user)->post(route('nomina.periodos.movimientos.store', $periodo), [
            'concepto' => 'ias',
            'empleado_id' => $empleado->id,
            'cantidad' => 1,
            'motivo' => 'Falta',
        ])->assertRedirect(route('nomina.periodos.show', ['periodo' => $periodo, 'ajuste' => 'ias']));

        $this->actingAs($user)->post(route('nomina.periodos.movimientos.store', $periodo), [
            'concepto' => 'adelanto',
            'empleado_id' => $empleado->id,
            'monto' => 40,
            'motivo' => 'Adelanto',
        ])->assertRedirect(route('nomina.periodos.show', ['periodo' => $periodo, 'ajuste' => 'adelantos']));

        $this->actingAs($user)->post(route('nomina.periodos.movimientos.store', $periodo), [
            'concepto' => 'bono',
            'empleado_id' => $empleado->id,
            'monto' => 25,
            'motivo' => 'Bono de nómina',
        ])->assertRedirect(route('nomina.periodos.show', ['periodo' => $periodo, 'ajuste' => 'bonos']));

        $this->assertSame(1, NominaHoraExtra::query()->count());
        $this->assertSame('DIAS', NominaHoraExtra::query()->first()->unidad);
        $this->assertSame(1, NominaInasistencia::query()->count());
        $this->assertSame(1, NominaAbonoSueldo::query()->count());
        $bono = NominaEmpleadoAjuste::query()->first();
        $this->assertSame(NominaEmpleadoAjuste::TIPO_BONIFICACION, $bono->tipo);
        $this->assertSame(NominaEmpleadoAjuste::DESTINO_NOMINA, $bono->destino);
    }

    public function test_aplicar_seleccion_quita_el_adelanto_desmarcado(): void
    {
        [$user, $empleado, $periodo] = $this->escena();
        $queda = NominaAbonoSueldo::create([
            'empleado_id' => $empleado->id,
            'fecha' => '2026-10-10',
            'monto' => 10,
            'quincena_inicio' => '2026-10-01',
            'quincena_fin' => '2026-10-15',
            'etiqueta' => '01/10/2026 al 15/10/2026',
            'estado' => 'PENDIENTE',
            'motivo' => 'Se queda',
        ]);
        $sale = NominaAbonoSueldo::create([
            'empleado_id' => $empleado->id,
            'fecha' => '2026-10-11',
            'monto' => 20,
            'quincena_inicio' => '2026-10-01',
            'quincena_fin' => '2026-10-15',
            'etiqueta' => '01/10/2026 al 15/10/2026',
            'estado' => 'PENDIENTE',
            'motivo' => 'Sale',
        ]);

        $this->actingAs($user)->post(route('nomina.periodos.movimientos.aplicar', $periodo), [
            'tab' => 'adelantos',
            'adelanto_ids' => [$queda->id],
        ])->assertRedirect(route('nomina.periodos.show', ['periodo' => $periodo, 'ajuste' => 'adelantos']));

        $this->assertSame('PENDIENTE', $queda->fresh()->estado);
        $this->assertSame('CANCELADO', $sale->fresh()->estado);
        $this->assertNull($sale->fresh()->nomina_periodo_id);
    }

    public function test_la_pagina_muestra_los_apartados_del_ajuste(): void
    {
        [$user, , $periodo] = $this->escena();

        $this->actingAs($user)
            ->get(route('nomina.periodos.show', $periodo))
            ->assertOk()
            ->assertSee('Ajustes de la quincena')
            ->assertSee('Horas extras')
            ->assertSee('IAS')
            ->assertSee('Adelantos')
            ->assertSee('Bonificaciones')
            ->assertSee('Deducciones')
            ->assertSee('Totales por sede y área');
    }

    /**
     * @return array{0: User, 1: NominaEmpleado, 2: NominaPeriodo}
     */
    private function escena(): array
    {
        $user = User::create([
            'name' => 'RRHH Mov',
            'email' => 'rrhh-mov-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $cliente = Cliente::create([
            'cedula' => (string) random_int(41000000, 41999999),
            'nombre' => 'Luis Nomina',
        ]);
        $empleado = NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'salario_base' => 600,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'modo_comision' => NominaEmpleado::COMISION_NINGUNA,
            'fecha_ingreso' => '2025-01-01',
        ]);
        $periodo = NominaPeriodo::create([
            'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2026-10-15',
            'etiqueta' => '01/10/2026 al 15/10/2026',
            'estado' => NominaPeriodo::ABIERTO,
            'tasa_bcv' => 40,
        ]);

        return [$user, $empleado, $periodo];
    }
}
