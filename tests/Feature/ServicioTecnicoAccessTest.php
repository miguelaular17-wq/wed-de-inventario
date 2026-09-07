<?php

namespace Tests\Feature;

use App\Models\StOrden;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\Concerns\CreatesServicioEquipoSchema;
use Tests\TestCase;

class ServicioTecnicoAccessTest extends TestCase
{
    use CreatesNominaSchema;
    use CreatesServicioEquipoSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->ensureStOrdenesTable();
        $this->ensureStOrdenesExtras();
        $this->ensureStEquipoBitacoraSchema();
    }

    public function test_gerente_abre_servicio_sin_sede_seleccionada(): void
    {
        $gerente = User::create([
            'name' => 'Gerente ST',
            'email' => 'gerente-st-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_GERENTE,
        ]);

        $this->actingAs($gerente)
            ->get(route('servicio.ordenes.index'))
            ->assertOk();
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
        $this->get('/')->assertRedirect(route('servicio.celulares.hub'));
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
                'cliente_nombre' => 'Ana Pérez',
                'prioridad' => 'alta',
                'falla' => 'No enciende',
                'serial' => 'SN-ANA-001',
                'marca' => 'Samsung',
                'modelo' => 'A54',
            ])
            ->assertRedirect();

        $orden = StOrden::query()->where('cliente_nombre', 'Ana Pérez')->first();
        $this->assertNotNull($orden, 'La orden no se creó; revisar validación del wizard.');
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
        if (Schema::hasTable('st_ordenes')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                if (! Schema::hasColumn('st_ordenes', 'tipo_gestion')) {
                    $table->string('tipo_gestion', 16)->default('ST');
                }
                if (! Schema::hasColumn('st_ordenes', 'equipo_id')) {
                    $table->unsignedBigInteger('equipo_id')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'imei')) {
                    $table->string('imei', 32)->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'sede_destino_transfer')) {
                    $table->string('sede_destino_transfer', 32)->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'inspeccion_recepcion')) {
                    $table->json('inspeccion_recepcion')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'firma_recepcion_cliente')) {
                    $table->longText('firma_recepcion_cliente')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'firma_recepcion_empleado')) {
                    $table->longText('firma_recepcion_empleado')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'conformidad_trabajo')) {
                    $table->text('conformidad_trabajo')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'firma_conformidad_cliente')) {
                    $table->longText('firma_conformidad_cliente')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'firma_conformidad_empleado')) {
                    $table->longText('firma_conformidad_empleado')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'conformidad_at')) {
                    $table->timestamp('conformidad_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('st_equipos')) {
            Schema::create('st_equipos', function (Blueprint $table) {
                $table->id();
                $table->string('imei', 32)->nullable()->unique();
                $table->string('imei2', 32)->nullable();
                $table->string('serial', 64)->nullable();
                $table->string('marca', 64)->nullable();
                $table->string('modelo', 128)->nullable();
                $table->string('color', 64)->nullable();
                $table->string('telefono_asociado', 40)->nullable();
                $table->string('estado_actual', 32)->default('en_taller');
                $table->string('sede_actual', 32)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('st_equipo_eventos')) {
            Schema::create('st_equipo_eventos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('equipo_id');
                $table->unsignedBigInteger('orden_id')->nullable();
                $table->unsignedBigInteger('backup_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('sede', 32)->nullable();
                $table->string('tipo', 32);
                $table->string('titulo')->nullable();
                $table->text('descripcion')->nullable();
                $table->text('payload')->nullable();
                $table->timestamp('created_at')->nullable();
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
            });
        }
    }
}
