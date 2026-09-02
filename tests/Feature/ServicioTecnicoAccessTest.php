<?php

namespace Tests\Feature;

use App\Models\StOrden;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ServicioTecnicoAccessTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->ensureStOrdenesTable();
        $this->ensureStOrdenesExtras();
    }

    public function test_tecnico_can_use_servicio_and_cannot_use_finanzas_or_gerencial(): void
    {
        $user = $this->makeTecnico();

        $this->assertTrue($user->canAccess('servicio'));
        $this->assertFalse($user->canAccess('operacion'));
        $this->assertFalse($user->canAccess('finanzas.ver'));
        $this->assertFalse($user->canAccess('gerencial'));
        $this->assertTrue($user->requiresSede());
        $this->assertTrue($user->sedeIsLocked());

        $this->actingAs($user)->withSession(['sede_local' => 'DORAL']);

        $this->get(route('servicio.ordenes.index'))->assertOk();
        $this->get(route('finanzas.flujo_caja'))->assertRedirect('/');
        $this->get(route('gerencial.dashboard'))->assertRedirect('/');
        $this->get(route('ventas.index'))->assertRedirect('/');
        $this->get('/')->assertRedirect(route('servicio.dashboard'));
    }

    public function test_tecnico_only_sees_orders_from_own_sede(): void
    {
        $user = $this->makeTecnico();
        $propia = StOrden::crearEnSede([
            'sede' => 'DORAL',
            'cliente_nombre' => 'Cliente Doral',
            'prioridad' => 'normal',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => StOrden::ESTADO_PENDIENTE,
        ], $user);
        $ajena = StOrden::create([
            'sede' => 'JRZ',
            'numero' => 1,
            'cliente_nombre' => 'Cliente JRZ',
            'prioridad' => 'normal',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => StOrden::ESTADO_PENDIENTE,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->withSession(['sede_local' => 'DORAL']);

        $this->get(route('servicio.ordenes.index'))
            ->assertOk()
            ->assertSee('Cliente Doral')
            ->assertDontSee('Cliente JRZ');

        $this->get(route('servicio.ordenes.show', $propia))->assertOk();
        $this->get(route('servicio.ordenes.show', $ajena))->assertForbidden();
    }

    public function test_tecnico_creates_order_in_assigned_sede_with_server_side_number(): void
    {
        $user = $this->makeTecnico();

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'sede' => 'JRZ',
                'cliente_nombre' => 'Ana Pérez',
                'prioridad' => 'alta',
                'falla' => 'No enciende',
            ])
            ->assertRedirect();

        $orden = StOrden::query()->where('cliente_nombre', 'Ana Pérez')->first();
        $this->assertNotNull($orden);
        $this->assertSame('DORAL', $orden->sede);
        $this->assertSame(1, $orden->numero);
        $this->assertSame('alta', $orden->prioridad);
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

    private function ensureStOrdenesTable(): void
    {
        if (Schema::hasTable('st_ordenes')) {
            return;
        }

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
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('tecnico_id')->nullable();
                $table->string('sede_origen_transfer', 32)->nullable();
                $table->string('transfer_estado', 16)->nullable();
                $table->timestamp('repuestos_descontados_at')->nullable();
                $table->decimal('presupuesto', 12, 2)->nullable();
                $table->decimal('costo_mano_obra', 12, 2)->nullable();
                $table->decimal('costo_refacciones', 12, 2)->nullable();
                $table->timestamps();
            $table->unique(['sede', 'numero']);
        });

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

    private function ensureStOrdenesExtras(): void
    {
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
            });
        }
    }
}
