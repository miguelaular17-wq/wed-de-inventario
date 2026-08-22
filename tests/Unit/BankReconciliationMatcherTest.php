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
        $this->assertTrue($this->matcher->haystackTieneLote($this->matcher->textoBanco($linea), 'L.57'));
        $this->assertTrue($this->matcher->haystackTieneLote('TMD L.58 EMBUTIDOS', '58'));
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

    public function test_parte_cuenta_separa_banco_y_titular(): void
    {
        [$banco, $titular] = $this->matcher->partesCuenta('BANESCO DORAL', null);
        $this->assertSame('BANESCO', $banco);
        $this->assertSame('DORAL', $titular);

        [$banco2, $titular2] = $this->matcher->partesCuenta('PROVINCIAL JRZ', '');
        $this->assertSame('PROVINCIAL', $banco2);
        $this->assertSame('JRZ', $titular2);
    }
}
