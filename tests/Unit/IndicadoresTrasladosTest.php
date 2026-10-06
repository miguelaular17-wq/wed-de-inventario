<?php

namespace Tests\Unit;

use App\Services\IndicadoresOperativosService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class IndicadoresTrasladosTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
    }

    public function test_separa_utilidad_y_perdida_por_el_signo(): void
    {
        DB::table('ajustes_inventario')->insert([
            ['sede' => 'DORAL', 'tipo_movimiento' => 'TRA', 'numero_documento' => '1', 'fecha' => '2026-10-02', 'cantidad' => 10, 'costo_unitario' => 5],
            ['sede' => 'DORAL', 'tipo_movimiento' => 'TRA', 'numero_documento' => '2', 'fecha' => '2026-10-03', 'cantidad' => -4, 'costo_unitario' => 5],
            ['sede' => 'DORAL', 'tipo_movimiento' => 'AJU', 'numero_documento' => '3', 'fecha' => '2026-10-03', 'cantidad' => 100, 'costo_unitario' => 9],
            ['sede' => 'CENTRO', 'tipo_movimiento' => 'TRA', 'numero_documento' => '4', 'fecha' => '2026-10-04', 'cantidad' => -2, 'costo_unitario' => 3],
        ]);

        $data = app(IndicadoresOperativosService::class)->traslados(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-05'),
            null
        );

        $this->assertSame(3, $data['kpis']['documentos']);
        $this->assertSame(10.0, $data['kpis']['entrada']);
        $this->assertSame(-6.0, $data['kpis']['salida']);
        $this->assertSame(4.0, $data['kpis']['neto']);
        $this->assertSame(50.0, $data['kpis']['utilidad']);
        $this->assertSame(-26.0, $data['kpis']['perdida']);
        $this->assertSame(24.0, $data['kpis']['valor']);
        $this->assertSame('CENTRO', $data['porSede'][0]['sede']);
        $this->assertSame('DORAL', $data['porSede'][1]['sede']);
    }
}
