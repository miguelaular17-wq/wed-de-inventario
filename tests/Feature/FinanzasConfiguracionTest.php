<?php

namespace Tests\Feature;

use App\Http\Controllers\FinanzasController;
use App\Models\FinanzasCatalogo;
use App\Models\User;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class FinanzasConfiguracionTest extends TestCase
{
    use CreatesNominaSchema;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        (require database_path('migrations/2026_10_27_160000_create_finanzas_catalogos_table.php'))->up();
        $this->user = User::create(['name' => 'Finanzas', 'email' => 'fin-conf@test.local', 'password' => 'password123', 'role' => User::ROLE_FINANZAS]);
    }

    public function test_agrega_tipo_de_gasto_con_codigo_y_aparece_en_las_listas(): void
    {
        $this->actingAs($this->user)->post(route('finanzas.configuracion.store'), [
            'tipo' => 'tipo_gasto', 'codigo' => '102', 'nombre' => 'gastos de  vigilancia',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('finanzas_catalogos', ['tipo' => 'tipo_gasto', 'nombre' => '102 - GASTOS DE VIGILANCIA', 'activo' => true]);
        $tipos = FinanzasCatalogo::tiposGasto();
        $this->assertSame('102 - GASTOS DE VIGILANCIA', end($tipos));
        $this->assertSame(config('finanzas_tipos_gasto.tipos_gasto')[0], $tipos[0]);
    }

    public function test_no_repite_lo_que_ya_esta_en_el_sistema(): void
    {
        $this->actingAs($this->user)->post(route('finanzas.configuracion.store'), ['tipo' => 'tipo_gasto', 'nombre' => '003 - alquileres'])
            ->assertSessionHas('error');
        $this->actingAs($this->user)->post(route('finanzas.configuracion.store'), [
            'tipo' => 'cuenta', 'banco' => 'banesco', 'nombre' => 'grupo  jrz', 'categoria' => FinanzasCatalogo::CATEGORIAS_CUENTA[0],
        ])->assertSessionHas('error');
        $this->assertSame(0, FinanzasCatalogo::query()->count());
    }

    public function test_ajuste_repara_la_tabla_creada_con_la_primera_version(): void
    {
        \Illuminate\Support\Facades\Schema::drop('finanzas_catalogos');
        \Illuminate\Support\Facades\Schema::create('finanzas_catalogos', function (\Illuminate\Database\Schema\Blueprint $t) {
            $t->id();
            $t->string('tipo', 20);
            $t->string('nombre', 256);
            $t->string('banco', 120)->default('');
            $t->string('categoria', 160)->nullable();
            $t->boolean('activo')->default(true);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
            $t->unique(['tipo', 'banco', 'nombre']);
        });
        \Illuminate\Support\Facades\DB::table('finanzas_catalogos')->insert(['tipo' => 'beneficiario', 'nombre' => 'Viejo', 'banco' => '', 'categoria' => null]);

        $ajuste = require database_path('migrations/2026_10_27_170000_ajustar_finanzas_catalogos.php');
        $ajuste->up();
        $ajuste->up();

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('finanzas_catalogos', 'origen'));
        $this->assertSame('', \Illuminate\Support\Facades\DB::table('finanzas_catalogos')->value('categoria'));
        $this->seed(\Database\Seeders\FinanzasCatalogoSeeder::class);
        // Banesco · Doral está en dos grupos del selector: las dos quedan.
        $this->assertSame(2, FinanzasCatalogo::query()->where('tipo', 'cuenta')->where('banco', 'Banesco')->where('nombre', 'Doral')->count());
    }

    public function test_el_seeder_migra_lo_existente_sin_duplicar_ni_reactivar(): void
    {
        $this->seed(\Database\Seeders\FinanzasCatalogoSeeder::class);

        $tipos = count(config('finanzas_tipos_gasto.tipos_gasto'));
        $cuentas = count(FinanzasController::cuentasBase());
        $this->assertSame($tipos, FinanzasCatalogo::query()->where('tipo', 'tipo_gasto')->where('origen', 'sistema')->count());
        $this->assertSame($cuentas, FinanzasCatalogo::query()->where('tipo', 'cuenta')->where('origen', 'sistema')->count());
        // Las listas de los formularios no cambian por migrar.
        $this->assertSame(config('finanzas_tipos_gasto.tipos_gasto'), FinanzasCatalogo::tiposGasto());
        $this->assertSame(FinanzasController::cuentasBase(), FinanzasCatalogo::combinarCuentas(FinanzasController::cuentasBase()));

        // Desactivar uno del sistema lo quita de los formularios; volver a correr el seeder no lo reactiva ni duplica.
        $alquileres = FinanzasCatalogo::query()->where('nombre', '003 - ALQUILERES')->sole();
        $this->actingAs($this->user)->post(route('finanzas.configuracion.toggle', $alquileres));
        $this->seed(\Database\Seeders\FinanzasCatalogoSeeder::class);
        $this->assertSame($tipos, FinanzasCatalogo::query()->where('tipo', 'tipo_gasto')->count());
        $this->assertFalse($alquileres->fresh()->activo);
        $this->assertNotContains('003 - ALQUILERES', FinanzasCatalogo::tiposGasto());

        // Agregarlo de nuevo lo reactiva.
        $this->actingAs($this->user)->post(route('finanzas.configuracion.store'), ['tipo' => 'tipo_gasto', 'nombre' => '003 - Alquileres'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'se activó de nuevo'));
        $this->assertContains('003 - ALQUILERES', FinanzasCatalogo::tiposGasto());
    }

    public function test_cuenta_y_beneficiario_nuevos_llegan_a_los_formularios_y_se_pueden_desactivar(): void
    {
        $this->actingAs($this->user)->post(route('finanzas.configuracion.store'), [
            'tipo' => 'cuenta', 'banco' => 'Banesco', 'nombre' => 'Tecno House', 'categoria' => FinanzasCatalogo::CATEGORIAS_CUENTA[1],
        ])->assertSessionHas('success');
        $this->actingAs($this->user)->post(route('finanzas.configuracion.store'), ['tipo' => 'beneficiario', 'nombre' => 'Vigilancia Falcón C.A.'])
            ->assertSessionHas('success');

        $cuentas = (new \ReflectionMethod(FinanzasController::class, 'getCuentas'))->invoke(app(FinanzasController::class));
        $this->assertContains(['banco' => 'Banesco', 'titular' => 'Tecno House', 'categoria' => FinanzasCatalogo::CATEGORIAS_CUENTA[1]], $cuentas);
        $this->assertCount(count(FinanzasController::cuentasBase()) + 1, $cuentas);
        $this->assertSame(['Vigilancia Falcón C.A.'], FinanzasCatalogo::nombres(FinanzasCatalogo::BENEFICIARIO));

        $cuenta = FinanzasCatalogo::query()->where('tipo', 'cuenta')->sole();
        $this->actingAs($this->user)->post(route('finanzas.configuracion.toggle', $cuenta))->assertRedirect();
        $this->assertFalse($cuenta->fresh()->activo);
        $this->assertSame(FinanzasController::cuentasBase(), FinanzasCatalogo::combinarCuentas(FinanzasController::cuentasBase()));

        $this->actingAs($this->user)->get(route('finanzas.configuracion'))
            ->assertOk()
            ->assertSee('Configuración de Finanzas')
            ->assertSee('Tecno House')
            ->assertSee('desactivado')
            ->assertSee('Vigilancia Falcón C.A.');
    }
}
