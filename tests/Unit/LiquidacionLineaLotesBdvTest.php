<?php

namespace Tests\Unit;

use App\Services\BankReconciliationMatcher;
use Tests\TestCase;

class LiquidacionLineaLotesBdvTest extends TestCase
{
    public function test_abono_bdv_liq_linea_lotes_es_un_lote_del_punto(): void
    {
        $matcher = app(BankReconciliationMatcher::class);

        $this->assertTrue($matcher->esLiquidacionPuntoVenta('ABONO BDV BDV LIQ LINEA LOTES'));
        $this->assertTrue($matcher->esAbonoLotePuntoVenta('ABONO BDV BDV LIQ LINEA LOTES', '0000123'));
        $this->assertFalse($matcher->esLiquidacionPuntoVenta('ABONO BDV PAGO MOVIL'));
    }
}
