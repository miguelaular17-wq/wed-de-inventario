<?php

namespace Tests\Feature;

use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class CasheaConciliacionTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        Storage::fake('local');
        if (! Schema::hasTable('tesoreria_ingresos')) {
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
        }
        if (! Schema::hasTable('conciliacion_lineas')) {
            Schema::create('conciliacion_lineas', function (Blueprint $t) {
                $t->id();
                $t->string('banco')->nullable();
                $t->string('titular')->nullable();
                $t->date('fecha')->nullable();
                $t->text('descripcion')->nullable();
                $t->string('referencia')->nullable();
                $t->decimal('monto', 15, 2)->default(0);
                $t->string('estado', 32)->default('pendiente');
                $t->string('tipo', 32)->nullable();
                $t->unsignedBigInteger('flujo_caja_id')->nullable();
                $t->unsignedBigInteger('tesoreria_ingreso_id')->nullable();
                $t->unsignedBigInteger('compra_divisa_id')->nullable();
                $t->timestamps();
            });
        }
    }

    private function abono(string $fecha, string $ref, float $monto, string $titular = 'L.S. CASHEA'): ConciliacionLinea
    {
        return ConciliacionLinea::create([
            'banco' => 'BNC', 'titular' => $titular, 'fecha' => $fecha, 'descripcion' => 'TELF.:584121414554 BANCO:0191 PMPC',
            'referencia' => $ref, 'monto' => $monto, 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);
    }

    private function export(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cashea') . '.csv';
        file_put_contents($tmp, "Fecha,Referencia,Debito bs,Tasa de cambio,Usd,Status,Tipo pago,Nro cuota pagada,Fecha programada cuota,Orden,Factura,Sucursal,Canal,Monto asignado\n"
            // Una cuota, cuadra exacto (el export trae más decimales que el banco).
            . "2026-08-22,'823431284740,15693.266000000001,784.6633,20,CONCILIADO,Pago Adelantado,3,2026-09-02,191566549,142828,AV BOLIVAR,IN STORE,20\n"
            // Un pago móvil para dos cuotas + fila de ajuste de $0,0033 que el banco no recibe.
            . "2026-08-30,'1040219537,30000,794.9917,37.736,CONCILIADO,Pago En Periodo,2,2026-08-30,203149076,147429,AV BOLIVAR,IN STORE,37.736\n"
            . "2026-08-30,'1040219537,5210.18,794.9917,6.5537,CONCILIADO,Pago En Periodo,3,2026-09-16,203149076,147429,AV BOLIVAR,IN STORE,6.5537\n"
            . "2026-08-30,'1040219537,2.6234726100000003,794.9917,0.0033,CONCILIADO,Pago Adelantado,2,2026-09-16,203149076,147429,AV BOLIVAR,IN STORE,0.0033\n"
            // Diferencia pequeña (0,1 %) con la referencia exacta: se concilia con nota.
            . "2026-08-07,'980909372,7007.5,766,9.15,CONCILIADO,Pago En Periodo,1,2026-08-07,1,1,AV BOLIVAR,IN STORE,9.15\n"
            // Diferencia grande: a revisar.
            . "2026-08-08,'980909999,9000,766,11.75,CONCILIADO,Pago En Periodo,1,2026-08-08,2,2,AV BOLIVAR,IN STORE,11.75\n"
            // Transferencia: el banco trae una referencia larga con la misma cola de 6 dígitos.
            . "2026-08-09,'14044736,11733.48,766,15.32,CONCILIADO,Pago En Periodo,1,2026-08-09,3,3,AV BOLIVAR,IN STORE,15.32\n"
            // No está en el banco.
            . "2026-08-31,'1042050901,4785.850034,794.9917,6.02,CONCILIADO,Pago Adelantado,2,2026-09-04,198307919,5694586,AV BOLIVAR,IN STORE,6.02\n");

        return new UploadedFile($tmp, 'export (1).csv', 'text/csv', null, true);
    }

    public function test_concilia_por_referencia_y_monto_sumando_cuotas_y_quitando_ajustes(): void
    {
        $exacto = $this->abono('2026-08-22', '823431284740', 15693.27);
        $agrupado = $this->abono('2026-08-30', '1040219537', 35210.18);
        $copia = $this->abono('2026-08-30', '1040219537', 35210.18);
        $diferencia = $this->abono('2026-08-07', '980909372', 7000.00);
        $revisar = $this->abono('2026-08-08', '980909999', 8000.00);
        $cola = $this->abono('2026-08-09', '10492614044736', 11733.48);
        $sobra = $this->abono('2026-08-10', '555555555', 88000.00);
        $otraCuenta = $this->abono('2026-08-22', '823431284740', 15693.27, 'GRUPO JRZ');
        $user = User::create(['name' => 'Contab', 'email' => 'contab-cashea@test.local', 'password' => 'password123', 'role' => User::ROLE_CONTABILIDAD]);

        $this->actingAs($user)->post(route('finanzas.conciliaciones.cashea.preview'), ['archivo' => $this->export()])
            ->assertOk()
            ->assertSee('Se concilian al confirmar')
            ->assertSee('1 líneas repetidas', false)
            ->assertSee('Por día (4)', false)
            ->assertSee('diferencia de Bs 7,50 con Cashea', false)
            ->assertSee('por los últimos 6 dígitos', false)
            ->assertSee('diferencia Bs 1.000,00', false)
            ->assertSee('Abonos del banco sin pago en Cashea (1', false);

        $token = array_key_first(session('cashea'));
        $this->actingAs($user)->post(route('finanzas.conciliaciones.cashea.aplicar'), ['token' => $token])
            ->assertRedirect(route('finanzas.conciliaciones'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '4 abonos conciliados') && str_contains($m, '4 ingresos de Tesorería') && str_contains($m, '1 para revisar') && str_contains($m, '1 sin abono'));

        $this->assertSame('conciliado', $exacto->fresh()->estado);
        $this->assertSame('conciliado', $agrupado->fresh()->estado);
        $this->assertSame('conciliado', $diferencia->fresh()->estado);
        $this->assertSame('conciliado', $cola->fresh()->estado);
        $this->assertSame('pendiente', $copia->fresh()->estado);
        $this->assertSame('pendiente', $revisar->fresh()->estado);
        $this->assertSame('pendiente', $sobra->fresh()->estado);
        $this->assertSame('pendiente', $otraCuenta->fresh()->estado);

        $ingreso = TesoreriaIngreso::find($agrupado->fresh()->tesoreria_ingreso_id);
        $this->assertSame(['banco', 'BNC', 'L.S. CASHEA', 'CASHEA-20260830'], [$ingreso->tipo, $ingreso->banco, $ingreso->titular, $ingreso->lote_referencia]);
        $this->assertEquals(35210.18, (float) $ingreso->monto);
        $this->assertTrue((bool) $ingreso->es_conciliado);

        // Subirlo otra vez no duplica: lo conciliado queda como «ya conciliado».
        $this->actingAs($user)->post(route('finanzas.conciliaciones.cashea.preview'), ['archivo' => $this->export()])
            ->assertOk()
            ->assertDontSee('Confirmar y conciliar');
        $this->assertSame(4, TesoreriaIngreso::query()->count());
    }

    public function test_varios_pagos_moviles_con_la_misma_referencia_suman_el_pago_de_cashea(): void
    {
        $a = $this->abono('2026-08-01', '821358607475', 25675.89);
        $b = $this->abono('2026-08-01', '821358607475', 25678.35);
        $tmp = tempnam(sys_get_temp_dir(), 'cashea') . '.csv';
        file_put_contents($tmp, "Fecha,Referencia,Debito bs,Tasa de cambio,Usd,Status,Tipo pago,Nro cuota pagada,Fecha programada cuota,Orden,Factura,Sucursal,Canal,Monto asignado\n"
            . "2026-08-01,'821358607475,51354.24,750,68.47,CONCILIADO,Pago En Periodo,1,2026-08-01,1,1,AV BOLIVAR,IN STORE,68.47\n");
        $svc = app(\App\Services\Conciliacion\CasheaConciliador::class);
        $r = $svc->verificar($svc->leer($tmp, 'csv'));

        $this->assertSame('conciliar', $r['pagos'][0]['estado']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_map(fn ($l) => $l->id, $r['pagos'][0]['lineas']));
        $this->assertStringContainsString('pagado en 2 pagos móviles', $r['pagos'][0]['nota']);
        $svc->aplicar($r['pagos'], 'x.csv', null);
        $this->assertSame($a->fresh()->tesoreria_ingreso_id, $b->fresh()->tesoreria_ingreso_id);
        $this->assertEquals(51354.24, (float) TesoreriaIngreso::query()->sole()->monto);
    }
}
