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

    public function test_cuenta_movimientos_del_banco_pendientes_y_en_transito(): void
    {
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
                $table->boolean('forzar_comision')->default(false);
                $table->unsignedBigInteger('flujo_caja_id')->nullable();
                $table->unsignedBigInteger('devolucion_linea_id')->nullable();
                $table->timestamps();
            });
        }
        foreach (['es_conciliado' => fn (Blueprint $t) => $t->boolean('es_conciliado')->default(false), 'banco' => fn (Blueprint $t) => $t->string('banco')->nullable(), 'titular' => fn (Blueprint $t) => $t->string('titular')->nullable(), 'monto_bs' => fn (Blueprint $t) => $t->decimal('monto_bs', 15, 2)->default(0)] as $col => $def) {
            if (! Schema::hasColumn('flujo_cajas', $col)) {
                Schema::table('flujo_cajas', $def);
            }
        }

        $linea = fn (string $ref, float $monto, string $estado, string $desc = 'PAGO PROVEEDOR') => [
            'banco' => 'BANCAMIGA', 'titular' => 'DORAL', 'fecha' => '2026-08-10', 'descripcion' => $desc,
            'referencia' => $ref, 'monto' => $monto, 'estado' => $estado, 'tipo' => 'cargo',
            'created_at' => now(), 'updated_at' => now(),
        ];
        \Illuminate\Support\Facades\DB::table('conciliacion_lineas')->insert([
            $linea('A1', 100, 'conciliado'),
            $linea('A2', 200, 'conciliado'),
            $linea('A3', 50, 'pendiente'),
            // Copia de un extracto subido dos veces: ya está conciliado en la otra copia.
            $linea('A1', 100, 'pendiente'),
            // Comisión: el PDF la muestra aparte, no es un movimiento por conciliar.
            $linea('C1', 5, 'pendiente', 'COMISION MANTENIMIENTO'),
            // Otra cuenta.
            ['banco' => 'BNC', 'titular' => 'LNACEH'] + $linea('Z1', 999, 'pendiente'),
        ]);
        \Illuminate\Support\Facades\DB::table('flujo_cajas')->insert([
            ['fecha' => '2026-08-12', 'tipo' => 'egreso', 'banco' => 'BANCAMIGA', 'titular' => 'DORAL', 'monto_bs' => 75, 'es_conciliado' => false, 'created_at' => now(), 'updated_at' => now()],
            ['fecha' => '2026-08-13', 'tipo' => 'egreso', 'banco' => 'BANCAMIGA', 'titular' => 'DORAL', 'monto_bs' => 30, 'es_conciliado' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $cal = app(ConciliacionCalendarioService::class)->armar('2026-08', ['BANCAMIGA' => ['DORAL']], true);
        $mov = $cal['filas'][0]['movimientos'];

        $this->assertSame(3, $mov['banco_total']);
        $this->assertSame(1, $mov['banco_pendientes']);
        $this->assertSame(50.0, $mov['banco_pendientes_bs']);
        $this->assertSame(1, $mov['transito']);
        $this->assertSame(75.0, $mov['transito_bs']);

        // La página carga sin contar y luego los pide por la ruta de movimientos.
        $this->assertNull(app(ConciliacionCalendarioService::class)->armar('2026-08', ['BANCAMIGA' => ['DORAL']])['filas'][0]['movimientos']);
        $this->actingAs($this->makeUser(User::ROLE_CONTABILIDAD))
            ->getJson(route('finanzas.calendario_conciliaciones.movimientos', ['cal_mes' => '2026-08']))
            ->assertOk()
            ->assertJsonPath($cal['filas'][0]['clave'].'.banco_pendientes', 1)
            ->assertJsonPath($cal['filas'][0]['clave'].'.transito', 1);
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
