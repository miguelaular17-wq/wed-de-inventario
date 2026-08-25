<?php

namespace Tests\Feature\Nomina;

use App\Models\Nomina\NominaCargo;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaSede;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class EmpleadoCrudTest extends TestCase
{
    use CreatesNominaSchema;

    private User $rrhh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        $this->rrhh = User::create([
            'name' => 'RRHH',
            'email' => 'rrhh-emp@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
    }

    public function test_crea_empleado_con_sede_cargo_y_supervisor(): void
    {
        $sede = NominaSede::create(['nombre' => 'Centro', 'codigo' => 'CENTRO', 'estado' => 'ACTIVO']);
        $cargoSup = NominaCargo::create(['nombre' => 'Supervisor de sede', 'estado' => 'ACTIVO']);
        $cargoVen = NominaCargo::create(['nombre' => 'Vendedor', 'estado' => 'ACTIVO']);

        $this->actingAs($this->rrhh);

        $this->post(route('nomina.empleados.store'), [
            'cedula' => '19648944',
            'nombre' => 'Carlos Gomez',
            'salario_base' => 900,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'sede_id' => $sede->id,
            'cargo_id' => $cargoSup->id,
            'es_supervisor' => 1,
            'fecha_ingreso' => '2024-01-15',
        ])->assertRedirect();

        $supervisor = NominaEmpleado::query()->first();
        $this->assertTrue($supervisor->es_supervisor);
        $this->assertEquals('CENTRO', $supervisor->sede);

        $this->post(route('nomina.empleados.store'), [
            'cedula' => '28501943',
            'nombre' => 'Juan Perez',
            'salario_base' => 800,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'sede_id' => $sede->id,
            'cargo_id' => $cargoVen->id,
            'supervisor_id' => $supervisor->id,
            'codigo_vendedor' => 'Juan Perez',
            'fecha_ingreso' => '2024-02-01',
        ])->assertRedirect();

        $juan = NominaEmpleado::query()->where('supervisor_id', $supervisor->id)->with('jefes')->first();
        $this->assertNotNull($juan);
        $this->assertEquals('Juan Perez', $juan->nombre());
        $this->assertEquals('JUAN PEREZ', $juan->fresh()->codigo_vendedor);
        $this->assertEquals($juan->id, NominaEmpleado::buscarPorVendedor('juan perez')?->id);
        $this->assertTrue($juan->jefes->contains('id', $supervisor->id));

        $this->get(route('nomina.empleados.show', ['empleado' => $juan, 'tab' => 'laboral']))
            ->assertOk()
            ->assertSee('JUAN PEREZ')
            ->assertSee('Carlos Gomez')
            ->assertDontSee('Alias de vendedor');

        $this->get(route('nomina.empleados.show', ['empleado' => $juan, 'tab' => 'comisiones']))
            ->assertOk()
            ->assertSee('Comisiones de marca');
    }

    public function test_empleado_puede_tener_dos_supervisores_de_sede(): void
    {
        $sede = NominaSede::create(['nombre' => 'Virtudes', 'codigo' => 'VIRTUDES', 'estado' => 'ACTIVO']);
        $cargoSup = NominaCargo::create(['nombre' => 'Supervisor de sede', 'estado' => 'ACTIVO']);
        $cargoVen = NominaCargo::create(['nombre' => 'Asesor de venta', 'estado' => 'ACTIVO']);
        $this->actingAs($this->rrhh);

        $this->post(route('nomina.empleados.store'), [
            'cedula' => '26598293',
            'nombre' => 'Josmarly Velazquez',
            'salario_base' => 450,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'sede_id' => $sede->id,
            'cargo_id' => $cargoSup->id,
            'es_supervisor' => 1,
        ])->assertRedirect();
        $this->post(route('nomina.empleados.store'), [
            'cedula' => '25402263',
            'nombre' => 'Brandon Sanchez',
            'salario_base' => 250,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'sede_id' => $sede->id,
            'cargo_id' => $cargoSup->id,
            'es_supervisor' => 1,
        ])->assertRedirect();

        $josmarly = NominaEmpleado::query()->whereHas('cliente', fn ($q) => $q->where('cedula', '26598293'))->first();
        $brandon = NominaEmpleado::query()->whereHas('cliente', fn ($q) => $q->where('cedula', '25402263'))->first();

        $this->post(route('nomina.empleados.store'), [
            'cedula' => '31852005',
            'nombre' => 'Juan Pablo Beaujon',
            'salario_base' => 200,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'sede_id' => $sede->id,
            'cargo_id' => $cargoVen->id,
            'supervisor_ids' => [$josmarly->id, $brandon->id],
        ])->assertRedirect();

        $juan = NominaEmpleado::query()->whereHas('cliente', fn ($q) => $q->where('cedula', '31852005'))->first();
        $this->assertEqualsCanonicalizing([$josmarly->id, $brandon->id], $juan->fresh()->jefes->pluck('id')->all());
        $this->assertEquals($josmarly->id, $juan->supervisor_id);
    }

    public function test_index_carga_personas_desde_clientes(): void
    {
        \App\Models\Cliente::create(['cedula' => '28766068', 'nombre' => 'MIGUEL AULAR']);
        \App\Models\Cliente::create(['cedula' => '17500110', 'nombre' => 'DIANA BUSTILLO']);

        $this->actingAs($this->rrhh);

        $this->get(route('nomina.empleados.index'))
            ->assertOk()
            ->assertSee('MIGUEL AULAR')
            ->assertSee('DIANA BUSTILLO')
            ->assertSee('28766068');

        $this->assertEquals(2, NominaEmpleado::query()->count());
    }

    public function test_muestra_ventas_por_codigo_de_vendedor(): void
    {
        $this->actingAs($this->rrhh);

        $this->post(route('nomina.empleados.store'), [
            'cedula' => '28369068',
            'nombre' => 'JOSEMAR MAVAREZ',
            'salario_base' => 80,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'codigo_vendedor' => 'JOSEMAR',
        ])->assertRedirect();

        $empleado = NominaEmpleado::query()->first();

        \Illuminate\Support\Facades\DB::table('ventas_detalle')->insert([
            'sede' => 'CENTRO',
            'tipo_documento' => 'FAC',
            'numero_documento' => '9901',
            'fecha' => now()->toDateString(),
            'cantidad' => 2,
            'precio_venta' => 40,
            'nombre_producto' => 'SET MICROFONOS INALAM. JBL WIRELESS',
            'cliente' => 'MARIETH BRACHO',
            'vendedor' => 'JOSEMAR',
            'anulado' => false,
        ]);

        $this->get(route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'ventas']))
            ->assertOk()
            ->assertSee('FAC 9901')
            ->assertSee('80.00')
            ->assertSee('16.00')
            ->assertSee('64.00')
            ->assertSee('JOSEMAR');

        $this->get(route('nomina.empleados.show', [
            'empleado' => $empleado,
            'tab' => 'ventas',
            'fac_sede' => 'CENTRO',
            'fac_tipo' => 'FAC',
            'fac_numero' => '9901',
            'fac_fecha' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('SET MICROFONOS INALAM. JBL WIRELESS')
            ->assertSee('MARIETH BRACHO')
            ->assertSee('Neto cobrado');
    }

    public function test_registra_abono_de_sueldo_sin_prestamo(): void
    {
        $this->actingAs($this->rrhh);

        $this->post(route('nomina.empleados.store'), [
            'cedula' => '28501944',
            'nombre' => 'Abel Yajuris',
            'salario_base' => 100,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
        ])->assertRedirect();

        $empleado = NominaEmpleado::query()->first();

        $this->get(route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'abonos']))
            ->assertOk()
            ->assertSee('Registrar abono de quincena')
            ->assertDontSee('No hay préstamos activos.');

        $this->post(route('nomina.abonos_sueldo.store', $empleado), [
            'fecha' => '2026-08-20',
            'monto' => 40,
            'motivo' => 'Adelanto',
        ])->assertRedirect(route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'abonos']));

        $this->get(route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'abonos']))
            ->assertOk()
            ->assertSee('40.00')
            ->assertSee('PENDIENTE')
            ->assertSee('Adelanto');
    }

    public function test_registra_inasistencia_y_horas_extras_en_recibo(): void
    {
        $this->actingAs($this->rrhh);

        $this->post(route('nomina.empleados.store'), [
            'cedula' => '28501945',
            'nombre' => 'Abel Yajuris',
            'salario_base' => 100,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
        ])->assertRedirect();

        $empleado = NominaEmpleado::query()->first();

        $this->put(route('nomina.configuracion.update'), [
            'valor_hora_extra' => 4,
            'descuento_venta_pct' => 20,
        ])->assertRedirect(route('nomina.configuracion.index'));

        $this->post(route('nomina.inasistencias.hoy', $empleado))->assertRedirect();
        $this->post(route('nomina.horas_extras.store', $empleado), [
            'fecha' => now()->format('Y-m-d'),
            'horas' => 2,
            'motivo' => 'Cierre',
        ])->assertRedirect();

        $this->get(route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'nomina']))
            ->assertOk()
            ->assertSee('Ausencias:')
            ->assertSee('6.67')
            ->assertSee('Horas extras:')
            ->assertSee('Faltó hoy');
    }

    public function test_crea_regla_de_comision_usando_catalogo_de_productos(): void
    {
        $productoId = DB::table('productos')->insertGetId([
            'codigo' => 'PROD-001',
            'nombre' => 'Producto catálogo',
            'categoria' => 'TELEFONIA',
            'subcategoria' => 'ANDROID',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $segundoProductoId = DB::table('productos')->insertGetId([
            'codigo' => 'PROD-002',
            'nombre' => 'Segundo producto',
            'categoria' => 'ACCESORIOS',
            'subcategoria' => 'CARGADORES',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->rrhh)
            ->get(route('nomina.configuracion.index'))
            ->assertOk()
            ->assertSee('PROD-001')
            ->assertSee('TELEFONIA')
            ->assertSee('ANDROID');

        $this->post(route('nomina.configuracion.reglas.store'), [
            'nombre' => 'Comisión producto catálogo',
            'nivel' => 'PRODUCTO',
            'producto_ids' => [$productoId, $segundoProductoId],
            'porcentaje' => 2.5,
            'base_comisionable' => 'TOTAL',
            'fecha_inicio' => '2026-08-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('nomina_reglas_comision', [
            'nombre' => 'Comisión producto catálogo',
            'nivel' => 'PRODUCTO',
            'producto_id' => $productoId,
            'codigo_producto' => 'PROD-001',
        ]);
        $this->assertDatabaseHas('nomina_reglas_comision', [
            'nombre' => 'Comisión producto catálogo',
            'producto_id' => $segundoProductoId,
            'codigo_producto' => 'PROD-002',
        ]);

        $this->post(route('nomina.configuracion.reglas.store'), [
            'nombre' => 'Comisión Android',
            'nivel' => 'SUBCATEGORIA',
            'subcategorias' => [json_encode([
                'categoria' => 'TELEFONIA',
                'subcategoria' => 'ANDROID',
            ]), json_encode([
                'categoria' => 'ACCESORIOS',
                'subcategoria' => 'CARGADORES',
            ])],
            'porcentaje' => 1.5,
            'base_comisionable' => 'NETO',
            'fecha_inicio' => '2026-08-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('nomina_reglas_comision', [
            'nombre' => 'Comisión Android',
            'nivel' => 'SUBCATEGORIA',
            'categoria' => 'TELEFONIA',
            'subcategoria' => 'ANDROID',
        ]);
        $this->assertDatabaseHas('nomina_reglas_comision', [
            'nombre' => 'Comisión Android',
            'nivel' => 'SUBCATEGORIA',
            'categoria' => 'ACCESORIOS',
            'subcategoria' => 'CARGADORES',
        ]);
    }

    public function test_no_permite_supervisor_circular(): void
    {
        $this->actingAs($this->rrhh);

        $this->post(route('nomina.empleados.store'), [
            'cedula' => '111',
            'nombre' => 'Ana',
            'salario_base' => 1,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'es_supervisor' => 1,
        ]);
        $this->post(route('nomina.empleados.store'), [
            'cedula' => '222',
            'nombre' => 'Luis',
            'salario_base' => 1,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'es_supervisor' => 1,
            'supervisor_id' => NominaEmpleado::query()->whereHas('cliente', fn ($q) => $q->where('cedula', '111'))->value('id'),
        ]);

        $ana = NominaEmpleado::query()->whereHas('cliente', fn ($q) => $q->where('cedula', '111'))->first();
        $luis = NominaEmpleado::query()->whereHas('cliente', fn ($q) => $q->where('cedula', '222'))->first();

        $this->put(route('nomina.empleados.update', $ana), [
            'cedula' => '111',
            'nombre' => 'Ana',
            'salario_base' => 1,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'es_supervisor' => 1,
            'supervisor_id' => $luis->id,
        ])->assertSessionHasErrors('supervisor_id');
    }
}
