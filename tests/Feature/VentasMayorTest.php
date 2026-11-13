<?php

namespace Tests\Feature;

use App\Models\Mayor\MayorCliente;
use App\Models\Mayor\MayorFactura;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class VentasMayorTest extends TestCase
{
    use CreatesNominaSchema;

    private User $user;

    private int $vendedora;

    private int $parlante;

    private int $agotado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        foreach (['activo' => fn (Blueprint $t) => $t->boolean('activo')->default(true), 'oculto' => fn (Blueprint $t) => $t->boolean('oculto')->default(false),
            'precio_unidad' => fn (Blueprint $t) => $t->decimal('precio_unidad', 12, 2)->default(0), 'precio_mayor' => fn (Blueprint $t) => $t->decimal('precio_mayor', 12, 2)->default(0)] as $col => $def) {
            if (! Schema::hasColumn('productos', $col)) {
                Schema::table('productos', $def);
            }
        }
        if (! Schema::hasTable('finanzas_resumen')) {
            Schema::create('finanzas_resumen', function (Blueprint $t) {
                $t->id();
                $t->date('fecha')->nullable();
                $t->decimal('tasa_bcv_usd', 14, 4)->default(1);
                $t->timestamps();
            });
        }
        (require database_path('migrations/2026_11_12_150000_create_ventas_mayor_tables.php'))->up();
        (require database_path('migrations/2026_11_12_200000_create_mayor_catalogo_y_presentaciones.php'))->up();
        if (! Schema::hasColumn('productos', 'costo_actual')) {
            Schema::table('productos', fn (Blueprint $t) => $t->decimal('costo_actual', 12, 2)->default(0));
        }
        DB::table('finanzas_resumen')->insert(['fecha' => now()->toDateString(), 'tasa_bcv_usd' => 40, 'created_at' => now(), 'updated_at' => now()]);

        $this->user = User::create(['name' => 'Mayorista', 'email' => 'mayor-'.uniqid().'@test.local', 'password' => 'password123', 'role' => User::ROLE_MAYOR]);
        $clienteId = DB::table('clientes')->insertGetId(['cedula' => 'V1', 'nombre' => 'Ana Vendedora', 'created_at' => now(), 'updated_at' => now()]);
        $this->vendedora = DB::table('nomina_empleados')->insertGetId(['cliente_id' => $clienteId, 'estado' => 'ACTIVO', 'codigo_vendedor' => 'V07', 'created_at' => now(), 'updated_at' => now()]);

        $this->parlante = DB::table('productos')->insertGetId(['codigo' => 'PAR-1', 'nombre' => 'Parlante Bluetooth', 'precio_unidad' => 15, 'precio_mayor' => 12.5, 'activo' => true]);
        $this->agotado = DB::table('productos')->insertGetId(['codigo' => 'AUD-2', 'nombre' => 'Audífono', 'precio_unidad' => 8, 'precio_mayor' => 6, 'activo' => true]);
        DB::table('stock_actual')->insert([
            ['producto_id' => $this->parlante, 'sede' => 'JRZ', 'existencia' => 10],
            ['producto_id' => $this->parlante, 'sede' => 'DORAL', 'existencia' => 50],
            ['producto_id' => $this->agotado, 'sede' => 'DORAL', 'existencia' => 30],
        ]);
    }

    private function factura(array $items, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->post(route('ventas_mayor.store'), $extra + [
            'fecha' => now()->toDateString(),
            'tasa_bcv' => 40,
            'vendedor_empleado_id' => $this->vendedora,
            'cliente_documento' => 'j-40123456-7',
            'cliente_nombre' => 'Comercial Los Andes',
            'cliente_telefono' => '0414-1234567',
            'condicion' => 'contado',
            'items' => $items,
        ]);
    }

    public function test_factura_a_credito_con_abonos_hasta_pagarla(): void
    {
        $item = [['producto_id' => $this->parlante, 'cantidad' => 4, 'precio_usd' => 12.5]];

        // De contado no puede quedar una parte sin pagar; a crédito pide la fecha de vencimiento.
        $this->factura($item, ['pago_monto_usd' => 20])->assertSessionHasErrors('pago_monto_usd');
        $this->factura($item, ['condicion' => 'credito', 'pago_monto_usd' => 20, 'fecha_vencimiento' => ''])->assertSessionHasErrors('fecha_vencimiento');
        $this->assertSame(0, MayorFactura::query()->count());

        $vence = now()->addDays(15)->toDateString();
        $this->factura($item, ['condicion' => 'credito', 'pago_monto_usd' => 20, 'pago_forma' => 'Efectivo $', 'fecha_vencimiento' => $vence])->assertSessionHasNoErrors();
        $f = MayorFactura::query()->with('pagos')->firstOrFail();
        $this->assertTrue($f->esCredito());
        $this->assertSame($vence, $f->fecha_vencimiento->toDateString());
        $this->assertEquals(30, $f->saldoUsd());
        $this->assertSame('parcial', $f->estadoPago());
        $this->assertNull($f->pagos->first()->url);

        $this->actingAs($this->user)->get(route('ventas_mayor.facturas', ['por_cobrar' => 1]))->assertOk()->assertSee('MAY-000001')->assertSee('$30.00');

        // Abono mayor a lo que se debe: no. Abonos sin comprobante hasta pagarla.
        $this->actingAs($this->user)->post(route('ventas_mayor.comprobantes.store', $f), ['monto_usd' => 31])->assertSessionHasErrors('monto_usd');
        $this->actingAs($this->user)->post(route('ventas_mayor.comprobantes.store', $f), ['monto_usd' => 10, 'forma_pago' => 'Zelle'])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post(route('ventas_mayor.comprobantes.store', $f), ['monto_usd' => 20])->assertSessionHasNoErrors();
        $f->refresh()->load('pagos');
        $this->assertEquals(0, $f->saldoUsd());
        $this->assertSame('pagada', $f->estadoPago());
        $this->actingAs($this->user)->get(route('ventas_mayor.facturas', ['por_cobrar' => 1]))->assertOk()->assertDontSee('MAY-000001');

        // A crédito sin abono: todo queda por cobrar.
        $this->factura([['producto_id' => $this->parlante, 'cantidad' => 1, 'precio_usd' => 12.5]], ['condicion' => 'credito', 'pago_monto_usd' => 0, 'fecha_vencimiento' => $vence])->assertSessionHasNoErrors();
        $this->assertEquals(12.5, MayorFactura::query()->where('numero', 2)->firstOrFail()->saldoUsd());
    }

    public function test_ve_stock_de_todas_las_sedes_con_jrz_primero(): void
    {
        $this->actingAs($this->user)->get(route('ventas_mayor.stock', ['q' => 'parlante']))
            ->assertOk()
            ->assertSee('solo se factura del de JRZ', false)
            ->assertSee(route('ventas_mayor.catalogo'), false)
            ->assertSee('Parlante Bluetooth')
            ->assertSee('$12.50')
            ->assertSee(route('ventas_mayor.create', ['productos' => $this->parlante]), false);
        // «Con stock en JRZ» no muestra el que solo tiene en DORAL; «Facturar» abre la factura con el producto.
        $this->actingAs($this->user)->get(route('ventas_mayor.stock', ['ver' => 'jrz']))->assertOk()->assertSee('Parlante Bluetooth')->assertDontSee('Audífono');
        $this->actingAs($this->user)->get(route('ventas_mayor.create', ['productos' => $this->parlante]))->assertOk()->assertSee('"disponible_jrz":10', false);

        $vendedor = User::create(['name' => 'V', 'email' => 'v-'.uniqid().'@test.local', 'password' => 'password123', 'role' => User::ROLE_VENDEDOR]);
        $this->actingAs($vendedor)->get(route('ventas_mayor.stock'))->assertRedirect();
        $this->actingAs($vendedor)->post(route('ventas_mayor.store'), [])->assertRedirect();
        $this->assertSame(0, MayorFactura::query()->count());
    }

    public function test_catalogo_al_mayor_vende_por_caja_con_su_precio_y_costo(): void
    {
        DB::table('productos')->where('id', $this->parlante)->update(['costo_actual' => 9]);

        // El usuario de ventas al mayor ve la ficha pero no la edita; la regente sí.
        $this->actingAs($this->user)->post(route('ventas_mayor.catalogo.guardar', $this->parlante), ['presentacion' => 'caja', 'unidades' => 5])->assertRedirect();
        $this->assertSame(0, \App\Models\Mayor\MayorCatalogo::query()->count());
        $this->actingAs($this->user)->get(route('ventas_mayor.stock', ['q' => 'parlante']))->assertOk()
            ->assertSee('Ver ficha')->assertSee('Solo la regente puede')->assertDontSee('Ficha / editar');
        $regente = User::create(['name' => 'Regente', 'email' => 'regente-'.uniqid().'@test.local', 'password' => 'password123', 'role' => User::ROLE_SEDE]);
        $regente->extraPermissions()->create(['permission' => 'ventas_mayor.catalogo']);
        $guardar = fn (array $datos) => $this->actingAs($regente)->post(route('ventas_mayor.catalogo.guardar', $this->parlante), $datos);

        $guardar(['presentacion' => 'caja'])->assertSessionHasErrors('unidades');
        $guardar(['presentacion' => 'caja', 'unidades' => 5, 'precio_usd' => 55, 'costo_usd' => 8])->assertSessionHasNoErrors();
        $this->actingAs($regente)->get(route('ventas_mayor.stock', ['q' => 'parlante']))->assertOk()
            ->assertSee('Ficha / editar')
            ->assertSee('Caja x5')->assertSee('$55.00')->assertSee('$11.00 c/u')->assertSee('$40.00')->assertSee('+37.5% ganancia');

        // El catálogo al mayor (para clientes) muestra lo que puso la regente, sin el costo.
        $this->actingAs($this->user)->get(route('ventas_mayor.catalogo'))->assertOk()
            ->assertSee('Parlante Bluetooth')->assertSee('Caja x5')->assertSee('$55.00')->assertSee('por caja')->assertSee('2 cajas')
            ->assertDontSee('$40.00')->assertDontSee('Audífono');
        Http::fake(['*' => Http::response('', 404)]);
        $pdf = $this->actingAs($this->user)->get(route('ventas_mayor.catalogo.pdf'));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // 3 cajas son 15 unidades y en JRZ hay 10: no alcanza. 2 cajas sí y sacan las 10.
        // El usuario de ventas al mayor no cambia el precio: aunque mande 1, se cobra el del catálogo.
        $this->factura([['producto_id' => $this->parlante, 'cantidad' => 3, 'precio_usd' => 55]])->assertSessionHasErrors('items');
        $this->factura([['producto_id' => $this->parlante, 'cantidad' => 2, 'precio_usd' => 1]])->assertSessionHasNoErrors();
        $item = MayorFactura::query()->with('items')->firstOrFail()->items->first();
        $this->assertSame(['caja', 5, 10, 110.0], [$item->presentacion, $item->unidades_por, $item->unidades, $item->total_usd]);
        $this->assertSame('2 cajas (x5)', $item->cantidadTexto());
        $p = app(\App\Services\Mayor\VentasMayorService::class)->conStock(DB::table('productos')->where('id', $this->parlante)->get())->first();
        $this->assertSame([0, 0], [$p->disponible_jrz, $p->disponible_presentaciones]);

        // Precio y costo vacíos vuelven a los de Suite.
        $guardar(['presentacion' => 'unidad', 'precio_usd' => '', 'costo_usd' => ''])->assertSessionHasNoErrors();
        $c = app(\App\Services\Mayor\VentasMayorService::class)->catalogo([$this->parlante])[$this->parlante];
        $this->assertSame(['unidad', 1, 12.5, 9.0, false], [$c['presentacion'], $c['unidades'], $c['precio'], $c['costo_unidad'], $c['precio_editado']]);
    }

    public function test_factura_solo_del_stock_de_jrz_y_lo_aparta_hasta_cargarla_en_profit(): void
    {
        $this->factura([['producto_id' => $this->parlante, 'cantidad' => 4, 'precio_usd' => 12.5]])->assertSessionHasNoErrors()->assertRedirect();

        $f = MayorFactura::query()->with('items', 'cliente')->firstOrFail();
        $this->assertSame(1, $f->numero);
        $this->assertSame('MAY-000001', $f->numeroFormateado());
        $this->assertSame('J401234567', $f->cliente->documento);
        $this->assertSame('Ana Vendedora', $f->vendedor_nombre);
        $this->assertEquals(50, $f->total_usd);
        $this->assertEquals(2000, $f->total_bs);
        $this->assertSame(4, $f->items->first()->cantidad);

        // Quedan 6 disponibles en JRZ: 7 no alcanza, y DORAL no cuenta para facturar.
        $this->factura([['producto_id' => $this->parlante, 'cantidad' => 7, 'precio_usd' => 12.5]])->assertSessionHasErrors('items');
        $this->factura([['producto_id' => $this->agotado, 'cantidad' => 1, 'precio_usd' => 6]])->assertSessionHasErrors('items');
        $this->assertSame(1, MayorFactura::query()->count());

        // El cliente ya existe: la segunda factura lo reutiliza.
        $this->factura([['producto_id' => $this->parlante, 'cantidad' => 6, 'precio_usd' => 11]])->assertSessionHasNoErrors();
        $this->assertSame(1, MayorCliente::query()->count());
        $this->assertSame(0, app(\App\Services\Mayor\VentasMayorService::class)->conStock(DB::table('productos')->where('id', $this->parlante)->get())->first()->disponible_jrz);

        // Cargada en Profit deja de apartar; anulada también libera.
        $this->actingAs($this->user)->post(route('ventas_mayor.profit', $f))->assertRedirect();
        $this->actingAs($this->user)->post(route('ventas_mayor.anular', MayorFactura::query()->where('numero', 2)->first()), ['motivo' => 'Cliente desistió'])->assertRedirect();
        $this->assertSame(10, app(\App\Services\Mayor\VentasMayorService::class)->conStock(DB::table('productos')->where('id', $this->parlante)->get())->first()->disponible_jrz);
    }

    public function test_comprobantes_y_pdf_de_la_factura(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        Storage::fake('public');

        $this->factura([['producto_id' => $this->parlante, 'cantidad' => 2, 'precio_usd' => 12.5]], [
            'comprobantes' => [UploadedFile::fake()->image('pago.jpg')],
            'pago_forma' => 'Zelle',
            'pago_referencia' => '998877',
            'pago_monto_usd' => 25,
        ])->assertSessionHasNoErrors();
        $f = MayorFactura::query()->firstOrFail();
        $this->assertSame(1, $f->pagos()->count());
        $this->assertEquals(25, $f->pagos()->first()->monto_usd);

        // Pagada de contado: ya no pide más comprobantes.
        $this->actingAs($this->user)->post(route('ventas_mayor.comprobantes.store', $f), [
            'comprobantes' => [UploadedFile::fake()->create('transferencia.pdf', 50, 'application/pdf')],
            'forma_pago' => 'Transferencia',
        ])->assertSessionHasErrors('monto_usd');
        $this->assertSame(1, $f->pagos()->count());

        $this->actingAs($this->user)->get(route('ventas_mayor.show', $f))->assertOk()->assertSee('MAY-000001')->assertSee('Ref 998877')
            ->assertSee('Pagada completa')->assertDontSee('Agregar comprobante');
        $pdf = $this->actingAs($this->user)->get(route('ventas_mayor.pdf', $f));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($this->user)->get(route('ventas_mayor.facturas'))->assertOk()->assertSee('Comercial Los Andes');
    }
}
