<?php

namespace Tests\Unit;

use App\Services\GerencialAbcService;
use Tests\TestCase;

class GerencialAbcServiceTest extends TestCase
{
    public function test_clasifica_80_15_5_por_unidades(): void
    {
        $items = collect([
            (object) ['nombre' => 'A1', 'unidades' => 80],
            (object) ['nombre' => 'B1', 'unidades' => 15],
            (object) ['nombre' => 'C1', 'unidades' => 5],
        ]);
        $abc = app(GerencialAbcService::class);
        $out = $abc->clasificar($items, 'unidades');

        $this->assertSame('A', $out[0]->abc);
        $this->assertSame('B', $out[1]->abc);
        $this->assertSame('C', $out[2]->abc);
        $this->assertEquals(80.0, $out[0]->pct);
        $this->assertEquals(95.0, $out[1]->pct_acum);

        $resumen = $abc->resumen($out);
        $this->assertSame(1, $resumen['A']['productos']);
        $this->assertSame(1, $resumen['B']['productos']);
        $this->assertSame(1, $resumen['C']['productos']);
    }

    public function test_ceros_van_a_clase_c(): void
    {
        $out = app(GerencialAbcService::class)->clasificar(collect([
            (object) ['nombre' => 'Top', 'unidades' => 10],
            (object) ['nombre' => 'Cero', 'unidades' => 0],
        ]), 'unidades');

        $this->assertSame('A', $out[0]->abc);
        $this->assertSame('C', $out[1]->abc);
    }
}
