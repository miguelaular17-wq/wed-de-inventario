<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EtiquetasPrecioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class EtiquetasPrecioTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('productos');
        Schema::enableForeignKeyConstraints();
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('nombre');
            $table->string('categoria')->nullable();
            $table->decimal('precio_unidad', 12, 2)->nullable();
            $table->decimal('precio_mayor', 12, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('oculto')->nullable();
        });

        DB::table('productos')->insert([
            ['id' => 1, 'codigo' => '#0707 / 0378789000952', 'nombre' => 'RUBOR DREAM #0707', 'precio_unidad' => 2.00, 'precio_mayor' => 1.80, 'activo' => true, 'oculto' => false],
            ['id' => 2, 'codigo' => '#9AM60', 'nombre' => 'PERFUME BASE ACEITE 9AM FEM 60ML', 'precio_unidad' => 5.00, 'precio_mayor' => 4.50, 'activo' => true, 'oculto' => null],
            ['id' => 3, 'codigo' => '-2124', 'nombre' => 'BASE CON PROTECTOR SOLAR', 'precio_unidad' => 1.50, 'precio_mayor' => 0, 'activo' => true, 'oculto' => false],
            ['id' => 4, 'codigo' => '#0708', 'nombre' => 'RUBOR VIEJO', 'precio_unidad' => 3.00, 'precio_mayor' => 2.00, 'activo' => false, 'oculto' => false],
        ]);
    }

    public function test_escanear_parte_del_codigo_compuesto_devuelve_coincidencia_exacta(): void
    {
        $user = $this->makeUser(User::ROLE_VENDEDOR);

        $this->actingAs($user)
            ->getJson(route('etiquetas.buscar', ['q' => '0378789000952']))
            ->assertOk()
            ->assertJsonPath('exacto.id', 1)
            ->assertJsonPath('exacto.precio_mayor', 2);

        $this->actingAs($user)
            ->getJson(route('etiquetas.buscar', ['q' => '0707']))
            ->assertOk()
            ->assertJsonPath('exacto.id', 1);
    }

    public function test_busqueda_por_nombre_lista_resultados_sin_inactivos(): void
    {
        $res = $this->actingAs($this->makeUser(User::ROLE_VENDEDOR))
            ->getJson(route('etiquetas.buscar', ['q' => 'rubor']))
            ->assertOk()
            ->assertJsonPath('exacto', null);

        $this->assertSame([1], array_column($res->json('resultados'), 'id'));
    }

    public function test_ficha_sin_precio_mayor_muestra_un_solo_precio(): void
    {
        $svc = new EtiquetasPrecioService();

        $con = $svc->ficha(['id' => 2, 'codigo' => 'x', 'nombre' => 'x', 'categoria' => '', 'precio_unidad' => 5.2, 'precio_mayor' => 4.1], 3);
        $this->assertTrue($con['con_mayor']);
        $this->assertSame(6.0, $con['precio_unidad']);
        $this->assertSame(5.0, $con['precio_mayor']);
        $this->assertSame(17, $con['ahorro_pct']);
        $this->assertSame('6,00', $con['unidad_fmt']);
        $this->assertSame('5,00', $con['mayor_fmt']);

        // 5,00 y 4,50 → ambos a 5: un solo precio en la etiqueta.
        $ceilIgual = $svc->ficha(['id' => 2, 'codigo' => 'x', 'nombre' => 'x', 'categoria' => '', 'precio_unidad' => 5.0, 'precio_mayor' => 4.5], 3);
        $this->assertFalse($ceilIgual['con_mayor']);
        $this->assertSame('5,00', $ceilIgual['unidad_fmt']);

        $sin = $svc->ficha(['id' => 3, 'codigo' => 'x', 'nombre' => 'x', 'categoria' => '', 'precio_unidad' => 1.5, 'precio_mayor' => 0], 3);
        $this->assertFalse($sin['con_mayor']);
        $this->assertSame('2,00', $sin['unidad_fmt']);

        $mayorIgual = $svc->ficha(['id' => 3, 'codigo' => 'x', 'nombre' => 'x', 'categoria' => '', 'precio_unidad' => 2.0, 'precio_mayor' => 2.0], 3);
        $this->assertFalse($mayorIgual['con_mayor']);
    }

    public function test_precios_se_redondean_hacia_arriba(): void
    {
        $svc = new EtiquetasPrecioService();
        $this->assertSame(27.0, $svc->redondearArriba(26.66));
        $this->assertSame(114.0, $svc->redondearArriba(113.34));
        $this->assertSame(2.0, $svc->redondearArriba(1.01));
        $this->assertSame(5.0, $svc->redondearArriba(5.0));
        $this->assertSame(0.0, $svc->redondearArriba(0));
    }

    public function test_nombre_largo_baja_la_letra_y_nunca_pasa_de_tres_lineas(): void
    {
        $svc = new EtiquetasPrecioService();
        $ancho = fn (string $t, float $fs) => mb_strlen($t) * $fs * 0.7;
        $alto = fn (float $fs) => $fs * 1.28;

        $corto = $svc->ajustarNombre('Rubor dream', $ancho, $alto, 70, 10, [12, 10, 8]);
        $this->assertSame(12.0, (float) $corto['fs']);
        $this->assertSame(['RUBOR DREAM'], $corto['lineas']);

        $medio = $svc->ajustarNombre('IPHONE 18 PRO MAX 256GB BURGUNDY', $ancho, $alto, 70, 10, [12, 10, 8]);
        $this->assertLessThanOrEqual(2, count($medio['lineas']));
        $this->assertSame('IPHONE 18 PRO MAX 256GB BURGUNDY', implode(' ', $medio['lineas']));

        $largo = $svc->ajustarNombre(str_repeat('PALABRA LARGA ', 30), $ancho, $alto, 70, 10, [12, 10, 8]);
        $this->assertSame(8.0, (float) $largo['fs']);
        $this->assertLessThanOrEqual(3, count($largo['lineas']));
        $this->assertStringEndsWith('…', end($largo['lineas']));
        foreach ($largo['lineas'] as $linea) {
            $this->assertLessThanOrEqual(70 * 72 / 25.4, $ancho($linea, 8));
        }
    }

    public function test_genera_pdf_con_copias(): void
    {
        $svc = new EtiquetasPrecioService();
        $fichas = $svc->armarEtiquetas([['id' => 2, 'copias' => 3], ['id' => 1, 'copias' => 1], ['id' => 4, 'copias' => 5]], 3);
        $this->assertCount(4, $fichas);
        $this->assertSame([2, 2, 2, 1], array_column($fichas, 'id'));

        $this->actingAs($this->makeUser(User::ROLE_VENDEDOR))
            ->post(route('etiquetas.pdf'), [
                'items' => [['id' => 2, 'copias' => 3], ['id' => 3, 'copias' => 1]],
                'tamano' => 'pequena',
                'cantidad_mayor' => 3,
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_lee_ficha_tecnica_de_laptop_desde_el_nombre(): void
    {
        $specs = (new EtiquetasPrecioService())->sugerirSpecsPc('LAPTOP DELL 15 DC15250 INTEL I5-1334U 8GB DDR5 512GB SSD 15.6" FHD W11');

        $this->assertSame([
            'marca' => 'DELL',
            'modelo' => 'DELL 15 DC15250',
            'procesador' => 'INTEL I5-1334U',
            'grafica' => '',
            'pantalla' => 'Pantalla 15.6" FHD',
            'ram' => '8GB DDR5',
            'almacenamiento' => '512GB SSD',
            'sistema' => 'Windows 11',
        ], $specs);
    }

    public function test_laptop_gamer_victus_lee_grafica_y_usa_logo_victus(): void
    {
        $svc = new EtiquetasPrecioService();
        $nombre = 'HP - VICTUS 15.6" FULL HD 144HZ GAMING LAPTOP - INTEL CORE I7 - 16GB MEMORY NVIDIA GEFORCE RTX 4050 - 512GB SSD';
        $specs = $svc->sugerirSpecsPc($nombre);

        $this->assertSame('HP', $specs['marca']);
        $this->assertSame('HP VICTUS', $specs['modelo']);
        $this->assertSame('INTEL CORE I7', $specs['procesador']);
        $this->assertSame('NVIDIA RTX 4050', $specs['grafica']);
        $this->assertSame('Pantalla 15.6" FHD 144Hz', $specs['pantalla']);
        $this->assertSame('16GB RAM', $specs['ram']);
        $this->assertSame('512GB SSD', $specs['almacenamiento']);

        $this->assertSame('victus', $svc->logoMarca($specs, $nombre));
        $this->assertSame('dell', $svc->logoMarca(['marca' => 'Dell'], 'LAPTOP DELL 15'));
        $this->assertSame('apple', $svc->logoMarca(['marca' => ''], 'MACBOOK AIR M2'));
        $this->assertSame('hp', $svc->logoMarca(['marca' => 'HP'], 'LAPTOP HP 15'));
        $this->assertNull($svc->logoMarca(['marca' => 'MSI'], 'LAPTOP MSI THIN'));
    }

    public function test_celulares_leen_ram_y_almacenamiento_y_usan_su_logo(): void
    {
        $svc = new EtiquetasPrecioService();

        $poco = $svc->sugerirSpecsPc('XIAOMI POCO X6 PRO 5G 12/512GB BLACK');
        $this->assertSame('12GB RAM', $poco['ram']);
        $this->assertSame('512GB', $poco['almacenamiento']);
        $this->assertSame('poco', $svc->logoMarca($poco, 'XIAOMI POCO X6 PRO 5G 12/512GB BLACK'));

        $redmi = $svc->sugerirSpecsPc('REDMI NOTE 13 8+256GB');
        $this->assertSame('8GB RAM', $redmi['ram']);
        $this->assertSame('256GB', $redmi['almacenamiento']);
        $this->assertSame('xiaomi', $svc->logoMarca($redmi, 'REDMI NOTE 13 8+256GB'));

        $forro = $svc->sugerirSpecsPc('FORRO SAMSUNG A16 DE LUJO');
        $this->assertSame('SAMSUNG', $forro['marca']);
        $this->assertSame('samsung', $svc->logoMarca($forro, 'FORRO SAMSUNG A16 DE LUJO'));
        $this->assertSame('', $svc->sugerirSpecsPc('BASE CON PROTECTOR SOLAR #2123/2124')['marca']);

        $aio = $svc->sugerirSpecsPc('HP CHROMEBASE ALL-IN-ONE 4GB/128GB INTEL PENTIUM');
        $this->assertSame('HP CHROMEBASE ALL-IN-ONE', $aio['modelo']);
        $this->assertSame('128GB SSD', $aio['almacenamiento']);
    }

    public function test_genera_pdf_de_locura_y_de_pc_con_ficha_editada(): void
    {
        $user = $this->makeUser(User::ROLE_VENDEDOR);

        $this->actingAs($user)
            ->post(route('etiquetas.pdf'), [
                'items' => [['id' => 1, 'copias' => 2]],
                'tipo' => 'locura',
                'tamano' => 'pequena',
                'cantidad_mayor' => 3,
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->post(route('etiquetas.pdf'), [
                'items' => [['id' => 2, 'copias' => 1, 'specs' => ['marca' => 'DELL', 'modelo' => 'DELL 15', 'procesador' => 'INTEL I5', 'pantalla' => '', 'ram' => '8GB', 'almacenamiento' => '512GB SSD', 'sistema' => 'Windows 11']]],
                'tipo' => 'pc',
                'tamano' => 'normal',
                'cantidad_mayor' => 3,
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $fichas = (new EtiquetasPrecioService())->armarEtiquetas([['id' => 2, 'copias' => 1, 'specs' => ['marca' => 'HP', 'ram' => '16GB']]], 3);
        $this->assertSame('HP', $fichas[0]['specs']['marca']);
        $this->assertSame('16GB', $fichas[0]['specs']['ram']);
        $this->assertSame('', $fichas[0]['specs']['procesador']);
        $this->assertSame('$5', $fichas[0]['unidad_entero_fmt']);
    }

    public function test_requiere_permiso_de_ver_precios(): void
    {
        $this->get(route('etiquetas.index'))->assertOk()->assertSee('Etiquetas de precio');
        $this->getJson(route('etiquetas.buscar', ['q' => '0707']))->assertOk()->assertJsonPath('exacto.id', 1);
        $this->post(route('etiquetas.pdf'), ['items' => [['id' => 1, 'copias' => 1]], 'tipo' => 'clasica'])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->makeUser(User::ROLE_SUPERVISOR))
            ->get(route('etiquetas.index'))
            ->assertForbidden();

        $this->actingAs($this->makeUser(User::ROLE_VENDEDOR))
            ->get(route('etiquetas.index'))
            ->assertOk()
            ->assertSee('Etiquetas de precio');
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'Usuario '.$role,
            'email' => $role.'-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => $role,
            'sede' => 'DORAL',
        ]);
    }
}
