<?php

namespace Tests\Feature;

use App\Models\StOrden;
use App\Models\StRepuesto;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\Concerns\CreatesServicioEquipoSchema;
use Tests\TestCase;

class ServicioTecnicoFase2Test extends TestCase
{
    use CreatesNominaSchema;
    use CreatesServicioEquipoSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->ensureStTables();
        $this->ensureStEquipoBitacoraSchema();
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
                'comentario_estado' => 'La reparación fue terminada y verificada.',
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

    public function test_cambio_rapido_de_estado_exige_y_registra_comentario(): void
    {
        $tecnico = $this->makeTecnico();
        $orden = StOrden::crearEnSede([
            'sede' => 'DORAL',
            'cliente_nombre' => 'Cambio de estado',
            'prioridad' => 'normal',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => StOrden::ESTADO_PENDIENTE,
        ], $tecnico);

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.cambiar_estado', $orden), [
                'estado' => StOrden::ESTADO_EN_PROCESO,
            ])
            ->assertSessionHasErrors('comentario_estado');

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.cambiar_estado', $orden), [
                'estado' => StOrden::ESTADO_EN_PROCESO,
                'comentario_estado' => 'Se inició el diagnóstico del dispositivo.',
            ])
            ->assertRedirect();

        $this->assertSame(StOrden::ESTADO_EN_PROCESO, $orden->fresh()->estado);
        $this->assertDatabaseHas('st_orden_eventos', [
            'orden_id' => $orden->id,
            'descripcion' => 'Estado: Pendiente → En proceso. Motivo: Se inició el diagnóstico del dispositivo.',
        ]);
    }

    public function test_flujo_externo_de_garantia_bloquea_edicion_y_registra_bitacora(): void
    {
        $tecnico = $this->makeTecnico();
        $equipo = \App\Models\StEquipo::create([
            'imei' => '359999999999991',
            'marca' => 'Samsung',
            'modelo' => 'A18',
            'estado_actual' => \App\Models\StEquipo::ESTADO_EN_TALLER,
            'sede_actual' => 'DORAL',
        ]);
        $orden = StOrden::crearEnSede([
            'sede' => 'DORAL',
            'equipo_id' => $equipo->id,
            'tipo_gestion' => StOrden::TIPO_GARANTIA,
            'cliente_nombre' => 'Cliente garantía',
            'prioridad' => 'normal',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => StOrden::ESTADO_PENDIENTE,
            'estado_garantia_externa' => StOrden::GARANTIA_PENDIENTE_ENVIO,
        ], $tecnico);

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('servicio.ordenes.edit', $orden))
            ->assertOk()
            ->assertDontSee('Motivo del cambio de estado');
        $this->get(route('servicio.ordenes.index'))
            ->assertOk()
            ->assertSee('Gestionar envío de garantía')
            ->assertSee('Nuevo estado de envío');

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.garantia.estado', $orden), [
                'estado_garantia' => StOrden::GARANTIA_ENVIADO,
                'comentario_garantia' => 'Equipo no enciende',
                'motivo' => 'Reparación',
            ])
            ->assertSessionHasErrors('empresa');

        $this->post(route('servicio.ordenes.garantia.estado', $orden), [
            'estado_garantia' => StOrden::GARANTIA_ENVIADO,
            'empresa' => 'GLOBAL FIT',
            'motivo' => 'Reparación',
            'comentario_garantia' => 'Equipo no enciende',
        ])->assertRedirect();

        $orden->refresh();
        $this->assertSame(StOrden::GARANTIA_ENVIADO, $orden->estado_garantia_externa);
        $this->assertSame('GLOBAL FIT', $orden->empresa_envio_garantia);
        $this->assertNotNull($orden->garantia_enviado_at);
        $this->assertSame(\App\Models\StEquipo::ESTADO_EN_TRANSITO, $equipo->fresh()->estado_actual);
        $this->assertDatabaseHas('st_equipo_eventos', [
            'equipo_id' => $equipo->id,
            'orden_id' => $orden->id,
            'titulo' => 'Enviado a garantía: GLOBAL FIT',
        ]);

        $this->put(route('servicio.ordenes.update', $orden), [
            'cliente_nombre' => 'Nombre modificado',
            'prioridad' => 'normal',
            'estado' => StOrden::ESTADO_PENDIENTE,
        ])->assertSessionHasErrors('garantia');
        $this->assertSame('Cliente garantía', $orden->fresh()->cliente_nombre);

        $this->post(route('servicio.ordenes.garantia.actualizacion', $orden), [
            'tipo' => 'avance',
            'comentario' => 'Equipo recibido para diagnóstico.',
        ])->assertRedirect();
        $this->post(route('servicio.ordenes.garantia.estado', $orden), [
            'estado_garantia' => StOrden::GARANTIA_EN_PROCESO,
            'comentario_garantia' => 'Se detectó falla en módulo de carga.',
        ])->assertRedirect();
        $this->assertSame(StOrden::GARANTIA_EN_PROCESO, $orden->fresh()->estado_garantia_externa);

        $this->post(route('servicio.ordenes.garantia.estado', $orden), [
            'estado_garantia' => StOrden::GARANTIA_RECIBIDO,
            'comentario_garantia' => 'Equipo revisado al regresar.',
        ])->assertRedirect();

        $orden->refresh();
        $this->assertSame(StOrden::GARANTIA_RECIBIDO, $orden->estado_garantia_externa);
        $this->assertNotNull($orden->garantia_recibido_at);
        $this->assertSame(\App\Models\StEquipo::ESTADO_EN_TALLER, $equipo->fresh()->estado_actual);
        $this->assertDatabaseHas('st_orden_eventos', [
            'orden_id' => $orden->id,
            'descripcion' => 'Equipo recibido nuevamente en sede DORAL. Observación: Equipo revisado al regresar.',
        ]);
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

        // Orden ya existente en destino con el mismo número → fuerza renumeración al transferir.
        StOrden::crearEnSede([
            'sede' => 'VIRTUDES',
            'cliente_nombre' => 'Ya en destino',
            'prioridad' => 'normal',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => StOrden::ESTADO_PENDIENTE,
        ], $supervisor);

        $orden = StOrden::crearEnSede([
            'sede' => 'DORAL',
            'cliente_nombre' => 'Transfer test',
            'prioridad' => 'normal',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => StOrden::ESTADO_PENDIENTE,
        ], $supervisor);
        $numeroOrigen = (int) $orden->numero;

        $this->actingAs($supervisor)
            ->withSession(['sede_local' => 'DORAL'])
            ->put(route('servicio.ordenes.update', $orden), [
                'cliente_nombre' => 'Transfer test',
                'prioridad' => 'normal',
                'estado' => StOrden::ESTADO_PENDIENTE,
                'tecnico_destino_id' => $tecnicoDestino->id,
            ])
            ->assertRedirect();

        $orden->refresh();
        $this->assertSame('VIRTUDES', $orden->sede);
        $this->assertSame('DORAL', $orden->sede_origen_transfer);
        $this->assertSame(StOrden::TRANSFER_PENDIENTE, $orden->transfer_estado);
        $this->assertSame(2, (int) $orden->numero);
        $this->assertNotSame($numeroOrigen, (int) $orden->numero);

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
                $table->string('tipo_gestion', 16)->default('ST');
                $table->string('empresa_envio_garantia', 40)->nullable();
                $table->string('estado_garantia_externa', 24)->nullable();
                $table->string('motivo_envio_garantia')->nullable();
                $table->text('observacion_envio_garantia')->nullable();
                $table->timestamp('garantia_enviado_at')->nullable();
                $table->unsignedBigInteger('garantia_enviado_por')->nullable();
                $table->timestamp('garantia_recibido_at')->nullable();
                $table->unsignedBigInteger('garantia_recibido_por')->nullable();
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
