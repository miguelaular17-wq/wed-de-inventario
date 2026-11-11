<?php

namespace Tests\Feature;

use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class TesoreriaPagoMovilConciliacionTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        Schema::create('tesoreria_ingresos', function (Blueprint $t) {
            $t->id();
            $t->string('tipo', 32);
            $t->string('banco')->nullable();
            $t->string('titular')->nullable();
            $t->date('fecha');
            $t->decimal('monto', 15, 2);
            $t->string('lote_referencia')->nullable();
            $t->boolean('es_conciliado')->default(false);
            $t->text('descripcion')->nullable();
            $t->string('comprobante_path')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });
        Schema::create('conciliacion_lineas', function (Blueprint $t) {
            $t->id();
            $t->string('banco')->nullable();
            $t->string('titular')->nullable();
            $t->date('fecha');
            $t->string('descripcion')->nullable();
            $t->string('referencia')->nullable();
            $t->decimal('monto', 15, 2);
            $t->string('estado')->default('pendiente');
            $t->string('tipo')->nullable();
            $t->unsignedBigInteger('flujo_caja_id')->nullable();
            $t->unsignedBigInteger('tesoreria_ingreso_id')->nullable();
            $t->timestamps();
        });
    }

    public function test_el_pago_del_sabado_se_concilia_con_el_abono_del_lunes(): void
    {
        $ingreso = TesoreriaIngreso::create([
            'tipo' => 'banco', 'banco' => 'Pago Movil', 'fecha' => '2026-08-01', 'monto' => 1550, 'lote_referencia' => '217936',
        ]);
        $lunes = $this->linea('2026-08-03', '964217936', 1550);
        $lejos = $this->linea('2026-08-09', '964217936', 1550);

        $this->actingAs($this->tesoreria())
            ->post(route('tesoreria.ingresos.reconciliar'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'se conciliaron 1'));

        $this->assertTrue((bool) $ingreso->fresh()->es_conciliado);
        $this->assertSame($ingreso->id, (int) $lunes->fresh()->tesoreria_ingreso_id);
        $this->assertSame('pendiente', $lejos->fresh()->estado);
    }

    public function test_no_concilia_si_el_abono_es_de_antes_del_pago_o_de_otro_monto(): void
    {
        $ingreso = TesoreriaIngreso::create([
            'tipo' => 'banco', 'banco' => 'Pago Movil', 'fecha' => '2026-08-05', 'monto' => 1550, 'lote_referencia' => '217936',
        ]);
        $this->linea('2026-08-03', '964217936', 1550);
        $this->linea('2026-08-05', '964217936', 1551);

        $this->actingAs($this->tesoreria())->post(route('tesoreria.ingresos.reconciliar'));

        $this->assertFalse((bool) $ingreso->fresh()->es_conciliado);
    }

    private function linea(string $fecha, string $ref, float $monto): ConciliacionLinea
    {
        return ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'JRZ', 'fecha' => $fecha, 'descripcion' => 'Banesco Pago Movil',
            'referencia' => $ref, 'monto' => $monto, 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);
    }

    private function tesoreria(): User
    {
        return User::create([
            'name' => 'Tesorería', 'email' => 'tes-'.uniqid().'@test.local', 'password' => 'password123', 'role' => User::ROLE_TESORERIA,
        ]);
    }
}
