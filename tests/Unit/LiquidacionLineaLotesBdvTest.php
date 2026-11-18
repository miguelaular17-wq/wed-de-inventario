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

    public function test_td_pos_comercio_es_lote_punto_de_venta(): void
    {
        $matcher = app(BankReconciliationMatcher::class);

        $this->assertTrue($matcher->esLiquidacionPuntoVenta('TD POS J0409254852002'));
        $this->assertTrue($matcher->esAbonoLotePuntoVenta('TD POS J0409254852002', '2815'));
        $this->assertTrue($matcher->esAbonoLotePuntoVenta('TC POS J0409254852002', '2810'));
        $this->assertFalse($matcher->esLiquidacionPuntoVenta('TRANSFERENCIA POSIBLE CLIENTE'));
    }
}
