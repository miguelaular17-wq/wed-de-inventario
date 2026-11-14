<?php

namespace Tests\Feature\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaRegistro;
use App\Models\Nomina\NominaTasaDiferencial;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class DiferencialTasaNominaTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        Cache::put('tasa_bcv_'.now()->toDateString(), 45, 3600);
    }

    public function test_paga_la_diferencia_de_tasa_solo_a_quienes_no_se_les_cancelo(): void
    {
        $user = User::create([
            'name' => 'RRHH Tasa',
            'email' => 'rrhh-tasa@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $empresa = NominaEmpresa::create(['codigo' => 'E1', 'nombre' => 'Empresa Uno']);
        $periodo = NominaPeriodo::create([
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-15',
            'etiqueta' => '01/11/2026 al 15/11/2026',
            'estado' => NominaPeriodo::CALCULADO,
            'tasa_bcv' => 40,
        ]);

        $ana = $this->empleado('28200001', 'Ana Cajera', $empresa);
        $luis = $this->empleado('28200002', 'Luis Almacén', $empresa);
        $sinPago = $this->empleado('28200003', 'Eva Sin Pago', $empresa);
        $fuera = $this->empleado('28200004', 'Pedro Fuera', $empresa);

        NominaRegistro::create(['periodo_id' => $periodo->id, 'empleado_id' => $ana->id, 'total_pagar' => 100]);
        NominaRegistro::create(['periodo_id' => $periodo->id, 'empleado_id' => $luis->id, 'total_pagar' => 50.50]);
        NominaRegistro::create(['periodo_id' => $periodo->id, 'empleado_id' => $sinPago->id, 'total_pagar' => 0]);
        NominaRegistro::create(['periodo_id' => $periodo->id, 'empleado_id' => $fuera->id, 'total_pagar' => 80]);

        $this->actingAs($user)
            ->get(route('nomina.periodos.diferenciales_tasa.create', $periodo))
            ->assertOk()
            ->assertSee('40.0000', false)
            ->assertSee('45.0000', false)
            ->assertSee('Pedro Fuera')
            ->assertSee('<option value="no" selected>No se le canceló la diferencia</option>', false)
            ->assertSee('Ya se le pagó la diferencia')
            ->assertDontSee('Eva Sin Pago');

        $payload = [
            'motivo' => 'Se pagó con la tasa vieja',
            'fecha_pago' => '2026-11-18',
            'tasa_pagada' => 40,
            'tasa_nueva' => 45,
            'empleados' => [$ana->id, $luis->id],
            'pagado' => [$ana->id => 'no', $luis->id => 'si', $fuera->id => 'no'],
        ];

        $this->actingAs($user)
            ->post(route('nomina.periodos.diferenciales_tasa.store', $periodo), ['pagado' => [$ana->id => 'si', $luis->id => 'si']] + $payload)
            ->assertSessionHasErrors('seleccion');

        $this->actingAs($user)
            ->post(route('nomina.periodos.diferenciales_tasa.store', $periodo), $payload + ['accion' => 'previsualizar'])
            ->assertOk()
            ->assertSee('Vista previa')
            ->assertSee('Ana Cajera');
        $this->assertSame(0, NominaTasaDiferencial::count());

        $this->actingAs($user)
            ->post(route('nomina.periodos.diferenciales_tasa.store', $periodo), ['tasa_nueva' => 39] + $payload)
            ->assertSessionHasErrors('tasa_nueva');

        $respuesta = $this->actingAs($user)
            ->post(route('nomina.periodos.diferenciales_tasa.store', $periodo), $payload + ['accion' => 'guardar']);

        $diferencial = NominaTasaDiferencial::firstOrFail();
        $respuesta->assertRedirect(route('nomina.periodos.diferenciales_tasa.show', [$periodo, $diferencial]));

        $lineas = $diferencial->lineas->keyBy('empleado_id');
        $this->assertCount(2, $lineas);
        $this->assertSame('4000.00', $lineas[$ana->id]->bs_pagado);
        $this->assertSame('4500.00', $lineas[$ana->id]->bs_nuevo);
        $this->assertSame('500.00', $lineas[$ana->id]->diferencia_bs);
        $this->assertTrue($lineas[$ana->id]->no_pagado);
        $this->assertSame(500.0, $lineas[$ana->id]->aPagarBs());
        // Ya se le pagó la diferencia: queda registrada (50,50 × 5) pero no entra al TXT.
        $this->assertFalse($lineas[$luis->id]->no_pagado);
        $this->assertSame('252.50', $lineas[$luis->id]->diferencia_bs);
        $this->assertSame(0.0, $lineas[$luis->id]->aPagarBs());

        $this->actingAs($user)
            ->get(route('nomina.periodos.diferenciales_tasa.show', [$periodo, $diferencial]))
            ->assertOk()
            ->assertSee('Luis Almacén')
            ->assertSee('Descargar TXT');

        $pdf = $this->actingAs($user)
            ->get(route('nomina.periodos.diferenciales_tasa.reporte', [$periodo, $diferencial]))
            ->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->actingAs($user)
            ->get(route('nomina.periodos.diferenciales_tasa.reporte', [$periodo, $diferencial, 'formato' => 'xlsx']))
            ->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $xlsx->headers->get('content-disposition'));

        $txt = $this->actingAs($user)
            ->get(route('nomina.periodos.diferenciales_tasa.banco', [$periodo, $diferencial, $empresa]))
            ->assertOk()
            ->streamedContent();

        $this->assertSame(
            'V028200001  '.str_pad('50000', 21, '0', STR_PAD_LEFT)."18112026\r\n",
            $txt
        );
    }

    public function test_quincena_abierta_no_permite_diferencial(): void
    {
        $user = User::create([
            'name' => 'RRHH Abierta',
            'email' => 'rrhh-abierta@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $periodo = NominaPeriodo::create([
            'fecha_inicio' => '2026-11-16',
            'fecha_fin' => '2026-11-30',
            'etiqueta' => '16/11/2026 al 30/11/2026',
            'estado' => NominaPeriodo::ABIERTO,
        ]);

        $this->actingAs($user)
            ->get(route('nomina.periodos.diferenciales_tasa.create', $periodo))
            ->assertRedirect(route('nomina.periodos.show', $periodo));
    }

    private function empleado(string $cedula, string $nombre, NominaEmpresa $empresa): NominaEmpleado
    {
        $cliente = Cliente::create(['cedula' => $cedula, 'nombre' => $nombre]);

        return NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'empresa_id' => $empresa->id,
            'salario_base' => 500,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'fecha_ingreso' => '2025-01-01',
        ]);
    }
}
