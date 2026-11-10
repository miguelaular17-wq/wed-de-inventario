<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\ContratoCuota;
use App\Models\User;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ContratoEliminarCuotaTest extends TestCase
{
    use CreatesNominaSchema;

    private Contrato $contrato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        foreach ([
            '2026_07_21_200000_create_contratos_tables',
            '2026_07_22_151314_add_abono_capital_to_contrato_cuotas_table',
            '2026_07_24_024724_add_garantia_documento_to_contratos_table',
            '2026_07_24_100000_add_aumento_capital_fields_to_contratos_table',
            '2026_07_25_162758_add_liquidado_columns_to_contratos_table',
            '2026_08_07_205900_add_pago_fields_to_contrato_cuotas_and_seguimientos',
        ] as $migracion) {
            (require database_path("migrations/{$migracion}.php"))->up();
        }

        $this->contrato = Contrato::create([
            'numero_contrato' => 'C-001', 'cliente' => 'Cliente Prueba', 'capital' => 134,
            'cuota_fija' => 0, 'total_a_pagar' => 134, 'fecha_inicio' => '2026-09-24', 'frecuencia' => 'MENSUAL',
        ]);
    }

    public function test_elimina_una_cuota_sin_pagos_y_deja_seguimiento(): void
    {
        $cuota = ContratoCuota::create([
            'contrato_id' => $this->contrato->id, 'numero_cuota' => 2, 'fecha_vencimiento' => '2026-11-08',
            'monto' => 0, 'saldo' => 0, 'estatus' => 'vencido',
        ]);

        $this->actingAs($this->admin())
            ->delete(route('contratos.eliminar_cuota', $cuota->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('contrato_cuotas', ['id' => $cuota->id]);
        $this->assertDatabaseHas('contrato_seguimientos', ['contrato_id' => $this->contrato->id, 'resultado' => 'CUOTA_ELIMINADA']);
    }

    public function test_no_elimina_una_cuota_con_pagos(): void
    {
        $cuota = ContratoCuota::create([
            'contrato_id' => $this->contrato->id, 'numero_cuota' => 1, 'fecha_vencimiento' => '2026-10-24',
            'monto' => 0, 'saldo' => 0, 'estatus' => 'pagado', 'monto_pagado' => 0, 'abono_capital' => 134,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('contratos.eliminar_cuota', $cuota->id))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('contrato_cuotas', ['id' => $cuota->id]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@test.local', 'password' => 'password123', 'role' => 'admin',
        ]);
    }
}
