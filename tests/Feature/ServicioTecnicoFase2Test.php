<?php

namespace Tests\Feature;

use App\Models\StOrden;
use App\Models\StRepuesto;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ServicioTecnicoFase2Test extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->ensureStTables();
    }

    public function test_marcar_listo_descuenta_stock_en_servidor(): void
    {
        $tecnico = $this->makeTecnico();
        $repuesto = StRepuesto::create([
            'sede' => 'DORAL',
            'nombre' => 'Pantalla iPhone',
            'stock' => 5,
            'stock_min' => 1,
            'costo' => 10,
            'precio_venta' => 25,
        ]);

        $orden = StOrden::crearEnSede([
            'sede' => 'DORAL',
            'cliente_nombre' => 'Cliente',
            'prioridad' => 'normal',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => StOrden::ESTADO_EN_PROCESO,
        ], $tecnico);

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->put(route('servicio.ordenes.update', $orden), [
                'cliente_nombre' => 'Cliente',
                'prioridad' => 'normal',
                'estado' => StOrden::ESTADO_LISTO,
                'repuestos' => [
                    ['repuesto_id' => $repuesto->id, 'cantidad' => 2],
                ],
            ])
            ->assertRedirect();

        $repuesto->refresh();
        $orden->refresh();

        $this->assertSame(3, $repuesto->stock);
        $this->assertNotNull($orden->repuestos_descontados_at);
        $this->assertSame(StOrden::ESTADO_LISTO, $orden->estado);
        $this->assertEquals(20.0, (float) $orden->costo_refacciones);
    }

    public function test_supervisor_puede_transferir_y_tecnico_destino_confirma(): void
    {
        $supervisor = User::create([
            'name' => 'Supervisor ST',
            'email' => 'sup-st-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);
        $supervisor->syncExtraPermissions(['servicio', 'servicio.inventario']);

        $tecnicoDestino = User::create([
            'name' => 'Técnico VIRTUDES',
            'email' => 'tecnico-virtudes-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TECNICO,
            'sede' => 'VIRTUDES',
        ]);

        $orden = StOrden::crearEnSede([
            'sede' => 'DORAL',
            'cliente_nombre' => 'Transfer test',
            'prioridad' => 'normal',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => StOrden::ESTADO_PENDIENTE,
        ], $supervisor);

        $this->actingAs($supervisor)
            ->withSession(['sede_local' => 'DORAL'])
            ->put(route('servicio.ordenes.update', $orden), [
                'cliente_nombre' => 'Transfer test',
                'prioridad' => 'normal',
                'estado' => StOrden::ESTADO_PENDIENTE,
                'sede_destino' => 'VIRTUDES',
            ])
            ->assertRedirect();

        $orden->refresh();
        $this->assertSame('VIRTUDES', $orden->sede);
        $this->assertSame('DORAL', $orden->sede_origen_transfer);
        $this->assertSame(StOrden::TRANSFER_PENDIENTE, $orden->transfer_estado);

        $this->actingAs($tecnicoDestino)
            ->withSession(['sede_local' => 'VIRTUDES'])
            ->post(route('servicio.ordenes.confirmar_recepcion', $orden))
            ->assertRedirect();

        $orden->refresh();
        $this->assertSame(StOrden::TRANSFER_ACEPTADA, $orden->transfer_estado);
    }

    public function test_tecnico_no_accede_a_inventario_repuestos(): void
    {
        $tecnico = $this->makeTecnico();

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('servicio.repuestos.index'))
            ->assertRedirect('/');
    }

    private function makeTecnico(): User
    {
        return User::create([
            'name' => 'Técnico Doral',
            'email' => 'tecnico-'.uniqid().'@test.local',
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
                $table->string('cliente_telefono', 40)->nullable();
                $table->string('cliente_cedula', 40)->nullable();
                $table->string('equipo')->nullable();
                $table->string('serial')->nullable();
                $table->text('falla')->nullable();
                $table->string('accesorios')->nullable();
                $table->text('diagnostico')->nullable();
                $table->string('estado', 32)->default('pendiente');
                $table->string('prioridad', 16)->default('normal');
                $table->date('fecha_ingreso');
                $table->date('fecha_prometida')->nullable();
                $table->text('observaciones')->nullable();
                $table->decimal('presupuesto', 12, 2)->nullable();
                $table->decimal('costo_mano_obra', 12, 2)->nullable();
                $table->decimal('costo_refacciones', 12, 2)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('tecnico_id')->nullable();
                $table->string('sede_origen_transfer', 32)->nullable();
                $table->string('transfer_estado', 16)->nullable();
                $table->timestamp('repuestos_descontados_at')->nullable();
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

        if (! Schema::hasTable('st_orden_repuestos')) {
            Schema::create('st_orden_repuestos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orden_id');
                $table->unsignedBigInteger('repuesto_id');
                $table->unsignedInteger('cantidad');
                $table->decimal('precio_unitario', 12, 2)->default(0);
                $table->decimal('costo_unitario', 12, 2)->default(0);
                $table->boolean('descontado')->default(false);
                $table->timestamps();
                $table->unique(['orden_id', 'repuesto_id']);
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

        if (! Schema::hasTable('st_orden_eventos')) {
            Schema::create('st_orden_eventos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orden_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('tipo', 32);
                $table->text('descripcion');
                $table->text('meta')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }
}
