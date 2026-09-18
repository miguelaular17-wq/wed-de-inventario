<?php

namespace Tests\Unit\Nfc;

use App\Models\NfcMovimiento;
use App\Models\NfcRecompensa;
use App\Models\NfcTarjeta;
use App\Services\Nfc\NfcTarjetaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NfcTarjetaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearTablasNfc();
    }

    public function test_recarga_suma_saldo_y_registra_movimiento(): void
    {
        $service = app(NfcTarjetaService::class);
        $tarjeta = $service->asignar(['cliente_nombre' => 'Cliente Demo'], 1);

        $mov = $service->recargarSaldo($tarjeta, 25.5, 'Caja principal', 1);

        $tarjeta->refresh();
        $this->assertSame(25.5, (float) $tarjeta->saldo);
        $this->assertSame(NfcMovimiento::RECARGA, $mov->tipo);
        $this->assertSame(25.5, (float) $mov->monto);
        $this->assertSame(25.5, (float) $mov->saldo_despues);
        $this->assertSame('Caja principal', $mov->concepto);
    }

    public function test_restar_saldo_descuenta_y_falla_si_no_alcanza(): void
    {
        $service = app(NfcTarjetaService::class);
        $tarjeta = $service->asignar(['cliente_nombre' => 'Cliente'], 1);
        $service->recargarSaldo($tarjeta, 20, null, 1);

        $mov = $service->restarSaldo($tarjeta, 7.5, 'Consumo', 1);
        $tarjeta->refresh();
        $this->assertSame(12.5, (float) $tarjeta->saldo);
        $this->assertSame(NfcMovimiento::GASTO, $mov->tipo);
        $this->assertSame(-7.5, (float) $mov->monto);

        $this->expectException(ValidationException::class);
        $service->restarSaldo($tarjeta, 100);
    }

    public function test_sumar_y_restar_puntos(): void
    {
        $service = app(NfcTarjetaService::class);
        $tarjeta = $service->asignar(['cliente_nombre' => 'Cliente Puntos'], 1);

        $service->sumarPuntos($tarjeta, 100, 'Bienvenida', 1);
        $mov = $service->restarPuntos($tarjeta, 40, 'Ajuste', 1);

        $tarjeta->refresh();
        $this->assertSame(60, (int) $tarjeta->puntos);
        $this->assertSame(NfcMovimiento::RESTAR_PUNTOS, $mov->tipo);
        $this->assertSame(-40, (int) $mov->puntos);
    }

    public function test_canje_recompensa_descuenta_puntos(): void
    {
        $service = app(NfcTarjetaService::class);
        $tarjeta = $service->asignar(['cliente_nombre' => 'Cliente Canje'], 1);
        $service->sumarPuntos($tarjeta, 150, null, 1);

        $recompensa = NfcRecompensa::create([
            'nombre' => 'Café gratis',
            'descripcion' => 'Cualquier tamaño',
            'puntos_costo' => 100,
            'activa' => true,
            'orden' => 1,
        ]);

        $mov = $service->canjearRecompensa($tarjeta, $recompensa, 1);
        $tarjeta->refresh();

        $this->assertSame(50, (int) $tarjeta->puntos);
        $this->assertSame(NfcMovimiento::CANJE, $mov->tipo);
        $this->assertSame(-100, (int) $mov->puntos);
        $this->assertSame($recompensa->id, (int) $mov->nfc_recompensa_id);
        $this->assertStringContainsString('Café gratis', (string) $mov->concepto);
    }

    public function test_canje_falla_sin_puntos(): void
    {
        $service = app(NfcTarjetaService::class);
        $tarjeta = $service->asignar(['cliente_nombre' => 'Cliente'], 1);
        $recompensa = NfcRecompensa::create([
            'nombre' => 'Premio',
            'puntos_costo' => 50,
            'activa' => true,
        ]);

        $this->expectException(ValidationException::class);
        $service->canjearRecompensa($tarjeta, $recompensa, 1);
    }

    public function test_recarga_cero_falla(): void
    {
        $service = app(NfcTarjetaService::class);
        $tarjeta = $service->asignar(['cliente_nombre' => 'Cliente'], 1);

        $this->expectException(ValidationException::class);
        $service->recargarSaldo($tarjeta, 0);
    }

    private function crearTablasNfc(): void
    {
        Schema::dropIfExists('nfc_movimientos');
        Schema::dropIfExists('nfc_recompensas');
        Schema::dropIfExists('nfc_tarjetas');

        Schema::create('nfc_tarjetas', function (Blueprint $table) {
            $table->id();
            $table->string('token', 32)->unique();
            $table->string('uid', 64)->nullable()->unique();
            $table->string('cliente_nombre', 160);
            $table->string('cliente_cedula', 32)->nullable();
            $table->string('cliente_telefono', 40)->nullable();
            $table->string('cliente_email', 160)->nullable();
            $table->text('notas')->nullable();
            $table->decimal('saldo', 12, 2)->default(0);
            $table->unsignedInteger('puntos')->default(0);
            $table->string('estado', 16)->default('ACTIVA');
            $table->timestamp('asignada_at')->nullable();
            $table->unsignedBigInteger('asignada_por')->nullable();
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->timestamps();
        });

        Schema::create('nfc_recompensas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 160);
            $table->string('descripcion', 500)->nullable();
            $table->unsignedInteger('puntos_costo');
            $table->boolean('activa')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('nfc_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nfc_tarjeta_id');
            $table->unsignedBigInteger('nfc_recompensa_id')->nullable();
            $table->string('tipo', 24);
            $table->decimal('monto', 12, 2)->default(0);
            $table->integer('puntos')->default(0);
            $table->decimal('saldo_despues', 12, 2)->default(0);
            $table->unsignedInteger('puntos_despues')->default(0);
            $table->string('concepto', 255)->nullable();
            $table->unsignedBigInteger('registrado_por')->nullable();
            $table->timestamps();
        });
    }
}
