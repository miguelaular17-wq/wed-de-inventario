<?php

namespace Tests\Unit\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPrestamoAbono;
use App\Models\User;
use App\Services\Nomina\LoanPaymentService;
use App\Services\Nomina\LoanService;
use App\Services\Nomina\PayrollDeductionService;
use Carbon\Carbon;
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

    public function test_genera_calendario_y_abonos_reducen_saldo(): void
    {
        $loan = app(LoanService::class);
        $payments = app(LoanPaymentService::class);

        $prestamo = $loan->create($this->empleado, [
            'fecha' => '2026-08-01',
            'monto_original' => 2000,
            'numero_cuotas' => 20,
            'frecuencia' => 'QUINCENAL',
            'fecha_inicio' => '2026-08-01',
            'motivo' => 'Prueba',
        ], auth()->id());

        $this->assertCount(20, $prestamo->cuotas);
        $this->assertEquals(100.0, (float) $prestamo->valor_cuota);
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
        $this->assertEquals(3, $prestamo->cuotas()->where('estado', 'PAGADA')->count());
    }

    public function test_no_descuenta_dos_veces_la_misma_cuota_en_nomina(): void
    {
        $loan = app(LoanService::class);
        $payroll = app(PayrollDeductionService::class);

        $prestamo = $loan->create($this->empleado, [
            'fecha' => '2026-08-01',
            'monto_original' => 200,
            'numero_cuotas' => 2,
            'frecuencia' => 'QUINCENAL',
            'fecha_inicio' => '2026-08-01',
        ]);

        $inicio = Carbon::parse('2026-08-01');
        $fin = Carbon::parse('2026-08-15');

        $primero = $payroll->aplicarCuotasDelPeriodo(10, $inicio, $fin, auth()->id());
        $segundo = $payroll->aplicarCuotasDelPeriodo(10, $inicio, $fin, auth()->id());

        $this->assertCount(1, $primero);
        $this->assertCount(0, $segundo);
        $this->assertEquals(100.0, (float) $prestamo->fresh()->saldo_pendiente);
        $this->assertEquals(10, $prestamo->cuotas()->first()->nomina_periodo_id);
    }
}
