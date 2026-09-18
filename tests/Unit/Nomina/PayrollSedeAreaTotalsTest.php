<?php

namespace Tests\Unit\Nomina;

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaSede;
use App\Services\Nomina\PayrollSedeAreaTotals;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class PayrollSedeAreaTotalsTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
    }

    public function test_areas_consolidadas_call_digital_y_administracion(): void
    {
        $call = NominaSede::create(['codigo' => 'Call Center', 'nombre' => 'Call Center', 'tipo' => 'AREA', 'estado' => 'ACTIVO']);
        $digital = NominaSede::create(['codigo' => 'Digital Manage', 'nombre' => 'Digital Manage', 'tipo' => 'AREA', 'estado' => 'ACTIVO']);
        $compras = NominaSede::create(['codigo' => 'COMPRAS', 'nombre' => 'Compras', 'tipo' => 'AREA', 'estado' => 'ACTIVO']);
        $deposito = NominaSede::create(['codigo' => 'DEPOSITO', 'nombre' => 'Depósito', 'tipo' => 'AREA', 'estado' => 'ACTIVO']);
        $doral = NominaSede::create(['codigo' => 'DORAL', 'nombre' => 'Doral', 'tipo' => 'SEDE', 'estado' => 'ACTIVO']);

        $svc = app(PayrollSedeAreaTotals::class);

        $this->assertSame('Call Center', $svc->grupoDeEmpleado($this->empleadoConSede($call))['nombre']);
        $this->assertSame('Digital Manage', $svc->grupoDeEmpleado($this->empleadoConSede($digital))['nombre']);
        $this->assertSame('Administración', $svc->grupoDeEmpleado($this->empleadoConSede($compras))['nombre']);
        $this->assertSame('Administración', $svc->grupoDeEmpleado($this->empleadoConSede($deposito))['nombre']);
        $this->assertSame('Doral', $svc->grupoDeEmpleado($this->empleadoConSede($doral))['nombre']);
        $this->assertSame('SEDE', $svc->grupoDeEmpleado($this->empleadoConSede($doral))['tipo']);
        $this->assertSame('AREA|ADMINISTRACIÓN', $svc->grupoDeEmpleado($this->empleadoConSede($compras))['clave']);
    }

    public function test_con_ventas_netas_incluye_call_center_por_vendedor_y_administracion_sin_venta(): void
    {
        $call = NominaSede::create(['codigo' => 'Call Center', 'nombre' => 'Call Center', 'tipo' => 'AREA', 'estado' => 'ACTIVO']);
        NominaSede::create(['codigo' => 'COMPRAS', 'nombre' => 'Compras', 'tipo' => 'AREA', 'estado' => 'ACTIVO']);

        $this->empleadoConSede($call, 'CALL VEND');

        DB::table('ventas_detalle')->insert([
            [
                'sede' => 'DORAL',
                'tipo_documento' => 'FAC',
                'numero_documento' => '1',
                'fecha' => '2026-09-05',
                'cantidad' => 1,
                'precio_venta' => 500,
                'precio_neto' => 500,
                'costo_unitario' => 100,
                'vendedor' => 'CALL VEND',
                'anulado' => false,
            ],
        ]);

        $periodo = NominaPeriodo::create([
            'etiqueta' => 'Q1 Sep',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-15',
            'estado' => NominaPeriodo::CALCULADO,
        ]);

        $grupos = collect([
            [
                'clave' => 'AREA|CALL CENTER',
                'tipo' => 'AREA',
                'etiqueta' => 'Área',
                'nombre' => 'Call Center',
                'empleados' => 1,
                'asignaciones' => 50.0,
                'deducciones' => 0.0,
                'pagar_usd' => 50.0,
                'pagar_bs' => 45000.0,
            ],
            [
                'clave' => 'AREA|ADMINISTRACIÓN',
                'tipo' => 'AREA',
                'etiqueta' => 'Área',
                'nombre' => 'Administración',
                'empleados' => 1,
                'asignaciones' => 100.0,
                'deducciones' => 10.0,
                'pagar_usd' => 90.0,
                'pagar_bs' => 81000.0,
            ],
        ]);

        $filas = app(PayrollSedeAreaTotals::class)->conVentasNetas($grupos, $periodo);

        $callFila = $filas->firstWhere('nombre', 'Call Center');
        $adminFila = $filas->firstWhere('nombre', 'Administración');

        $this->assertNotNull($callFila);
        $this->assertSame(500.0, $callFila['venta_neta']);
        $this->assertSame(10.0, $callFila['pct_nomina_sobre_venta']);

        $this->assertNotNull($adminFila);
        $this->assertSame(0.0, $adminFila['venta_neta']);
        $this->assertNull($adminFila['pct_nomina_sobre_venta']);
    }

    private function empleadoConSede(NominaSede $sede, ?string $vendedor = null): NominaEmpleado
    {
        $clienteId = DB::table('clientes')->insertGetId([
            'nombre' => 'Emp '.$sede->codigo.uniqid(),
            'cedula' => 'V-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return NominaEmpleado::create([
            'cliente_id' => $clienteId,
            'sede' => $sede->codigo,
            'sede_id' => $sede->id,
            'codigo_vendedor' => $vendedor,
            'estado' => 'ACTIVO',
            'salario_base' => 100,
            'tipo_salario' => 'QUINCENAL',
        ]);
    }
}
