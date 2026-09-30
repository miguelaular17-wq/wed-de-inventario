<?php

namespace Tests\Unit;

use App\Services\Nomina\ComisionMarcaService;
use Tests\TestCase;

class ComisionMarcaServiceTest extends TestCase
{
    public function test_promedia_la_tasa_del_dia_y_pasa_bs_a_usd(): void
    {
        $servicio = new ComisionMarcaService;

        $tasa = $servicio->promedio(collect([782.62, 782.62]));

        $this->assertEquals(782.62, $tasa);
        $this->assertEquals(1.46, $servicio->aUsd(1142.62, $tasa));
    }

    public function test_sin_tasa_no_convierte(): void
    {
        $servicio = new ComisionMarcaService;

        $this->assertNull($servicio->promedio(collect([0, null])));
        $this->assertNull($servicio->aUsd(100, null));
    }
}
