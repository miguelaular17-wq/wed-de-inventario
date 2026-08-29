<?php

namespace Tests\Unit;

use App\Models\Contrato;
use App\Models\ContratoCuota;
use Tests\TestCase;

class ContratoCuotaMontoVisibleTest extends TestCase
{
    public function test_sin_comision_muestra_el_total_de_la_deuda(): void
    {
        $contrato = new Contrato([
            'interes_porcentaje' => 0,
            'cuota_fija' => 0,
            'total_a_pagar' => 2500,
            'activo' => true,
        ]);
        $cuota = new ContratoCuota(['saldo' => 0, 'monto' => 0]);
        $cuota->setRelation('contrato', $contrato);

        $this->assertSame(2500.0, $cuota->montoVisible());
    }

    public function test_con_comision_muestra_el_saldo_de_la_cuota(): void
    {
        $contrato = new Contrato([
            'interes_porcentaje' => 0.06,
            'cuota_fija' => 360,
            'total_a_pagar' => 6000,
            'activo' => true,
        ]);
        $cuota = new ContratoCuota(['saldo' => 360, 'monto' => 360]);
        $cuota->setRelation('contrato', $contrato);

        $this->assertSame(360.0, $cuota->montoVisible());
    }

    public function test_contrato_liquidado_se_considera_cerrado(): void
    {
        $liquidado = new Contrato(['estado' => 'liquidado', 'activo' => false]);
        $activo = new Contrato(['estado' => 'activo', 'activo' => true]);

        $this->assertTrue($liquidado->esLiquidado());
        $this->assertFalse($activo->esLiquidado());
    }
}
