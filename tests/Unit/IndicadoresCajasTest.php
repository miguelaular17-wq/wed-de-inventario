<?php

namespace Tests\Unit;

use App\Services\IndicadoresOperativosService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class IndicadoresCajasTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        foreach (['ventas_detalle', 'ventas_documentos'] as $tabla) {
            if (! Schema::hasColumn($tabla, 'cajera')) {
                Schema::table($tabla, fn (Blueprint $t) => $t->string('cajera')->nullable());
            }
        }
        if (! Schema::hasColumn('ventas_documentos', 'estacion')) {
            Schema::table('ventas_documentos', fn (Blueprint $t) => $t->string('estacion', 128)->nullable());
        }
        if (! Schema::hasTable('venta_diaria_reportes')) {
            Schema::create('venta_diaria_reportes', function (Blueprint $t) {
                $t->id();
                $t->string('sede', 16);
                $t->date('fecha');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('venta_diaria_cajas')) {
            Schema::create('venta_diaria_cajas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('reporte_id');
                $t->string('nombre');
                $t->decimal('fact_credito', 14, 2)->default(0);
                $t->timestamps();
            });
        }
        $reporte = DB::table('venta_diaria_reportes')->insertGetId(['sede' => 'DORAL', 'fecha' => '2026-10-17', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('venta_diaria_cajas')->insert([
            ['reporte_id' => $reporte, 'nombre' => 'ariamna', 'fact_credito' => 6.68, 'created_at' => now(), 'updated_at' => now()],
            // De otra cajera: baja el total de la tienda, no la venta de Ariamna.
            ['reporte_id' => $reporte, 'nombre' => 'jorgelis', 'fact_credito' => 20, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('clientes')->insert(['id' => 9301, 'nombre' => 'ARIAMNA REYES', 'cedula' => 'V-9301', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('nomina_empleados')->insert([
            'cliente_id' => 9301,
            'cargo' => 'Cajera',
            'sede' => 'DORAL',
            'estado' => 'ACTIVO',
            'salario_base' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ventas_documentos')->insert([
            ['sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => 'F-1', 'fecha' => '2026-10-16', 'cajera' => 'ARIAMNA REYES', 'created_at' => now(), 'updated_at' => now()],
            ['sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => 'F-2', 'fecha' => '2026-10-17', 'cajera' => 'ARIAMNA REYES', 'created_at' => now(), 'updated_at' => now()],
            ['sede' => 'DORAL', 'tipo_documento' => 'DEV', 'numero_documento' => 'D-1', 'fecha' => '2026-10-28', 'cajera' => null, 'created_at' => now(), 'updated_at' => now()],
            ['sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => 'T-1', 'fecha' => '2026-10-24', 'cajera' => 'ARIAMNA REYES', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('ventas_documentos')->where('numero_documento', 'T-1')->update(['estacion' => 'Tesoreria']);
        DB::table('ventas_documentos')->where('numero_documento', '!=', 'T-1')->update(['estacion' => 'STI-CAJA3']);
        $linea = fn (string $tipo, string $num, string $fecha, float $cant, float $precio, ?string $cajera, ?string $origen = null) => [
            'sede' => 'DORAL', 'tipo_documento' => $tipo, 'numero_documento' => $num, 'item_numero' => 1, 'fecha' => $fecha,
            'cantidad' => $cant, 'precio_venta' => $precio, 'precio_neto' => $precio, 'cliente' => 'CLIENTE '.$num,
            'cajera' => $cajera, 'factura_origen' => $origen, 'anulado' => false,
        ];
        DB::table('ventas_detalle')->insert([
            $linea('FAC', 'F-1', '2026-10-16', 2, 50, 'ARIAMNA REYES'),
            $linea('FAC', 'F-2', '2026-10-17', 1, 106.68, 'ARIAMNA REYES'),
            // Factura duplicada devuelta sin cajera en el sistema de facturación.
            $linea('DEV', 'D-1', '2026-10-28', 1, 106.68, null, 'F-2'),
            // Hecha desde Tesorería con su usuario: no pasa por su caja.
            $linea('FAC', 'T-1', '2026-10-24', 1, 3430.89, 'ARIAMNA REYES'),
        ]);
    }

    public function test_devolucion_sin_cajera_se_resta_a_la_cajera_de_la_factura_original(): void
    {
        $data = app(IndicadoresOperativosService::class)->cajas(Carbon::parse('2026-10-16'), Carbon::parse('2026-10-28'), null);
        $fila = collect($data['filas'])->firstWhere('nombre', 'ARIAMNA REYES');

        $this->assertNotNull($fila);
        // 100 + 106,68 − 106,68 de la devolución − 6,68 de crédito; Tesorería no cuenta.
        $this->assertEqualsWithDelta(93.32, $fila['monto'], 0.01);
        // Tienda: lo mismo menos también el crédito de la otra cajera.
        $this->assertEqualsWithDelta(73.32, $fila['meta_venta'], 0.01);
        $this->assertSame('ARIAMNA REYES', $fila['cajera_pos']);
        $this->assertSame('DORAL', $fila['sede_codigo']);
    }

    public function test_detalle_lista_facturas_y_devoluciones_por_dia(): void
    {
        $detalle = app(IndicadoresOperativosService::class)->documentosCajera(
            Carbon::parse('2026-10-16'),
            Carbon::parse('2026-10-28'),
            'ariamna reyes',
            'DORAL'
        );

        $this->assertSame(2, $detalle['total']['facturas']);
        $this->assertSame(1, $detalle['total']['devoluciones']);
        $this->assertEqualsWithDelta(6.68, $detalle['total']['credito'], 0.01);
        $this->assertEqualsWithDelta(93.32, $detalle['total']['monto'], 0.01);
        $this->assertCount(3, $detalle['dias']);
        $dia17 = collect($detalle['dias'])->firstWhere('fecha', '2026-10-17');
        $this->assertEqualsWithDelta(6.68, $dia17['credito'], 0.01);
        $this->assertEqualsWithDelta(100.0, $dia17['monto'], 0.01);

        $dev = collect($detalle['documentos'])->firstWhere('tipo', 'DEV');
        $this->assertTrue($dev['cajera_de_origen']);
        $this->assertSame('F-2', $dev['factura_origen']);
        $this->assertEqualsWithDelta(-106.68, $dev['monto'], 0.01);
    }
}
