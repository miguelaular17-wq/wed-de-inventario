<?php

namespace Tests\Feature\Patrimonial;

use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Propiedad;
use App\Models\Patrimonial\Reserva;
use App\Models\User;
use Tests\Concerns\CreatesPatrimonialSchema;
use Tests\TestCase;

class ReservaComisionTransaccionTest extends TestCase
{
    use CreatesPatrimonialSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPatrimonialSchema();
    }

    public function test_registrar_reserva_crea_comision_en_transacciones(): void
    {
        $prop = Propiedad::create([
            'codigo' => 'AIR',
            'nombre' => 'Airbnb Centro',
            'tipo' => 'apartamento',
            'estado' => 'disponible',
        ]);
        $admin = User::create([
            'name' => 'Admin PAT',
            'email' => 'admin-res-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($admin)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('patrimonial.reservas.store'), [
                'propiedad_id' => $prop->id,
                'cliente_nombre' => 'Ana Pérez',
                'fecha_entrada' => '2026-09-10',
                'fecha_salida' => '2026-09-15',
                'precio_noche' => 100,
                'comision' => 80,
                'moneda' => 'usd',
                'estado' => 'confirmada',
            ])
            ->assertRedirect();

        $reserva = Reserva::query()->where('cliente_nombre', 'Ana Pérez')->first();
        $this->assertNotNull($reserva);
        $this->assertEquals(80.0, (float) $reserva->comision);

        $tx = PatTransaccion::query()
            ->where('propiedad_id', $prop->id)
            ->where('tipo', 'comision')
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(80.0, (float) $tx->monto);
        $this->assertSame('Comisión plataforma', $tx->categoria);
        $this->assertEquals($reserva->id, (int) $tx->reserva_id);
        $this->assertSame('2026-09-10', $tx->fecha->toDateString());
    }

    public function test_reserva_sin_comision_no_crea_transaccion(): void
    {
        $prop = Propiedad::create([
            'codigo' => 'AIR2',
            'nombre' => 'Airbnb 2',
            'tipo' => 'apartamento',
            'estado' => 'disponible',
        ]);
        $admin = User::create([
            'name' => 'Admin PAT',
            'email' => 'admin-res2-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($admin)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('patrimonial.reservas.store'), [
                'propiedad_id' => $prop->id,
                'cliente_nombre' => 'Sin Comisión',
                'fecha_entrada' => '2026-09-10',
                'fecha_salida' => '2026-09-12',
                'precio_noche' => 50,
                'comision' => 0,
                'moneda' => 'usd',
                'estado' => 'confirmada',
            ])
            ->assertRedirect();

        $this->assertSame(0, PatTransaccion::query()->where('tipo', 'comision')->count());
    }

    public function test_guardar_comision_en_reserva_existente_crea_transaccion(): void
    {
        $prop = Propiedad::create([
            'codigo' => 'EMI',
            'nombre' => 'Casa Doña Emilia',
            'tipo' => 'casa',
            'estado' => 'disponible',
        ]);
        $reserva = Reserva::create([
            'propiedad_id' => $prop->id,
            'cliente_nombre' => 'Miguel Aular',
            'fecha_entrada' => '2026-09-06',
            'fecha_salida' => '2026-09-11',
            'precio_noche' => 100,
            'comision' => 0,
            'moneda' => 'usd',
            'estado' => 'confirmada',
        ]);
        $admin = User::create([
            'name' => 'Admin PAT',
            'email' => 'admin-res3-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($admin)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('patrimonial.reservas.comision', $reserva), [
                'comision' => 20,
            ])
            ->assertRedirect();

        $this->assertEquals(20.0, (float) $reserva->fresh()->comision);
        $tx = PatTransaccion::query()->where('tipo', 'comision')->where('propiedad_id', $prop->id)->first();
        $this->assertNotNull($tx);
        $this->assertEquals(20.0, (float) $tx->monto);
        $this->assertSame('Comisión plataforma', $tx->categoria);
    }
}
