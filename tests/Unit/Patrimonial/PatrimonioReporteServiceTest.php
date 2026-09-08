<?php

namespace Tests\Unit\Patrimonial;

use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Propiedad;
use App\Services\Patrimonial\PatrimonioReporteService;
use Tests\Concerns\CreatesPatrimonialSchema;
use Tests\TestCase;

class PatrimonioReporteServiceTest extends TestCase
{
    use CreatesPatrimonialSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPatrimonialSchema();
    }

    public function test_mensual_desglosa_recibido_gastos_comision_y_neto(): void
    {
        $salamar = Propiedad::create([
            'codigo' => 'SAL',
            'nombre' => 'Apartamentos Salamar',
            'tipo' => 'apartamento',
            'estado' => 'alquilado',
        ]);
        $otra = Propiedad::create([
            'codigo' => 'OTR',
            'nombre' => 'Local Centro',
            'tipo' => 'local',
            'estado' => 'alquilado',
        ]);

        PatTransaccion::create($this->tx($salamar->id, 'gasto', 'Condominio', 200, '2026-09-05'));
        PatTransaccion::create($this->tx($salamar->id, 'comision', 'Comisión administración', 60, '2026-09-05'));
        PatTransaccion::create($this->tx($otra->id, 'ingreso', 'Alquiler', 1150, '2026-09-01'));
        PatTransaccion::create($this->tx($otra->id, 'gasto', 'Mantenimiento', 100, '2026-09-02'));
        PatTransaccion::create($this->tx($otra->id, 'gasto', 'Condominio', 50, '2026-09-03'));
        PatTransaccion::create($this->tx($salamar->id, 'ingreso', 'Alquiler', 400, '2026-08-10'));

        $data = app(PatrimonioReporteService::class)->mensual(9, 2026);

        $this->assertEquals(1150.0, $data['totales']['ingresos']);
        $this->assertEquals(350.0, $data['totales']['gastos']);
        $this->assertEquals(60.0, $data['totales']['comisiones']);
        $this->assertEquals(740.0, $data['totales']['balance']);

        $this->assertEquals(
            ['Apartamentos Salamar', 'Local Centro'],
            array_column($data['ingresosPorPropiedad'], 'nombre')
        );
        $this->assertEquals(0.0, collect($data['ingresosPorPropiedad'])->firstWhere('nombre', 'Apartamentos Salamar')['monto']);
        $this->assertEquals(250.0, collect($data['gastosPorCategoria'])->firstWhere('categoria', 'Condominio')['monto']);
        $this->assertEquals(100.0, collect($data['gastosPorCategoria'])->firstWhere('categoria', 'Mantenimiento')['monto']);
        $this->assertNull(collect($data['gastosPorCategoria'])->firstWhere('categoria', 'Limpieza'));

        $salamarFila = $data['filas']->firstWhere('codigo', 'SAL');
        $this->assertEquals(-260.0, $salamarFila['balance']);
        $this->assertEquals([['categoria' => 'Condominio', 'monto' => 200.0]], $salamarFila['gastosPorCategoria']);
    }

    public function test_propiedad_separa_resumen_del_mes_del_acumulado(): void
    {
        $prop = Propiedad::create([
            'codigo' => 'SAL',
            'nombre' => 'Apartamentos Salamar',
            'tipo' => 'apartamento',
            'estado' => 'alquilado',
        ]);
        PatTransaccion::create($this->tx($prop->id, 'ingreso', 'Alquiler', 1254.15, '2026-08-12'));
        PatTransaccion::create($this->tx($prop->id, 'gasto', 'Condominio', 200, '2026-09-05'));
        PatTransaccion::create($this->tx($prop->id, 'comision', 'Comisión administración', 60, '2026-09-05'));

        $data = app(PatrimonioReporteService::class)->propiedad($prop, 9, 2026, 2026, 2026);

        $this->assertEquals(0.0, $data['mesResumen']['ingresos']);
        $this->assertEquals(200.0, $data['mesResumen']['gastos']);
        $this->assertEquals(60.0, $data['mesResumen']['comisiones']);
        $this->assertEquals(-260.0, $data['mesResumen']['balance']);
        $this->assertEquals(1254.15, $data['totales']['ingresos']);
        $this->assertEquals(200.0, $data['totales']['gastos']);
        $this->assertEquals(2, $data['historial']->count());
    }

    public function test_mensual_incluye_comision_de_reserva_pagada_y_neto(): void
    {
        $prop = Propiedad::create([
            'codigo' => 'EMI',
            'nombre' => 'Casa Doña Emilia',
            'tipo' => 'casa',
            'estado' => 'disponible',
        ]);
        $reserva = \App\Models\Patrimonial\Reserva::create([
            'propiedad_id' => $prop->id,
            'cliente_nombre' => 'Miguel Aular',
            'fecha_entrada' => '2026-10-01',
            'fecha_salida' => '2026-10-05',
            'precio_noche' => 125,
            'comision' => 100,
            'moneda' => 'usd',
            'estado' => 'confirmada',
        ]);
        $reserva->pagos()->create([
            'monto_pagado' => 500,
            'forma_pago' => 'Zelle',
            'fecha_pago' => '2026-09-08',
        ]);
        PatTransaccion::create($this->tx($prop->id, 'ingreso', 'Reserva temporal', 500, '2026-09-08') + ['reserva_id' => $reserva->id]);
        PatTransaccion::create($this->tx($prop->id, 'comision', 'Comisión administración', 45, '2026-09-07'));

        $data = app(PatrimonioReporteService::class)->mensual(9, 2026, true);
        $fila = $data['filas']->firstWhere('codigo', 'EMI');

        $this->assertEquals(500.0, $fila['ingresos']);
        $this->assertEquals(145.0, $fila['comisiones']);
        $this->assertEquals(355.0, $fila['balance']);

        $ingreso = $fila['transacciones']->firstWhere('tipo', 'ingreso');
        $this->assertEquals(400.0, $ingreso->neto_reserva);
        $this->assertEquals(355.0, $fila['transacciones']->last()->neto_acumulado);
    }

    /**
     * @return array<string, mixed>
     */
    private function tx(int $propiedadId, string $tipo, string $categoria, float $monto, string $fecha): array
    {
        $d = \Carbon\Carbon::parse($fecha);

        return [
            'propiedad_id' => $propiedadId,
            'tipo' => $tipo,
            'categoria' => $categoria,
            'monto' => $monto,
            'moneda' => 'usd',
            'mes' => $d->month,
            'anio' => $d->year,
            'fecha' => $fecha,
        ];
    }
}
