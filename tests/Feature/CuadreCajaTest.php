<?php

namespace Tests\Feature;

use App\Models\CuadreCaja;
use App\Models\CuadreCajaPago;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\CuadreCajaService;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class CuadreCajaTest extends TestCase
{
    use CreatesNominaSchema;

    private CuadreCaja $cierre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        (require database_path('migrations/2026_11_05_090000_create_cuadres_caja_tables.php'))->up();

        $this->cierre = CuadreCaja::create([
            'sede' => 'DORAL', 'id_cierre' => 49929, 'fecha_cierre' => '2026-10-30', 'codigo_caja' => 'CJA02',
            'descripcion_caja' => 'CAJA 02', 'usuario' => '28738920', 'usuario_nombre' => 'ARIAMNA REYES',
            'estado' => 2, 'factor_cambio' => 889.26,
        ]);
        $this->pagosDeSuite($this->cierre);

        // Otro cierre de la sede donde sí se declaró Binance: queda disponible para agregarlo.
        $otro = CuadreCaja::create([
            'sede' => 'DORAL', 'id_cierre' => 49928, 'fecha_cierre' => '2026-10-30', 'codigo_caja' => 'CJA01',
            'usuario' => '28369458', 'factor_cambio' => 889.26,
        ]);
        CuadreCajaPago::create([
            'cuadre_caja_id' => $otro->id, 'codigo_metodo' => 'TRF', 'forma_pago' => 'TRANSF. $', 'codigo_cuenta' => 'BINANCE',
            'cuenta' => 'BINANCE', 'en_dolares' => true, 'ingresos' => 20, 'ingresos_usd' => 20,
        ]);
    }

    public function test_la_diferencia_es_lo_contado_por_tesoreria_menos_lo_declarado_en_suite(): void
    {
        $tesoreria = $this->usuario('tesoreria');
        $this->actingAs($tesoreria)
            ->post(route('cuadre_caja.conciliar', $this->cierre), [
                'montos' => ['02|' => '810', 'TDD|BAN02' => '731536.94', 'TRF|BINANCE' => '22.50'],
                'observacion' => 'Faltan 5 en efectivo',
            ])
            ->assertRedirect();

        $resumen = app(CuadreCajaService::class)->resumen($this->cierre->fresh()->load(['pagos', 'conteos']));

        $this->assertSame(1637.64, $resumen['ingresos_usd']);
        $this->assertSame(1655.14, $resumen['tesoreria_usd']);
        $this->assertSame(17.5, $resumen['diferencia_usd']);
        $lineas = collect($resumen['lineas'])->keyBy('clave');
        $this->assertSame(-5.0, $lineas['02|']['diferencia_usd']);
        $this->assertSame(0.0, $lineas['TDD|BAN02']['diferencia_usd']);
        $this->assertSame(22.5, $lineas['TRF|BINANCE']['diferencia_usd']);
        $this->assertSame('Faltan 5 en efectivo', $this->cierre->fresh()->observacion_tesoreria);
        $this->assertSame($tesoreria->id, $this->cierre->fresh()->conciliado_por);

        $this->actingAs($tesoreria)
            ->get(route('cuadre_caja.index', ['desde' => '2026-10-30', 'hasta' => '2026-10-30']))
            ->assertOk()
            ->assertSee('ARIAMNA REYES')
            ->assertSee('$17,50');

        $this->actingAs($tesoreria)
            ->get(route('cuadre_caja.show', $this->cierre))
            ->assertOk()
            ->assertSee('-$5,00', false)
            ->assertSee('Guardar cambios');
    }

    public function test_una_forma_de_pago_sin_contar_cuenta_como_cero_al_conciliar(): void
    {
        $this->actingAs($this->usuario('tesoreria'))
            ->post(route('cuadre_caja.conciliar', $this->cierre), ['montos' => ['02|' => '815', 'TDD|BAN02' => '']]);

        $resumen = app(CuadreCajaService::class)->resumen($this->cierre->fresh()->load(['pagos', 'conteos']));

        $this->assertSame(-822.64, $resumen['diferencia_usd']);
    }

    public function test_lo_contado_no_se_pierde_cuando_el_sincronizador_vuelve_a_subir_el_cierre(): void
    {
        $this->actingAs($this->usuario('tesoreria'))
            ->post(route('cuadre_caja.conciliar', $this->cierre), ['montos' => ['02|' => '810', 'TDD|BAN02' => '731536.94']]);

        CuadreCajaPago::query()->where('cuadre_caja_id', $this->cierre->id)->delete();
        $this->pagosDeSuite($this->cierre);

        $resumen = app(CuadreCajaService::class)->resumen($this->cierre->fresh()->load(['pagos', 'conteos']));
        $this->assertSame(-5.0, $resumen['diferencia_usd']);
    }

    public function test_ver_no_deja_conciliar_y_sin_permiso_no_entra(): void
    {
        $sinPermiso = $this->usuario(User::ROLE_SUPERVISOR);
        $this->assertNotSame(200, $this->actingAs($sinPermiso)->get(route('cuadre_caja.index'))->status());

        $soloVer = $this->usuario(User::ROLE_SUPERVISOR);
        UserPermission::create(['user_id' => $soloVer->id, 'permission' => 'cuadre_caja']);
        $soloVer = $soloVer->fresh();

        $this->actingAs($soloVer)->get(route('cuadre_caja.show', $this->cierre))->assertOk()->assertDontSee('Guardar cuadre');
        $this->actingAs($soloVer)->post(route('cuadre_caja.conciliar', $this->cierre), ['montos' => ['02|' => '1']]);
        $this->assertFalse($this->cierre->fresh()->conciliado());
    }

    private function pagosDeSuite(CuadreCaja $cierre): void
    {
        CuadreCajaPago::create([
            'cuadre_caja_id' => $cierre->id, 'codigo_metodo' => '02', 'forma_pago' => 'EFECTIVO $',
            'en_dolares' => true, 'ingresos' => 815, 'ingresos_usd' => 815,
        ]);
        CuadreCajaPago::create([
            'cuadre_caja_id' => $cierre->id, 'codigo_metodo' => 'TDD', 'forma_pago' => 'TARJ. DÉBITO', 'codigo_cuenta' => 'BAN02',
            'cuenta' => 'BANESCO P.V. DORAL', 'en_dolares' => false, 'ingresos' => 731536.94, 'ingresos_usd' => 822.6356,
        ]);
    }

    private function usuario(string $rol): User
    {
        return User::create([
            'name' => 'Usuario '.$rol,
            'email' => 'u-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => $rol,
            'sede' => 'DORAL',
        ]);
    }
}
