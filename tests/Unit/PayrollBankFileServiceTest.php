<?php

namespace Tests\Unit;

use App\Services\Nomina\PayrollBankFileService;
use Tests\TestCase;

class PayrollBankFileServiceTest extends TestCase
{
    public function test_formatea_cedula_v_monto_sin_punto_y_fecha_pegada(): void
    {
        $linea = PayrollBankFileService::formatearLinea('31475493', 50215.10, '2026-08-15');

        $this->assertSame('V031475493  00000000000000502151015082026', $linea);
        $this->assertSame(41, strlen($linea));
    }

    public function test_acepta_cedula_con_letra_y_monto_con_decimales(): void
    {
        $linea = PayrollBankFileService::formatearLinea('V-28.766.449', 61288.17, '2026-08-15');

        $this->assertSame('V028766449  00000000000000612881715082026', $linea);
    }

    public function test_usa_el_monto_en_bs_del_ejemplo_local(): void
    {
        $linea = PayrollBankFileService::formatearLinea('31930120', 84817.70, '2026-08-28');

        $this->assertSame('V031930120  00000000000000848177028082026', $linea);
    }
}
