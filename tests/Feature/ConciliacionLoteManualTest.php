<?php

namespace Tests\Feature;

use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ConciliacionLoteManualTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        if (! Schema::hasTable('tesoreria_ingresos')) {
            Schema::create('tesoreria_ingresos', function (Blueprint $table) {
                $table->id();
                $table->string('tipo', 32);
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->date('fecha');
                $table->decimal('monto', 15, 2);
                $table->string('lote_referencia')->nullable();
                $table->boolean('es_conciliado')->default(false);
                $table->text('descripcion')->nullable();
                $table->string('comprobante_path')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('conciliacion_lineas')) {
            Schema::create('conciliacion_lineas', function (Blueprint $table) {
                $table->id();
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->date('fecha')->nullable();
                $table->text('descripcion')->nullable();
                $table->string('referencia')->nullable();
                $table->decimal('monto', 15, 2)->default(0);
                $table->string('estado', 32)->default('pendiente');
                $table->string('tipo', 32)->nullable();
                $table->string('session_id')->nullable();
                $table->unsignedBigInteger('flujo_caja_id')->nullable();
                $table->unsignedBigInteger('tesoreria_ingreso_id')->nullable();
                $table->unsignedBigInteger('compra_divisa_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_concilia_un_lote_con_varias_liq_del_banco(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);

        $lote = TesoreriaIngreso::create([
            'tipo' => 'punto_venta',
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-01',
            'monto' => 1000.00,
            'lote_referencia' => '000058',
            'es_conciliado' => false,
            'user_id' => $user->id,
        ]);

        $l1 = ConciliacionLinea::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-01',
            'descripcion' => 'TDB CAPIT. 123 L.000058 30687',
            'referencia' => '58',
            'monto' => 600.00,
            'estado' => 'pendiente',
            'tipo' => 'abono',
        ]);
        $l2 = ConciliacionLinea::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-02',
            'descripcion' => 'TDB CAPIT. 456 L.000058 30687',
            'referencia' => '58b',
            'monto' => 400.00,
            'estado' => 'pendiente',
            'tipo' => 'abono',
        ]);

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.lotes.manual'), [
                'tesoreria_ingreso_id' => $lote->id,
                'linea_ids' => [$l1->id, $l2->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue((bool) $lote->fresh()->es_conciliado);
        $this->assertSame('conciliado', $l1->fresh()->estado);
        $this->assertSame('conciliado', $l2->fresh()->estado);
        $this->assertSame((int) $lote->id, (int) $l1->fresh()->tesoreria_ingreso_id);
        $this->assertSame((int) $lote->id, (int) $l2->fresh()->tesoreria_ingreso_id);
    }

    public function test_rechaza_si_la_suma_no_coincide(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);

        $lote = TesoreriaIngreso::create([
            'tipo' => 'punto_venta',
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-01',
            'monto' => 1000.00,
            'lote_referencia' => '000095',
            'es_conciliado' => false,
            'user_id' => $user->id,
        ]);

        $l1 = ConciliacionLinea::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-01',
            'descripcion' => 'TDB CAPIT. 123 L.000095 30687',
            'referencia' => '95',
            'monto' => 500.00,
            'estado' => 'pendiente',
            'tipo' => 'abono',
        ]);

        $this->actingAs($user)
            ->from(route('finanzas.conciliaciones.lotes'))
            ->post(route('finanzas.conciliaciones.lotes.manual'), [
                'tesoreria_ingreso_id' => $lote->id,
                'linea_ids' => [$l1->id],
            ])
            ->assertRedirect(route('finanzas.conciliaciones.lotes'))
            ->assertSessionHas('error');

        $this->assertFalse((bool) $lote->fresh()->es_conciliado);
        $this->assertSame('pendiente', $l1->fresh()->estado);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'Contabilidad',
            'email' => 'contab-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => $role,
        ]);
    }
}
