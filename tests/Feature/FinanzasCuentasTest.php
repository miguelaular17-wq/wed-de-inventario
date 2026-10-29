<?php

namespace Tests\Feature;

use App\Http\Controllers\FinanzasController;
use App\Models\CuentaBancaria;
use App\Models\FinanzasCatalogo;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class FinanzasCuentasTest extends TestCase
{
    use CreatesNominaSchema;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        (require database_path('migrations/2026_10_27_160000_create_finanzas_catalogos_table.php'))->up();
        (require database_path('migrations/2026_10_27_180000_add_reporte_to_finanzas_catalogos.php'))->up();
        if (! Schema::hasTable('cuentas_bancarias')) {
            Schema::create('cuentas_bancarias', function (Blueprint $t) {
                $t->id();
                $t->string('banco');
                $t->string('titular');
                $t->string('categoria_reporte')->nullable();
                $t->boolean('mostrar_en_principal')->default(false);
                $t->string('color_tc')->nullable();
                $t->integer('orden')->default(0);
                $t->decimal('reporte_bs', 18, 2)->default(0);
                $t->string('numero_cuenta', 32)->nullable();
                $t->timestamps();
            });
        }
        $this->user = User::create(['name' => 'Finanzas', 'email' => 'fin-cta@test.local', 'password' => 'password123', 'role' => User::ROLE_FINANZAS]);
    }

    public function test_disponibilidad_y_reporte_se_editan_por_cuenta_y_se_pueden_quitar_sin_perder_saldos(): void
    {
        $cuenta = CuentaBancaria::query()->create([
            'banco' => 'BANESCO', 'titular' => 'GRUPO JRZ', 'categoria_reporte' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
            'mostrar_en_principal' => true, 'orden' => 1, 'color_tc' => '#f4b183', 'reporte_bs' => 1500,
        ]);
        CuentaBancaria::query()->create(['banco' => 'BINANCE', 'titular' => 'MARIA NUÑEZ', 'categoria_reporte' => 'BANCA INTERNACIONAL / BILLETERAS', 'orden' => 3]);

        $this->actingAs($this->user)->get(route('finanzas.cuentas'))
            ->assertOk()
            ->assertSeeInOrder(['Banca nacional · alto y mediano movimiento', 'GRUPO JRZ', 'Banca internacional / billeteras', 'MARIA NUÑEZ']);

        $this->actingAs($this->user)->post(route('finanzas.cuentas.guardar', $cuenta), [
            'categoria_reporte' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'numero_cuenta' => '0134 1070 4900 0101 3470',
            'color_tc' => '#00b050', 'orden' => 7, 'mostrar_en_principal' => 1,
        ])->assertRedirect(route('finanzas.cuentas', ['tab' => 'reporte']))->assertSessionHas('success');
        $cuenta->refresh();
        $this->assertSame(['BANCA NACIONAL - BAJO MOVIMIENTO', '01341070490001013470', '#00b050', 7, true],
            [$cuenta->categoria_reporte, $cuenta->numero_cuenta, $cuenta->color_tc, (int) $cuenta->orden, (bool) $cuenta->mostrar_en_principal]);

        // Sin marcar «En Disponibilidad» y «sin color».
        $this->actingAs($this->user)->post(route('finanzas.cuentas.guardar', $cuenta), [
            'categoria_reporte' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'numero_cuenta' => '01341070490001013470', 'color_tc' => '#ffffff', 'sin_color' => 1,
        ]);
        $this->assertFalse((bool) $cuenta->fresh()->mostrar_en_principal);
        $this->assertNull($cuenta->fresh()->color_tc);

        $this->actingAs($this->user)->post(route('finanzas.cuentas.quitar', $cuenta))->assertSessionHas('success');
        $this->assertSame(FinanzasCatalogo::SECCION_OCULTA, $cuenta->fresh()->categoria_reporte);
        $this->assertEquals(1500, (float) $cuenta->fresh()->reporte_bs);
        $this->actingAs($this->user)->get(route('finanzas.cuentas'))->assertSee('Quitadas del reporte (1)');

        // Volver a ponerla: elegir sección y guardar.
        $this->actingAs($this->user)->post(route('finanzas.cuentas.guardar', $cuenta), ['categoria_reporte' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'mostrar_en_principal' => 1]);
        $this->assertSame('BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', $cuenta->fresh()->categoria_reporte);
        $this->assertSame(2, CuentaBancaria::query()->count());
    }

    public function test_agregar_cuenta_al_reporte_y_no_repetirla(): void
    {
        $this->actingAs($this->user)->post(route('finanzas.cuentas.crear'), [
            'banco' => 'bbva', 'titular' => ' grupo  jrz ', 'categoria_reporte' => 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 'numero_cuenta' => '01080258000100351262',
        ])->assertSessionHas('success');
        $nueva = CuentaBancaria::query()->sole();
        $this->assertSame(['BBVA', 'GRUPO JRZ', '01080258000100351262'], [$nueva->banco, $nueva->titular, $nueva->numero_cuenta]);
        $this->assertFalse((bool) $nueva->mostrar_en_principal);

        $this->actingAs($this->user)->post(route('finanzas.cuentas.crear'), [
            'banco' => 'BBVA', 'titular' => 'Grupo JRZ', 'categoria_reporte' => 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS',
        ])->assertSessionHas('error', fn ($m) => str_contains($m, 'ya está'));
        $this->assertSame(1, CuentaBancaria::query()->count());

        $this->actingAs($this->user)->post(route('finanzas.cuentas.crear'), [
            'banco' => 'BBVA', 'titular' => 'GRUPO JRZ', 'categoria_reporte' => 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 'numero_cuenta' => 'abc',
        ])->assertSessionHasErrors('numero_cuenta');
    }

    public function test_selector_de_egresos_una_fila_por_cuenta_con_sus_grupos(): void
    {
        [$nacional, $bajo] = [FinanzasCatalogo::CATEGORIAS_CUENTA[0], FinanzasCatalogo::CATEGORIAS_CUENTA[1]];
        $tarjetas = FinanzasCatalogo::CATEGORIAS_CUENTA[2];
        $doral = collect(FinanzasController::cuentasBase())->where('banco', 'Banesco')->where('titular', 'Doral')->pluck('categoria')->all();
        $this->assertSame([$nacional, $tarjetas], $doral);

        $this->actingAs($this->user)->get(route('finanzas.cuentas', ['tab' => 'selector']))->assertOk()->assertSee('Doral');

        // Quitar un grupo a una cuenta del sistema: deja de salir solo en ese grupo.
        $this->actingAs($this->user)->post(route('finanzas.cuentas.grupos'), ['banco' => 'Banesco', 'titular' => 'Doral', 'grupos' => [$nacional]])
            ->assertSessionHas('success');
        $cuentas = $this->cuentasDelSelector();
        $this->assertContains(['banco' => 'Banesco', 'titular' => 'Doral', 'categoria' => $nacional], $cuentas);
        $this->assertNotContains(['banco' => 'Banesco', 'titular' => 'Doral', 'categoria' => $tarjetas], $cuentas);

        // Volver a marcarlo lo reactiva sin duplicar.
        $this->actingAs($this->user)->post(route('finanzas.cuentas.grupos'), ['banco' => 'Banesco', 'titular' => 'Doral', 'grupos' => [$nacional, $tarjetas]]);
        $this->assertContains(['banco' => 'Banesco', 'titular' => 'Doral', 'categoria' => $tarjetas], $this->cuentasDelSelector());
        $this->assertSame(1, FinanzasCatalogo::query()->where('nombre', 'Doral')->where('categoria', $tarjetas)->count());

        // Cuenta nueva en dos grupos; repetirla da error.
        $this->actingAs($this->user)->post(route('finanzas.cuentas.grupos'), ['nueva' => 1, 'banco' => 'Banesco', 'titular' => 'Tecno House', 'grupos' => [$nacional, $bajo]])
            ->assertSessionHas('success');
        $this->assertContains(['banco' => 'Banesco', 'titular' => 'Tecno House', 'categoria' => $bajo], $this->cuentasDelSelector());
        $this->actingAs($this->user)->post(route('finanzas.cuentas.grupos'), ['nueva' => 1, 'banco' => 'BANESCO', 'titular' => 'tecno house', 'grupos' => [$nacional]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'ya está en el selector'));

        // Sin grupos: la cuenta deja de salir en «Banco y Titular».
        $this->actingAs($this->user)->post(route('finanzas.cuentas.grupos'), ['banco' => 'Banesco', 'titular' => 'Tecno House'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'ya no sale'));
        $this->assertEmpty(array_filter($this->cuentasDelSelector(), fn ($c) => $c['titular'] === 'Tecno House'));
        $this->actingAs($this->user)->get(route('finanzas.cuentas', ['tab' => 'selector']))->assertSee('desactivada');
    }

    private function cuentasDelSelector(): array
    {
        return (new \ReflectionMethod(FinanzasController::class, 'getCuentas'))->invoke(app(FinanzasController::class));
    }
}
