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
use Laravel\Sanctum\Sanctum;
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
        (require database_path('migrations/2026_11_06_200000_add_resultado_interna_to_st_ordenes.php'))->up();
        (require database_path('migrations/2026_11_09_100000_add_realizado_at_to_st_ordenes.php'))->up();
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

        $this->assertSame('SAMSUNG', $equipo->marca);
        $this->get(route('servicio.celulares.bitacora', ['q' => '359998887776665']))
            ->assertOk()
            ->assertSee('SAMSUNG S23');

        $this->get(route('servicio.celulares.show', $equipo))
            ->assertOk()
            ->assertSee('Orden creada')
            ->assertSee('No enciende')
            ->assertSee($orden->codigo());
    }

    public function test_eliminar_la_unica_orden_saca_el_equipo_de_la_bitacora(): void
    {
        $user = $this->makeTecnico();
        $crear = fn (string $imei, bool $existente = false) => $this->actingAs($user)
            ->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'cliente_nombre' => 'Cliente Borrar',
                'prioridad' => 'normal',
                'tipo_gestion' => 'ST',
                'tipo_dispositivo' => 'celular',
                'imei' => $imei,
                'marca' => 'Honor',
                'modelo' => 'Play 10',
                'color' => 'Negro',
                'almacenamiento' => '128 GB',
                'falla' => 'Pantalla',
                'usar_equipo_existente' => $existente ? '1' : '0',
            ])->assertSessionHasNoErrors();

        $crear('86465087139675');
        $equipo = StEquipo::query()->where('imei', '86465087139675')->firstOrFail();
        $orden = StOrden::query()->where('equipo_id', $equipo->id)->firstOrFail();

        $this->delete(route('servicio.ordenes.destroy', $orden))->assertSessionHasNoErrors();
        $this->assertNull(StEquipo::query()->find($equipo->id));
        $this->assertSame(0, StEquipoEvento::query()->where('equipo_id', $equipo->id)->count());
        $this->get(route('servicio.celulares.bitacora', ['q' => '9675']))->assertOk()->assertDontSee('86465087139675');

        // Con otra orden del mismo equipo, el equipo se queda.
        $crear('864656087139675');
        $crear('864656087139675', true);
        $equipo = StEquipo::query()->where('imei', '864656087139675')->firstOrFail();
        $this->assertSame(2, StOrden::query()->where('equipo_id', $equipo->id)->count());
        $this->delete(route('servicio.ordenes.destroy', StOrden::query()->where('equipo_id', $equipo->id)->firstOrFail()))->assertSessionHasNoErrors();
        $this->assertNotNull(StEquipo::query()->find($equipo->id));
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
        $this->assertStringContainsString('La garantía es con la marca del equipo (APPLE)', $html);
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
            ->assertSessionHasErrors('reparado_por');

        $this->post(route('servicio.ordenes.conformidad', $orden), [
            'conformidad_trabajo' => 'Se cambió el pin de carga.',
            'firma_conformidad_cliente' => $firma,
            'reparado_por' => $user->id,
        ])->assertRedirect(route('servicio.ordenes.show', $orden));

        $orden->refresh();
        $this->assertSame('Se cambió el pin de carga.', $orden->conformidad_trabajo);
        $this->assertNotNull($orden->conformidad_at);
        $this->assertSame((int) $user->id, (int) $orden->reparado_por);
        $this->assertNotNull($orden->realizado_at);

        $this->get(route('servicio.ordenes.conformidad_pdf', $orden))->assertOk();
        $this->get(route('servicio.ordenes.recepcion_pdf', $orden))->assertOk();

        // Desde la ventana de la ficha: guarda y abre la hoja.
        $this->post(route('servicio.ordenes.conformidad', $orden), [
            'conformidad_trabajo' => 'Se cambió el pin de carga y la batería.',
            'ver_pdf' => '1',
        ])->assertRedirect(route('servicio.ordenes.conformidad_pdf', $orden));
        $this->assertSame('Se cambió el pin de carga y la batería.', $orden->fresh()->conformidad_trabajo);
        $this->assertStringStartsWith('data:image/png', (string) $orden->fresh()->firma_conformidad_cliente);
        $this->get(route('servicio.ordenes.show', $orden))->assertOk()
            ->assertSee('data-abrir-conformidad', false)
            ->assertDontSee('Guardar conformidad');
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
            ->assertDontSee('MOTOROLA Edge');

        $this->actingAs($tecnicoDestino)
            ->withSession(['sede_local' => 'VIRTUDES'])
            ->get(route('servicio.celulares.por_recibir'))
            ->assertOk()
            ->assertSee('MOTOROLA Edge')
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
            ->assertDontSee('name="tipo_gestion"', false)
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

    public function test_api_android_registra_lista_y_cambia_estado_de_celular(): void
    {
        $user = $this->makeTecnico();
        Sanctum::actingAs($user, ['mobile']);

        $this->getJson('/api/v1/servicio/celulares/opciones')
            ->assertOk()
            ->assertJsonPath('data.sede_activa', 'DORAL')
            ->assertJsonPath('data.sede_bloqueada', true);

        $created = $this->postJson('/api/v1/servicio/celulares/ordenes', [
            'sede' => 'DORAL',
            'tipo_gestion' => 'ST',
            'valor_dispositivo' => 325.50,
            'cliente_nombre' => 'Cliente Android',
            'cliente_telefono' => '04141234567',
            'imei' => '350099988877766',
            'marca' => 'Samsung',
            'modelo' => 'A55',
            'color' => 'Azul',
            'almacenamiento' => '256 GB',
            'falla' => 'No carga',
            'prioridad' => 'alta',
            'inspeccion' => ['pantalla' => 'ok'],
            'firma_recepcion_cliente' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJ',
        ])->assertCreated()
            ->assertJsonPath('data.cliente_nombre', 'Cliente Android')
            ->assertJsonPath('data.imei', '350099988877766')
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.tipo_gestion', StOrden::TIPO_SIN_TIPO)
            ->assertJsonPath('data.marca', 'SAMSUNG');

        $this->getJson('/api/v1/servicio/celulares/opciones')->assertOk()
            ->assertJsonFragment(['marcas' => ['SAMSUNG']]);

        $orderId = $created->json('data.id');
        $this->assertDatabaseHas('st_ordenes', [
            'id' => $orderId,
            'firma_recepcion_cliente' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJ',
        ]);

        $this->get("/api/v1/servicio/celulares/ordenes/{$orderId}/pdf/recepcion")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->getJson('/api/v1/servicio/celulares/ordenes?q=350099988877766')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $orderId);

        $this->postJson("/api/v1/servicio/celulares/ordenes/{$orderId}/estado", [
            'estado' => 'en_proceso',
            'comentario' => 'Se inició el diagnóstico desde Android.',
        ])->assertOk()
            ->assertJsonPath('data.estado', 'en_proceso');

        $this->getJson("/api/v1/servicio/celulares/ordenes/{$orderId}")
            ->assertOk()
            ->assertJsonFragment(['tipo' => 'estado']);
    }

    public function test_registro_sin_tipo_de_gestion_y_marca_de_la_lista_u_otra(): void
    {
        $user = $this->makeTecnico();
        StEquipo::query()->create(['imei' => '350000000000099', 'marca' => 'redmi', 'estado_actual' => StEquipo::ESTADO_DISPONIBLE]);

        $this->actingAs($user)->withSession(['sede_local' => 'DORAL'])
            ->get(route('servicio.ordenes.create'))
            ->assertOk()
            ->assertDontSee('name="tipo_gestion"', false)
            ->assertSee('<option value="REDMI"', false)
            ->assertSee('Otra (escribirla)');

        $this->post(route('servicio.ordenes.store'), [
            'prioridad' => 'normal', 'tipo_dispositivo' => 'celular', 'imei' => '350077766655544',
            'marca' => \App\Support\MarcasServicio::OTRA, 'marca_otra' => '  nueva   marca ',
            'modelo' => 'X1', 'color' => 'Negro', 'almacenamiento' => '64 GB', 'falla' => 'No enciende',
        ])->assertSessionHasNoErrors();

        $orden = StOrden::query()->where('imei', '350077766655544')->firstOrFail();
        $this->assertSame(StOrden::TIPO_SIN_TIPO, $orden->tipo_gestion);
        $this->assertSame('Sin tipo', $orden->etiquetaTipoGestion());
        $this->assertSame('Sin datos del cliente', $orden->cliente_nombre);
        $this->assertSame('NUEVA MARCA', $orden->equipoCelular->marca);
        $this->get(route('servicio.ordenes.create'))->assertSee('<option value="NUEVA MARCA"', false);
    }

    public function test_api_ve_la_bitacora_de_otra_sede_pero_no_la_cambia(): void
    {
        $doral = $this->makeTecnico();
        Sanctum::actingAs($doral, ['mobile']);
        $orderId = $this->postJson('/api/v1/servicio/celulares/ordenes', [
            'sede' => 'DORAL', 'cliente_nombre' => 'Cliente Doral', 'imei' => '351234567890123',
            'marca' => 'Tecno', 'modelo' => 'Spark 20', 'color' => 'Negro', 'almacenamiento' => '128 GB',
            'falla' => 'No carga', 'prioridad' => 'normal',
        ])->assertCreated()->json('data.id');

        $centro = User::create(['name' => 'Técnico Centro', 'email' => 'tec-centro-'.uniqid().'@test.local',
            'password' => 'password123', 'role' => User::ROLE_TECNICO, 'sede' => 'CENTRO']);
        Sanctum::actingAs($centro, ['mobile']);

        $this->getJson('/api/v1/servicio/celulares/ordenes')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/servicio/celulares/ordenes?q=567890123')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.sede', 'DORAL');
        $this->getJson("/api/v1/servicio/celulares/ordenes/{$orderId}")->assertOk()
            ->assertJsonPath('data.puede_gestionar', false)
            ->assertJsonPath('data.cliente_nombre', 'Cliente Doral');
        $this->get("/api/v1/servicio/celulares/ordenes/{$orderId}/pdf/recepcion")->assertOk();
        $this->postJson("/api/v1/servicio/celulares/ordenes/{$orderId}/estado", ['estado' => 'en_proceso', 'comentario' => 'Probando'])
            ->assertForbidden();

        Sanctum::actingAs($doral, ['mobile']);
        $this->getJson("/api/v1/servicio/celulares/ordenes/{$orderId}")->assertOk()->assertJsonPath('data.puede_gestionar', true);
    }

    public function test_buscar_por_imei_encuentra_la_orden_sea_del_tipo_que_sea(): void
    {
        $user = $this->makeTecnico();
        $this->actingAs($user)->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'prioridad' => 'normal', 'tipo_gestion' => StOrden::TIPO_REPARACION_INTERNA, 'tipo_dispositivo' => 'celular',
                'imei' => '353791683504556', 'marca' => 'Hotwav', 'modelo' => 'A17 Pro Max', 'color' => 'Negro',
                'almacenamiento' => '128 GB', 'falla' => 'Pantalla',
            ])->assertSessionHasNoErrors();
        $orden = StOrden::query()->where('imei', '353791683504556')->firstOrFail();

        $sinBuscar = fn ($ordenes) => ! collect($ordenes->items())->contains('id', $orden->id);
        $encontrada = fn ($ordenes) => collect($ordenes->items())->contains('id', $orden->id);
        $this->get(route('servicio.ordenes.index'))->assertOk()->assertViewHas('ordenes', $sinBuscar);
        $this->get(route('servicio.ordenes.index', ['q' => '504556']))->assertOk()->assertViewHas('ordenes', $encontrada);
        $this->get(route('servicio.ordenes.index', ['q' => '353791683504556']))->assertOk()->assertViewHas('ordenes', $encontrada);
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

    public function test_la_reparacion_interna_registra_su_resultado_y_el_tecnico_que_la_reparo(): void
    {
        (require database_path('migrations/2026_11_06_200000_add_resultado_interna_to_st_ordenes.php'))->up();
        $user = $this->makeTecnico();
        $this->actingAs($user)->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'prioridad' => 'normal', 'tipo_gestion' => 'REPARACION_INTERNA', 'tipo_dispositivo' => 'celular',
                'imei' => '350044433322211', 'marca' => 'Cubot', 'modelo' => 'A50', 'color' => 'Negro', 'almacenamiento' => '64 GB',
                'falla' => 'Pantalla en negro',
            ])->assertSessionHasNoErrors();
        $orden = StOrden::query()->where('imei', '350044433322211')->firstOrFail();
        $this->assertTrue($orden->esReparacionInterna());

        $this->postJson(route('servicio.ordenes.resultado_interna', $orden), ['resultado' => 'REPARADO'])
            ->assertStatus(422)->assertJsonValidationErrors('tecnico_id');

        $this->postJson(route('servicio.ordenes.resultado_interna', $orden), ['resultado' => 'REPARADO', 'tecnico_id' => $user->id])
            ->assertOk()
            ->assertJsonPath('resultado', 'REPARADO')
            ->assertJsonPath('tecnico', $user->name);
        $orden->refresh();
        $this->assertSame((int) $user->id, (int) $orden->reparado_por);
        $this->assertSame('Reparado · '.$user->name, $orden->etiquetaResultadoInterna());

        $this->postJson(route('servicio.ordenes.resultado_interna', $orden), ['resultado' => 'PARA_REPUESTO'])->assertOk();
        $orden->refresh();
        $this->assertSame('PARA_REPUESTO', $orden->resultado_interna);
        $this->assertNull($orden->reparado_por);

        $this->get(route('servicio.ordenes.reporte_marca', ['tipo_gestion' => 'REPARACION_INTERNA']))
            ->assertOk()
            ->assertSee('Reparado (técnico)')
            ->assertSee('ri-resultado', false);
        $this->get(route('servicio.ordenes.show', $orden))->assertOk()->assertSee('Para repuesto');
    }

    public function test_se_entrega_sin_hoja_y_despues_la_orden_queda_cerrada(): void
    {
        $user = $this->makeTecnico();
        $this->actingAs($user)->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'cliente_nombre' => 'Cliente Entrega', 'cliente_telefono' => '0414', 'prioridad' => 'normal',
                'tipo_gestion' => 'ST', 'tipo_dispositivo' => 'celular', 'imei' => '350066655544433',
                'marca' => 'Tecno', 'modelo' => 'Spark 20', 'color' => 'Azul', 'almacenamiento' => '128 GB', 'falla' => 'Pantalla',
            ])->assertSessionHasNoErrors();
        $orden = StOrden::query()->where('cliente_nombre', 'Cliente Entrega')->firstOrFail();
        $this->assertFalse($orden->tieneConformidad());

        $this->post(route('servicio.ordenes.entregar', $orden))->assertSessionHasErrors('reparado_por');
        $this->post(route('servicio.ordenes.entregar', $orden), ['reparado_por' => $user->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('servicio.ordenes.show', $orden));
        $orden->refresh();
        $this->assertSame(StOrden::ESTADO_ENTREGADO, $orden->estado);
        $this->assertSame((int) $user->id, (int) $orden->reparado_por);
        $this->assertNotNull($orden->realizado_at);

        $this->get(route('servicio.ordenes.index'))->assertOk()
            ->assertViewHas('ordenes', fn ($ordenes) => ! collect($ordenes->items())->contains('id', $orden->id));
        $this->get(route('servicio.ordenes.realizados'))->assertOk()
            ->assertSee($orden->codigo())
            ->assertSee($user->name);
        $this->assertTrue($orden->entregada());
        $this->assertSame(StEquipo::ESTADO_ENTREGADO, StEquipo::query()->find($orden->equipo_id)?->estado_actual);
        $this->assertDatabaseHas('st_orden_eventos', ['orden_id' => $orden->id, 'tipo' => 'estado']);

        $this->get(route('servicio.ordenes.show', $orden))->assertOk()
            ->assertSee('orden cerrada')
            ->assertSee('Se entregó sin hoja de conformidad firmada')
            ->assertDontSee('Cambiar tipo')
            ->assertDontSee(route('servicio.ordenes.edit', $orden), false)
            ->assertDontSee('Entregar al cliente');

        $this->post(route('servicio.ordenes.entregar', $orden))->assertSessionHasErrors('entregada');
        $this->get(route('servicio.ordenes.edit', $orden))->assertSessionHasErrors('entregada');
        $this->put(route('servicio.ordenes.update', $orden), ['cliente_nombre' => 'Otro', 'prioridad' => 'normal'])->assertSessionHasErrors('entregada');
        $this->post(route('servicio.ordenes.conformidad', $orden), ['conformidad_trabajo' => 'x'])->assertSessionHasErrors('entregada');
        $this->post(route('servicio.ordenes.tipo_gestion', $orden), ['tipo_gestion' => 'REPARACION_INTERNA', 'motivo_cambio' => 'Prueba'])->assertSessionHasErrors('entregada');
        $this->post(route('servicio.ordenes.cambiar_estado', $orden), ['estado' => 'en_proceso', 'comentario_estado' => 'Volver'])->assertSessionHasErrors('entregada');
        $this->delete(route('servicio.ordenes.destroy', $orden))->assertSessionHasErrors('entregada');

        $orden->refresh();
        $this->assertSame('Cliente Entrega', $orden->cliente_nombre);
        $this->assertSame(StOrden::ESTADO_ENTREGADO, $orden->estado);
        $this->assertNull($orden->conformidad_trabajo);
    }

    public function test_quien_tiene_sede_fija_crea_la_orden_en_su_sede_aunque_elija_otra(): void
    {
        $telefonia = User::create([
            'name' => 'Telefonía Doral', 'email' => 'tel-'.uniqid().'@test.local', 'password' => 'password123',
            'role' => User::ROLE_TELEFONIA, 'sede' => 'DORAL',
        ]);
        \App\Models\UserPermission::create(['user_id' => $telefonia->id, 'permission' => 'servicio']);
        $telefonia = $telefonia->fresh();
        $this->assertTrue($telefonia->creaServicioEnSuSede());

        $this->actingAs($telefonia)->withSession(['sede_local' => 'CENTRO'])
            ->post(route('servicio.ordenes.store'), [
                'sede' => 'CENTRO', 'sede_local' => 'CENTRO', 'enviar_otra_sede' => '0',
                'cliente_nombre' => 'Cliente Sede Fija', 'cliente_telefono' => '0414', 'prioridad' => 'normal',
                'tipo_gestion' => 'ST', 'tipo_dispositivo' => 'celular', 'imei' => '350077766655544',
                'marca' => 'Honor', 'modelo' => 'Play 20a', 'color' => 'Negro', 'almacenamiento' => '64 GB', 'falla' => 'No carga',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $orden = StOrden::query()->where('cliente_nombre', 'Cliente Sede Fija')->firstOrFail();
        $this->assertSame('DORAL', $orden->sede);
        $this->assertSame('DORAL', $orden->sedeOrigen());
    }

    public function test_cambiar_el_tipo_de_gestion_pide_motivo_y_queda_en_la_auditoria(): void
    {
        (require database_path('migrations/2026_11_06_180000_create_auditorias_table.php'))->up();
        $user = $this->makeTecnico();
        $this->actingAs($user)->withSession(['sede_local' => 'DORAL'])
            ->post(route('servicio.ordenes.store'), [
                'cliente_nombre' => 'Cliente Tipo', 'cliente_telefono' => '0414', 'prioridad' => 'normal',
                'tipo_gestion' => 'ST', 'tipo_dispositivo' => 'celular', 'imei' => '350099988877766',
                'marca' => 'Xiaomi', 'modelo' => 'Redmi 13', 'color' => 'Negro', 'almacenamiento' => '128 GB', 'falla' => 'No enciende',
            ])->assertSessionHasNoErrors()->assertRedirect();
        $orden = StOrden::query()->where('cliente_nombre', 'Cliente Tipo')->firstOrFail();

        $creada = \App\Models\Auditoria::query()->where('auditable_type', 'StOrden')->where('auditable_id', $orden->id)->sole();
        $this->assertSame('creado', $creada->accion);
        $this->assertSame($user->id, (int) $creada->user_id);

        $this->post(route('servicio.ordenes.tipo_gestion', $orden), ['tipo_gestion' => 'GARANTIA', 'rango_garantia' => 'fuera'])
            ->assertSessionHasErrors('motivo_cambio');
        $this->post(route('servicio.ordenes.tipo_gestion', $orden), [
            'tipo_gestion' => 'GARANTIA', 'rango_garantia' => 'fuera', 'cliente_nombre' => 'Cliente Tipo',
            'cliente_telefono' => '0414', 'motivo_cambio' => 'Falta la cédula',
        ])->assertSessionHasErrors('cliente_cedula');
        $this->assertSame('ST', $orden->fresh()->tipo_gestion);

        $this->post(route('servicio.ordenes.tipo_gestion', $orden), [
            'tipo_gestion' => 'GARANTIA', 'rango_garantia' => 'fuera', 'empresa_envio_garantia' => 'GLOBAL FIT',
            'cliente_nombre' => 'Cliente Tipo', 'cliente_telefono' => '0414', 'cliente_cedula' => 'V9',
            'motivo_cambio' => 'Se registró como ST pero está en garantía',
        ])->assertRedirect(route('servicio.ordenes.show', $orden));

        $orden->refresh();
        $this->assertSame('GARANTIA', $orden->tipo_gestion);
        $this->assertSame('fuera', $orden->rango_garantia);
        $this->assertSame('GLOBAL FIT', $orden->empresa_envio_garantia);

        $cambio = \App\Models\Auditoria::query()->where('auditable_type', 'StOrden')->where('auditable_id', $orden->id)
            ->where('accion', 'actualizado')->latest('id')->firstOrFail();
        $this->assertSame(['ST', 'GARANTIA'], $cambio->cambios['tipo_gestion']);
        $this->assertSame([null, 'fuera'], $cambio->cambios['rango_garantia']);
        $this->assertStringContainsString('Se registró como ST pero está en garantía', (string) $cambio->motivo);
        $this->assertSame($user->id, (int) $cambio->user_id);
        $this->assertDatabaseHas('st_orden_eventos', ['orden_id' => $orden->id, 'tipo' => 'nota']);

        // A reparación interna: se quitan los datos del cliente.
        $this->post(route('servicio.ordenes.tipo_gestion', $orden), [
            'tipo_gestion' => 'REPARACION_INTERNA', 'motivo_cambio' => 'Es un equipo de la tienda',
        ])->assertRedirect();
        $orden->refresh();
        $this->assertSame('REPARACION_INTERNA', $orden->tipo_gestion);
        $this->assertSame('Reparación interna', $orden->cliente_nombre);
        $this->assertNull($orden->rango_garantia);
        $this->assertNull($orden->cliente_cedula);

        $this->post(route('servicio.ordenes.tipo_gestion', $orden), [
            'tipo_gestion' => 'REPARACION_INTERNA', 'motivo_cambio' => 'Otra vez',
        ])->assertSessionHasErrors('tipo_gestion');
    }

    public function test_los_cambios_de_la_ficha_y_las_firmas_quedan_auditados(): void
    {
        (require database_path('migrations/2026_11_06_180000_create_auditorias_table.php'))->up();
        $user = $this->makeTecnico();
        $this->actingAs($user);
        $equipo = StEquipo::create(['imei' => '350055544433322', 'marca' => 'Apple', 'modelo' => 'iPhone 12', 'sede_actual' => 'DORAL']);
        $equipo->update(['sede_actual' => 'CENTRO']);

        $cambio = \App\Models\Auditoria::query()->where('auditable_type', 'StEquipo')->where('accion', 'actualizado')->sole();
        $this->assertSame(['DORAL', 'CENTRO'], $cambio->cambios['sede_actual']);
        $this->assertArrayNotHasKey('updated_at', $cambio->cambios);

        $equipo->delete();
        $this->assertSame(1, \App\Models\Auditoria::query()->where('auditable_type', 'StEquipo')->where('accion', 'eliminado')->count());
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

        if (! Schema::hasTable('st_orden_repuestos')) {
            Schema::create('st_orden_repuestos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orden_id');
                $table->unsignedBigInteger('repuesto_id');
                $table->unsignedInteger('cantidad')->default(1);
                $table->decimal('precio_unitario', 12, 2)->default(0);
                $table->decimal('costo_unitario', 12, 2)->default(0);
                $table->boolean('descontado')->default(false);
                $table->timestamps();
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
