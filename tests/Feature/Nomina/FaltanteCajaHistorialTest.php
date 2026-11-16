<?php

namespace Tests\Feature\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaCargo;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaEmpleado;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class FaltanteCajaHistorialTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        Cache::put('tasa_bcv_'.now()->toDateString(), 40, 3600);
    }

    public function test_historial_muestra_faltantes_de_cualquier_empleado(): void
    {
        $user = User::create([
            'name' => 'RRHH Faltante',
            'email' => 'rrhh-faltante@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $cargo = NominaCargo::create(['nombre' => 'Cajera', 'estado' => 'ACTIVO']);
        $ana = $this->empleado('29100001', 'Ana Cajera', $cargo);
        $luis = $this->empleado('29100002', 'Luis Call Center', NominaCargo::create(['nombre' => 'Call Center', 'estado' => 'ACTIVO']));

        NominaComisionDescuento::create([
            'empleado_id' => $ana->id,
            'fecha' => '2026-11-10',
            'tipo' => 'FALTANTE',
            'monto' => 15.90,
            'motivo' => 'Faltante de caja: turno mañana',
            'estado' => 'PENDIENTE',
            'decision' => NominaComisionDescuento::DECISION_PENDIENTE,
            'created_by' => $user->id,
        ]);
        NominaComisionDescuento::create([
            'empleado_id' => $ana->id,
            'fecha' => '2026-11-12',
            'tipo' => 'FALTANTE',
            'monto' => -5.00,
            'motivo' => 'Ajuste (resta): corrección',
            'estado' => 'PENDIENTE',
            'decision' => NominaComisionDescuento::DECISION_PENDIENTE,
            'created_by' => $user->id,
        ]);
        NominaComisionDescuento::create([
            'empleado_id' => $luis->id,
            'fecha' => '2026-11-11',
            'tipo' => 'FALTANTE',
            'monto' => 8.00,
            'motivo' => 'Faltante de caja: Luis',
            'estado' => 'PENDIENTE',
            'decision' => NominaComisionDescuento::DECISION_PENDIENTE,
        ]);

        $this->actingAs($user)
            ->get(route('nomina.faltante_caja.historial'))
            ->assertOk()
            ->assertSee('Historial de faltante de caja');

        $this->actingAs($user)
            ->get(route('nomina.faltante_caja.historial', ['q' => 'Ana']))
            ->assertOk()
            ->assertSee('Ana Cajera')
            ->assertSee('turno mañana')
            ->assertSee('corrección')
            ->assertDontSee('Faltante de caja: Luis');

        $this->actingAs($user)
            ->get(route('nomina.faltante_caja.historial', $luis))
            ->assertOk()
            ->assertSee('Luis Call Center')
            ->assertSee('Faltante de caja: Luis')
            ->assertDontSee('turno mañana');

        $this->actingAs($user)
            ->get(route('nomina.faltante_caja.index'))
            ->assertOk()
            ->assertSee(route('nomina.faltante_caja.historial', $ana), false)
            ->assertSee('Historial por persona');
    }

    private function empleado(string $cedula, string $nombre, NominaCargo $cargo): NominaEmpleado
    {
        $cliente = Cliente::create(['cedula' => $cedula, 'nombre' => $nombre]);

        return NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'cargo_id' => $cargo->id,
            'salario_base' => 400,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'fecha_ingreso' => '2025-01-01',
        ]);
    }
}
