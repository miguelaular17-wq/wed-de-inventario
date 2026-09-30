<?php

namespace Tests\Feature;

use App\Models\ConciliacionCierre;
use App\Models\User;
use App\Services\ConciliacionCalendarioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ConciliacionCalendarioTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        if (! Schema::hasTable('conciliacion_cierres')) {
            Schema::create('conciliacion_cierres', function (Blueprint $table) {
                $table->id();
                $table->string('banco', 80);
                $table->string('titular', 120);
                $table->date('fecha_desde');
                $table->date('fecha_hasta');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_marca_un_mes_para_banco_y_titular_y_pinta_el_calendario(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.periodos.store'), [
                'banco' => 'BNC',
                'titular' => 'LNACEH',
                'fecha_desde' => '2026-09-01',
                'fecha_hasta' => '2026-09-30',
            ])
            ->assertRedirect(route('finanzas.calendario_conciliaciones', ['cal_mes' => '2026-09']));

        $this->assertSame(1, ConciliacionCierre::query()->count());

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.periodos.store'), [
                'banco' => 'BNC',
                'titular' => 'LNACEH',
                'fecha_desde' => '2026-09-01',
                'fecha_hasta' => '2026-09-30',
            ])
            ->assertRedirect();

        $this->assertSame(1, ConciliacionCierre::query()->count());

        $cal = app(ConciliacionCalendarioService::class)->armar('2026-09', [
            'BNC' => ['LNACEH', 'DORAL'],
        ]);

        $lnaceh = collect($cal['filas'])->firstWhere('titular', 'LNACEH');
        $doral = collect($cal['filas'])->firstWhere('titular', 'DORAL');

        $this->assertTrue($lnaceh['completo']);
        $this->assertSame(30, $lnaceh['cubiertos']);
        $this->assertFalse($doral['completo']);
        $this->assertSame(1, $cal['completas']);

        $dia15 = collect($cal['celdas'])->first(fn (array $celda) => $celda['en_mes'] && $celda['dia'] === 15);
        $this->assertSame(['BNC · LNACEH'], $dia15['cuentas']);
    }

    public function test_un_rango_parcial_solo_cubre_esos_dias(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);

        $this->actingAs($user)->post(route('finanzas.conciliaciones.periodos.store'), [
            'banco' => 'BANESCO',
            'titular' => 'Grupo JRZ',
            'fecha_desde' => '2026-09-10',
            'fecha_hasta' => '2026-09-12',
        ])->assertRedirect();

        $cal = app(ConciliacionCalendarioService::class)->armar('2026-09', [
            'BANESCO' => ['GRUPO JRZ'],
        ]);
        $fila = $cal['filas'][0];

        $this->assertFalse($fila['completo']);
        $this->assertTrue($fila['dias'][10]);
        $this->assertTrue($fila['dias'][12]);
        $this->assertFalse($fila['dias'][9]);
        $this->assertFalse($fila['dias'][13]);
    }

    public function test_se_puede_quitar_la_marca(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $cierre = ConciliacionCierre::create([
            'banco' => 'TESORO',
            'titular' => 'LNACEH',
            'fecha_desde' => '2026-08-01',
            'fecha_hasta' => '2026-08-31',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('finanzas.conciliaciones.periodos.destroy', $cierre))
            ->assertRedirect(route('finanzas.calendario_conciliaciones', ['cal_mes' => '2026-08']));

        $this->assertSame(0, ConciliacionCierre::query()->count());
    }

    public function test_sin_permiso_no_marca(): void
    {
        $user = $this->makeUser(User::ROLE_VENDEDOR);

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.periodos.store'), [
                'banco' => 'BNC',
                'titular' => 'LNACEH',
                'fecha_desde' => '2026-09-01',
                'fecha_hasta' => '2026-09-30',
            ])
            ->assertRedirect('/');

        $this->assertSame(0, ConciliacionCierre::query()->count());
    }

    public function test_desde_la_conciliacion_toma_titular_y_rango_y_vuelve_ahi(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.periodos.store'), [
                'origen' => 'conciliaciones',
                'banco' => 'BANCAMIGA',
                'titular' => 'DORAL',
                'fecha_desde' => '2026-08-01',
                'fecha_hasta' => '2026-08-31',
            ])
            ->assertRedirect(route('finanzas.conciliaciones'));

        $cierre = ConciliacionCierre::query()->first();
        $this->assertSame('BANCAMIGA', $cierre->banco);
        $this->assertSame('DORAL', $cierre->titular);
        $this->assertSame('2026-08-01', $cierre->fecha_desde->toDateString());
        $this->assertSame('2026-08-31', $cierre->fecha_hasta->toDateString());
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'Usuario '.$role,
            'email' => $role.'-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => $role,
            'sede' => 'DORAL',
        ]);
    }
}
