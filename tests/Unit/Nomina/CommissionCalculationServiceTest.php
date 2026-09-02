<?php

namespace Tests\Unit\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaComisionAbono;
use App\Models\Nomina\NominaConfig;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaSede;
use App\Services\Nomina\CommissionCalculationService;
use App\Services\Nomina\CommissionSettlementService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class CommissionCalculationServiceTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        NominaConfig::put('descuento_venta_pct', 0);
        NominaConfig::put('comision_supervisor_pct', 0.05);
        NominaConfig::put('comision_marketing_pct', 0.10);
        NominaConfig::put('comision_telefonia_pct', 0.20);
        NominaConfig::put('comision_otros_pct', 1);
        NominaConfig::put('comision_servicio_tecnico_pct', 50);
        NominaConfig::put('retencion_comision_pct', 10);
        DB::table('nomina_grupos_comision')->insert([
            'grupo' => 'TELEFONIA',
            'categoria' => 'TELEFONIA',
            'categoria_normalizada' => 'TELEFONIA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ventas_propias_telefonia_al_cero_punto_veinte_y_resto_al_uno(): void
    {
        $empleado = $this->empleado(NominaEmpleado::COMISION_VENTAS_PROPIAS, 'VEND-001');
        $periodo = $this->periodo();
        $telefoniaId = DB::table('productos')->insertGetId([
            'codigo' => 'P-001',
            'nombre' => 'Teléfono',
            'categoria' => 'TELEFONIA',
            'subcategoria' => 'EQUIPOS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otrosId = DB::table('productos')->insertGetId([
            'codigo' => 'P-002',
            'nombre' => 'Perfume',
            'categoria' => 'PERFUMERIA',
            'subcategoria' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->venta('VEND-001', 1000, ['producto_id' => $telefoniaId]);
        $this->venta('VEND-001', 500, ['producto_id' => $otrosId]);

        $resultado = app(CommissionCalculationService::class)->calcular($periodo, $empleado);

        $this->assertSame(2.0, $resultado['comision_telefonia']);
        $this->assertSame(5.0, $resultado['comision_otros']);
        $this->assertSame(7.0, $resultado['total']);
        $this->assertSame(1500.0, $resultado['base']);
    }

    public function test_ignora_ventas_de_sede_excluida_de_comision(): void
    {
        $empleado = $this->empleado(NominaEmpleado::COMISION_VENTAS_PROPIAS, 'VEND-EXCL');
        NominaSede::query()->where('codigo', 'CENTRO')->update(['excluir_comision' => true]);
        $periodo = $this->periodo();
        $this->venta('VEND-EXCL', 1000);

        $resultado = app(CommissionCalculationService::class)->calcular($periodo, $empleado);

        $this->assertSame(0.0, $resultado['total']);
        $this->assertSame(0.0, $resultado['base']);
        $this->assertDatabaseCount('nomina_comision_registros', 0);
    }

    public function test_supervisor_de_sede_cobra_cero_punto_cero_cinco_sobre_toda_la_tienda(): void
    {
        $empleado = $this->empleado(NominaEmpleado::COMISION_SUPERVISOR_SEDE, 'SUP-001', true);
        $periodo = $this->periodo();
        $this->venta('SUP-001', 400);
        $this->documento('CENTRO', 400);
        $this->venta('OTRO-001', 600);
        $this->documento('CENTRO', 600);

        $resultado = app(CommissionCalculationService::class)->calcular($periodo, $empleado);

        $this->assertSame(0.5, $resultado['total']);
        $this->assertSame(1000.0, $resultado['base']);
        $this->assertDatabaseCount('nomina_comision_registros', 1);
    }

    public function test_supervisor_de_sede_usa_venta_neta_con_precio_neto(): void
    {
        $empleado = $this->empleado(NominaEmpleado::COMISION_SUPERVISOR_SEDE, 'SUP-NETO', true);
        $periodo = $this->periodo();
        $this->venta('OTRO-001', 1000, ['precio_neto' => 800]);
        $this->documento('CENTRO', 800);
        $this->venta('OTRO-002', 500, ['precio_neto' => 400]);
        $this->documento('CENTRO', 400);

        $resultado = app(CommissionCalculationService::class)->calcular($periodo, $empleado);

        $this->assertSame(1200.0, $resultado['base']);
        $this->assertSame(0.6, $resultado['total']);
    }

    public function test_supervisor_de_equipo_cobra_solo_ventas_de_subordinados(): void
    {
        $supervisor = $this->empleado(NominaEmpleado::COMISION_SUPERVISOR_EQUIPO, 'MKT-001', true);
        $vendedor = $this->empleado(NominaEmpleado::COMISION_VENTAS_PROPIAS, 'VEND-EQ');
        $vendedor->update(['supervisor_id' => $supervisor->id]);
        $periodo = $this->periodo();
        $this->venta('VEND-EQ', 2000);
        $this->venta('MKT-001', 5000);

        $resultado = app(CommissionCalculationService::class)->calcular($periodo, $supervisor);

        $this->assertSame(2.0, $resultado['total']);
        $this->assertSame(2000.0, $resultado['base']);
    }

    public function test_ventas_propias_usan_precio_neto_cuando_existe(): void
    {
        NominaConfig::put('descuento_venta_pct', 20);
        $empleado = $this->empleado(NominaEmpleado::COMISION_VENTAS_PROPIAS, 'VEND-NETO');
        $periodo = $this->periodo();
        $this->venta('VEND-NETO', 1000, ['precio_neto' => 910.25]);

        $resultado = app(CommissionCalculationService::class)->calcular($periodo, $empleado);

        $this->assertSame(910.25, $resultado['base']);
        $this->assertSame(9.1, $resultado['total']);
    }

    public function test_ventas_propias_ajustan_a_ventas_documentos_cuando_difiere_de_lineas(): void
    {
        $empleado = $this->empleado(NominaEmpleado::COMISION_VENTAS_PROPIAS, 'VEND-DOC');
        $periodo = $this->periodo();
        $otrosId = DB::table('productos')->insertGetId([
            'codigo' => 'P-DOC',
            'nombre' => 'Accesorio',
            'categoria' => 'PERFUMERIA',
            'subcategoria' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->venta('VEND-DOC', 100, [
            'producto_id' => $otrosId,
            'precio_neto' => 80,
            'numero_documento' => 'DOC-1',
        ]);
        $this->documento('CENTRO', 95, 'FAC', 'VEND-DOC', 'DOC-1');

        $resultado = app(CommissionCalculationService::class)->calcular($periodo, $empleado);

        $this->assertSame(95.0, $resultado['base']);
        $this->assertSame(0.95, $resultado['total']);
    }

    public function test_servicio_tecnico_resta_egresos_058_en_usd_del_mismo_periodo(): void
    {
        NominaConfig::put('descuento_venta_pct', 20);
        $empleado = $this->empleado(NominaEmpleado::COMISION_SERVICIO_TECNICO, 'TEC-001', false, true);
        $periodo = $this->periodo();
        $this->venta('TEC-001', 1000, ['nombre_producto' => 'SERVICIO TECNICO']);
        DB::table('flujo_cajas')->insert([
            'fecha' => '2026-08-10',
            'tipo' => 'egreso',
            'tipo_gasto' => '058 - SERVICIO TECNICO (GARANTIAS)',
            'nomina_empleado_id' => $empleado->id,
            'monto_usd' => 300,
            'monto_bs' => 0,
            'tasa_cambio' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultado = app(CommissionCalculationService::class)->calcular($periodo, $empleado);

        $this->assertSame(800.0, $resultado['ventas_st']);
        $this->assertSame(500.0, $resultado['base_st']);
        $this->assertSame(300.0, $resultado['gastos']);
        $this->assertSame(250.0, $resultado['total']);
    }

    public function test_servicio_tecnico_aplica_058_solo_a_st_y_el_resto_como_vendedor(): void
    {
        NominaConfig::put('descuento_venta_pct', 0);
        $empleado = $this->empleado(NominaEmpleado::COMISION_SERVICIO_TECNICO, 'TEC-MIX', false, true);
        $periodo = $this->periodo();
        $otrosId = DB::table('productos')->insertGetId([
            'codigo' => 'P-MORRAL',
            'nombre' => 'Morral',
            'categoria' => 'PERFUMERIA',
            'subcategoria' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->venta('TEC-MIX', 304, ['nombre_producto' => 'SERVICIO TECNICO', 'precio_neto' => 304]);
        $this->venta('TEC-MIX', 49.76, [
            'producto_id' => $otrosId,
            'nombre_producto' => 'MORRAL 2EN1 NINA',
        ]);
        DB::table('flujo_cajas')->insert([
            'fecha' => '2026-08-10',
            'tipo' => 'egreso',
            'tipo_gasto' => '058 - SERVICIO TECNICO (GARANTIAS)',
            'nomina_empleado_id' => $empleado->id,
            'monto_usd' => 58.39,
            'monto_bs' => 0,
            'tasa_cambio' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultado = app(CommissionCalculationService::class)->calcular($periodo, $empleado);

        $this->assertSame(304.0, $resultado['ventas_st']);
        $this->assertSame(58.39, $resultado['gastos']);
        $this->assertSame(245.61, $resultado['base_st']);
        $this->assertSame(122.81, $resultado['comision_st']);
        $this->assertSame(49.76, $resultado['base_otros']);
        $this->assertSame(0.5, $resultado['comision_otros']);
        $this->assertSame(123.31, $resultado['total']);
    }

    public function test_liquidacion_suma_abono_aplica_retencion_y_no_usa_neto(): void
    {
        $empleado = $this->empleado(NominaEmpleado::COMISION_VENTAS_PROPIAS, 'VEND-LIQ');
        $periodo = $this->periodo();
        $this->venta('VEND-LIQ', 1000);
        NominaComisionAbono::create([
            'empleado_id' => $empleado->id,
            'fecha' => '2026-08-10',
            'monto' => 20,
            'estado' => 'PENDIENTE',
        ]);

        $calculo = app(CommissionCalculationService::class)->calcular($periodo, $empleado);
        $liq = app(CommissionSettlementService::class)->liquidar($periodo, $empleado, $calculo, 5);

        $this->assertSame(10.0, (float) $liq->comision_total);
        $this->assertSame(20.0, (float) $liq->abonos);
        $this->assertSame(3.0, (float) $liq->retencion);
        $this->assertSame(5.0, (float) $liq->prestamos);
        $this->assertSame(22.0, (float) $liq->total_pagar);
    }

    public function test_no_liquida_comision_a_inactivos_ni_sin_comision(): void
    {
        $sinComision = $this->empleado(NominaEmpleado::COMISION_NINGUNA, 'SIN-001');
        $inactivo = $this->empleado(NominaEmpleado::COMISION_VENTAS_PROPIAS, 'INA-001');
        $inactivo->update(['estado' => 'INACTIVO']);
        $activo = $this->empleado(NominaEmpleado::COMISION_VENTAS_PROPIAS, 'ACT-001');
        $periodo = $this->periodo();
        $this->venta('SIN-001', 900);
        $this->venta('INA-001', 800);
        $this->venta('ACT-001', 700);

        app(\App\Services\Nomina\PayrollPeriodService::class)->calcular($periodo);

        $this->assertDatabaseHas('nomina_registros', ['empleado_id' => $sinComision->id, 'periodo_id' => $periodo->id]);
        $this->assertDatabaseMissing('nomina_registros', ['empleado_id' => $inactivo->id, 'periodo_id' => $periodo->id]);
        $this->assertDatabaseMissing('nomina_liquidaciones_comision', ['empleado_id' => $sinComision->id, 'periodo_id' => $periodo->id]);
        $this->assertDatabaseMissing('nomina_liquidaciones_comision', ['empleado_id' => $inactivo->id, 'periodo_id' => $periodo->id]);
        $this->assertDatabaseHas('nomina_liquidaciones_comision', ['empleado_id' => $activo->id, 'periodo_id' => $periodo->id]);
        $this->assertSame(700.0, (float) \App\Models\Nomina\NominaLiquidacionComision::query()->where('empleado_id', $activo->id)->value('base_total'));
    }

    public function test_txt_de_comisiones_es_por_empresa(): void
    {
        $empresa = \App\Models\Nomina\NominaEmpresa::create([
            'codigo' => 'EMP1',
            'nombre' => 'Empresa 1',
            'estado' => 'ACTIVO',
        ]);
        $empleado = $this->empleado(NominaEmpleado::COMISION_VENTAS_PROPIAS, 'TXT-001');
        $empleado->update(['empresa_id' => $empresa->id]);
        $periodo = $this->periodo();
        $this->venta('TXT-001', 1000);
        app(\App\Services\Nomina\PayrollPeriodService::class)->calcular($periodo);

        $bank = app(\App\Services\Nomina\PayrollBankFileService::class);
        $txt = $bank->generarComisiones($periodo, $empresa, 100, '2026-08-18');

        $this->assertStringContainsString('V', $txt);
        $this->assertSame('comision_EMP1_'.$periodo->id.'_20260818.txt', $bank->nombreArchivoComisiones($periodo, $empresa, '2026-08-18'));
        $resumen = $bank->resumenComisionesPorEmpresa($periodo->load('liquidacionesComision.empleado.empresa'));
        $this->assertSame(1, $resumen->count());
        $this->assertSame('EMP1', $resumen->first()->empresa->codigo);
    }

    private function empleado(string $modo, string $codigo, bool $supervisor = false, bool $tecnico = false): NominaEmpleado
    {
        $sede = NominaSede::query()->firstOrCreate(
            ['codigo' => 'CENTRO'],
            ['nombre' => 'Centro', 'tipo' => 'SEDE', 'estado' => 'ACTIVO', 'excluir_comision' => false]
        );
        $cliente = Cliente::create([
            'cedula' => 'V-'.uniqid(),
            'nombre' => 'Empleado '.uniqid(),
        ]);

        return NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'sede_id' => $sede->id,
            'sede' => $sede->codigo,
            'estado' => 'ACTIVO',
            'salario_base' => 0,
            'tipo_salario' => 'SOLO_COMISION',
            'codigo_vendedor' => $codigo,
            'modo_comision' => $modo,
            'es_supervisor' => $supervisor,
            'es_servicio_tecnico' => $tecnico,
        ])->load('sedeCatalogo');
    }

    private function periodo(): NominaPeriodo
    {
        return NominaPeriodo::create([
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-08-15',
            'fecha_pago_comision' => '2026-08-18',
            'etiqueta' => '01/08/2026 al 15/08/2026',
            'estado' => NominaPeriodo::ABIERTO,
        ]);
    }

    private function venta(string $vendedor, float $monto, array $extra = []): int
    {
        return DB::table('ventas_detalle')->insertGetId($extra + [
            'sede' => 'CENTRO',
            'tipo_documento' => 'FAC',
            'numero_documento' => (string) random_int(10000, 99999),
            'fecha' => '2026-08-10',
            'cantidad' => 1,
            'precio_venta' => $monto,
            'costo_unitario' => 0,
            'ganancia' => $monto,
            'vendedor' => $vendedor,
            'anulado' => false,
        ]);
    }

    private function documento(string $sede, float $monto, string $tipo = 'FAC', ?string $vendedor = null, ?string $numero = null): void
    {
        DB::table('ventas_documentos')->insert([
            'sede' => $sede,
            'tipo_documento' => $tipo,
            'numero_documento' => $numero ?? (string) random_int(10000, 99999),
            'fecha' => '2026-08-10',
            'estado' => 'registrado',
            'total_neto_bs' => 0,
            'total_neto_usd' => $monto,
            'vendedor' => $vendedor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
