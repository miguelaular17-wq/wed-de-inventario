<?php

namespace Tests\Unit;

use App\Models\MetaQuincenaProducto;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\MetaQuincenaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class MetaQuincenaServiceTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        Carbon::setTestNow(Carbon::parse('2026-09-10', 'America/Caracas'));

        if (! Schema::hasTable('meta_quincena_productos')) {
            Schema::create('meta_quincena_productos', function ($table) {
                $table->id();
                $table->unsignedBigInteger('producto_id');
                $table->string('sede', 32);
                $table->date('quincena_inicio');
                $table->date('quincena_fin');
                $table->decimal('cantidad_inicial', 18, 4)->default(0);
                $table->unsignedBigInteger('responsable_empleado_id')->nullable();
                $table->unsignedBigInteger('creado_por_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('productos')) {
            Schema::create('productos', function ($table) {
                $table->id();
                $table->string('codigo')->nullable();
                $table->string('nombre')->nullable();
                $table->string('categoria')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_actual')) {
            Schema::create('stock_actual', function ($table) {
                $table->id();
                $table->unsignedBigInteger('producto_id');
                $table->string('sede', 32);
                $table->decimal('existencia', 18, 4)->default(0);
            });
        }

        if (! Schema::hasTable('ventas_detalle')) {
            Schema::create('ventas_detalle', function ($table) {
                $table->id();
                $table->unsignedBigInteger('producto_id')->nullable();
                $table->string('sede', 16);
                $table->string('tipo_documento', 8);
                $table->date('fecha');
                $table->decimal('cantidad', 18, 4)->default(0);
                $table->boolean('anulado')->default(false);
            });
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_marcar_guarda_stock_inicial_de_la_sede(): void
    {
        $productoId = DB::table('productos')->insertGetId([
            'codigo' => 'META1',
            'nombre' => 'Producto meta',
            'categoria' => 'HOGAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_actual')->insert([
            'producto_id' => $productoId,
            'sede' => 'VIRTUDES',
            'existencia' => 42,
        ]);

        $user = User::create([
            'name' => 'Marketing Meta',
            'email' => 'meta@test.local',
            'password' => 'password123',
            'role' => User::ROLE_MARKETING,
        ]);
        UserPermission::create(['user_id' => $user->id, 'permission' => 'meta']);

        $meta = app(MetaQuincenaService::class)->marcar($productoId, 'virtudes', $user);

        $this->assertSame('VIRTUDES', $meta->sede);
        $this->assertSame(42.0, (float) $meta->cantidad_inicial);
        $this->assertSame('2026-09-01', $meta->quincena_inicio->toDateString());
        $this->assertSame('2026-09-15', $meta->quincena_fin->toDateString());
    }

    public function test_stock_por_sedes_incluye_almacen_jrz_en_el_total(): void
    {
        $productoId = DB::table('productos')->insertGetId([
            'codigo' => 'META666',
            'nombre' => 'Perfume',
            'categoria' => 'PERFUMERIA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_actual')->insert([
            ['producto_id' => $productoId, 'sede' => 'DORAL', 'existencia' => 16],
            ['producto_id' => $productoId, 'sede' => 'VIRTUDES', 'existencia' => 11],
            ['producto_id' => $productoId, 'sede' => 'ZAMORA', 'existencia' => 24],
            ['producto_id' => $productoId, 'sede' => 'CENTRO', 'existencia' => 15],
            ['producto_id' => $productoId, 'sede' => 'JRZ', 'existencia' => 600],
        ]);

        $stock = app(MetaQuincenaService::class)->stockPorSedes($productoId);

        $this->assertSame(16.0, $stock['DORAL']);
        $this->assertSame(600.0, $stock['JRZ']);
        $this->assertEquals(666.0, array_sum($stock));
        $this->assertNotContains('JRZ', app(MetaQuincenaService::class)->sedesMarcables());
    }

    public function test_listado_calcula_vendido_y_stock_actual(): void
    {
        $productoId = DB::table('productos')->insertGetId([
            'codigo' => 'META2',
            'nombre' => 'Producto venta',
            'categoria' => 'HOGAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_actual')->insert([
            ['producto_id' => $productoId, 'sede' => 'MOVISTAR', 'existencia' => 30],
        ]);
        MetaQuincenaProducto::create([
            'producto_id' => $productoId,
            'sede' => 'MOVISTAR',
            'quincena_inicio' => '2026-09-01',
            'quincena_fin' => '2026-09-15',
            'cantidad_inicial' => 50,
        ]);
        DB::table('ventas_detalle')->insert([
            ['producto_id' => $productoId, 'sede' => 'MOVISTAR', 'tipo_documento' => 'FAC', 'numero_documento' => 'M-1', 'fecha' => '2026-09-05', 'cantidad' => 12, 'anulado' => false],
            ['producto_id' => $productoId, 'sede' => 'MOVISTAR', 'tipo_documento' => 'DEV', 'numero_documento' => 'M-2', 'fecha' => '2026-09-06', 'cantidad' => 2, 'anulado' => false],
        ]);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-meta@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        $filas = app(MetaQuincenaService::class)->listarParaUsuario($admin);
        $fila = $filas->firstWhere('producto_id', $productoId);

        $this->assertNotNull($fila);
        $this->assertSame(50.0, $fila['cantidad_inicial']);
        $this->assertSame(30.0, $fila['cantidad_actual']);
        $this->assertSame(10.0, $fila['vendido']);
    }

    public function test_area_call_center_se_marca_con_cantidad_y_sin_stock(): void
    {
        $productoId = DB::table('productos')->insertGetId([
            'codigo' => 'META-CC',
            'nombre' => 'Producto call center',
            'categoria' => 'HOGAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $servicio = app(MetaQuincenaService::class);

        try {
            $servicio->marcar($productoId, 'Call Center');
            $this->fail('Sin cantidad no debe marcar meta de área.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('cantidad', $e->errors());
        }

        $meta = $servicio->marcar($productoId, 'call_center', null, 5);

        $this->assertSame('CALL CENTER', $meta->sede);
        $this->assertSame(5.0, (float) $meta->cantidad_inicial);
        $this->assertSame(['CALL CENTER' => 5.0], $servicio->cantidadesAreaDelProducto($productoId));
        $this->assertTrue($servicio->desmarcar($productoId, 'CALL CENTER'));
    }

    public function test_meta_de_area_cuenta_ventas_de_sus_empleados_en_cualquier_sede(): void
    {
        $productoId = DB::table('productos')->insertGetId([
            'codigo' => 'META-CC2',
            'nombre' => 'Producto call center',
            'categoria' => 'HOGAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('clientes')->insert(['id' => 9101, 'nombre' => 'Agente', 'cedula' => 'V-9101', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('nomina_empleados')->insert([
            'cliente_id' => 9101,
            'sede' => 'Call Center',
            'codigo_vendedor' => 'AGENTE CC',
            'estado' => 'ACTIVO',
            'salario_base' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        MetaQuincenaProducto::create([
            'producto_id' => $productoId,
            'sede' => 'CALL CENTER',
            'quincena_inicio' => '2026-09-01',
            'quincena_fin' => '2026-09-15',
            'cantidad_inicial' => 10,
        ]);
        DB::table('ventas_detalle')->insert([
            ['producto_id' => $productoId, 'sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => 'CC-1', 'fecha' => '2026-09-05', 'cantidad' => 3, 'vendedor' => 'AGENTE CC', 'anulado' => false],
            ['producto_id' => $productoId, 'sede' => 'CENTRO', 'tipo_documento' => 'FAC', 'numero_documento' => 'CC-2', 'fecha' => '2026-09-06', 'cantidad' => 4, 'vendedor' => 'AGENTE CC', 'anulado' => false],
            ['producto_id' => $productoId, 'sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => 'X-1', 'fecha' => '2026-09-06', 'cantidad' => 50, 'vendedor' => 'OTRO VEND', 'anulado' => false],
        ]);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-area@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        $fila = app(MetaQuincenaService::class)->listarParaUsuario($admin)->firstWhere('producto_id', $productoId);

        $this->assertTrue($fila['es_area']);
        $this->assertSame(7.0, $fila['vendido']);
        $this->assertSame(3.0, $fila['cantidad_actual']);
        $this->assertSame(70.0, $fila['avance_pct']);
    }

    public function test_con_permiso_meta_quien_esta_en_call_center_solo_ve_su_area(): void
    {
        $productoId = DB::table('productos')->insertGetId([
            'codigo' => 'META-CC3',
            'nombre' => 'Producto',
            'categoria' => 'HOGAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (['DORAL', 'CALL CENTER'] as $sede) {
            MetaQuincenaProducto::create([
                'producto_id' => $productoId,
                'sede' => $sede,
                'quincena_inicio' => '2026-09-01',
                'quincena_fin' => '2026-09-15',
                'cantidad_inicial' => 4,
            ]);
        }

        $user = User::create([
            'name' => 'Maria Petit',
            'email' => 'maria-cc@test.local',
            'password' => 'password123',
            'role' => User::ROLE_VENDEDOR,
        ]);
        UserPermission::create(['user_id' => $user->id, 'permission' => 'meta']);
        DB::table('clientes')->insert(['id' => 9201, 'nombre' => 'Maria', 'cedula' => 'V-9201', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('nomina_empleados')->insert([
            'cliente_id' => 9201,
            'user_id' => $user->id,
            'sede' => 'CALL_CENTER',
            'estado' => 'ACTIVO',
            'salario_base' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = $user->fresh();
        $servicio = app(MetaQuincenaService::class);

        $this->assertFalse($servicio->veTodasLasSedes($user));
        $this->assertSame('CALL CENTER', $servicio->areaDelUsuario($user));
        $this->assertSame(['CALL CENTER'], $servicio->sedesOperables($user));
        $this->assertTrue($servicio->puedeOperarSede($user, 'Call Center'));
        $this->assertFalse($servicio->puedeOperarSede($user, 'DORAL'));
        $this->assertSame(['CALL CENTER'], $servicio->listarParaUsuario($user)->pluck('sede')->unique()->values()->all());
    }

    public function test_ruta_metas_exige_permiso_ver(): void
    {
        $user = User::create([
            'name' => 'Sede',
            'email' => 'sede-meta@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SEDE,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($user)->get(route('metas.index'))->assertForbidden();

        $supervisor = User::create([
            'name' => 'Supervisor',
            'email' => 'sup-meta@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);
        $this->actingAs($supervisor)->get(route('metas.index'))->assertOk();
    }

    public function test_supervisor_de_doral_no_ve_metas_de_otras_sedes(): void
    {
        $productoId = DB::table('productos')->insertGetId([
            'codigo' => 'META3',
            'nombre' => 'Producto sede',
            'categoria' => 'HOGAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (['DORAL', 'CENTRO'] as $sede) {
            MetaQuincenaProducto::create([
                'producto_id' => $productoId,
                'sede' => $sede,
                'quincena_inicio' => '2026-09-01',
                'quincena_fin' => '2026-09-15',
                'cantidad_inicial' => 4,
            ]);
        }

        $supervisor = User::create([
            'name' => 'Supervisor Doral',
            'email' => 'sup-doral-meta@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);
        UserPermission::create(['user_id' => $supervisor->id, 'permission' => 'meta']);
        $supervisor->unsetRelation('extraPermissions');

        $servicio = app(MetaQuincenaService::class);
        $this->assertFalse($servicio->veTodasLasSedes($supervisor));

        $sedes = $servicio->listarParaUsuario($supervisor->fresh())
            ->pluck('sede')
            ->unique()
            ->values()
            ->all();

        $this->assertSame(['DORAL'], $sedes);
    }
}
