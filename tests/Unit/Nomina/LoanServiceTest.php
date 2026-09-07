<?php

namespace Tests\Unit\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPrestamoAbono;
use App\Models\Nomina\NominaPrestamoCuota;
use App\Models\User;
use App\Services\Nomina\LoanPaymentService;
use App\Services\Nomina\LoanService;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use CreatesNominaSchema;

    private NominaEmpleado $empleado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        $user = User::create([
            'name' => 'RRHH',
            'email' => 'loan@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $this->actingAs($user);

        $cliente = Cliente::create(['cedula' => '999001', 'nombre' => 'Juan Perez']);
        $this->empleado = NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'salario_base' => 800,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
        ]);
    }

    public function test_crea_prestamo_libre_y_abonos_reducen_saldo(): void
    {
        $loan = app(LoanService::class);
        $payments = app(LoanPaymentService::class);

        $prestamo = $loan->create($this->empleado, [
            'fecha' => '2026-08-01',
            'monto_original' => 2000,
            'motivo' => 'Prueba',
        ], auth()->id());

        $this->assertTrue($prestamo->sinCuotas());
        $this->assertCount(0, $prestamo->cuotas);
        $this->assertSame('LIBRE', $prestamo->frecuencia);
        $this->assertEquals(2000.0, (float) $prestamo->saldo_pendiente);

        for ($i = 0; $i < 3; $i++) {
            $payments->registrarAbono($prestamo->fresh(), [
                'fecha' => '2026-08-15',
                'monto' => 100,
                'tipo' => NominaPrestamoAbono::TIPO_EXTRAORDINARIO,
            ], auth()->id());
        }

        $prestamo->refresh();
        $this->assertEquals(1700.0, (float) $prestamo->saldo_pendiente);
        $this->assertCount(3, $prestamo->abonos);
        $this->assertCount(0, $prestamo->cuotas);
    }

    public function test_convertir_todos_elimina_cuotas_y_conserva_abonos(): void
    {
        $loan = app(LoanService::class);
        $payments = app(LoanPaymentService::class);

        $prestamo = $loan->create($this->empleado, [
            'fecha' => '2026-09-01',
            'monto_original' => 100,
            'motivo' => 'Viejo',
        ], auth()->id());

        // Simula préstamo antiguo con cuotas.
        $prestamo->numero_cuotas = 2;
        $prestamo->valor_cuota = 50;
        $prestamo->frecuencia = 'QUINCENAL';
        $prestamo->save();
        NominaPrestamoCuota::create([
            'prestamo_id' => $prestamo->id,
            'numero' => 1,
            'fecha_programada' => '2026-09-01',
            'monto' => 50,
            'monto_pagado' => 0,
            'estado' => 'PENDIENTE',
        ]);
        NominaPrestamoCuota::create([
            'prestamo_id' => $prestamo->id,
            'numero' => 2,
            'fecha_programada' => '2026-09-15',
            'monto' => 50,
            'monto_pagado' => 0,
            'estado' => 'PENDIENTE',
        ]);

        $payments->registrarAbono($prestamo->fresh(), [
            'fecha' => '2026-09-04',
            'monto' => 25,
            'tipo' => NominaPrestamoAbono::TIPO_EFECTIVO,
        ], auth()->id());

        $convertidos = $loan->convertirTodosAModoLibre();
        $this->assertGreaterThanOrEqual(1, $convertidos);

        $prestamo->refresh();
        $this->assertSame(0, (int) $prestamo->numero_cuotas);
        $this->assertSame('LIBRE', $prestamo->frecuencia);
        $this->assertCount(0, $prestamo->cuotas);
        $this->assertCount(1, $prestamo->abonos);
        $this->assertEquals(75.0, (float) $prestamo->saldo_pendiente);
    }

    public function test_prestamo_libre_cierra_al_pagar_total(): void
    {
        $loan = app(LoanService::class);
        $payments = app(LoanPaymentService::class);

        $prestamo = $loan->create($this->empleado, [
            'fecha' => '2026-09-01',
            'monto_original' => 100,
            'motivo' => 'Libre',
        ], auth()->id());

        $payments->registrarAbono($prestamo->fresh(), [
            'fecha' => '2026-09-04',
            'monto' => 40,
            'tipo' => NominaPrestamoAbono::TIPO_EFECTIVO,
        ], auth()->id());

        $payments->registrarAbono($prestamo->fresh(), [
            'fecha' => '2026-09-10',
            'monto' => 60,
            'tipo' => NominaPrestamoAbono::TIPO_NOMINA,
        ], auth()->id());

        $prestamo->refresh();
        $this->assertEquals(0.0, (float) $prestamo->saldo_pendiente);
        $this->assertSame('PAGADO', $prestamo->estado);
        $this->assertCount(2, $prestamo->abonos);
    }
}
