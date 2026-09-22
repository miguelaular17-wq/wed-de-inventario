<?php

namespace Tests\Unit;

use App\Models\ConciliacionLinea;
use App\Services\BankReconciliationMatcher;
use PHPUnit\Framework\TestCase;

class BankReconciliationMatcherTest extends TestCase
{
    private BankReconciliationMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new BankReconciliationMatcher();
    }

    public function test_encuentra_lote_de_punto_en_la_descripcion_del_banco(): void
    {
        $linea = new ConciliacionLinea([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-03',
            'referencia' => '99',
            'descripcion' => 'TDB CAPIT. 123 L.000095 30687',
            'monto' => 26357.10,
            'tipo' => 'abono',
        ]);
        $lote = (object) [
            'tipo' => 'punto_venta',
            'banco' => 'Banesco',
            'titular' => 'Doral',
            'fecha' => '2026-08-03',
            'monto' => 26357.10,
            'lote_referencia' => '95',
        ];

        $this->assertTrue($this->matcher->coincideLotePunto($linea, $lote));
        $this->assertTrue($this->matcher->haystackTieneLote($this->matcher->textoBanco($linea), '95'));
        $this->assertTrue($this->matcher->haystackTieneLote('TMD L.58 EMBUTIDOS', '58'));
    }

    public function test_bnc_lote_en_referencia_con_pos_en_descripcion(): void
    {
        $linea = new ConciliacionLinea([
            'banco' => 'BNC',
            'titular' => 'GRUPO JRZ',
            'fecha' => '2026-08-23',
            'referencia' => '487',
            'descripcion' => 'POS: 860963370 GRUPO JRZ TECH ELECTRONIC FECHA:23/08/2026',
            'monto' => 12554.56,
            'tipo' => 'abono',
        ]);
        $lote = (object) [
            'tipo' => 'punto_venta',
            'banco' => 'BNC',
            'titular' => 'JRZ',
            'fecha' => '2026-08-23',
            'monto' => 12554.56,
            'lote_referencia' => '0487',
        ];

        $this->assertTrue($this->matcher->mismoTitular('GRUPO JRZ', 'JRZ', 'BNC', 'BNC'));
        $this->assertTrue($this->matcher->loteIgualReferencia('487', '0487'));
        $this->assertTrue($this->matcher->haystackTieneLote($this->matcher->textoBanco($linea), '0487'));
        $this->assertTrue($this->matcher->coincideLotePunto($linea, $lote));
        // El terminal POS no es el lote
        $this->assertFalse($this->matcher->loteIgualReferencia('487', '860963370'));
    }

    public function test_lote_tesoreria_banesco_doral_sin_monto_exacto(): void
    {
        $linea = new ConciliacionLinea([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-03',
            'referencia' => '58',
            'descripcion' => 'TDB CAPIT. 0052320997 L.000058 30687',
            'monto' => 117675.46,
            'tipo' => 'abono',
        ]);
        $lote = (object) [
            'tipo' => 'punto_venta',
            'banco' => 'BANESCO DORAL',
            'titular' => null,
            'fecha' => '2026-08-01',
            'monto' => 126817,
            'lote_referencia' => '000058',
        ];

        $this->assertTrue($this->matcher->mismoBanco('BANESCO', 'BANESCO DORAL'));
        $this->assertTrue($this->matcher->coincideLotePunto($linea, $lote));
    }

    public function test_egreso_por_referencia_aunque_el_monto_bs_difiera(): void
    {
        $linea = new ConciliacionLinea([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-04',
            'referencia' => '5003740987',
            'descripcion' => 'TodoTicket 2004 C.A.',
            'monto' => 44759.17,
            'tipo' => 'cargo',
        ]);
        $flujo = (object) [
            'banco' => 'Banesco',
            'titular' => 'Doral',
            'fecha' => '2026-08-04',
            'monto_bs' => 37757.50,
            'referencia' => '5003740987',
            'motivo' => 'ANTICIPO DE NOMINA MARIA POLANCO',
        ];

        $this->assertTrue($this->matcher->coincideEgreso($linea, $flujo));
    }

    public function test_concilia_egreso_por_monto_banco_y_referencia(): void
    {
        $linea = new ConciliacionLinea([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-06',
            'referencia' => '5003745290',
            'descripcion' => 'PAGO PROVEEDOR',
            'monto' => 30268,
            'tipo' => 'cargo',
        ]);
        $flujo = (object) [
            'banco' => 'Banesco',
            'titular' => 'Doral',
            'fecha' => '2026-08-06',
            'monto_bs' => 30268.00,
            'monto_usd' => 30,
            'referencia' => '5003745290',
            'concepto' => 'ANTICIPO DE NOMINA ANDRES QUEVEDO',
            'motivo' => '073 - ANTICIPO NOMINAS BOLIVARES',
        ];

        $this->assertTrue($this->matcher->coincideEgreso($linea, $flujo));
    }

    public function test_concilia_egreso_aunque_el_monto_usd_no_coincida(): void
    {
        $linea = new ConciliacionLinea([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-08',
            'referencia' => '5003751137',
            'descripcion' => 'ANTICIPO',
            'monto' => 11465.10,
            'tipo' => 'cargo',
        ]);
        $flujo = (object) [
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-08',
            'monto_bs' => 11465.10,
            'monto_usd' => 12.50,
            'referencia' => '1137',
            'concepto' => 'ANTICIPO DE NOMINA ALEJANDRA SALAS 15S',
            'motivo' => null,
        ];

        $this->assertTrue($this->matcher->coincideEgreso($linea, $flujo));
    }

    public function test_concilia_los_dos_lados_de_un_traslado_bancario(): void
    {
        $traslado = (object) [
            'categoria_egreso' => 'traslados',
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'banco_receptor' => 'MERCANTIL',
            'titular_receptor' => 'JRZ',
            'fecha' => '2026-09-08',
            'monto_bs' => 12500.75,
            'referencia' => '5003998765',
        ];
        $salida = new ConciliacionLinea([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-09-08',
            'referencia' => '5003998765',
            'monto' => -12500.75,
            'tipo' => 'cargo',
        ]);
        $entrada = new ConciliacionLinea([
            'banco' => 'MERCANTIL',
            'titular' => 'JRZ',
            'fecha' => '2026-09-08',
            'referencia' => '8765',
            'monto' => 12500.75,
            'tipo' => 'abono',
        ]);

        $this->assertSame('salida', $this->matcher->ladoTraslado($salida, $traslado));
        $this->assertSame('entrada', $this->matcher->ladoTraslado($entrada, $traslado));
        $this->assertTrue($this->matcher->coincideTraslado($salida, $traslado));
        $this->assertTrue($this->matcher->coincideTraslado($entrada, $traslado));
    }

    public function test_traslado_exige_monto_correcto_y_permite_sin_referencia_mismo_dia(): void
    {
        $traslado = (object) [
            'categoria_egreso' => 'traslados',
            'banco' => 'BNC',
            'titular' => 'JRZ',
            'banco_receptor' => 'MERCANTIL',
            'titular_receptor' => 'JENU',
            'fecha' => '2026-08-07',
            'monto_bs' => 3783550.00,
            'referencia' => '96212757',
        ];
        $montoIncorrecto = new ConciliacionLinea([
            'banco' => 'BNC',
            'titular' => 'JRZ',
            'fecha' => '2026-08-07',
            'referencia' => '999',
            'monto' => -3783550.01,
            'tipo' => 'cargo',
        ]);
        // Extracto BNC: misma fecha/monto, referencia distinta → concilia por monto
        $salidaSoloMonto = new ConciliacionLinea([
            'banco' => 'BNC',
            'titular' => 'JRZ',
            'fecha' => '2026-08-07',
            'referencia' => '88445566',
            'monto' => -3783550.00,
            'tipo' => 'cargo',
        ]);
        $otraFecha = new ConciliacionLinea([
            'banco' => 'BNC',
            'titular' => 'JRZ',
            'fecha' => '2026-08-09',
            'referencia' => '88445566',
            'monto' => -3783550.00,
            'tipo' => 'cargo',
        ]);

        $this->assertFalse($this->matcher->coincideTraslado($montoIncorrecto, $traslado));
        $this->assertTrue($this->matcher->coincideTraslado($salidaSoloMonto, $traslado));
        $this->assertFalse($this->matcher->coincideTraslado($otraFecha, $traslado));
    }

    public function test_venezuela_egreso_concilia_monto_neto_comision_2_porciento(): void
    {
        // BDD: bruto 100000; extracto BDV: 98000 (neto −2%)
        $linea = new ConciliacionLinea([
            'banco' => 'VENEZUELA',
            'titular' => 'GRUPO JRZ',
            'fecha' => '2026-08-14',
            'referencia' => '0429716928121',
            'descripcion' => 'PAGO A OTROS BANCOS 0134 V11767394',
            'monto' => -98000.00,
            'tipo' => 'cargo',
        ]);
        $flujo = (object) [
            'banco' => 'Venezuela',
            'titular' => 'Grupo JRZ',
            'fecha' => '2026-08-14',
            'monto_bs' => 100000.00,
            'referencia' => 'otro-ref',
            'motivo' => '049 - INSUMOS MANTENIMIENTO',
            'categoria_egreso' => 'egreso_realizado',
        ];

        $this->assertTrue($this->matcher->esBancoVenezuela('BANCO DE VENEZUELA'));
        $this->assertTrue($this->matcher->montoLoteNetoBdv(98000, 100000));
        $this->assertTrue($this->matcher->coincideEgreso($linea, $flujo));
    }

    public function test_venezuela_traslado_entrada_neto_2_porciento(): void
    {
        $traslado = (object) [
            'categoria_egreso' => 'traslados',
            'banco' => 'BNC',
            'titular' => 'JRZ',
            'banco_receptor' => 'VENEZUELA',
            'titular_receptor' => 'GRUPO JRZ',
            'fecha' => '2026-08-27',
            'monto_bs' => 4000000.00,
            'referencia' => '97245073',
        ];
        // En Venezuela abona 3.920.000 (bruto − 2%)
        $entrada = new ConciliacionLinea([
            'banco' => 'VENEZUELA',
            'titular' => 'GRUPO JRZ',
            'fecha' => '2026-08-27',
            'referencia' => '5073',
            'monto' => 3920000.00,
            'tipo' => 'abono',
        ]);

        $this->assertTrue($this->matcher->coincideTraslado($entrada, $traslado));
    }

    public function test_parte_cuenta_separa_banco_y_titular(): void
    {
        [$banco, $titular] = $this->matcher->partesCuenta('BANESCO DORAL', null);
        $this->assertSame('BANESCO', $banco);
        $this->assertSame('DORAL', $titular);

        [$banco2, $titular2] = $this->matcher->partesCuenta('PROVINCIAL JRZ', '');
        $this->assertSame('PROVINCIAL', $banco2);
        $this->assertSame('JRZ', $titular2);
    }

    public function test_banco_de_venezuela_se_canoniza_a_venezuela(): void
    {
        [$banco, $titular] = $this->matcher->partesCuenta('BANCO DE VENEZUELA', 'GRUPO JRZ');
        $this->assertSame('VENEZUELA', $banco);
        $this->assertSame('GRUPO JRZ', $titular);

        [$banco2, $titular2] = $this->matcher->partesCuenta('Banco de Venezuela', '');
        $this->assertSame('VENEZUELA', $banco2);
        $this->assertSame('', $titular2);

        $this->assertContains('BANCO DE VENEZUELA', $this->matcher->variantesBanco('VENEZUELA'));
        $this->assertTrue($this->matcher->mismoBanco('VENEZUELA', 'BANCO DE VENEZUELA'));
    }

    public function test_bdv_liq_tarjeta_por_monto_neto_comision_2_porciento(): void
    {
        // Extracto BDV: sin nº de lote; abona lote 45320.13 − 2% = 44413.73 (T+1).
        $linea = new ConciliacionLinea([
            'banco' => 'VENEZUELA',
            'titular' => 'GRUPO JRZ',
            'fecha' => '2026-08-08',
            'referencia' => '1742502407086',
            'descripcion' => 'LIQ.TARJETA DEBITO MAESTRO BDV',
            'monto' => 44413.73,
            'tipo' => 'abono',
        ]);
        $lote = (object) [
            'tipo' => 'punto_venta',
            'banco' => 'VENEZUELA',
            'titular' => 'JRZ',
            'fecha' => '2026-08-07',
            'monto' => 45320.13,
            'lote_referencia' => '121',
        ];

        $this->assertTrue($this->matcher->esLiquidacionPuntoVenta($linea->descripcion));
        $this->assertTrue($this->matcher->montoLoteNetoBdv(44413.73, 45320.13));
        $this->assertTrue($this->matcher->coincideLotePunto($linea, $lote));
    }

    public function test_bdv_no_cruza_pagomovil_con_lote_corto_por_substring(): void
    {
        $linea = new ConciliacionLinea([
            'banco' => 'VENEZUELA',
            'titular' => 'GRUPO JRZ',
            'fecha' => '2026-08-10',
            'referencia' => '0677288021694',
            'descripcion' => 'PAGOMOVIL BDV V019647116 MAURICIO   PUENTE',
            'monto' => 4868.00,
            'tipo' => 'abono',
        ]);
        $lote = (object) [
            'tipo' => 'punto_venta',
            'banco' => 'VENEZUELA',
            'titular' => 'JRZ',
            'fecha' => '2026-08-06',
            'monto' => 30615.98,
            'lote_referencia' => '116',
        ];

        $this->assertFalse($this->matcher->haystackTieneLote($this->matcher->textoBanco($linea), '116'));
        $this->assertFalse($this->matcher->coincideLotePunto($linea, $lote));
    }

    public function test_bdv_pagomovil_no_cruza_con_lote_igual_a_codigo_banco(): void
    {
        // Extracto real: "PAGOMOVIL OTROS BANCOS 0134 …" — 0134 es Banesco, no el lote.
        $linea = new ConciliacionLinea([
            'banco' => 'VENEZUELA',
            'titular' => 'GRUPO JRZ',
            'fecha' => '2026-08-27',
            'referencia' => '0050993242654',
            'descripcion' => 'PAGOMOVIL OTROS BANCOS 0134 04123382151',
            'monto' => 5.00,
            'tipo' => 'abono',
        ]);
        $lote = (object) [
            'tipo' => 'punto_venta',
            'banco' => 'VENEZUELA',
            'titular' => 'JRZ',
            'fecha' => '2026-08-27',
            'monto' => 5.00,
            'lote_referencia' => '0134',
        ];

        $this->assertTrue($this->matcher->esPagoMovil($linea->descripcion));
        $this->assertTrue($this->matcher->esCodigoBancoVenezuela('0134'));
        $this->assertFalse($this->matcher->haystackTieneLote($this->matcher->textoBanco($linea), '0134'));
        $this->assertFalse($this->matcher->coincideLotePunto($linea, $lote));
    }

    public function test_bdv_liq_tarjeta_monto_exacto_sin_lote_en_texto(): void
    {
        $linea = new ConciliacionLinea([
            'banco' => 'VENEZUELA',
            'titular' => 'GRUPO JRZ',
            'fecha' => '2026-08-08',
            'referencia' => '1742502407086',
            'descripcion' => 'LIQ.TARJETA DEBITO MAESTRO BDV',
            'monto' => 85186.98,
            'tipo' => 'abono',
        ]);
        $lote = (object) [
            'tipo' => 'punto_venta',
            'banco' => 'VENEZUELA',
            'titular' => 'JRZ',
            'fecha' => '2026-08-08',
            'monto' => 85186.98,
            'lote_referencia' => '999',
        ];

        $this->assertTrue($this->matcher->coincideLotePunto($linea, $lote));
    }
}
