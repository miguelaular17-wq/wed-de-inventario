<?php

namespace Tests\Feature\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaEmpleadoAjuste;
use App\Models\Nomina\NominaPeriodo;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class QuitarBonoComisionTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        Cache::put('tasa_bcv_'.now()->toDateString(), 40, 3600);
    }

    public function test_quita_un_bono_ya_aplicado_en_la_quincena_de_comision(): void
    {
        $user = User::create([
            'name' => 'RRHH Bonos',
            'email' => 'rrhh-bonos@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $cliente = Cliente::create(['cedula' => '28000001', 'nombre' => 'Ana Bono']);
        $empleado = NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'salario_base' => 500,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'modo_comision' => NominaEmpleado::COMISION_NINGUNA,
            'fecha_ingreso' => '2025-01-01',
        ]);
        $periodo = NominaPeriodo::create([
            'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2026-10-15',
            'etiqueta' => '01/10/2026 al 15/10/2026',
            'estado' => NominaPeriodo::CALCULADO,
            'tasa_bcv' => 40,
        ]);
        $bono = NominaEmpleadoAjuste::create([
            'empleado_id' => $empleado->id,
            'fecha' => '2026-10-02',
            'tipo' => NominaEmpleadoAjuste::TIPO_BONIFICACION,
            'destino' => NominaEmpleadoAjuste::DESTINO_COMISION,
            'monto' => 25,
            'quincena_inicio' => '2026-10-01',
            'quincena_fin' => '2026-10-15',
            'etiqueta' => '01/10/2026 al 15/10/2026',
            'estado' => NominaEmpleadoAjuste::APLICADO,
            'nomina_periodo_id' => $periodo->id,
            'motivo' => 'Meta de venta',
        ]);

        $this->actingAs($user)
            ->get(route('nomina.comisiones.show', $periodo))
            ->assertOk()
            ->assertSee('Meta de venta')
            ->assertSee('Aplicar selección');

        $this->actingAs($user)
            ->post(route('nomina.comisiones.bonos.quitar', [$periodo, $bono]))
            ->assertRedirect(route('nomina.comisiones.show', $periodo));

        $bono->refresh();
        $this->assertSame(NominaEmpleadoAjuste::CANCELADO, $bono->estado);
        $this->assertNull($bono->nomina_periodo_id);

        $this->actingAs($user)
            ->get(route('nomina.comisiones.show', $periodo))
            ->assertOk()
            ->assertDontSee('Meta de venta');
    }

    public function test_aplicar_seleccion_deja_el_marcado_y_quita_el_otro(): void
    {
        $user = User::create([
            'name' => 'RRHH Bonos 2',
            'email' => 'rrhh-bonos2@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $cliente = Cliente::create(['cedula' => '28000002', 'nombre' => 'Luis Bono']);
        $empleado = NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'salario_base' => 500,
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
        $base = [
            'empleado_id' => $empleado->id,
            'fecha' => '2026-10-02',
            'tipo' => NominaEmpleadoAjuste::TIPO_BONIFICACION,
            'destino' => NominaEmpleadoAjuste::DESTINO_COMISION,
            'quincena_inicio' => '2026-10-01',
            'quincena_fin' => '2026-10-15',
            'etiqueta' => '01/10/2026 al 15/10/2026',
            'estado' => NominaEmpleadoAjuste::APLICADO,
            'nomina_periodo_id' => $periodo->id,
        ];
        $seQueda = NominaEmpleadoAjuste::create($base + ['monto' => 10, 'motivo' => 'Se queda']);
        $seVa = NominaEmpleadoAjuste::create($base + ['monto' => 20, 'motivo' => 'Se va']);

        $this->actingAs($user)
            ->post(route('nomina.comisiones.bonos.aplicar', $periodo), [
                'ajuste_ids' => [$seQueda->id],
            ])
            ->assertRedirect(route('nomina.comisiones.show', $periodo));

        $this->assertSame(NominaEmpleadoAjuste::APLICADO, $seQueda->fresh()->estado);
        $this->assertSame(NominaEmpleadoAjuste::CANCELADO, $seVa->fresh()->estado);
        $this->assertNull($seVa->fresh()->nomina_periodo_id);
    }
}
