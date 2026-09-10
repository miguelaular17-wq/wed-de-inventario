<?php

namespace Tests\Feature;

use App\Models\TesoreriaIngreso;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class TesoreriaDashboardTest extends TestCase
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
    }

    public function test_filtra_lotes_pos_por_fecha(): void
    {
        $user = User::create([
            'name' => 'Tesorería',
            'email' => 'tesoreria-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TESORERIA,
        ]);

        TesoreriaIngreso::create([
            'tipo' => 'punto_venta',
            'banco' => 'BANESCO',
            'titular' => 'JRZ',
            'fecha' => '2026-09-01',
            'monto' => 100,
            'lote_referencia' => 'L-JRZ',
            'descripcion' => 'Sede JRZ',
            'user_id' => $user->id,
        ]);
        TesoreriaIngreso::create([
            'tipo' => 'punto_venta',
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-09-08',
            'monto' => 200,
            'lote_referencia' => 'L-DORAL',
            'descripcion' => 'Sede Doral',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('tesoreria.dashboard', ['desde' => '2026-09-07', 'hasta' => '2026-09-09']))
            ->assertOk()
            ->assertSee('Desde')
            ->assertSee('Hasta')
            ->assertSee('L-DORAL')
            ->assertDontSee('L-JRZ');
    }
}
