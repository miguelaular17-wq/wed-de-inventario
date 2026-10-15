<?php

namespace Tests\Unit;

use App\Services\BankReconciliationMatcher;
use App\Services\ConciliacionCalendarioService;
use PHPUnit\Framework\TestCase;

class ConciliacionCuentasExcluidasTest extends TestCase
{
    public function test_oculta_jose_jerez_y_terceros_de_venezuela(): void
    {
        $servicio = new ConciliacionCalendarioService(new BankReconciliationMatcher);

        $this->assertFalse($servicio->cuentaIncluida('BANESCO', 'JOSE JEREZ'));
        $this->assertFalse($servicio->cuentaIncluida('TESORO', 'José Jerez'));
        $this->assertFalse($servicio->cuentaIncluida('BNC', 'Tipo A José Jerez'));
        $this->assertFalse($servicio->cuentaIncluida('VENEZUELA', 'DAYANA LOPEZ'));
        $this->assertFalse($servicio->cuentaIncluida('VENEZUELA', 'EDWARD MAVO'));
        $this->assertFalse($servicio->cuentaIncluida('VENEZUELA', 'JOSE SEMECO'));
        $this->assertFalse($servicio->cuentaIncluida('VENEZUELA', 'MARIA NUÑEZ'));

        $this->assertFalse($servicio->cuentaIncluida('BANCARIBE', 'CURAZAO'));
        $this->assertFalse($servicio->cuentaIncluida('BANCARIBE', 'EURONISSI'));
        $this->assertFalse($servicio->cuentaIncluida('BNC', 'EURONISSI'));
        $this->assertTrue($servicio->cuentaIncluida('BANCARIBE', 'DORAL'));
        $this->assertTrue($servicio->cuentaIncluida('BNC', 'LNACEH'));
        $this->assertTrue($servicio->cuentaIncluida('BINANCE', 'MARIA NUÑEZ'));
    }
}
