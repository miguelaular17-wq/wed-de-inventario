<?php

namespace Tests\Feature\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaComisionDiferencial;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaEmpresa;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class DiferencialComisionTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        Cache::put('tasa_bcv_'.now()->toDateString(), 40, 3600);
    }

    public function test_diferencial_paga_no_pagados_completo_y_al_resto_solo_la_diferencia(): void
    {
        $user = User::create([
            'name' => 'RRHH Diferencial',
            'email' => 'rrhh-dif@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $empresa = NominaEmpresa::create(['codigo' => 'E1', 'nombre' => 'Empresa Uno']);
        $periodo = NominaPeriodo::create([
            'fecha_inicio' => '2026-10-16',
            'fecha_fin' => '2026-10-31',
            'etiqueta' => '16/10/2026 al 31/10/2026',
            'estado' => NominaPeriodo::CALCULADO,
            'tasa_bcv' => 40,
        ]);

        $vendedor = $this->empleado('28100001', 'Ana Vendedora', NominaEmpleado::COMISION_VENTAS_PROPIAS, $empresa);
        $supervisor = $this->empleado('28100002', 'Luis Supervisor', NominaEmpleado::COMISION_SUPERVISOR_SEDE, $empresa);
        $otro = $this->empleado('28100003', 'Eva Fuera', NominaEmpleado::COMISION_VENTAS_PROPIAS, $empresa);

        NominaLiquidacionComision::create([
            'periodo_id' => $periodo->id, 'empleado_id' => $vendedor->id, 'modo' => NominaEmpleado::COMISION_VENTAS_PROPIAS,
            'base_total' => 1500, 'base_telefonia' => 1000, 'base_otros' => 500,
            'pct_telefonia' => 0.1, 'pct_otros' => 1, 'comision_telefonia' => 1.00, 'comision_otros' => 5.00,
            'comision_total' => 6.00, 'abonos' => 0, 'retencion_pct' => 10, 'retencion' => 0.60,
            'descuentos' => 0, 'prestamos' => 0, 'total_pagar' => 5.40,
        ]);
        NominaLiquidacionComision::create([
            'periodo_id' => $periodo->id, 'empleado_id' => $supervisor->id, 'modo' => NominaEmpleado::COMISION_SUPERVISOR_SEDE,
            'base_total' => 10000, 'comision_total' => 5.00, 'abonos' => 0, 'retencion_pct' => 10, 'retencion' => 0.50,
            'descuentos' => 1.00, 'prestamos' => 0, 'total_pagar' => 3.50,
        ]);
        NominaLiquidacionComision::create([
            'periodo_id' => $periodo->id, 'empleado_id' => $otro->id, 'modo' => NominaEmpleado::COMISION_VENTAS_PROPIAS,
            'base_total' => 100, 'base_otros' => 100, 'pct_otros' => 1, 'comision_otros' => 1,
            'comision_total' => 1, 'retencion_pct' => 10, 'retencion' => 0.10, 'total_pagar' => 0.90,
        ]);

        $this->actingAs($user)
            ->get(route('nomina.comisiones.diferenciales.create', $periodo))
            ->assertOk()
            ->assertSee('Eva Fuera');

        $payload = [
            'motivo' => 'Ajuste telefonía',
            'fecha_pago' => '2026-11-05',
            'tasa_bcv' => 40,
            'config' => [
                'comision_telefonia_pct' => 0.2,
                'comision_otros_pct' => 1,
                'comision_supervisor_pct' => 0.05,
                'retencion_comision_pct' => 10,
            ],
            'empleados' => [$vendedor->id, $supervisor->id],
            'pagado' => [$vendedor->id => 'si', $supervisor->id => 'no', $otro->id => 'no'],
        ];

        $this->actingAs($user)
            ->post(route('nomina.comisiones.diferenciales.store', $periodo), $payload + ['accion' => 'previsualizar'])
            ->assertOk()
            ->assertSee('Vista previa');
        $this->assertSame(0, NominaComisionDiferencial::count());

        $respuesta = $this->actingAs($user)
            ->post(route('nomina.comisiones.diferenciales.store', $periodo), $payload + ['accion' => 'guardar']);

        $diferencial = NominaComisionDiferencial::firstOrFail();
        $respuesta->assertRedirect(route('nomina.comisiones.diferenciales.show', [$periodo, $diferencial]));

        $lineas = $diferencial->lineas->keyBy('empleado_id');
        $this->assertCount(2, $lineas);

        // Telefonía 0,1% → 0,2%: comisión 7,00, retención 0,70, total 6,30; ya cobró 5,40.
        $this->assertSame('7.00', $lineas[$vendedor->id]->comision_ajustada);
        $this->assertSame('6.30', $lineas[$vendedor->id]->total_ajustado);
        $this->assertSame('0.90', $lineas[$vendedor->id]->diferencia);
        $this->assertFalse($lineas[$vendedor->id]->no_pagado);

        // No cobró: se le paga completo (5,00 − 0,50 ret − 1,00 desc).
        $this->assertSame('3.50', $lineas[$supervisor->id]->diferencia);
        $this->assertTrue($lineas[$supervisor->id]->no_pagado);

        $this->actingAs($user)
            ->get(route('nomina.comisiones.diferenciales.show', [$periodo, $diferencial]))
            ->assertOk()
            ->assertSee('Ana Vendedora')
            ->assertSee('Descargar TXT');

        $pdf = $this->actingAs($user)
            ->get(route('nomina.comisiones.diferenciales.reporte', [$periodo, $diferencial]))
            ->assertOk();
        $this->assertStringContainsString('.pdf', (string) $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->actingAs($user)
            ->get(route('nomina.comisiones.diferenciales.reporte', [$periodo, $diferencial, 'formato' => 'xlsx']))
            ->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $xlsx->headers->get('content-disposition'));

        $txt = $this->actingAs($user)
            ->get(route('nomina.comisiones.diferenciales.banco', [$periodo, $diferencial, $empresa]))
            ->assertOk()
            ->streamedContent();

        $this->assertSame(
            'V028100001  '.str_pad('3600', 21, '0', STR_PAD_LEFT)."05112026\r\n"
            .'V028100002  '.str_pad('14000', 21, '0', STR_PAD_LEFT)."05112026\r\n",
            $txt
        );
    }

    private function empleado(string $cedula, string $nombre, string $modo, NominaEmpresa $empresa): NominaEmpleado
    {
        $cliente = Cliente::create(['cedula' => $cedula, 'nombre' => $nombre]);

        return NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'empresa_id' => $empresa->id,
            'salario_base' => 500,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'modo_comision' => $modo,
            'fecha_ingreso' => '2025-01-01',
        ]);
    }
}
