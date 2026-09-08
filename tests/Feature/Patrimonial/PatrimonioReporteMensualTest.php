<?php

namespace Tests\Feature\Patrimonial;

use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Propiedad;
use App\Models\User;
use Tests\Concerns\CreatesPatrimonialSchema;
use Tests\TestCase;

class PatrimonioReporteMensualTest extends TestCase
{
    use CreatesPatrimonialSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPatrimonialSchema();
    }

    public function test_reporte_general_muestra_recibido_gastos_comision_neto(): void
    {
        $prop = Propiedad::create([
            'codigo' => 'SAL',
            'nombre' => 'Apartamentos Salamar',
            'tipo' => 'apartamento',
            'estado' => 'alquilado',
        ]);
        PatTransaccion::create([
            'propiedad_id' => $prop->id,
            'tipo' => 'gasto',
            'categoria' => 'Condominio',
            'monto' => 200,
            'moneda' => 'usd',
            'mes' => 9,
            'anio' => 2026,
            'fecha' => '2026-09-05',
        ]);
        PatTransaccion::create([
            'propiedad_id' => $prop->id,
            'tipo' => 'comision',
            'categoria' => 'Comisión administración',
            'monto' => 60,
            'moneda' => 'usd',
            'mes' => 9,
            'anio' => 2026,
            'fecha' => '2026-09-05',
        ]);

        $admin = User::create([
            'name' => 'Admin PAT',
            'email' => 'admin-pat-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($admin)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('patrimonial.reportes.mensual', ['mes' => 9, 'anio' => 2026]))
            ->assertOk()
            ->assertSee('Total recibido')
            ->assertSee('recibido')
            ->assertSee('neto')
            ->assertSee('Condominio')
            ->assertSee('Comisión administración')
            ->assertSee('Apartamentos Salamar')
            ->assertDontSee('Limpieza');
    }
}
