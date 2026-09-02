<?php

namespace Tests\Feature;

use App\Models\StFactura;
use App\Models\StReparacion;
use App\Models\StRepuesto;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ServicioTecnicoFase3Test extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->ensureStTables();
    }

    public function test_tecnico_accede_al_dashboard(): void
    {
        $tecnico = $this->makeTecnico();

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('servicio.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard de taller');
    }

    public function test_tecnico_crea_reparacion_en_su_sede(): void
    {
        $tecnico = $this->makeTecnico();

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.reparaciones.store'), [
                'tipo' => 'garantia',
                'producto' => 'iPhone 13',
                'categoria' => 'telefonia',
                'accion' => 'pendiente',
                'estado' => 'en_proceso',
                'cliente_nombre' => 'María López',
            ])
            ->assertRedirect();

        $reparacion = StReparacion::query()->where('producto', 'iPhone 13')->first();
        $this->assertNotNull($reparacion);
        $this->assertSame('DORAL', $reparacion->sede);
        $this->assertSame($tecnico->id, $reparacion->tecnico_id);
    }

    public function test_tecnico_no_ve_reparaciones_de_otra_sede(): void
    {
        $tecnico = $this->makeTecnico();
        $propia = StReparacion::create([
            'sede' => 'DORAL',
            'tipo' => 'garantia',
            'producto' => 'Visible',
            'categoria' => 'otro',
            'accion' => 'pendiente',
            'estado' => 'en_proceso',
        ]);
        $ajena = StReparacion::create([
            'sede' => 'VIRTUDES',
            'tipo' => 'interno',
            'producto' => 'Oculta',
            'categoria' => 'otro',
            'accion' => 'pendiente',
            'estado' => 'en_proceso',
        ]);

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('servicio.reparaciones.index'))
            ->assertOk()
            ->assertSee('Visible')
            ->assertDontSee('Oculta');

        $this->get(route('servicio.reparaciones.show', $propia))->assertOk();
        $this->get(route('servicio.reparaciones.show', $ajena))->assertForbidden();
    }

    public function test_factura_usa_numeracion_por_sede_en_servidor(): void
    {
        $tecnico = $this->makeTecnico();

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.facturas.store'), [
                'cliente_nombre' => 'Cliente factura',
                'descripcion' => 'Cambio de pantalla',
                'costo_mano_obra' => 30,
                'costo_refacciones' => 45,
                'estado_pago' => 'pendiente',
            ])
            ->assertRedirect();

        $factura = StFactura::query()->where('cliente_nombre', 'Cliente factura')->first();
        $this->assertNotNull($factura);
        $this->assertSame('DORAL', $factura->sede);
        $this->assertSame(1, $factura->numero);
        $this->assertSame(75.0, (float) $factura->total);
        $this->assertSame('F-DORAL-0001', $factura->codigo());
    }

    public function test_tecnico_no_puede_eliminar_factura(): void
    {
        $tecnico = $this->makeTecnico();
        $factura = StFactura::create([
            'sede' => 'DORAL',
            'numero' => 1,
            'cliente_nombre' => 'Test',
            'total' => 50,
            'estado_pago' => 'pendiente',
            'fecha' => now()->toDateString(),
            'tecnico_id' => $tecnico->id,
        ]);

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->delete(route('servicio.facturas.destroy', $factura))
            ->assertForbidden();
    }

    public function test_supervisor_importa_repuestos_desde_csv(): void
    {
        $supervisor = User::create([
            'name' => 'Supervisor ST',
            'email' => 'sup-st3-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);
        $supervisor->syncExtraPermissions(['servicio', 'servicio.inventario']);

        $csv = "nombre;codigo;categoria;stock;stock_minimo;costo;venta\nBateria Samsung;BAT-001;bateria;10;2;15;35\n";
        $file = UploadedFile::fake()->createWithContent('repuestos.csv', $csv);

        $this->actingAs($supervisor)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.repuestos.import.store'), [
                'archivo' => $file,
                'sede' => 'DORAL',
            ])
            ->assertRedirect(route('servicio.repuestos.index'));

        $repuesto = StRepuesto::query()->where('nombre', 'Bateria Samsung')->first();
        $this->assertNotNull($repuesto);
        $this->assertSame('DORAL', $repuesto->sede);
        $this->assertSame(10, $repuesto->stock);
    }

    private function makeTecnico(): User
    {
        return User::create([
            'name' => 'Técnico Doral',
            'email' => 'tecnico-f3-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TECNICO,
            'sede' => 'DORAL',
        ]);
    }

    private function ensureStTables(): void
    {
        if (! Schema::hasTable('st_ordenes')) {
            Schema::create('st_ordenes', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->unsignedInteger('numero');
                $table->string('cliente_nombre');
                $table->string('estado', 32)->default('pendiente');
                $table->string('prioridad', 16)->default('normal');
                $table->date('fecha_ingreso');
                $table->decimal('presupuesto', 12, 2)->nullable();
                $table->decimal('costo_mano_obra', 12, 2)->nullable();
                $table->decimal('costo_refacciones', 12, 2)->nullable();
                $table->timestamps();
                $table->unique(['sede', 'numero']);
            });
        }

        if (! Schema::hasTable('st_repuestos')) {
            Schema::create('st_repuestos', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->string('codigo', 64)->nullable();
                $table->string('nombre');
                $table->string('categoria', 64)->nullable();
                $table->unsignedInteger('stock')->default(0);
                $table->unsignedInteger('stock_min')->default(0);
                $table->decimal('costo', 12, 2)->default(0);
                $table->decimal('precio_venta', 12, 2)->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('st_movimientos_repuesto')) {
            Schema::create('st_movimientos_repuesto', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('repuesto_id');
                $table->unsignedBigInteger('orden_id')->nullable();
                $table->string('tipo', 16);
                $table->integer('cantidad');
                $table->unsignedInteger('stock_antes');
                $table->unsignedInteger('stock_despues');
                $table->string('motivo')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('st_reparaciones')) {
            Schema::create('st_reparaciones', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->string('tipo', 16);
                $table->string('cliente_nombre')->nullable();
                $table->string('cliente_telefono', 40)->nullable();
                $table->string('producto');
                $table->string('categoria', 32)->default('otro');
                $table->string('comprobante_venta', 64)->nullable();
                $table->text('falla')->nullable();
                $table->string('accion', 32)->default('pendiente');
                $table->string('repuestos_texto')->nullable();
                $table->decimal('costo_interno', 12, 2)->nullable();
                $table->string('estado', 32)->default('en_proceso');
                $table->text('observaciones')->nullable();
                $table->unsignedBigInteger('tecnico_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('st_facturas')) {
            Schema::create('st_facturas', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->unsignedInteger('numero');
                $table->string('cliente_nombre');
                $table->text('descripcion')->nullable();
                $table->decimal('presupuesto', 12, 2)->nullable();
                $table->decimal('costo_mano_obra', 12, 2)->nullable();
                $table->decimal('costo_refacciones', 12, 2)->nullable();
                $table->decimal('total', 12, 2);
                $table->string('estado_pago', 16)->default('pendiente');
                $table->date('fecha');
                $table->unsignedBigInteger('tecnico_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['sede', 'numero']);
            });
        }
    }
}
