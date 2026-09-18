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
        $this->assertTrue($this->classifier->esComision('EMISION DE ESTADO DE CUENTA'));
        $this->assertTrue($this->classifier->esComision('COMIS EMIS EDO DE CUENTAS ME'));
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
