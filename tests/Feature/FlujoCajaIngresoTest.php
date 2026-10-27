<?php

namespace Tests\Feature;

use App\Models\FlujoCaja;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class FlujoCajaIngresoTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        foreach ([
            'categoria_egreso' => fn (Blueprint $t) => $t->string('categoria_egreso')->nullable(),
            'banco' => fn (Blueprint $t) => $t->string('banco')->nullable(),
            'titular' => fn (Blueprint $t) => $t->string('titular')->nullable(),
            'categoria_cuenta' => fn (Blueprint $t) => $t->string('categoria_cuenta')->nullable(),
            'banco_receptor' => fn (Blueprint $t) => $t->string('banco_receptor')->nullable(),
            'titular_receptor' => fn (Blueprint $t) => $t->string('titular_receptor')->nullable(),
            'referencia' => fn (Blueprint $t) => $t->string('referencia')->nullable(),
            'motivo' => fn (Blueprint $t) => $t->text('motivo')->nullable(),
            'diferencial_cambiario' => fn (Blueprint $t) => $t->decimal('diferencial_cambiario', 14, 2)->nullable(),
            'comision' => fn (Blueprint $t) => $t->decimal('comision', 14, 2)->nullable(),
            'sede' => fn (Blueprint $t) => $t->string('sede')->nullable(),
            'oculto' => fn (Blueprint $t) => $t->boolean('oculto')->default(false),
            'comprobante_url' => fn (Blueprint $t) => $t->string('comprobante_url')->nullable(),
            'comprobantes' => fn (Blueprint $t) => $t->text('comprobantes')->nullable(),
        ] as $col => $def) {
            if (! Schema::hasColumn('flujo_cajas', $col)) {
                Schema::table('flujo_cajas', $def);
            }
        }
        if (! Schema::hasTable('finanzas_resumen')) {
            Schema::create('finanzas_resumen', function (Blueprint $t) {
                $t->id();
                $t->date('fecha')->nullable();
                $t->decimal('tasa_bcv_usd', 14, 4)->default(1);
                $t->decimal('saldo_inicial', 14, 2)->default(0);
                $t->decimal('queda_dia_anterior', 14, 2)->default(0);
                $t->decimal('porcentaje_total_diferencial', 14, 4)->default(0);
                foreach (['total_egresos_usd', 'total_egresos_bs_usd', 'total_otros_usd', 'total_otros_bs_usd', 'total_salidas_usd', 'total_salidas_bs_en_usd'] as $c) {
                    $t->decimal($c, 14, 2)->default(0);
                }
                $t->timestamps();
            });
        }
    }

    private function usuario(): User
    {
        return User::create([
            'name' => 'Finanzas', 'email' => 'fin-ing-' . uniqid() . '@test.local', 'password' => 'password123', 'role' => User::ROLE_FINANZAS,
        ]);
    }

    public function test_registra_un_ingreso_que_no_cuenta_como_egreso(): void
    {
        $this->actingAs($this->usuario())->post(route('finanzas.store_egreso'), [
            'categoria_egreso' => 'ingresos',
            'banco_titular' => 'Banesco|Grupo JRZ|BANCA NACIONAL',
            'fecha' => '2026-09-05',
            'referencia' => '000123456',
            'monto_bs' => '15.000,50',
            'tasa_cambio' => '150',
            'tipo_gasto' => '080 - INGRESO ALQUILERES',
            'beneficiario' => 'Inquilino Local 3',
            'motivo' => 'Alquiler septiembre',
        ])->assertRedirect()->assertSessionHas('success', 'Ingreso registrado correctamente.');

        $ing = FlujoCaja::query()->sole();
        $this->assertSame('ingreso', $ing->tipo);
        $this->assertSame('ingresos', $ing->categoria_egreso);
        $this->assertSame(['Banesco', 'Grupo JRZ'], [$ing->banco, $ing->titular]);
        $this->assertEquals(15000.50, (float) $ing->monto_bs);
        $this->assertEquals(100.0, (float) $ing->monto_usd);
        $this->assertSame('Inquilino Local 3', $ing->titular_receptor);
        $this->assertFalse((bool) $ing->es_conciliado);
    }

    public function test_ingreso_sin_monto_o_sin_motivo_se_rechaza(): void
    {
        $base = ['categoria_egreso' => 'ingresos', 'banco_titular' => 'Banesco|Grupo JRZ|X', 'fecha' => '2026-09-05'];
        $this->actingAs($this->usuario())->post(route('finanzas.store_egreso'), $base + ['motivo' => 'Algo'])
            ->assertSessionHas('error', 'El monto del ingreso debe ser mayor a cero.');
        $this->actingAs($this->usuario())->post(route('finanzas.store_egreso'), $base + ['monto_bs' => '10'])
            ->assertSessionHas('error', 'Escribe el motivo del ingreso.');
        $this->assertSame(0, FlujoCaja::query()->count());
    }

    public function test_se_puede_eliminar_un_ingreso(): void
    {
        $ing = FlujoCaja::create([
            'tipo' => 'ingreso', 'categoria_egreso' => 'ingresos', 'banco' => 'Banesco', 'titular' => 'Grupo JRZ',
            'fecha' => '2026-09-05', 'monto_bs' => 10, 'motivo' => 'x', 'es_conciliado' => false, 'oculto' => false,
        ]);
        $admin = User::create(['name' => 'Admin', 'email' => 'adm-ing@test.local', 'password' => 'password123', 'role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->delete(route('finanzas.destroy_egreso', $ing->id))->assertRedirect();
        $this->assertSame(0, FlujoCaja::query()->count());
    }
}
