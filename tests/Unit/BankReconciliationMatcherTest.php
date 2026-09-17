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

    public function test_traslado_exige_monto_y_ultimos_cuatro_digitos_correctos(): void
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
        $montoIncorrecto = new ConciliacionLinea([
            'banco' => 'MERCANTIL',
            'titular' => 'JRZ',
            'fecha' => '2026-09-08',
            'referencia' => '8765',
            'monto' => 12500.76,
            'tipo' => 'abono',
        ]);
        $referenciaIncorrecta = new ConciliacionLinea([
            'banco' => 'MERCANTIL',
            'titular' => 'JRZ',
            'fecha' => '2026-09-08',
            'referencia' => '8764',
            'monto' => 12500.75,
            'tipo' => 'abono',
        ]);
        $salidaIncompleta = new ConciliacionLinea([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-09-08',
            'referencia' => '8765',
            'monto' => -12500.75,
            'tipo' => 'cargo',
        ]);

        $this->assertFalse($this->matcher->coincideTraslado($montoIncorrecto, $traslado));
        $this->assertFalse($this->matcher->coincideTraslado($referenciaIncorrecta, $traslado));
        $this->assertFalse($this->matcher->coincideTraslado($salidaIncompleta, $traslado));
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
