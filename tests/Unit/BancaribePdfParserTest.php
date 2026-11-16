<?php

namespace Tests\Unit;

use App\Services\BancaribePdfParser;
use PHPUnit\Framework\TestCase;

class BancaribePdfParserTest extends TestCase
{
    public function test_parsea_estado_de_cuenta_clasico(): void
    {
        $texto = <<<'TXT'
Fecha Oficina Referencia Cargo Abono Saldo
30-OCT-2026 31000771343 0,00 2.667.780,00 5.350.248,36 INTERNET
BANKING NCTRANSFERENCIA INMEDIATA RECIBIDA
29-OCT-2026 4281138874095 2.000.002,50 0,00 376.392,37 ADMINISTRACION
CARACAS ND TRANSFERENCIA INMEDIATA ENVIADA
TXT;

        $movs = (new BancaribePdfParser)->parseTexto($texto);

        $this->assertCount(2, $movs);
        $this->assertSame('2026-10-30', $movs[0]['fecha']);
        $this->assertSame('31000771343', $movs[0]['referencia']);
        $this->assertSame('abono', $movs[0]['tipo']);
        $this->assertEqualsWithDelta(2667780.0, $movs[0]['monto'], 0.01);
        $this->assertSame('cargo', $movs[1]['tipo']);
        $this->assertEqualsWithDelta(2000002.50, $movs[1]['monto'], 0.01);
    }

    public function test_parsea_conexion_personas(): void
    {
        $texto = <<<'TXT'
Detalles de la Cuenta - 01140251812510078090
Movimientos
Fecha Referencia Oficina Transacción
Signo
+/-
Monto IMP Disponible Real
30-OCT-2026 31000771343
Internet
banking
Transferencia
inmediata recibida
+ 2.667.780,00 0,00 5.350.248,36 5.350.248,36
29-OCT-2026 4281138874095
Internet
banking
Transferencia
inmediata enviada
- 2.000.002,50 0,00 376.392,37 376.392,37
30-OCT-2026 1771227328930
Punto fijo av.
tachira
Comision cuota de
mantenimiento
mensual
- 6.839,00 0,00 5.444.279,10 5.444.279,10
16/11/26, 3:35 p.m. Conexión Bancaribe Personas
https://www4.bancaribe.com.ve/bcj/proxy/ejemplo
TXT;

        $movs = (new BancaribePdfParser)->parseTexto($texto);

        $this->assertCount(3, $movs);

        $this->assertSame('2026-10-30', $movs[0]['fecha']);
        $this->assertSame('31000771343', $movs[0]['referencia']);
        $this->assertSame('abono', $movs[0]['tipo']);
        $this->assertEqualsWithDelta(2667780.0, $movs[0]['monto'], 0.01);
        $this->assertStringContainsString('Transferencia inmediata recibida', $movs[0]['descripcion']);
        $this->assertStringNotContainsString('Internet', $movs[0]['descripcion']);

        $this->assertSame('cargo', $movs[1]['tipo']);
        $this->assertEqualsWithDelta(2000002.50, $movs[1]['monto'], 0.01);

        $this->assertSame('1771227328930', $movs[2]['referencia']);
        $this->assertEqualsWithDelta(6839.0, $movs[2]['monto'], 0.01);
        $this->assertStringContainsString('Comision cuota', $movs[2]['descripcion']);
        $this->assertStringNotContainsString('tachira', mb_strtolower($movs[2]['descripcion']));
    }
}
