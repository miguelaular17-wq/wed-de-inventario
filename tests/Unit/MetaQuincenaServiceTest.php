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
        $this->actingAs($supervisor)->get(route('metas.index'))->assertForbidden();

        UserPermission::create(['user_id' => $supervisor->id, 'permission' => 'meta.ver']);
        $supervisor->unsetRelation('extraPermissions');
        $this->actingAs($supervisor->fresh())->get(route('metas.index'))->assertOk();
    }
}
