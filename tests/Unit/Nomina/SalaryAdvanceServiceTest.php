<?php

namespace Tests\Unit\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaEmpleado;
use App\Models\User;
use App\Services\Nomina\PayrollDeductionService;
use App\Services\Nomina\SalaryAdvanceService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class SalaryAdvanceServiceTest extends TestCase
{
    use CreatesNominaSchema;

    private NominaEmpleado $empleado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        $user = User::create([
            'name' => 'RRHH',
            'email' => 'abono@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $this->actingAs($user);

        $cliente = Cliente::create(['cedula' => '999002', 'nombre' => 'Abel Yajuris']);
        $this->empleado = NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'salario_base' => 100,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
        ]);
    }

    public function test_asigna_quincena_segun_el_dia(): void
    {
        $service = app(SalaryAdvanceService::class);

        $primera = $service->quincenaDe('2026-08-10');
        $this->assertEquals('2026-08-01', $primera['inicio']->toDateString());
        $this->assertEquals('2026-08-15', $primera['fin']->toDateString());

        $segunda = $service->quincenaDe('2026-08-20');
        $this->assertEquals('2026-08-16', $segunda['inicio']->toDateString());
        $this->assertEquals('2026-08-31', $segunda['fin']->toDateString());
    }

    public function test_crea_abono_sin_prestamo_y_lo_descuenta_una_sola_vez(): void
    {
        $service = app(SalaryAdvanceService::class);
        $payroll = app(PayrollDeductionService::class);

        $abono = $service->create($this->empleado, [
            'fecha' => '2026-08-20',
            'monto' => 40,
            'motivo' => 'Adelanto de quincena',
        ], auth()->id());

        $this->assertEquals('PENDIENTE', $abono->estado);
        $this->assertEquals(40.0, $service->pendientesDe($this->empleado, Carbon::parse('2026-08-20')));
        $this->assertEquals(0.0, $service->pendientesDe($this->empleado, Carbon::parse('2026-08-10')));

        $inicio = Carbon::parse('2026-08-16');
        $fin = Carbon::parse('2026-08-31');

        $payroll->aplicarCuotasDelPeriodo(22, $inicio, $fin, auth()->id());

        $abono->refresh();
        $this->assertEquals('DESCONTADO', $abono->estado);
        $this->assertEquals(22, $abono->nomina_periodo_id);
        $this->assertEquals(0.0, $service->pendientesDe($this->empleado, Carbon::parse('2026-08-20')));

        $this->assertEquals(0, $service->aplicarAPeriodo(23, $inicio, $fin));
        $this->assertEquals(22, $abono->fresh()->nomina_periodo_id);
    }

    public function test_no_permite_cancelar_abono_ya_descontado(): void
    {
        $service = app(SalaryAdvanceService::class);
        $abono = $service->create($this->empleado, [
            'fecha' => '2026-08-05',
            'monto' => 25,
        ], auth()->id());

        $service->aplicarAPeriodo(5, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-15'));

        $this->expectException(ValidationException::class);
        $service->cancelar($abono->fresh());
    }

    public function test_cancelar_conserva_el_registro(): void
    {
        $service = app(SalaryAdvanceService::class);
        $abono = $service->create($this->empleado, [
            'fecha' => '2026-08-05',
            'monto' => 15,
        ], auth()->id());

        $service->cancelar($abono, 'Solicitud anulada');

        $this->assertEquals(1, NominaAbonoSueldo::query()->count());
        $this->assertEquals('CANCELADO', $abono->fresh()->estado);
        $this->assertEquals(0.0, $service->pendientesDe($this->empleado));
    }

    public function test_kpis_acumulan_adelantos_sin_cancelados(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20'));
        $service = app(SalaryAdvanceService::class);
        $service->create($this->empleado, ['fecha' => '2026-08-20', 'monto' => 40], auth()->id());
        $service->create($this->empleado, ['fecha' => '2026-08-05', 'monto' => 10], auth()->id());
        $cancelado = $service->create($this->empleado, ['fecha' => '2026-08-06', 'monto' => 99], auth()->id());
        $service->cancelar($cancelado);
        $descontado = $service->create($this->empleado, ['fecha' => '2026-07-20', 'monto' => 25], auth()->id());
        $descontado->estado = 'DESCONTADO';
        $descontado->save();

        $kpis = $service->kpis();
        $this->assertEquals(75.0, $kpis['acumulado']);
        $this->assertEquals(50.0, $kpis['pendiente']);
        $this->assertEquals(25.0, $kpis['descontado']);
        $this->assertEquals(40.0, $kpis['esta_quincena']);
        $this->assertSame(3, $kpis['cantidad']);

        $resumen = $service->resumenEmpleado($this->empleado->fresh('abonosSueldo'));
        $this->assertEquals(75.0, $resumen['acumulado']);
        Carbon::setTestNow();
    }
}
