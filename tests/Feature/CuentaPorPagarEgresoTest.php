<?php

namespace Tests\Feature;

use App\Models\CuentaPorPagar;
use App\Models\FlujoCaja;
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
        ]))->assertRedirect();

        $cuenta->refresh();
        $this->assertEquals(500, (float) $cuenta->monto_pagado);
        $this->assertEquals(0, (float) $cuenta->saldo);
        $this->assertSame('pagada', $cuenta->estado);
        $this->assertSame(2, $cuenta->pagos()->count());
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
