<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class FacturaAjusteTest extends TestCase
{
    use CreatesNominaSchema;

    private User $admin;

    private int $docId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        Schema::dropIfExists('ventas_detalle');
        Schema::dropIfExists('ventas_documentos');
        Schema::create('ventas_documentos', function (Blueprint $t) {
            $t->id();
            $t->string('sede', 16);
            $t->string('tipo_documento', 8);
            $t->string('numero_documento', 32);
            $t->date('fecha');
            $t->string('estado', 24);
            $t->decimal('total_neto_bs', 18, 5)->default(0);
            $t->decimal('total_neto_usd', 18, 5)->default(0);
            $t->decimal('factor_cambio', 18, 6)->nullable();
            $t->string('cliente')->nullable();
            $t->string('vendedor')->nullable();
            $t->string('factura_origen', 32)->nullable();
            $t->string('cajera')->nullable();
            $t->timestamps();
            $t->unique(['sede', 'tipo_documento', 'numero_documento']);
        });
        Schema::create('ventas_detalle', function (Blueprint $t) {
            $t->id();
            $t->string('sede', 16);
            $t->string('tipo_documento', 8);
            $t->string('numero_documento', 32);
            $t->integer('item_numero')->default(0);
            $t->date('fecha');
            $t->string('codigo_producto', 64)->nullable();
            $t->text('nombre_producto')->nullable();
            $t->decimal('cantidad', 18, 4);
            $t->decimal('precio_venta', 12, 2)->nullable();
            $t->decimal('precio_neto', 24, 10)->nullable();
            $t->string('vendedor')->nullable();
            $t->boolean('anulado')->default(false);
            $t->timestamp('anulado_at')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        (require database_path('migrations/2026_10_31_130000_create_factura_ajustes.php'))->up();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin-fa@test.local', 'password' => 'x', 'role' => User::ROLE_ADMIN]);
        $this->docId = DB::table('ventas_documentos')->insertGetId([
            'sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => '00012345', 'fecha' => now()->subDays(2)->toDateString(),
            'estado' => 'registrado', 'total_neto_bs' => 3650, 'total_neto_usd' => 100, 'cliente' => 'MARIA PEREZ', 'vendedor' => 'DANIBET',
        ]);
        DB::table('ventas_documentos')->insert([
            'sede' => 'JRZ', 'tipo_documento' => 'FAC', 'numero_documento' => '00099999', 'fecha' => now()->subDays(1)->toDateString(),
            'estado' => 'registrado', 'total_neto_bs' => 365, 'total_neto_usd' => 10, 'cliente' => 'JUAN GOMEZ', 'vendedor' => 'LUIS',
        ]);
        foreach ([[1, 'PROWAX100', 'CERA PROWAX 100G', 2, 30], [2, '000055', 'GEL FIJADOR', 1, 40]] as [$item, $cod, $nom, $cant, $precio]) {
            DB::table('ventas_detalle')->insert([
                'sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => '00012345', 'item_numero' => $item,
                'fecha' => now()->subDays(2)->toDateString(), 'codigo_producto' => $cod, 'nombre_producto' => $nom,
                'cantidad' => $cant, 'precio_neto' => $precio, 'vendedor' => 'DANIBET',
            ]);
        }
    }

    public function test_busca_por_numero_cliente_y_producto(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.facturas.index'))->assertOk()->assertSee('00012345')->assertSee('00099999');
        $this->get(route('admin.facturas.index', ['q' => 'maria']))->assertOk()->assertSee('00012345')->assertDontSee('00099999');
        $this->get(route('admin.facturas.index', ['q' => '00099999']))->assertOk()->assertSee('JUAN GOMEZ')->assertDontSee('MARIA PEREZ');

        $porProducto = $this->get(route('admin.facturas.index', ['producto' => 'prowax']));
        $porProducto->assertOk()->assertSee('00012345')->assertDontSee('00099999')->assertSee('<mark>PROWAX</mark>', false);
        $this->assertSame(1, $porProducto->viewData('facturas')->total());
    }

    public function test_edita_encabezado_y_renglon_con_historial(): void
    {
        $this->actingAs($this->admin);
        $lineas = DB::table('ventas_detalle')->orderBy('item_numero')->get();

        $this->get(route('admin.facturas.edit', $this->docId))->assertOk()->assertSee('CERA PROWAX 100G')->assertSee('Guardar ajuste');

        $this->put(route('admin.facturas.update', $this->docId), [
            'encabezado' => ['sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => '00012345', 'fecha' => now()->subDays(2)->toDateString(),
                'estado' => 'registrado', 'total_neto_bs' => '3650', 'total_neto_usd' => '95.50', 'cliente' => 'MARIA PEREZ', 'vendedor' => 'CARLOS'],
            'renglones' => [
                $lineas[0]->id => ['item_numero' => '1', 'fecha' => now()->subDays(2)->toDateString(), 'codigo_producto' => 'PROWAX100',
                    'nombre_producto' => 'CERA PROWAX 100G', 'cantidad' => '3', 'precio_neto' => '30', 'vendedor' => 'CARLOS', 'anulado' => '0'],
                $lineas[1]->id => ['item_numero' => '2', 'fecha' => now()->subDays(2)->toDateString(), 'codigo_producto' => '000055',
                    'nombre_producto' => 'GEL FIJADOR', 'cantidad' => '1', 'precio_neto' => '40.0000000000', 'vendedor' => 'DANIBET', 'anulado' => '0'],
            ],
        ])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, '4 campo(s)'));

        $doc = DB::table('ventas_documentos')->find($this->docId);
        $this->assertEquals(95.5, (float) $doc->total_neto_usd);
        $this->assertSame('CARLOS', $doc->vendedor);
        $this->assertTrue((bool) $doc->ajuste_manual);
        $l1 = DB::table('ventas_detalle')->find($lineas[0]->id);
        $this->assertEquals(3, (float) $l1->cantidad);
        $this->assertTrue((bool) $l1->ajuste_manual);
        // El renglón sin cambios no se marca.
        $this->assertFalse((bool) DB::table('ventas_detalle')->find($lineas[1]->id)->ajuste_manual);

        $hist = DB::table('factura_ajustes')->orderBy('id')->get();
        $this->assertCount(2, $hist);
        $this->assertSame(['total_neto_usd', 'vendedor'], array_keys(json_decode($hist[0]->cambios, true)));
        $this->assertSame((int) $this->admin->id, (int) $hist[0]->user_id);

        $this->get(route('admin.facturas.edit', $this->docId))->assertOk()->assertSee('Ajustada a mano')->assertSee('Historial de ajustes')->assertSee('Renglón 1');
        $this->get(route('admin.facturas.index', ['ajustadas' => 1]))->assertOk()->assertSee('00012345')->assertDontSee('00099999');
    }

    public function test_cambiar_el_numero_mueve_los_renglones_y_no_choca_con_otra(): void
    {
        $this->actingAs($this->admin);
        $base = ['sede' => 'DORAL', 'tipo_documento' => 'FAC', 'fecha' => now()->subDays(2)->toDateString(), 'estado' => 'registrado',
            'total_neto_bs' => '3650', 'total_neto_usd' => '100', 'cliente' => 'MARIA PEREZ', 'vendedor' => 'DANIBET'];

        $this->put(route('admin.facturas.update', $this->docId), ['encabezado' => ['numero_documento' => '00099999', 'sede' => 'JRZ'] + $base])
            ->assertSessionHasErrors('encabezado');

        $this->put(route('admin.facturas.update', $this->docId), ['encabezado' => ['numero_documento' => '00012346'] + $base])
            ->assertSessionHas('success');
        $this->assertSame(2, DB::table('ventas_detalle')->where('numero_documento', '00012346')->where('ajuste_manual', true)->count());
        $this->assertSame(0, DB::table('ventas_detalle')->where('numero_documento', '00012345')->count());
        $this->assertSame(3, DB::table('factura_ajustes')->where('clave_cambiada', true)->count());

        // Liberar quita la marca y el bloqueo de la clave vieja.
        $this->post(route('admin.facturas.liberar', $this->docId))->assertSessionHas('success');
        $this->assertFalse((bool) DB::table('ventas_documentos')->find($this->docId)->ajuste_manual);
        $this->assertSame(0, DB::table('ventas_detalle')->where('ajuste_manual', true)->count());
        $this->assertSame(0, DB::table('factura_ajustes')->where('clave_cambiada', true)->count());
    }

    public function test_valida_los_campos(): void
    {
        $this->actingAs($this->admin);
        $id = DB::table('ventas_detalle')->value('id');

        $this->put(route('admin.facturas.update', $this->docId), ['renglones' => [$id => ['cantidad' => 'dos', 'fecha' => '2026-13-45']]])
            ->assertSessionHasErrors('campos');
        $this->put(route('admin.facturas.update', $this->docId), ['encabezado' => ['numero_documento' => '']])
            ->assertSessionHasErrors('campos');
        $this->assertSame(0, DB::table('factura_ajustes')->count());
    }

    public function test_solo_el_admin(): void
    {
        $gerente = User::create(['name' => 'G', 'email' => 'g-fa@test.local', 'password' => 'x', 'role' => 'gerente']);
        $this->actingAs($gerente)->get(route('admin.facturas.index'))->assertForbidden();
        $this->actingAs($gerente)->put(route('admin.facturas.update', $this->docId), ['encabezado' => ['cliente' => 'X']])->assertForbidden();

        $conta = User::create(['name' => 'C', 'email' => 'c-fa@test.local', 'password' => 'x', 'role' => User::ROLE_CONTABILIDAD]);
        $this->actingAs($conta)->get(route('admin.facturas.index'))->assertRedirect(route('login'));
    }
}
