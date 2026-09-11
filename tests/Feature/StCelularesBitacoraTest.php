<?php

namespace Tests\Feature;

use App\Models\StEquipo;
use App\Models\StEquipoEvento;
use App\Models\StOrden;
use App\Models\User;
use App\Services\ServicioTecnico\StEquipoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class StCelularesBitacoraTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->ensureTables();
    }

    public function test_imei_unico_exige_confirmacion_para_reutilizar(): void
    {
        $service = app(StEquipoService::class);
        $service->resolverOCrear([
            'imei' => '350000000000001',
            'marca' => 'Apple',
            'modelo' => 'iPhone 13',
            'sede_actual' => 'DORAL',
        ]);

        try {
            $service->resolverOCrear([
                'imei' => '350000000000001',
                'marca' => 'Apple',
                'modelo' => 'iPhone 13',
            ]);
            $this->fail('Debía exigir confirmación de equipo existente');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('imei', $e->errors());
            $this->assertArrayHasKey('equipo_existente_id', $e->errors());
        }

        $reuso = $service->resolverOCrear([
            'imei' => '350000000000001',
            'serial' => 'SN-1',
        ], true);

        $this->assertFalse($reuso['creado']);
        $this->assertTrue($reuso['existia']);
        $this->assertSame(1, StEquipo::query()->count());
    }

    public function test_crear_orden_con_imei_escribe_bitacora_y_se_consulta(): void
    {
        $user = $this->makeTecnico();

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'cliente_nombre' => 'Cliente Bitacora',
                'prioridad' => 'normal',
                'tipo_gestion' => 'ST',
                'tipo_dispositivo' => 'celular',
                'imei' => '359998887776665',
                'marca' => 'Samsung',
                'modelo' => 'S23',
                'color' => 'Negro',
                'almacenamiento' => '256 GB',
                'falla' => 'No enciende',
            ])
            ->assertRedirect();

        $equipo = StEquipo::query()->where('imei', '359998887776665')->first();
        $this->assertNotNull($equipo);
        $this->assertSame('DORAL', $equipo->sede_actual);

        $orden = StOrden::query()->where('equipo_id', $equipo->id)->first();
        $this->assertNotNull($orden);
        $this->assertSame('ST', $orden->tipo_gestion);

        $this->assertSame(1, StEquipoEvento::query()->where('equipo_id', $equipo->id)->count());

        $this->get(route('servicio.celulares.bitacora', ['q' => '359998887776665']))
            ->assertOk()
            ->assertSee('Samsung S23');

        $this->get(route('servicio.celulares.show', $equipo))
            ->assertOk()
            ->assertSee('Orden creada')
            ->assertSee('No enciende')
            ->assertSee($orden->codigo());
    }

    public function test_hub_y_login_chip_ruta_existen(): void
    {
        $user = $this->makeTecnico();

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('servicio.celulares.hub'))
            ->assertOk()
            ->assertSee('Consultar bitácora')
            ->assertSee('Registrar equipo');
    }

    public function test_wizard_local_con_backup_genera_documento(): void
    {
        $user = $this->makeTecnico();

        $response = $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'tipo_gestion' => 'GARANTIA',
                'rango_garantia' => 'fuera',
                'empresa_envio_garantia' => 'GLOBAL FIT',
                'tipo_dispositivo' => 'celular',
                'cliente_nombre' => 'Cliente Backup',
                'cliente_telefono' => '04141234567',
                'cliente_cedula' => 'V12345678',
                'prioridad' => 'normal',
                'falla' => 'Pantalla rota',
                'imei' => '351112223334445',
                'marca' => 'Apple',
                'modelo' => 'iPhone 12',
                'color' => 'Blanco',
                'almacenamiento' => '64 GB',
                'entrega_backup' => '1',
                'backup_marca' => 'Xiaomi',
                'backup_modelo' => 'Redmi Note',
                'backup_imei' => '359998887776661',
                'backup_estado_fisico' => 'Buen estado',
                'backup_firma_cliente' => 'Cliente Backup',
            ]);

        $orden = StOrden::query()->where('cliente_nombre', 'Cliente Backup')->first();
        $this->assertNotNull($orden);
        $this->assertSame('GARANTIA', $orden->tipo_gestion);
        $backup = \App\Models\StBackup::query()->where('orden_id', $orden->id)->first();
        $this->assertNotNull($backup);

        $response->assertRedirect(route('servicio.ordenes.show', $orden));

        $this->get(route('servicio.ordenes.backup_pdf', ['orden' => $orden, 'backup' => $backup]))
            ->assertOk();

        $recepcion = $this->get(route('servicio.ordenes.recepcion_pdf', $orden));
        $recepcion->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $recepcion->headers->get('content-type'));

        $html = view('servicio.ordenes.pdf-recepcion', [
            'orden' => $orden->load(['equipoCelular', 'creador', 'backups']),
            'backup' => $backup,
            'logo' => public_path('logo.png'),
        ])->render();
        $this->assertStringContainsString('La garantía es con la marca del equipo (Apple)', $html);
    }

    public function test_checklist_y_conformidad_quedan_en_la_orden(): void
    {
        $user = $this->makeTecnico();
        $firma = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'cliente_nombre' => 'Cliente Check',
                'prioridad' => 'normal',
                'tipo_gestion' => 'ST',
                'tipo_dispositivo' => 'celular',
                'imei' => '350011122233344',
                'marca' => 'Samsung',
                'modelo' => 'A15',
                'color' => 'Azul',
                'almacenamiento' => '128 GB',
                'falla' => 'No carga',
                'inspeccion' => [
                    'pantalla' => ['estado' => 'ok'],
                    'carga' => ['estado' => 'dano'],
                    'humedad' => ['estado' => 'na'],
                ],
                'firma_recepcion_cliente' => $firma,
            ])
            ->assertRedirect();

        $orden = StOrden::query()->where('cliente_nombre', 'Cliente Check')->first();
        $this->assertNotNull($orden);
        $this->assertSame('ok', $orden->inspeccion_recepcion['pantalla'] ?? null);
        $this->assertSame('dano', $orden->inspeccion_recepcion['carga'] ?? null);
        $this->assertStringStartsWith('data:image/png', (string) $orden->firma_recepcion_cliente);

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.conformidad', $orden), [
                'conformidad_trabajo' => 'Se cambió el pin de carga.',
                'firma_conformidad_cliente' => $firma,
            ])
            ->assertRedirect(route('servicio.ordenes.show', $orden));

        $orden->refresh();
        $this->assertSame('Se cambió el pin de carga.', $orden->conformidad_trabajo);
        $this->assertNotNull($orden->conformidad_at);

        $this->get(route('servicio.ordenes.conformidad_pdf', $orden))->assertOk();
        $this->get(route('servicio.ordenes.recepcion_pdf', $orden))->assertOk();
    }

    public function test_envio_entre_sedes_aparece_en_por_recibir(): void
    {
        $remitente = User::create([
            'name' => 'Usuario Envio',
            'email' => 'usuario-envio-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_VENDEDOR,
            'sede' => 'DORAL',
        ]);
        $tecnicoDestino = User::create([
            'name' => 'Técnico Virtudes',
            'email' => 'tec-virt-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TECNICO,
            'sede' => 'VIRTUDES',
        ]);

        $this->actingAs($remitente)
            ->post(route('servicio.ordenes.store'), [
                'tipo_gestion' => 'ST',
                'tipo_dispositivo' => 'celular',
                'sede' => 'DORAL',
                'enviar_otra_sede' => '1',
                'tecnico_destino_id' => $tecnicoDestino->id,
                'cliente_nombre' => 'Cliente Envio',
                'prioridad' => 'normal',
                'falla' => 'No carga',
                'imei' => '358887776665554',
                'marca' => 'Motorola',
                'modelo' => 'Edge',
                'color' => 'Gris',
                'almacenamiento' => '256 GB',
            ])
            ->assertRedirect();

        $orden = StOrden::query()->where('cliente_nombre', 'Cliente Envio')->first();
        $this->assertNotNull($orden);
        $this->assertSame('VIRTUDES', $orden->sede);
        $this->assertSame('DORAL', $orden->sede_origen_transfer);
        $this->assertSame(StOrden::TRANSFER_PENDIENTE, $orden->transfer_estado);
        $this->assertSame($tecnicoDestino->id, $orden->tecnico_id);

        $otroTecnico = User::create([
            'name' => 'Otro Técnico Virtudes',
            'email' => 'otro-tec-virt-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TECNICO,
            'sede' => 'VIRTUDES',
        ]);
        $this->actingAs($otroTecnico)
            ->withSession(['sede_local' => 'VIRTUDES'])
            ->get(route('servicio.celulares.por_recibir'))
            ->assertOk()
            ->assertDontSee('Motorola Edge');

        $this->actingAs($tecnicoDestino)
            ->withSession(['sede_local' => 'VIRTUDES'])
            ->get(route('servicio.celulares.por_recibir'))
            ->assertOk()
            ->assertSee('Motorola Edge')
            ->assertSee($orden->codigo());

        $this->actingAs($otroTecnico)
            ->withSession(['sede_local' => 'VIRTUDES'])
            ->post(route('servicio.ordenes.confirmar_recepcion', $orden))
            ->assertSessionHasErrors('transfer');

        $this->actingAs($tecnicoDestino)
            ->withSession(['sede_local' => 'VIRTUDES'])
            ->post(route('servicio.ordenes.confirmar_recepcion', $orden))
            ->assertRedirect();
        $this->assertSame(StOrden::TRANSFER_ACEPTADA, $orden->fresh()->transfer_estado);
    }

    public function test_selector_y_validacion_de_envio_solo_admiten_usuarios_de_servicio_tecnico(): void
    {
        $remitente = User::create([
            'name' => 'Remitente General',
            'email' => 'remitente-general-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_VENDEDOR,
            'sede' => 'DORAL',
        ]);
        $tecnico = User::create([
            'name' => 'Técnico Disponible',
            'email' => 'tecnico-disponible-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TECNICO,
            'sede' => 'VIRTUDES',
        ]);
        $noTecnico = User::create([
            'name' => 'Usuario No Técnico',
            'email' => 'no-tecnico-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_VENDEDOR,
            'sede' => 'VIRTUDES',
        ]);

        $this->actingAs($remitente)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('servicio.ordenes.create'))
            ->assertOk()
            ->assertSee($tecnico->name)
            ->assertDontSee($noTecnico->name);

        $this->actingAs($remitente)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'tipo_gestion' => 'ST',
                'tipo_dispositivo' => 'celular',
                'sede' => 'DORAL',
                'enviar_otra_sede' => '1',
                'tecnico_destino_id' => $noTecnico->id,
                'cliente_nombre' => 'Destino inválido',
                'prioridad' => 'normal',
                'falla' => 'No enciende',
                'imei' => '358887776665553',
                'marca' => 'Samsung',
                'modelo' => 'A54',
                'color' => 'Negro',
                'almacenamiento' => '128 GB',
            ])
            ->assertSessionHasErrors('tecnico_destino_id');
    }

    public function test_servicio_tecnico_no_crea_backup_aunque_lo_envien(): void
    {
        $user = $this->makeTecnico();

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'tipo_gestion' => 'ST',
                'tipo_dispositivo' => 'celular',
                'cliente_nombre' => 'Sin Backup',
                'prioridad' => 'normal',
                'falla' => 'No enciende',
                'imei' => '353334445556667',
                'marca' => 'Xiaomi',
                'modelo' => 'Redmi',
                'color' => 'Negro',
                'almacenamiento' => '64 GB',
                'entrega_backup' => '1',
                'backup_marca' => 'Xiaomi',
                'backup_modelo' => 'Backup',
            ])
            ->assertRedirect();

        $orden = StOrden::query()->where('cliente_nombre', 'Sin Backup')->first();
        $this->assertNotNull($orden);
        $this->assertSame(0, \App\Models\StBackup::query()->where('orden_id', $orden->id)->count());
    }

    public function test_impresora_guarda_serial_tipo_y_checklist_propio(): void
    {
        $user = $this->makeTecnico();

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'tipo_gestion' => 'ST',
                'tipo_dispositivo' => 'impresora',
                'cliente_nombre' => 'Cliente Impresora',
                'prioridad' => 'normal',
                'falla' => 'Atasco de papel',
                'serial' => 'HP-XYZ-99',
                'marca' => 'HP',
                'modelo' => 'LaserJet',
                'tipo_impresora' => 'laser',
                'accesorios_sel' => ['poder', 'usb'],
                'inspeccion' => [
                    'imp_encendido' => ['estado' => 'ok'],
                    'imp_wifi' => ['estado' => 'dano'],
                    'pantalla' => ['estado' => 'ok'],
                ],
            ])
            ->assertRedirect();

        $orden = StOrden::query()->where('cliente_nombre', 'Cliente Impresora')->first();
        $this->assertNotNull($orden);
        $this->assertSame('impresora', $orden->tipo_dispositivo);
        $this->assertSame('HP-XYZ-99', $orden->serial);
        $this->assertNull($orden->imei);
        $this->assertSame('laser', $orden->atributo('tipo_impresora'));
        $this->assertStringContainsString('Cable de poder', (string) $orden->accesorios);
        $this->assertSame('ok', $orden->inspeccion_recepcion['imp_encendido'] ?? null);
        $this->assertSame('dano', $orden->inspeccion_recepcion['imp_wifi'] ?? null);
        $this->assertArrayNotHasKey('pantalla', $orden->inspeccion_recepcion ?? []);

        $claves = array_column($orden->itemsInspeccionRecepcion(), 'clave');
        $this->assertContains('imp_encendido', $claves);
        $this->assertNotContains('imei_coincide', $claves);
    }

    public function test_garantia_dentro_de_rango_no_exige_cliente(): void
    {
        $user = $this->makeTecnico();

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'tipo_gestion' => 'GARANTIA',
                'rango_garantia' => 'dentro',
                'empresa_envio_garantia' => 'TECNOTROPOLIS',
                'tipo_dispositivo' => 'celular',
                'prioridad' => 'normal',
                'falla' => 'Pantalla rota',
                'imei' => '354445556667778',
                'marca' => 'Apple',
                'modelo' => 'iPhone 14',
                'color' => 'Negro',
                'almacenamiento' => '128 GB',
                'cliente_nombre' => 'No debe guardarse',
                'cliente_telefono' => '04140000000',
                'cliente_cedula' => 'V1',
                'fecha_prometida' => '2026-10-01',
            ])
            ->assertRedirect();

        $orden = StOrden::query()->where('imei', '354445556667778')->first();
        $this->assertNotNull($orden);
        $this->assertSame('dentro', $orden->rango_garantia);
        $this->assertSame('TECNOTROPOLIS', $orden->empresa_envio_garantia);
        $this->assertSame('Cambio en rango (empresa)', $orden->cliente_nombre);
        $this->assertNull($orden->cliente_telefono);
        $this->assertNull($orden->fecha_prometida);
    }

    public function test_garantia_fuera_de_rango_exige_datos_del_cliente(): void
    {
        $user = $this->makeTecnico();

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'tipo_gestion' => 'GARANTIA',
                'rango_garantia' => 'fuera',
                'empresa_envio_garantia' => 'TOTALINK',
                'tipo_dispositivo' => 'celular',
                'prioridad' => 'normal',
                'falla' => 'No carga',
                'imei' => '355556667778889',
                'marca' => 'Samsung',
                'modelo' => 'S24',
                'color' => 'Gris',
                'almacenamiento' => '256 GB',
            ])
            ->assertSessionHasErrors(['cliente_nombre', 'cliente_telefono', 'cliente_cedula']);
    }

    public function test_reparacion_interna_no_exige_cliente_y_guarda_valor_del_dispositivo(): void
    {
        $user = $this->makeTecnico();

        $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('servicio.ordenes.create'))
            ->assertOk()
            ->assertSee('Reparación interna')
            ->assertSee('Valor del dispositivo');

        $this->post(route('servicio.ordenes.store'), [
            'tipo_gestion' => StOrden::TIPO_REPARACION_INTERNA,
            'tipo_dispositivo' => 'celular',
            'prioridad' => 'normal',
            'falla' => 'Equipo de exhibición no enciende',
            'imei' => '356667778889990',
            'marca' => 'Samsung',
            'modelo' => 'A55',
            'color' => 'Azul',
            'almacenamiento' => '256 GB',
            'valor_dispositivo' => 650.50,
            'cliente_nombre' => 'No debe guardarse',
            'cliente_telefono' => '04140000000',
            'cliente_cedula' => 'V1',
        ])->assertRedirect();

        $orden = StOrden::query()->where('imei', '356667778889990')->firstOrFail();
        $this->assertSame(StOrden::TIPO_REPARACION_INTERNA, $orden->tipo_gestion);
        $this->assertTrue($orden->esReparacionInterna());
        $this->assertSame('Reparación interna', $orden->cliente_nombre);
        $this->assertNull($orden->cliente_telefono);
        $this->assertNull($orden->cliente_cedula);
        $this->assertSame('650.50', $orden->valor_dispositivo);
    }

    private function makeTecnico(): User
    {
        return User::create([
            'name' => 'Técnico Celulares',
            'email' => 'tec-cel-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TECNICO,
            'sede' => 'DORAL',
        ]);
    }

    private function ensureTables(): void
    {
        if (! Schema::hasTable('st_ordenes')) {
            Schema::create('st_ordenes', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->unsignedInteger('numero');
                $table->string('tipo_gestion', 16)->default('ST');
                $table->string('tipo_dispositivo', 32)->default('celular');
                $table->json('atributos')->nullable();
                $table->string('rango_garantia', 16)->nullable();
                $table->string('empresa_envio_garantia', 40)->nullable();
                $table->decimal('valor_dispositivo', 14, 2)->nullable();
                $table->unsignedBigInteger('equipo_id')->nullable();
                $table->string('cliente_nombre');
                $table->string('cliente_telefono', 40)->nullable();
                $table->string('cliente_cedula', 40)->nullable();
                $table->string('equipo')->nullable();
                $table->string('imei', 32)->nullable();
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
                $table->string('sede_destino_transfer', 32)->nullable();
                $table->string('transfer_estado', 16)->nullable();
                $table->timestamp('repuestos_descontados_at')->nullable();
                $table->decimal('presupuesto', 12, 2)->nullable();
                $table->decimal('costo_mano_obra', 12, 2)->nullable();
                $table->decimal('costo_refacciones', 12, 2)->nullable();
                $table->json('inspeccion_recepcion')->nullable();
                $table->longText('firma_recepcion_cliente')->nullable();
                $table->longText('firma_recepcion_empleado')->nullable();
                $table->text('conformidad_trabajo')->nullable();
                $table->longText('firma_conformidad_cliente')->nullable();
                $table->longText('firma_conformidad_empleado')->nullable();
                $table->timestamp('conformidad_at')->nullable();
                $table->timestamps();
                $table->unique(['sede', 'numero']);
            });
        } else {
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
                if (! Schema::hasColumn('st_ordenes', 'tipo_dispositivo')) {
                    $table->string('tipo_dispositivo', 32)->default('celular');
                }
                if (! Schema::hasColumn('st_ordenes', 'atributos')) {
                    $table->json('atributos')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'rango_garantia')) {
                    $table->string('rango_garantia', 16)->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'valor_dispositivo')) {
                    $table->decimal('valor_dispositivo', 14, 2)->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'empresa_envio_garantia')) {
                    $table->string('empresa_envio_garantia', 40)->nullable();
                }
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
                $table->string('tipo_dispositivo', 32)->default('celular');
                $table->json('atributos')->nullable();
                $table->timestamps();
            });
        } elseif (Schema::hasTable('st_equipos')) {
            Schema::table('st_equipos', function (Blueprint $table) {
                if (! Schema::hasColumn('st_equipos', 'tipo_dispositivo')) {
                    $table->string('tipo_dispositivo', 32)->default('celular');
                }
                if (! Schema::hasColumn('st_equipos', 'atributos')) {
                    $table->json('atributos')->nullable();
                }
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

        if (! Schema::hasTable('st_backups')) {
            Schema::create('st_backups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orden_id');
                $table->unsignedBigInteger('equipo_cliente_id')->nullable();
                $table->string('marca', 64)->nullable();
                $table->string('modelo', 128)->nullable();
                $table->string('imei', 32)->nullable();
                $table->string('serial', 64)->nullable();
                $table->string('estado_fisico')->nullable();
                $table->string('accesorios')->nullable();
                $table->text('condiciones')->nullable();
                $table->string('firma_cliente')->nullable();
                $table->string('firma_empleado')->nullable();
                $table->string('estado', 32)->default('entregado');
                $table->timestamp('entregado_at')->nullable();
                $table->timestamp('devuelto_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }
}
