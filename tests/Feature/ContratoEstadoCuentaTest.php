<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\ContratoCuota;
use App\Models\ContratoIntermediario;
use App\Models\User;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ContratoEstadoCuentaTest extends TestCase
{
    use CreatesNominaSchema;

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
            '2026_11_13_120000_create_contrato_intermediarios_table',
        ] as $migracion) {
            (require database_path("migrations/{$migracion}.php"))->up();
        }
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin-'.uniqid().'@test.local', 'password' => 'password123', 'role' => 'admin']);
    }

    private function contrato(string $numero, string $cliente, float $capital, array $cuotas): Contrato
    {
        $c = Contrato::create([
            'numero_contrato' => $numero, 'cliente' => $cliente, 'capital' => $capital, 'cuota_fija' => 0,
            'total_a_pagar' => $capital, 'fecha_inicio' => '2026-09-01', 'frecuencia' => 'MENSUAL', 'activo' => true,
        ]);
        foreach ($cuotas as $n => [$fecha, $saldo, $estatus]) {
            ContratoCuota::create([
                'contrato_id' => $c->id, 'numero_cuota' => $n + 1, 'fecha_vencimiento' => $fecha,
                'monto' => $saldo, 'saldo' => $saldo, 'estatus' => $estatus,
            ]);
        }

        return $c;
    }

    public function test_estado_de_cuenta_de_una_persona_con_varios_contratos(): void
    {
        $admin = $this->admin();
        $cruz = $this->contrato('CT-000034', 'DIECEL - CRUZ NEGRO', 2500, [['2026-10-03', 125, 'vencido'], ['2026-11-03', 125, 'vencido'], ['2026-12-03', 125, 'pendiente']]);
        $casa = $this->contrato('CT-000067', 'DIECEL - CASA JUDIBANA', 14243.70, [['2026-10-01', 712.19, 'vencido'], ['2026-09-01', 712.19, 'pagado']]);
        $otro = $this->contrato('CT-000099', 'OTRO CLIENTE', 1000, [['2026-12-01', 50, 'pendiente']]);

        $this->actingAs($admin)->post(route('contratos.estados_cuenta.store'), ['nombre' => 'Diecel Zúñiga', 'documento' => 'V24.306.133'])
            ->assertRedirect();
        $persona = ContratoIntermediario::query()->firstOrFail();
        $this->assertSame('DIECEL ZÚÑIGA', $persona->nombre);

        $this->actingAs($admin)->post(route('contratos.estados_cuenta.asignar', $persona), ['contratos' => [$cruz->id, $casa->id]])
            ->assertSessionHas('success');
        $this->assertSame([$cruz->id, $casa->id], $persona->contratos()->orderBy('id')->pluck('id')->all());

        $this->actingAs($admin)->get(route('contratos.estados_cuenta.show', $persona))->assertOk()
            ->assertSee('1 - CRUZ NEGRO')->assertSee('2 - CASA JUDIBANA')
            ->assertSee('03/10/2026')->assertSee('2.875,00')       // 2.500 + 375 sin pagar
            ->assertSee('14.955,89')                               // 14.243,70 + 712,19 (la pagada no cuenta)
            ->assertSee('17.830,89')                               // total de los dos
            ->assertDontSee('OTRO CLIENTE</strong>', false);

        $this->actingAs($admin)->get(route('contratos.estados_cuenta.index'))->assertOk()->assertSee('DIECEL ZÚÑIGA')->assertSee('$17,830.89');
        $pdf = $this->actingAs($admin)->get(route('contratos.estados_cuenta.pdf', $persona));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Quitar un contrato no lo borra; quitar a la persona tampoco borra sus contratos.
        $this->actingAs($admin)->delete(route('contratos.estados_cuenta.quitar', [$persona, $casa]))->assertSessionHas('success');
        $this->assertNull($casa->fresh()->intermediario_id);
        $this->actingAs($admin)->delete(route('contratos.estados_cuenta.quitar', [$persona, $otro]))->assertNotFound();
        $this->actingAs($admin)->delete(route('contratos.estados_cuenta.destroy', $persona))->assertRedirect();
        $this->assertNull($cruz->fresh()->intermediario_id);
        $this->assertSame(3, Contrato::query()->count());
    }
}
