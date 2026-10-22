<?php

namespace Tests\Unit;

use App\Services\BankMovementClassifier;
use PHPUnit\Framework\TestCase;

class BankMovementClassifierTest extends TestCase
{
    private BankMovementClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new BankMovementClassifier;
    }

    public function test_detecta_comisiones_del_extracto(): void
    {
        $this->assertTrue($this->classifier->esComision('COM COMPRA DE DIVISAS ICT'));
        $this->assertTrue($this->classifier->esComision('Mant y Serv Aplic 680956790826'));
        $this->assertTrue($this->classifier->esComision('COM TRF CRINMOB'));
        $this->assertTrue($this->classifier->esComision('COMSER VMTTO CTA'));
        $this->assertTrue($this->classifier->esComision('COMSERVMTTO CTA'));
        $this->assertTrue($this->classifier->esComision('COMIS.PAGO INMEDIATO'));
        $this->assertTrue($this->classifier->esComision('COM/LIQ/TDD 87758230'));
        $this->assertTrue($this->classifier->esComision('EMISION DE ESTADO DE CUENTA'));
        $this->assertTrue($this->classifier->esComision('COMIS EMIS EDO DE CUENTAS ME'));
        $this->assertTrue($this->classifier->esComision(
            "TELF.:000000000000\nCED.:J502551352\nCTA.:010500583510584123",
            'VENEZUELA'
        ));
        $this->assertFalse($this->classifier->esComision(
            'TELF.:04141234567 CED.:V12345678 CTA.:010500583510584123',
            'VENEZUELA'
        ));
        $this->assertFalse($this->classifier->esComision('PAGO DE SERVICIOS VIA INTERNET'));
        $this->assertTrue($this->classifier->esComision('SERVICIO USO PUNTO'));
    }

    public function test_bnc_grupo_jrz_no_trata_el_lote_pos_ni_la_transferencia_como_comision(): void
    {
        $this->assertFalse($this->classifier->esComision(
            'COMISION BANCO POS: 860963370 GRUPO JRZ TECH ELECTRONIC LOTE NRO.: 000398 DEPOSITO: 0.00',
            'BNC'
        ));
        $this->assertFalse($this->classifier->esComision(
            'TELF.:000000000000 CED.:J401722296 CTA.:01140251812510078090 CXC DORAL 4800 A 787 52',
            'BNC'
        ));
        $this->assertTrue($this->classifier->esComision(
            'SERVICIO USO PUNTO DE VENTA MES: Julio /2026 AFILIACION NRO.:860963370 TERMINAL NRO.:17184',
            'BNC'
        ));
    }

    public function test_mercantil_op_cred_dirt_es_comision(): void
    {
        $this->assertTrue($this->classifier->esComision('OP.CRED.DIRT. CLTE-CLTE PJ -VIA ELECTRON', 'MERCANTIL'));
        $this->assertTrue($this->classifier->esComision('COM.PAT APP-0163 4121936966', null));
        $this->assertFalse($this->classifier->esComision('TRANSF ENTRE CTAS BM PERS JURIDICA', 'MERCANTIL'));
    }

    public function test_compra_intervencion_es_compra_divisas_no_comision(): void
    {
        $desc = 'COMPRA INTERVENCION ELECTRONIC';
        $this->assertTrue($this->classifier->esCompraDivisas($desc));
        $this->assertFalse($this->classifier->esComision($desc));
    }

    public function test_credito_digital_es_pago_credito_no_comision(): void
    {
        $desc = 'CREDITO DIGITAL';
        $this->assertTrue($this->classifier->esPagoCredito($desc));
        $this->assertFalse($this->classifier->esComision($desc));
        $this->assertFalse($this->classifier->esCompraDivisas($desc));
    }
}
