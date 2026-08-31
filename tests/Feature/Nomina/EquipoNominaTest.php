<?php

namespace Tests\Feature\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaRegistro;
use App\Models\Nomina\NominaSede;
use App\Models\User;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class EquipoNominaTest extends TestCase
{
    use CreatesNominaSchema;

    private User $supervisor;

    private NominaEmpleado $aCargo;

    private NominaEmpleado $ajeno;

    private NominaPeriodo $calculado;

    private NominaPeriodo $abierto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        $this->supervisor = User::create([
            'name' => 'Supervisor Equipo',
            'email' => 'sup-equipo@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);

        $fichaSupervisor = NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '1001', 'nombre' => 'Jefe Doral'])->id,
            'user_id' => $this->supervisor->id,
            'salario_base' => 500,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'es_supervisor' => true,
        ]);

        $this->aCargo = NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '1002', 'nombre' => 'Ana Equipo'])->id,
            'salario_base' => 300,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'supervisor_id' => $fichaSupervisor->id,
        ]);

        $this->ajeno = NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '1003', 'nombre' => 'Luis Ajeno'])->id,
            'salario_base' => 400,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
        ]);

        $this->calculado = NominaPeriodo::create([
            'fecha_inicio' => '2026-08-16',
            'fecha_fin' => '2026-08-31',
            'etiqueta' => '16/08/2026 al 31/08/2026',
            'estado' => NominaPeriodo::CALCULADO,
        ]);

        $this->abierto = NominaPeriodo::create([
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-08-15',
            'etiqueta' => '01/08/2026 al 15/08/2026',
            'estado' => NominaPeriodo::ABIERTO,
        ]);

        NominaRegistro::create([
            'periodo_id' => $this->calculado->id,
            'empleado_id' => $this->aCargo->id,
            'salario_base' => 300,
            'total_comisiones' => 50,
            'total_otros_ingresos' => 4,
            'total_deducciones' => 10,
            'total_pagar' => 294,
            'observaciones' => json_encode([
                'horas_extras' => 4,
                'inasistencias' => 0,
                'abonos_sueldo' => 10,
                'liquidacion' => ['total_pagar' => 45],
            ]),
        ]);

        NominaRegistro::create([
            'periodo_id' => $this->calculado->id,
            'empleado_id' => $this->ajeno->id,
            'salario_base' => 400,
            'total_comisiones' => 80,
            'total_pagar' => 400,
            'observaciones' => json_encode(['liquidacion' => ['total_pagar' => 72]]),
        ]);
    }

    public function test_sin_permiso_no_entra(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('nomina.equipo.index'))
            ->assertRedirect('/');
    }

    public function test_con_permiso_ve_solo_su_equipo_y_sin_comision(): void
    {
        $this->supervisor->syncExtraPermissions(['nomina.equipo']);

        $this->actingAs($this->supervisor->fresh())
            ->get(route('nomina.equipo.index'))
            ->assertOk()
            ->assertSee('16/08/2026 al 31/08/2026')
            ->assertDontSee('01/08/2026 al 15/08/2026')
            ->assertSee('294.00');

        $this->get(route('nomina.equipo.show', $this->calculado))
            ->assertOk()
            ->assertSee('Ana Equipo')
            ->assertSee('294.00')
            ->assertDontSee('Luis Ajeno')
            ->assertDontSee('Comisión')
            ->assertDontSee('Mercancía')
            ->assertSee('Bonificaciones')
            ->assertSee('Deducciones')
            ->assertDontSee('50.00');

        $this->get(route('nomina.equipo.show', $this->abierto))->assertNotFound();
        $this->get(route('nomina.periodos.show', $this->calculado))->assertRedirect('/');
    }

    public function test_sin_usuario_en_la_ficha_no_muestra_el_equipo(): void
    {
        $sede = NominaSede::create([
            'nombre' => 'Centro',
            'codigo' => 'CENTRO',
            'tipo' => 'SEDE',
            'estado' => 'ACTIVO',
        ]);

        $user = User::create([
            'name' => 'Carlos Javier Gomez',
            'email' => 'carlos-centro@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'CENTRO',
        ]);
        $user->syncExtraPermissions(['nomina.equipo']);

        NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '2001', 'nombre' => 'CARLOS JAVIER GOMEZ JIMENEZ'])->id,
            'salario_base' => 500,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'es_supervisor' => true,
            'sede_id' => $sede->id,
            'sede' => 'CENTRO',
        ]);

        $this->actingAs($user)
            ->get(route('nomina.equipo.index'))
            ->assertOk()
            ->assertSee('no está en ninguna ficha')
            ->assertDontSee('180.00');
    }

    public function test_supervisor_de_sede_ve_el_equipo_cuando_su_ficha_tiene_usuario(): void
    {
        $sede = NominaSede::create([
            'nombre' => 'Centro',
            'codigo' => 'CENTRO',
            'tipo' => 'SEDE',
            'estado' => 'ACTIVO',
        ]);

        $user = User::create([
            'name' => 'Carlos Javier Gomez',
            'email' => 'carlos-centro@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'CENTRO',
        ]);
        $user->syncExtraPermissions(['nomina.equipo']);

        NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '2001', 'nombre' => 'CARLOS JAVIER GOMEZ JIMENEZ'])->id,
            'user_id' => $user->id,
            'salario_base' => 500,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'es_supervisor' => true,
            'sede_id' => $sede->id,
            'sede' => 'CENTRO',
        ]);

        $asesor = NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '2002', 'nombre' => 'GEOVANNI JESUS GUTIERREZ MARTINEZ'])->id,
            'salario_base' => 200,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'sede_id' => $sede->id,
            'sede' => 'CENTRO',
        ]);

        NominaRegistro::create([
            'periodo_id' => $this->calculado->id,
            'empleado_id' => $asesor->id,
            'salario_base' => 200,
            'total_pagar' => 180,
            'observaciones' => json_encode(['horas_extras' => 2]),
        ]);

        $this->actingAs($user)
            ->get(route('nomina.equipo.index'))
            ->assertOk()
            ->assertSee('180.00')
            ->assertSee('CARLOS JAVIER GOMEZ JIMENEZ');

        $this->get(route('nomina.equipo.show', $this->calculado))
            ->assertOk()
            ->assertSee('GEOVANNI JESUS GUTIERREZ MARTINEZ')
            ->assertSee('180.00')
            ->assertDontSee('Luis Ajeno')
            ->assertDontSee('Comisión');
    }
}
