<?php

namespace Tests\Feature;

use App\Models\CuadreCaja;
use App\Models\CuadreCajaPago;
use App\Models\FlujoCaja;
use App\Models\TesoreriaCaja;
use App\Models\TesoreriaCajaMovimiento;
use App\Models\TesoreriaCajaMovimientoDetalle;
use App\Models\User;
use App\Services\BcvRateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class TesoreriaCajaTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        (require database_path('migrations/2026_11_16_130000_create_tesoreria_caja_tables.php'))->up();
        (require database_path('migrations/2026_11_16_140000_tesoreria_caja_cuadre_efectivo.php'))->up();
        (require database_path('migrations/2026_11_16_150000_add_moneda_divisa_to_tesoreria_caja_movimientos.php'))->up();
        (require database_path('migrations/2026_11_17_160000_add_categoria_to_tesoreria_caja_movimientos.php'))->up();
        (require database_path('migrations/2026_11_18_123000_add_tipo_gasto_to_tesoreria_caja_movimientos.php'))->up();
        (require database_path('migrations/2026_11_18_124000_add_flujo_caja_id_to_tesoreria_caja_movimientos.php'))->up();
        $this->asegurarColumnasFlujoCaja();
        $this->mock(BcvRateService::class, fn ($m) => $m->shouldReceive('getRateForToday')->andReturn(40.0));
    }

    private function asegurarColumnasFlujoCaja(): void
    {
        $columnas = [
            'banco' => 'string', 'titular' => 'string', 'categoria_cuenta' => 'string',
            'categoria_egreso' => 'string', 'referencia' => 'string', 'motivo' => 'string',
            'origen' => 'string', 'oculto' => 'bool', 'es_conciliado' => 'bool',
            'comision' => 'decimal', 'diferencial_cambiario' => 'decimal',
        ];
        $faltan = array_filter($columnas, fn ($tipo, $col) => ! Schema::hasColumn('flujo_cajas', $col), ARRAY_FILTER_USE_BOTH);
        if ($faltan === []) {
            return;
        }
        Schema::table('flujo_cajas', function (Blueprint $table) use ($faltan) {
            foreach ($faltan as $col => $tipo) {
                match ($tipo) {
                    'bool' => $table->boolean($col)->default(false),
                    'decimal' => $table->decimal($col, 15, 2)->default(0),
                    default => $table->string($col)->nullable(),
                };
            }
        });
    }

    public function test_caja_con_ingresos_y_egresos_en_bolivares_y_divisas(): void
    {
        $user = User::create([
            'name' => 'Tesorería',
            'email' => 'tesoreria-caja-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TESORERIA,
        ]);

        $this->actingAs($user)->get(route('tesoreria.caja.index'))
            ->assertOk()
            ->assertSee('DISPONIBILIDAD EN TIEMPO REAL')
            ->assertSee('Nuevo Egreso')
            ->assertDontSee('+ Ingreso')
            ->assertSee('Tipo de gasto')
            ->assertSee('>Egreso</option>', false)
            ->assertSee('>Ingreso</option>', false)
            ->assertDontSee('EGRESOS REALIZADOS')
            ->assertDontSee('COMPRA DE DIVISAS')
            ->assertSee('BOLÍVARES')
            ->assertSee('DIVISAS');

        $bs = TesoreriaCaja::query()->where('codigo', TesoreriaCaja::CODIGO_BS)->firstOrFail();
        $usd = TesoreriaCaja::query()->where('codigo', TesoreriaCaja::CODIGO_USD)->firstOrFail();

        $this->actingAs($user)->post(route('tesoreria.caja.store'), [
            'caja_id' => $bs->id,
            'tipo' => 'ingreso',
            'fecha' => now()->toDateString(),
            'monto_bs' => 1000,
            'concepto' => 'Apertura caja Bs',
        ])->assertRedirect(route('tesoreria.caja.index'));

        $this->actingAs($user)->post(route('tesoreria.caja.store'), [
            'caja_id' => $usd->id,
            'tipo' => 'ingreso',
            'fecha' => now()->toDateString(),
            'moneda_divisa' => 'USD',
            'monto_divisa' => 200,
            'concepto' => 'Apertura caja USD',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('tesoreria.caja.store'), [
            'caja_id' => $usd->id,
            'tipo' => 'ingreso',
            'fecha' => now()->toDateString(),
            'moneda_divisa' => 'EUR',
            'monto_divisa' => 50,
            'concepto' => 'Apertura caja EUR',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('tesoreria.caja.store'), [
            'caja_id' => $bs->id,
            'tipo' => 'egreso',
            'tipo_gasto' => '087 - PAGO PROVEEDORES',
            'fecha' => now()->toDateString(),
            'monto_bs' => 250,
            'concepto' => 'Pago proveedor',
            'referencia' => 'REF-1',
        ])->assertRedirect();

        $this->assertEquals(750.0, (float) $bs->fresh()->saldo);
        $this->assertEquals(250.0, (float) $usd->fresh()->saldo);
        $this->assertSame(['USD' => 200.0, 'EUR' => 50.0], $usd->fresh()->saldosDivisa());

        $egreso = TesoreriaCajaMovimiento::query()->where('concepto', 'Pago proveedor')->firstOrFail();
        $this->assertSame('087 - PAGO PROVEEDORES', $egreso->tipo_gasto);
        $this->assertSame('egreso', $egreso->tipo);
        $this->assertNotNull($egreso->flujo_caja_id);
        $espejo = FlujoCaja::query()->findOrFail($egreso->flujo_caja_id);
        $this->assertSame(FlujoCaja::ORIGEN_TESORERIA_CAJA, $espejo->origen);
        $this->assertFalse((bool) $espejo->oculto);
        $this->assertSame('egreso_realizado', $espejo->categoria_egreso);
        $this->assertSame('CAJA PRINCIPAL', $espejo->banco);
        $this->assertSame('087 - PAGO PROVEEDORES', $espejo->tipo_gasto);
        $this->assertSame('6.25', number_format((float) $espejo->monto_usd, 2, '.', ''));

        $this->actingAs($user)->get(route('tesoreria.caja.index'))
            ->assertOk()
            ->assertSee('USD / EUR')
            ->assertSee('$200,00')
            ->assertSee('€50,00')
            ->assertSee('087 - PAGO PROVEEDORES');

        $this->actingAs($user)->post(route('tesoreria.caja.store'), [
            'caja_id' => $bs->id,
            'tipo' => 'egreso',
            'fecha' => now()->toDateString(),
            'monto_bs' => 100,
            'concepto' => 'Sin tipo de gasto',
        ])->assertSessionHasErrors('tipo_gasto');

        $this->actingAs($user)->post(route('tesoreria.caja.store'), [
            'caja_id' => $bs->id,
            'tipo' => 'egreso',
            'tipo_gasto' => '036 - MANT. Y REP.',
            'fecha' => now()->toDateString(),
            'monto_bs' => 900,
            'concepto' => 'Demasiado',
        ])->assertSessionHasErrors('monto');

        $this->actingAs($user)->delete(route('tesoreria.caja.destroy', $egreso))->assertRedirect();
        $this->assertEquals(1000.0, (float) $bs->fresh()->saldo);
        $this->assertNull(FlujoCaja::query()->find($espejo->id));

        $this->actingAs($user)->get(route('tesoreria.dash'))
            ->assertOk()
            ->assertSee('Dashboard Tesorería')
            ->assertSee('Forma de pago')
            ->assertSee('Descargar PDF');

        $this->actingAs($user)->get(route('tesoreria.dash.pdf', [
            'desde' => now()->startOfMonth()->toDateString(),
            'hasta' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_al_procesar_cuadre_el_efectivo_entra_como_ingreso_diario_con_detalle_por_sede(): void
    {
        $this->migrarCuadreMinimo();

        $user = User::create([
            'name' => 'Tesorería',
            'email' => 'tesoreria-cuadre-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TESORERIA,
        ]);

        $doral = CuadreCaja::create([
            'sede' => 'DORAL', 'id_cierre' => 90001, 'fecha_cierre' => '2026-11-16', 'codigo_caja' => 'CJA02',
            'descripcion_caja' => 'CAJA 02', 'usuario' => '111', 'usuario_nombre' => 'CAJERA A',
            'estado' => 2, 'factor_cambio' => 100,
        ]);
        CuadreCajaPago::create([
            'cuadre_caja_id' => $doral->id, 'codigo_metodo' => '02', 'forma_pago' => 'EFECTIVO $',
            'en_dolares' => true, 'ingresos' => 100, 'ingresos_usd' => 100,
        ]);
        CuadreCajaPago::create([
            'cuadre_caja_id' => $doral->id, 'codigo_metodo' => '01', 'forma_pago' => 'EFECTIVO BS',
            'en_dolares' => false, 'ingresos' => 5000, 'ingresos_usd' => 50,
        ]);

        $valencia = CuadreCaja::create([
            'sede' => 'VALENCIA', 'id_cierre' => 90002, 'fecha_cierre' => '2026-11-16', 'codigo_caja' => 'CJA01',
            'descripcion_caja' => 'CAJA 01', 'usuario' => '222', 'usuario_nombre' => 'CAJERA B',
            'estado' => 2, 'factor_cambio' => 100,
        ]);
        CuadreCajaPago::create([
            'cuadre_caja_id' => $valencia->id, 'codigo_metodo' => '02', 'forma_pago' => 'EFECTIVO $',
            'en_dolares' => true, 'ingresos' => 40, 'ingresos_usd' => 40,
        ]);

        $this->actingAs($user)->post(route('cuadre_caja.conciliar', $doral), [
            'accion' => 'procesar',
            'montos' => ['02|' => '95', '01|' => '4800'],
            'observacion' => 'Ok Doral',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('cuadre_caja.conciliar', $valencia), [
            'accion' => 'procesar',
            'montos' => ['02|' => '40'],
            'observacion' => 'Ok Valencia',
        ])->assertRedirect();

        $usd = TesoreriaCaja::query()->where('codigo', TesoreriaCaja::CODIGO_USD)->firstOrFail();
        $bs = TesoreriaCaja::query()->where('codigo', TesoreriaCaja::CODIGO_BS)->firstOrFail();
        $this->assertEquals(135.0, (float) $usd->fresh()->saldo);
        $this->assertEquals(4800.0, (float) $bs->fresh()->saldo);

        $ingresoUsd = TesoreriaCajaMovimiento::query()
            ->where('origen', TesoreriaCajaMovimiento::ORIGEN_CUADRE_DIARIO)
            ->where('caja_id', $usd->id)
            ->whereDate('fecha', '2026-11-16')
            ->firstOrFail();
        $this->assertEquals(135.0, (float) $ingresoUsd->monto);
        $this->assertCount(2, $ingresoUsd->detalles);
        $this->assertEquals(95.0, (float) $ingresoUsd->detalles->firstWhere('sede', 'DORAL')->monto);
        $this->assertEquals(40.0, (float) $ingresoUsd->detalles->firstWhere('sede', 'VALENCIA')->monto);

        $this->actingAs($user)->get(route('tesoreria.caja.index', [
            'desde' => '2026-11-16', 'hasta' => '2026-11-16',
        ]))
            ->assertOk()
            ->assertSee('Efectivo contado en cuadres del día')
            ->assertSee('Detalle por sede')
            ->assertSee('DORAL')
            ->assertSee('VALENCIA');

        $this->actingAs($user)->post(route('cuadre_caja.reabrir', $doral))->assertRedirect();
        $this->assertEquals(40.0, (float) $usd->fresh()->saldo);
        $this->assertEquals(0.0, (float) $bs->fresh()->saldo);
        $this->assertNull(TesoreriaCajaMovimientoDetalle::query()->where('cuadre_caja_id', $doral->id)->first());
    }

    private function migrarCuadreMinimo(): void
    {
        (require database_path('migrations/2026_11_05_090000_create_cuadres_caja_tables.php'))->up();
        (require database_path('migrations/2026_11_05_130000_cuadres_caja_por_dia_de_caja.php'))->up();
        (require database_path('migrations/2026_11_06_100000_add_estado_tesoreria_to_cuadres_caja.php'))->up();
        (require database_path('migrations/2026_11_06_120000_create_cuadre_caja_lotes_table.php'))->up();
        (require database_path('migrations/2026_11_06_140000_add_banco_to_cuadre_caja_lotes.php'))->up();
        (require database_path('migrations/2026_11_07_100000_add_titular_to_cuadre_caja_lotes.php'))->up();
        if (! Schema::hasTable('tesoreria_ingresos')) {
            Schema::create('tesoreria_ingresos', function (Blueprint $t) {
                $t->id();
                $t->string('tipo');
                $t->string('banco')->nullable();
                $t->string('titular')->nullable();
                $t->date('fecha');
                $t->decimal('monto', 15, 2);
                $t->string('lote_referencia')->nullable();
                $t->text('descripcion')->nullable();
                $t->string('comprobante_path')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->boolean('es_conciliado')->default(false);
                $t->date('conciliar_en')->nullable();
                $t->timestamps();
            });
        }
    }
}
