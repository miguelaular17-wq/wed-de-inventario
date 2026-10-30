<?php

namespace Tests\Feature\Nomina;

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaInasistencia;
use App\Models\Nomina\NominaSede;
use App\Models\User;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class InasistenciasModuloTest extends TestCase
{
    use CreatesNominaSchema;

    private User $rrhh;

    private NominaSede $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        $this->rrhh = User::create([
            'name' => 'RRHH',
            'email' => 'rrhh-inas@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $this->sede = NominaSede::create(['nombre' => 'Centro', 'codigo' => 'CENTRO', 'estado' => 'ACTIVO']);
        $this->actingAs($this->rrhh);
    }

    private function empleado(string $cedula, string $nombre, float $salario = 300): NominaEmpleado
    {
        $this->post(route('nomina.empleados.store'), [
            'cedula' => $cedula,
            'nombre' => $nombre,
            'salario_base' => $salario,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'sede_id' => $this->sede->id,
        ])->assertRedirect();

        return NominaEmpleado::query()->latest('id')->firstOrFail();
    }

    public function test_marca_a_varios_y_no_repite_el_mismo_dia(): void
    {
        $ana = $this->empleado('20111222', 'Ana Ruiz');
        $luis = $this->empleado('20333444', 'Luis Mora', 600);

        $this->get(route('nomina.inasistencias.index', ['sede_id' => $this->sede->id, 'fecha' => '2026-10-27']))
            ->assertOk()
            ->assertSee('Ana Ruiz')
            ->assertSee('Luis Mora')
            ->assertSee('$10.00');

        $this->post(route('nomina.inasistencias.masivas'), [
            'sede_id' => $this->sede->id,
            'fecha' => '2026-10-27',
            'cantidad' => 1,
            'motivo' => 'Sin justificar',
            'empleado_ids' => [$ana->id, $luis->id],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(2, NominaInasistencia::query()->count());
        $this->assertEquals(10.0, (float) NominaInasistencia::query()->where('empleado_id', $ana->id)->value('monto'));
        $this->assertEquals(20.0, (float) NominaInasistencia::query()->where('empleado_id', $luis->id)->value('monto'));

        $this->post(route('nomina.inasistencias.masivas'), [
            'sede_id' => $this->sede->id,
            'fecha' => '2026-10-27',
            'cantidad' => 1,
            'empleado_ids' => [$ana->id],
        ])->assertRedirect()->assertSessionHas('status', fn ($m) => str_contains($m, 'ya tenía una ese día'));

        $this->assertSame(2, NominaInasistencia::query()->count());

        $this->get(route('nomina.inasistencias.index', ['sede_id' => $this->sede->id, 'fecha' => '2026-10-27']))
            ->assertOk()
            ->assertSee('Ya marcado este día')
            ->assertSee('Sin justificar');
    }

    public function test_cancelar_vuelve_al_modulo_y_excel_descarga(): void
    {
        $ana = $this->empleado('20111222', 'Ana Ruiz');

        $this->post(route('nomina.inasistencias.masivas'), [
            'sede_id' => $this->sede->id,
            'fecha' => '2026-10-27',
            'cantidad' => 0.5,
            'empleado_ids' => [$ana->id],
        ])->assertRedirect();

        $this->get(route('nomina.inasistencias.excel', ['fecha' => '2026-10-27']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $falta = NominaInasistencia::query()->firstOrFail();
        $this->assertEquals(5.0, (float) $falta->monto);

        $this->post(route('nomina.inasistencias.cancelar', $falta), [
            'origen' => 'inasistencias',
            'sede_id' => $this->sede->id,
        ])->assertRedirect(route('nomina.inasistencias.index', ['fecha' => '2026-10-27', 'sede_id' => $this->sede->id]));

        $this->assertSame('CANCELADO', $falta->fresh()->estado);
    }

    public function test_pide_marcar_a_alguien(): void
    {
        $this->post(route('nomina.inasistencias.masivas'), [
            'sede_id' => $this->sede->id,
            'fecha' => '2026-10-27',
            'cantidad' => 1,
        ])->assertSessionHasErrors('empleado_ids');
    }
}
