<?php

namespace Tests\Feature\Patrimonial;

use App\Models\Patrimonial\Alquiler;
use App\Models\Patrimonial\AlquilerPago;
use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Propiedad;
use App\Models\User;
use Tests\Concerns\CreatesPatrimonialSchema;
use Tests\TestCase;

class AlquilerComisionTransaccionTest extends TestCase
{
    use CreatesPatrimonialSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPatrimonialSchema();
    }

    public function test_registrar_alquiler_guarda_comision_sin_crear_transaccion(): void
    {
        $prop = $this->propiedad();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('patrimonial.alquileres.store'), [
                'propiedad_id' => $prop->id,
                'inquilino_nombre' => 'Carlos Ruiz',
                'fecha_inicio' => '2026-09-01',
                'tipo_canon' => 'mensual',
                'canon_mensual' => 400,
                'comision' => 40,
                'dia_pago' => 1,
            ])
            ->assertRedirect();

        $alquiler = Alquiler::query()->where('inquilino_nombre', 'Carlos Ruiz')->first();
        $this->assertNotNull($alquiler);
        $this->assertEquals(40.0, (float) $alquiler->comision);
        $this->assertSame(0, PatTransaccion::query()->where('tipo', 'comision')->count());
    }

    public function test_pago_completo_crea_comision_en_transacciones(): void
    {
        $prop = $this->propiedad();
        $alquiler = Alquiler::create([
            'propiedad_id' => $prop->id,
            'inquilino_nombre' => 'Carlos Ruiz',
            'fecha_inicio' => '2026-09-01',
            'tipo_canon' => 'mensual',
            'canon_mensual' => 400,
            'comision' => 40,
            'dia_pago' => 1,
            'estado' => 'activo',
        ]);
        $pago = AlquilerPago::create([
            'alquiler_id' => $alquiler->id,
            'periodo' => '2026-09',
            'fecha_vencimiento' => '2026-09-01',
            'monto' => 400,
            'monto_pagado' => 0,
            'estado' => 'pendiente',
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['sede_local' => 'DORAL'])
            ->put(route('patrimonial.alquileres.actualizar_pago', $pago), [
                'monto' => 400,
                'fecha_pago' => '2026-09-08',
                'forma_pago' => 'Zelle',
            ])
            ->assertRedirect();

        $this->assertSame('pagado', $pago->fresh()->estado);

        $ingreso = PatTransaccion::query()->where('tipo', 'ingreso')->first();
        $this->assertNotNull($ingreso);
        $this->assertEquals(400.0, (float) $ingreso->monto);
        $this->assertEquals($alquiler->id, (int) $ingreso->alquiler_id);
        $this->assertEquals($pago->id, (int) $ingreso->alquiler_pago_id);

        $comision = PatTransaccion::query()->where('tipo', 'comision')->first();
        $this->assertNotNull($comision);
        $this->assertEquals(40.0, (float) $comision->monto);
        $this->assertSame('Comisión plataforma', $comision->categoria);
        $this->assertEquals($alquiler->id, (int) $comision->alquiler_id);
        $this->assertEquals($pago->id, (int) $comision->alquiler_pago_id);
        $this->assertSame('2026-09-08', $comision->fecha->toDateString());
        $this->assertStringContainsString('Carlos Ruiz', $comision->descripcion);
    }

    public function test_abono_parcial_no_crea_comision(): void
    {
        $prop = $this->propiedad();
        $alquiler = Alquiler::create([
            'propiedad_id' => $prop->id,
            'inquilino_nombre' => 'Ana López',
            'fecha_inicio' => '2026-09-01',
            'tipo_canon' => 'mensual',
            'canon_mensual' => 400,
            'comision' => 40,
            'estado' => 'activo',
        ]);
        $pago = AlquilerPago::create([
            'alquiler_id' => $alquiler->id,
            'periodo' => '2026-09',
            'fecha_vencimiento' => '2026-09-01',
            'monto' => 400,
            'monto_pagado' => 0,
            'estado' => 'pendiente',
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['sede_local' => 'DORAL'])
            ->put(route('patrimonial.alquileres.actualizar_pago', $pago), [
                'monto' => 100,
                'fecha_pago' => '2026-09-08',
                'forma_pago' => 'Zelle',
            ])
            ->assertRedirect();

        $this->assertSame(1, PatTransaccion::query()->where('tipo', 'ingreso')->count());
        $this->assertSame(0, PatTransaccion::query()->where('tipo', 'comision')->count());
    }

    private function propiedad(): Propiedad
    {
        return Propiedad::create([
            'codigo' => 'ALQ'.uniqid(),
            'nombre' => 'Local Centro',
            'tipo' => 'local',
            'estado' => 'disponible',
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin PAT',
            'email' => 'admin-alq-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'sede' => 'DORAL',
        ]);
    }
}
