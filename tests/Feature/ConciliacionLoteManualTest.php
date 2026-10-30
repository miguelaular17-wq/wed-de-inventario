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

    public function test_concilia_varios_grupos_de_una_vez_y_reporta_los_que_fallan(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $lote = fn (string $n, float $m) => TesoreriaIngreso::create([
            'tipo' => 'punto_venta', 'banco' => 'VENEZUELA', 'titular' => 'LNACEH', 'fecha' => '2026-10-01',
            'monto' => $m, 'lote_referencia' => $n, 'es_conciliado' => false, 'user_id' => $user->id,
        ]);
        $liq = fn (float $m) => ConciliacionLinea::create([
            'banco' => 'VENEZUELA', 'titular' => 'LNACEH', 'fecha' => '2026-10-02', 'descripcion' => 'LIQ.TARJETA DEBITO MAESTRO BDV',
            'referencia' => (string) random_int(1000, 9999), 'monto' => $m, 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);
        $l204 = $lote('204', 32875.70);
        $l185 = $lote('185', 49786.64);
        $l205 = $lote('205', 66560.46);
        $a = $liq(32382.57);
        $b = $liq(49786.64);
        $c = $liq(1000.00);

        $grupos = json_encode([
            ['lote' => $l204->id, 'lineas' => [$a->id]],
            ['lote' => $l185->id, 'lineas' => [$b->id]],
            ['lote' => $l205->id, 'lineas' => [$c->id]],
        ]);

        // Sin aceptar diferencias: solo el que cuadra.
        $this->actingAs($user)->post(route('finanzas.conciliaciones.lotes.manual'), ['grupos' => $grupos])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 de 3 grupo(s) conciliados') && str_contains($m, 'Lote 204: la suma de LIQ'));
        $this->assertTrue((bool) $l185->fresh()->es_conciliado);
        $this->assertFalse((bool) $l204->fresh()->es_conciliado);

        // Aceptando la diferencia: el resto, con el porcentaje en el mensaje.
        $resto = json_encode([['lote' => $l204->id, 'lineas' => [$a->id]], ['lote' => $l205->id, 'lineas' => [$c->id]]]);
        $this->actingAs($user)->post(route('finanzas.conciliaciones.lotes.manual'), ['grupos' => $resto, 'aceptar_diferencia' => 1])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 de 2 grupo(s)') && str_contains($m, '(1,50 %)'));
        $this->assertTrue((bool) $l204->fresh()->es_conciliado);
        $this->assertTrue((bool) $l205->fresh()->es_conciliado);
        $this->assertSame((int) $l204->id, (int) $a->fresh()->tesoreria_ingreso_id);

        // En «Lotes conciliados» la diferencia sale como la comisión que descontó el banco.
        if (! Schema::hasTable('cuentas_bancarias')) {
            Schema::create('cuentas_bancarias', function (Blueprint $t) {
                $t->id();
                $t->string('banco');
                $t->string('titular');
                $t->string('categoria_reporte')->nullable();
                $t->boolean('mostrar_en_principal')->default(true);
                $t->integer('orden')->default(0);
                $t->timestamps();
            });
        }
        $vista = $this->actingAs($user)->get(route('finanzas.conciliaciones.lotes', ['fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-31']))
            ->assertOk()->assertSee('Comisiones:');
        $comisiones = collect($vista->viewData('tarjetas'))->first()['comisiones'];
        $this->assertSame(['abonado' => 32382.57, 'comision' => 493.13, 'pct' => 1.5, 'nota' => null], $comisiones[$l204->id]);
        $this->assertSame(0.0, $comisiones[$l185->id]['comision']);
    }

    public function test_registra_el_lote_que_falta_desde_la_liq_y_queda_conciliado(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $liq = ConciliacionLinea::create([
            'banco' => 'VENEZUELA', 'titular' => 'LNACEH', 'fecha' => '2026-10-06', 'descripcion' => 'LIQUIDACION T/CREDITO VS/MC BD',
            'referencia' => '777', 'monto' => 3994.78, 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);
        $ruta = route('finanzas.conciliaciones.lotes.registrar_lote');

        $this->actingAs($user)->post($ruta, [
            'linea_id' => $liq->id, 'lote_referencia' => ' 190 ', 'fecha' => '2026-10-05', 'monto' => 4055.62, 'descripcion' => 'SEDE DORAL',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'Lote 190 registrado') && str_contains($m, 'Comisión del banco: Bs. 60,84 (1,50 %)'));

        $lote = TesoreriaIngreso::query()->sole();
        $this->assertSame(['punto_venta', 'VENEZUELA', 'LNACEH', '190', 'SEDE DORAL'], [$lote->tipo, $lote->banco, $lote->titular, $lote->lote_referencia, $lote->descripcion]);
        $this->assertTrue((bool) $lote->es_conciliado);
        $this->assertEquals(4055.62, (float) $lote->monto);
        $this->assertSame('conciliado', $liq->fresh()->estado);
        $this->assertSame((int) $lote->id, (int) $liq->fresh()->tesoreria_ingreso_id);

        // La misma LIQ no se usa dos veces.
        $this->actingAs($user)->post($ruta, ['linea_id' => $liq->id, 'lote_referencia' => '191', 'fecha' => '2026-10-05', 'monto' => 10])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'ya está conciliada'));
        $this->assertSame(1, TesoreriaIngreso::query()->count());

        // Un movimiento que no es liquidación de punto de venta no sirve.
        $otro = ConciliacionLinea::create([
            'banco' => 'VENEZUELA', 'titular' => 'LNACEH', 'fecha' => '2026-10-06', 'descripcion' => 'TRANSFERENCIA RECIBIDA',
            'referencia' => '9', 'monto' => 100, 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);
        $this->actingAs($user)->post($ruta, ['linea_id' => $otro->id, 'lote_referencia' => '1', 'fecha' => '2026-10-05', 'monto' => 100])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'no es una liquidación'));
    }

    public function test_registrar_lote_que_ya_esta_en_tesoreria_no_crea_otro(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $ruta = route('finanzas.conciliaciones.lotes.registrar_lote');
        $existente = TesoreriaIngreso::create([
            'tipo' => 'punto_venta', 'banco' => 'MERCANTIL', 'titular' => 'JRZ', 'fecha' => '2026-10-03',
            'monto' => 809167.35, 'lote_referencia' => '370', 'descripcion' => 'SEDE DORAL', 'es_conciliado' => false,
        ]);
        $liq = ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-10-03', 'referencia' => '370', 'monto' => 809167.35,
            'descripcion' => \App\Services\Conciliacion\MercantilLiqBalance::descripcion('370', 792983.98), 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);

        // Mismo número y monto (aunque se escriba otra fecha): se concilia el que ya estaba.
        $this->actingAs($user)->post($ruta, ['linea_id' => $liq->id, 'lote_referencia' => '370', 'fecha' => '2026-10-02', 'monto' => 809167.35])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'ya estaba en Tesorería') && str_contains($m, 'Bs. 16.183,37'));
        $this->assertSame(1, TesoreriaIngreso::query()->count());
        $this->assertTrue((bool) $existente->fresh()->es_conciliado);
        $this->assertSame((int) $existente->id, (int) $liq->fresh()->tesoreria_ingreso_id);

        // Mismo número con otro monto: hay que corregir el de Tesorería, no registrar otro.
        TesoreriaIngreso::create([
            'tipo' => 'punto_venta', 'banco' => 'MERCANTIL', 'titular' => 'JRZ', 'fecha' => '2026-10-08',
            'monto' => 900000.00, 'lote_referencia' => '314', 'es_conciliado' => false,
        ]);
        $liq314 = ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => '2026-10-08', 'referencia' => '314', 'monto' => 901463.83,
            'descripcion' => \App\Services\Conciliacion\MercantilLiqBalance::descripcion('314', 883158.97), 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);
        $this->actingAs($user)->post($ruta, ['linea_id' => $liq314->id, 'lote_referencia' => '314', 'fecha' => '2026-10-07', 'monto' => 901463.83])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'ya está en Tesorería del 08/10/2026 por Bs. 900.000,00'));
        $this->assertSame(2, TesoreriaIngreso::query()->count());
        $this->assertSame('pendiente', $liq314->fresh()->estado);
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
