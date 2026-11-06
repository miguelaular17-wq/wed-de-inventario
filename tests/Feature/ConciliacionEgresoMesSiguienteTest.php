<?php

namespace Tests\Feature;

use App\Models\FlujoCaja;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ConciliacionEgresoMesSiguienteTest extends TestCase
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
            'referencia' => fn (Blueprint $t) => $t->string('referencia')->nullable(),
            'motivo' => fn (Blueprint $t) => $t->text('motivo')->nullable(),
            'concepto' => fn (Blueprint $t) => $t->string('concepto')->nullable(),
            'es_conciliado' => fn (Blueprint $t) => $t->boolean('es_conciliado')->default(false),
        ] as $col => $def) {
            if (! Schema::hasColumn('flujo_cajas', $col)) {
                Schema::table('flujo_cajas', $def);
            }
        }
        (require database_path('migrations/2026_11_06_160000_add_conciliar_en_to_flujo_cajas.php'))->up();
    }

    public function test_un_egreso_del_cierre_de_mes_se_concilia_el_mes_siguiente_y_se_puede_devolver(): void
    {
        $nomina = $this->egreso('2026-10-31', '05003890108', 'PAGO DE NOMINA PERSONAL');
        $otro = $this->egreso('2026-10-20', '05003866939', 'COMISIONES JORGE DIAZ');
        $usuario = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@test.local', 'password' => 'password123', 'role' => User::ROLE_ADMIN]);

        $this->actingAs($usuario)->post(route('finanzas.conciliaciones.transito.diferir'), ['flujo_id' => $nomina->id])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'noviembre 2026'));
        $this->assertSame('2026-11-01', substr((string) $nomina->fresh()->conciliar_en, 0, 10));

        $octubre = FlujoCaja::query()->paraConciliarEntre('2026-10-01', '2026-10-31')->pluck('id')->all();
        $noviembre = FlujoCaja::query()->paraConciliarEntre('2026-11-01', '2026-11-30')->pluck('id')->all();
        $this->assertSame([$otro->id], $octubre);
        $this->assertSame([$nomina->id], $noviembre);

        $this->actingAs($usuario)->post(route('finanzas.conciliaciones.transito.diferir'), ['flujo_id' => $nomina->id, 'volver' => '1'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'devuelto a su mes'));
        $this->assertNull($nomina->fresh()->conciliar_en);
        $this->assertEqualsCanonicalizing([$otro->id, $nomina->id], FlujoCaja::query()->paraConciliarEntre('2026-10-01', '2026-10-31')->pluck('id')->all());
    }

    public function test_un_egreso_conciliado_no_se_pasa_de_mes(): void
    {
        $egreso = $this->egreso('2026-10-31', '1', 'X');
        $egreso->forceFill(['es_conciliado' => true])->save();
        $usuario = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@test.local', 'password' => 'password123', 'role' => User::ROLE_ADMIN]);

        $this->actingAs($usuario)->post(route('finanzas.conciliaciones.transito.diferir'), ['flujo_id' => $egreso->id])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'ya está conciliado'));
        $this->assertNull($egreso->fresh()->conciliar_en);
    }

    private function egreso(string $fecha, string $referencia, string $motivo): FlujoCaja
    {
        return FlujoCaja::create([
            'tipo' => 'egreso', 'categoria_egreso' => 'egreso_realizado', 'banco' => 'Banesco', 'titular' => 'DORAL',
            'fecha' => $fecha, 'referencia' => $referencia, 'motivo' => $motivo, 'monto_bs' => 100,
        ]);
    }
}
