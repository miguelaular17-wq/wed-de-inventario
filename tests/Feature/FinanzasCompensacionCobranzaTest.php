<?php

namespace Tests\Feature;

use App\Models\CobranzaFactura;
use App\Models\CobranzaMovimiento;
use App\Models\FlujoCaja;
use App\Models\User;
use App\Services\BcvRateService;
use App\Services\Cobranza\CobranzaFacturaService;
use App\Services\Cobranza\CobranzaFlujoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class FinanzasCompensacionCobranzaTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->asegurarFlujoColumnas();
        $this->mock(BcvRateService::class, fn ($m) => $m->shouldReceive('getRateForToday')->andReturn(40.0));
    }

    public function test_compensacion_desde_finanzas_abona_factura_sin_espejo_bancario(): void
    {
        Schema::create('cobranza_movimientos', function (Blueprint $t) {
            $t->id();
            $t->date('fecha');
            $t->string('tipo');
            $t->string('ambito')->nullable();
            $t->string('banco')->nullable();
            $t->string('titular')->nullable();
            $t->string('categoria_cuenta')->nullable();
            $t->decimal('monto_usd', 12, 2)->default(0);
            $t->decimal('tasa_cambio', 14, 4)->default(0);
            $t->decimal('monto_bs', 14, 2)->default(0);
            $t->string('referencia')->nullable();
            $t->string('concepto')->nullable();
            $t->string('contraparte')->nullable();
            $t->text('observaciones')->nullable();
            $t->string('forma_pago')->nullable();
            $t->string('tipo_gasto')->nullable();
            $t->string('cruce_id')->nullable();
            $t->string('vinculo_tipo')->nullable();
            $t->unsignedBigInteger('vinculo_id')->nullable();
            $t->unsignedBigInteger('flujo_caja_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });
        Schema::create('cobranza_facturas', function (Blueprint $t) {
            $t->id();
            $t->string('sede')->nullable();
            $t->string('id_documento')->nullable();
            $t->string('numero_documento')->nullable();
            $t->string('nombre_cliente')->nullable();
            $t->decimal('saldo', 12, 2)->default(0);
            $t->boolean('pagada')->default(false);
            $t->timestamps();
        });

        $factura = CobranzaFactura::query()->create([
            'sede' => 'DORAL',
            'id_documento' => 'FAC-100',
            'numero_documento' => '100',
            'nombre_cliente' => 'Cliente Deuda',
            'saldo' => 80,
            'pagada' => false,
        ]);

        $facturas = Mockery::mock(CobranzaFacturaService::class);
        $facturas->shouldReceive('resolver')->andReturn($factura);
        $facturas->shouldReceive('saldo')->andReturn(80.0);
        $facturas->shouldReceive('actualizarPagada')->once();
        $this->app->instance(CobranzaFacturaService::class, $facturas);

        $user = User::create([
            'name' => 'Fin',
            'email' => 'fin-comp-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        $antes = FlujoCaja::query()->count();
        $mov = app(CobranzaFlujoService::class)->registrarCompensacionDesdeFinanzas([
            'fecha' => '2026-11-18',
            'monto_usd' => 30,
            'factura_sede' => 'DORAL',
            'factura_doc' => 'FAC-100',
            'concepto' => 'Compensación egreso Finanzas · internet',
            'observaciones' => 'Diferencia gasto $100 − pago $70',
        ], $user);

        $this->assertSame('ingreso', $mov->tipo);
        $this->assertSame(CobranzaMovimiento::CUENTA_DESCUENTO_DIVISAS, $mov->banco);
        $this->assertSame(CobranzaFactura::VINCULO, $mov->vinculo_tipo);
        $this->assertSame($factura->id, (int) $mov->vinculo_id);
        $this->assertSame('30.00', number_format((float) $mov->monto_usd, 2, '.', ''));
        $this->assertNull($mov->flujo_caja_id);
        $this->assertSame($antes, FlujoCaja::query()->count(), 'No debe crear espejo bancario');
    }

    private function asegurarFlujoColumnas(): void
    {
        $cols = [
            'banco' => 'string', 'titular' => 'string', 'categoria_cuenta' => 'string',
            'categoria_egreso' => 'string', 'referencia' => 'string', 'motivo' => 'string',
            'origen' => 'string', 'oculto' => 'bool', 'es_conciliado' => 'bool',
            'titular_receptor' => 'string', 'comision' => 'decimal', 'diferencial_cambiario' => 'decimal',
        ];
        $faltan = array_filter($cols, fn ($t, $c) => ! Schema::hasColumn('flujo_cajas', $c), ARRAY_FILTER_USE_BOTH);
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
}
