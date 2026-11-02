<?php

namespace Tests\Feature;

use App\Models\CuentaPorPagar;
use App\Models\FlujoCaja;
use App\Models\GastoFijo;
use App\Models\GastoFijoPago;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class CuentaPorPagarEgresoTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->ensureFlujoCajaColumns();
        $this->ensureFinanzasResumenTable();
    }

    public function test_egreso_marcado_crea_cuenta_por_pagar_con_saldo(): void
    {
        $user = $this->finanzasUser();

        $this->actingAs($user)->post(route('finanzas.store_egreso'), $this->egresoPayload([
            'es_cuenta_por_pagar' => 1,
            'monto_total_gasto' => 1000,
            'monto_usd' => 200,
            'motivo' => 'Abono proveedor',
        ]));

        $this->assertDatabaseCount('cuentas_por_pagar', 1);
        $cuenta = CuentaPorPagar::query()->first();
        $this->assertSame('USD', $cuenta->moneda);
        $this->assertEquals(1000, (float) $cuenta->monto_total);
        $this->assertEquals(200, (float) $cuenta->monto_pagado);
        $this->assertEquals(800, (float) $cuenta->saldo);
        $this->assertTrue($cuenta->estaAbierta());
        $this->assertEquals($cuenta->id, FlujoCaja::query()->first()->cuenta_por_pagar_id);
    }

    public function test_abrir_cuenta_sin_egreso_deja_saldo_completo(): void
    {
        $user = $this->finanzasUser();

        $this->actingAs($user)->post(route('finanzas.cuentas_por_pagar.store'), [
            'fecha' => '2026-09-24',
            'beneficiario' => 'Proveedor Solo Gasto',
            'tipo_gasto' => '087 - PAGO PROVEEDORES',
            'motivo' => 'Factura pendiente',
            'sede' => 'DORAL',
            'moneda' => 'USD',
            'monto_total' => 1500.50,
        ])->assertRedirect(route('finanzas.cuentas_por_pagar'));

        $this->assertDatabaseCount('cuentas_por_pagar', 1);
        $this->assertDatabaseCount('flujo_cajas', 0);

        $cuenta = CuentaPorPagar::query()->first();
        $this->assertSame('Proveedor Solo Gasto', $cuenta->beneficiario);
        $this->assertEquals(1500.50, (float) $cuenta->monto_total);
        $this->assertEquals(0, (float) $cuenta->monto_pagado);
        $this->assertEquals(1500.50, (float) $cuenta->saldo);
        $this->assertSame('abierta', $cuenta->estado);
        $this->assertTrue($cuenta->estaAbierta());
    }

    public function test_pago_siguiente_reduce_saldo_y_cierra_cuenta(): void
    {
        $user = $this->finanzasUser();

        $this->actingAs($user)->post(route('finanzas.store_egreso'), $this->egresoPayload([
            'es_cuenta_por_pagar' => 1,
            'monto_total_gasto' => 500,
            'monto_usd' => 200,
            'motivo' => 'Primera cuota',
        ]))->assertRedirect();

        $cuenta = CuentaPorPagar::query()->first();

        $this->actingAs($user)->post(route('finanzas.store_egreso'), $this->egresoPayload([
            'cuenta_por_pagar_id' => $cuenta->id,
            'monto_usd' => 300,
            'motivo' => 'Segunda cuota',
        ]))->assertRedirect(route('finanzas.cuentas_por_pagar'));

        $cuenta->refresh();
        $this->assertEquals(500, (float) $cuenta->monto_pagado);
        $this->assertEquals(0, (float) $cuenta->saldo);
        $this->assertSame('pagada', $cuenta->estado);
        $this->assertSame(2, $cuenta->pagos()->count());
    }

    public function test_un_egreso_paga_varios_gastos_fijos_cada_uno_en_su_mes(): void
    {
        $this->ensureGastosFijosTables();
        $terranova = GastoFijo::create(['grupo_id' => 0, 'servicio' => 'CONDOMINIO', 'empresa' => 'TERRANOVA', 'fecha' => '8-15 de cada mes', 'costo' => 65]);
        $balcones = GastoFijo::create(['grupo_id' => 0, 'servicio' => 'CONDOMINIO', 'empresa' => 'BALCONES', 'fecha' => '8-15 de cada mes', 'costo' => 10]);

        $this->actingAs($this->finanzasUser())->post(route('finanzas.store_egreso'), $this->egresoPayload([
            'monto_usd' => 75,
            'motivo' => 'Factura condominios',
            'gf_items' => [
                ['id' => $terranova->id, 'mes_idx' => 8, 'anio' => 2026, 'monto' => 65],
                ['id' => $balcones->id, 'mes_idx' => 9, 'anio' => 2026, 'monto' => 10],
            ],
        ]))->assertRedirect();

        $this->assertDatabaseHas('gasto_fijo_pagos', ['gasto_fijo_id' => $terranova->id, 'mes_idx' => 8, 'anio' => 2026, 'pagado' => true]);
        $this->assertDatabaseHas('gasto_fijo_pagos', ['gasto_fijo_id' => $balcones->id, 'mes_idx' => 9, 'anio' => 2026, 'pagado' => true]);
        $this->assertEquals(10, (float) GastoFijoPago::query()->where('gasto_fijo_id', $balcones->id)->value('monto'));
    }

    public function test_desactivar_gasto_fijo_lo_saca_de_la_lista_para_vincular(): void
    {
        $this->ensureGastosFijosTables();
        $gasto = GastoFijo::create(['grupo_id' => 0, 'servicio' => 'CONDOMINIO', 'empresa' => 'SALAMAR', 'fecha' => '8', 'costo' => 100]);
        $user = $this->finanzasUser();

        $this->actingAs($user)->postJson(route('finanzas.gastos_fijos.config'), [
            'gasto_fijo_id' => $gasto->id,
            'activo' => false,
            'meses_atraso' => 1,
        ])->assertOk()->assertJson(['activo' => false, 'meses_atraso' => 1]);

        $lista = $this->actingAs($user)->getJson(route('finanzas.gastos_fijos.pendientes'))->assertOk()->json();
        $this->assertNotContains($gasto->id, array_column($lista, 'id'));
    }

    private function ensureGastosFijosTables(): void
    {
        if (! Schema::hasTable('gastos_fijos')) {
            Schema::create('gastos_fijos', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('grupo_id');
                $table->string('sede', 200)->default('');
                $table->string('servicio', 200);
                $table->string('fecha', 100)->default('');
                $table->string('empresa', 200)->default('');
                $table->decimal('costo', 10, 2)->default(0);
                $table->integer('orden')->default(0);
                $table->boolean('visible')->default(true);
                $table->timestamps();
            });
        }
        foreach (['activo' => fn (Blueprint $t) => $t->boolean('activo')->default(true), 'meses_atraso' => fn (Blueprint $t) => $t->unsignedTinyInteger('meses_atraso')->default(0)] as $col => $def) {
            if (! Schema::hasColumn('gastos_fijos', $col)) {
                Schema::table('gastos_fijos', $def);
            }
        }
        if (! Schema::hasTable('gasto_fijo_pagos')) {
            Schema::create('gasto_fijo_pagos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('gasto_fijo_id')->nullable();
                $table->integer('tabla_idx')->nullable();
                $table->integer('fila_idx')->nullable();
                $table->integer('mes_idx');
                $table->integer('anio');
                $table->decimal('monto', 10, 2)->nullable();
                $table->boolean('pagado')->default(false);
                $table->timestamp('pagado_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function finanzasUser(): User
    {
        return User::create([
            'name' => 'Finanzas CxP',
            'email' => 'finanzas-cxp-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_FINANZAS,
        ]);
    }

    private function egresoPayload(array $extra = []): array
    {
        return array_merge([
            'categoria_egreso' => 'egreso_realizado',
            'banco_titular' => 'Banesco|Grupo JRZ|BANCA NACIONAL',
            'fecha' => '2026-09-05',
            'monto_usd' => 100,
            'monto_bs' => 0,
            'tipo_gasto' => 'Compras',
            'motivo' => 'Pago',
            'beneficiario' => 'Proveedor Demo',
        ], $extra);
    }

    private function ensureFlujoCajaColumns(): void
    {
        $columns = [
            'categoria_egreso' => fn (Blueprint $table) => $table->string('categoria_egreso')->nullable(),
            'banco' => fn (Blueprint $table) => $table->string('banco')->nullable(),
            'titular' => fn (Blueprint $table) => $table->string('titular')->nullable(),
            'categoria_cuenta' => fn (Blueprint $table) => $table->string('categoria_cuenta')->nullable(),
            'banco_receptor' => fn (Blueprint $table) => $table->string('banco_receptor')->nullable(),
            'titular_receptor' => fn (Blueprint $table) => $table->string('titular_receptor')->nullable(),
            'referencia' => fn (Blueprint $table) => $table->string('referencia')->nullable(),
            'motivo' => fn (Blueprint $table) => $table->text('motivo')->nullable(),
            'diferencial_cambiario' => fn (Blueprint $table) => $table->decimal('diferencial_cambiario', 14, 2)->nullable(),
            'comision' => fn (Blueprint $table) => $table->decimal('comision', 14, 2)->nullable(),
            'sede' => fn (Blueprint $table) => $table->string('sede')->nullable(),
            'placa_vehiculo' => fn (Blueprint $table) => $table->string('placa_vehiculo')->nullable(),
            'oculto' => fn (Blueprint $table) => $table->boolean('oculto')->default(false),
            'comprobante_url' => fn (Blueprint $table) => $table->string('comprobante_url')->nullable(),
            'comprobantes' => fn (Blueprint $table) => $table->text('comprobantes')->nullable(),
            'desglose' => fn (Blueprint $table) => $table->text('desglose')->nullable(),
            'es_todoticket' => fn (Blueprint $table) => $table->boolean('es_todoticket')->default(false),
            'detalle_todoticket' => fn (Blueprint $table) => $table->text('detalle_todoticket')->nullable(),
            'cuenta_por_pagar_id' => fn (Blueprint $table) => $table->unsignedBigInteger('cuenta_por_pagar_id')->nullable(),
        ];

        foreach ($columns as $name => $define) {
            if (! Schema::hasColumn('flujo_cajas', $name)) {
                Schema::table('flujo_cajas', $define);
            }
        }
    }

    private function ensureFinanzasResumenTable(): void
    {
        if (Schema::hasTable('finanzas_resumen')) {
            return;
        }

        Schema::create('finanzas_resumen', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->nullable();
            $table->decimal('tasa_bcv_usd', 14, 4)->default(1);
            $table->decimal('saldo_inicial', 14, 2)->default(0);
            $table->decimal('queda_dia_anterior', 14, 2)->default(0);
            $table->decimal('porcentaje_total_diferencial', 14, 4)->default(0);
            $table->decimal('total_egresos_usd', 14, 2)->default(0);
            $table->decimal('total_egresos_bs_usd', 14, 2)->default(0);
            $table->decimal('total_otros_usd', 14, 2)->default(0);
            $table->decimal('total_otros_bs_usd', 14, 2)->default(0);
            $table->decimal('total_salidas_usd', 14, 2)->default(0);
            $table->decimal('total_salidas_bs_en_usd', 14, 2)->default(0);
            $table->timestamps();
        });
    }
}
