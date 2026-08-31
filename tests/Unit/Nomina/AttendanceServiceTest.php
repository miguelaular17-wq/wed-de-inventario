<?php

namespace Tests\Unit\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaHoraExtra;
use App\Models\Nomina\NominaInasistencia;
use App\Models\Nomina\NominaSede;
use App\Models\User;
use App\Services\Nomina\AttendanceService;
use App\Services\Nomina\PayrollDeductionService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use CreatesNominaSchema;

    private NominaEmpleado $empleado;

    private AttendanceService $attendance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        $user = User::create([
            'name' => 'RRHH',
            'email' => 'asistencia@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $this->actingAs($user);

        $cliente = Cliente::create(['cedula' => '999003', 'nombre' => 'Abel Yajuris']);
        $this->empleado = NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'salario_base' => 100,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
        ]);

        $this->attendance = app(AttendanceService::class);
        $this->attendance->guardarTarifasEmpresa([
            'valor_hora_extra' => 5,
        ]);
    }

    public function test_faltó_hoy_descuenta_un_dia(): void
    {
        $this->attendance->marcarFaltoHoy($this->empleado, auth()->id());

        $resumen = $this->attendance->resumenQuincena($this->empleado);
        $this->assertEquals(1.0, $resumen['dias']);
        $this->assertEquals(3.33, $resumen['monto_ausencias']);
        $this->assertTrue($resumen['ya_falto_hoy']);

        $this->expectException(ValidationException::class);
        $this->attendance->marcarFaltoHoy($this->empleado, auth()->id());
    }

    public function test_inasistencia_divide_el_salario_base_entre_treinta(): void
    {
        $this->attendance->registrarInasistencia($this->empleado, [
            'fecha' => '2026-08-18',
            'cantidad' => 2,
            'motivo' => 'IAS',
        ], auth()->id());

        $this->assertEquals(6.66, (float) NominaInasistencia::query()->value('monto'));
        $this->assertEquals(3.33, (float) NominaInasistencia::query()->value('valor_unitario'));
    }

    public function test_salario_mensual_se_divide_directamente_entre_treinta(): void
    {
        $this->empleado->update([
            'salario_base' => 300,
            'tipo_salario' => 'MENSUAL',
        ]);

        $this->assertEquals(10.0, $this->attendance->valorDia($this->empleado->fresh()));
    }

    public function test_horas_extras_usan_valor_por_hora(): void
    {
        $this->attendance->registrarHorasExtras($this->empleado, [
            'fecha' => '2026-08-20',
            'horas' => 3,
        ], auth()->id());

        $resumen = $this->attendance->resumenQuincena($this->empleado, '2026-08-20');
        $this->assertEquals(3.0, $resumen['horas']);
        $this->assertEquals(15.0, $resumen['monto_horas']);
        $this->assertSame('HORAS', NominaHoraExtra::query()->value('unidad'));
        $this->assertEquals(5.0, (float) NominaHoraExtra::query()->value('valor_unitario'));
    }

    public function test_hora_extra_supervisor_vale_uno_con_cincuenta(): void
    {
        $this->empleado->update(['es_supervisor' => true]);
        $this->attendance->guardarTarifasEmpresa([
            'valor_hora_extra_trabajador' => 1,
            'valor_hora_extra_supervisor' => 1.5,
        ]);

        $this->attendance->registrarHorasExtras($this->empleado->fresh(), [
            'fecha' => '2026-08-20',
            'unidad' => 'HORAS',
            'horas' => 2,
        ], auth()->id());

        $row = NominaHoraExtra::query()->first();
        $this->assertEquals(1.5, (float) $row->valor_unitario);
        $this->assertEquals(3.0, (float) $row->monto);
    }

    public function test_aplica_horas_extras_a_varios_de_una_sede(): void
    {
        $sede = NominaSede::create([
            'nombre' => 'Doral',
            'codigo' => 'DORAL',
            'tipo' => 'SEDE',
            'estado' => 'ACTIVO',
        ]);
        $otra = NominaSede::create([
            'nombre' => 'Zamora',
            'codigo' => 'ZAMORA',
            'tipo' => 'SEDE',
            'estado' => 'ACTIVO',
        ]);

        $this->empleado->update(['sede_id' => $sede->id, 'es_supervisor' => false]);
        $supervisor = NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '999004', 'nombre' => 'Supervisor Doral'])->id,
            'salario_base' => 200,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'sede_id' => $sede->id,
            'es_supervisor' => true,
        ]);
        NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '999005', 'nombre' => 'Otro sitio'])->id,
            'salario_base' => 100,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'sede_id' => $otra->id,
        ]);

        $this->attendance->guardarTarifasEmpresa([
            'valor_hora_extra_trabajador' => 1,
            'valor_hora_extra_supervisor' => 1.5,
        ]);

        $creados = $this->attendance->registrarHorasExtrasMasivas([], [
            'sede_id' => $sede->id,
            'alcance' => 'TODOS',
            'fecha' => '2026-08-20',
            'unidad' => 'HORAS',
            'horas' => 4,
        ], auth()->id());

        $this->assertCount(2, $creados);
        $this->assertEquals(4.0, (float) NominaHoraExtra::query()->where('empleado_id', $this->empleado->id)->value('monto'));
        $this->assertEquals(6.0, (float) NominaHoraExtra::query()->where('empleado_id', $supervisor->id)->value('monto'));
        $this->assertEquals(2, NominaHoraExtra::query()->count());
    }

    public function test_dias_extras_usan_salario_entre_treinta(): void
    {
        $this->attendance->registrarHorasExtras($this->empleado, [
            'fecha' => '2026-08-20',
            'unidad' => 'DIAS',
            'horas' => 2,
        ], auth()->id());

        $row = NominaHoraExtra::query()->first();
        $this->assertSame('DIAS', $row->unidad);
        $this->assertEquals(3.33, (float) $row->valor_unitario);
        $this->assertEquals(6.66, (float) $row->monto);
    }

    public function test_no_aplica_dos_veces_en_nomina(): void
    {
        $this->attendance->registrarInasistencia($this->empleado, [
            'fecha' => '2026-08-20',
            'cantidad' => 1,
        ], auth()->id());
        $this->attendance->registrarHorasExtras($this->empleado, [
            'fecha' => '2026-08-20',
            'horas' => 2,
        ], auth()->id());

        $payroll = app(PayrollDeductionService::class);
        $inicio = Carbon::parse('2026-08-16');
        $fin = Carbon::parse('2026-08-31');

        $this->assertEquals(2, $this->attendance->aplicarAPeriodo(30, $inicio, $fin));
        $this->assertEquals(0, $this->attendance->aplicarAPeriodo(31, $inicio, $fin));
        $this->assertEquals('APLICADO', NominaInasistencia::query()->value('estado'));
        $this->assertEquals(30, NominaHoraExtra::query()->value('nomina_periodo_id'));

        $payroll->aplicarCuotasDelPeriodo(32, $inicio, $fin, auth()->id());
        $this->assertEquals(30, NominaInasistencia::query()->value('nomina_periodo_id'));
    }
}
