<?php

namespace Tests\Unit;

use App\Services\Conciliacion\ConciliacionDivisasService;
use App\Services\Conciliacion\EstadoCuentaPdfParser;
use PHPUnit\Framework\TestCase;

class EstadoCuentaPdfParserTest extends TestCase
{
    public const CITIZENS = <<<'TXT'
BusinessAccount
Statement
BeginningAugust01,2026
throughAugust31,2026
citizensbank.com
ClearlyBetterBusinessChecking forXXXXXX-580-3
BalanceCalculation
PreviousBalance	1,000.00
Checks	-	.00
Debits	- 10.00
Deposits&Credit	+ 292.00
CurrentBalance	= 1,282.00
Debits*
OtherDebits
Date Amount Description
08/10 10.00SERVICECHARGE
WIRETRANSFERFEES
Deposits&Credits
Date Amount Description
08/10 100.00ZELLECREDIT NOWNETID:081026
ZelleEDILIOPEREAGUARDIA8006566561
08/10 92.00ZELLECREDIT NOWNETID:081026
ZelleJOSECARRASQUERO8006566561
Page2of3
Deposits&Credits(Continued)
08/11 100.00ZELLECREDIT NOWNETID:081126
ZelleANAPEREZ8006566561
DailyBalance
08/10 1,182.00
08/11 1,282.00
TXT;

    public const AMERANT = <<<'TXT'
Account Number:	602755772
Statement Date:	08/31/2026
Amerant Bank
Account Summary
Beginning Balance as of08/01/26	$100.00
    +  Deposits and Credits  (2)	$1,580.00
     -  Checks Posted  (0)	$0.00
     -  Withdrawals and Debits  (2)	$4.00
Ending Balance as of08/31/26	$1,676.00
Account Activity
Date Description
Aug01Beginning Balance	$100.00
Aug05ZELLE CREDIT
ZELLE GUILLERMO LUENGO VAMERANT92522220
ALHAMBRA CIRCL
$550.00
Aug05SERVICE CHARGE
ACCOUNT CREDIT P2P
-$2.00
Aug05SERVICE CHARGE
ACCOUNT CREDIT P2P
-$2.00
Debits Credits Balance
$650.00
Account Activity(Continued)
Aug12ZELLE CREDIT
ZELLE DERKIS VARGASAMERANT 92522220 ALHAMBRA
CIRCL
$1,030.00
Aug31Ending Balance	$1,678.00
Debits
Date Description	Withdrawals
Aug05SERVICE CHARGE
ACCOUNT CREDIT P2P
-$2.00
Credits 
Date Description	Deposits
Aug05ZELLE CREDIT
ZELLE GUILLERMO LUENGO VAMERANT92522220
$550.00
Daily Ledger Balance Summary
Aug05	$648.00
Aug12	$1,678.00
TXT;

    public function test_citizens_lee_debitos_y_creditos_y_cuadra_con_los_totales(): void
    {
        $r = (new EstadoCuentaPdfParser)->leerTexto(self::CITIZENS);

        $this->assertSame('Citizens', $r['banco']);
        $this->assertSame('5803', $r['cuenta']);
        $this->assertSame(['2026-08-01', '2026-08-31'], [$r['desde'], $r['hasta']]);
        $this->assertTrue($r['cuadra']);
        $this->assertSame([-10.0, 100.0, 92.0, 100.0], array_column($r['movimientos'], 'monto'));
        $this->assertSame(['2026-08-10', '2026-08-10', '2026-08-10', '2026-08-11'], array_column($r['movimientos'], 'fecha'));
        $this->assertSame('SERVICECHARGE WIRETRANSFERFEES', $r['movimientos'][0]['descripcion']);
        $this->assertSame('EDILIOPEREAGUARDIA', ConciliacionDivisasService::nombreZelle($r['movimientos'][1]['descripcion']));
        $this->assertSame('ANAPEREZ', ConciliacionDivisasService::nombreZelle($r['movimientos'][3]['descripcion']));
    }

    public function test_amerant_lee_solo_la_actividad_sin_contar_dos_veces(): void
    {
        $r = (new EstadoCuentaPdfParser)->leerTexto(self::AMERANT);

        $this->assertSame('Amerant', $r['banco']);
        $this->assertSame('602755772', $r['cuenta']);
        $this->assertSame(['2026-08-01', '2026-08-31'], [$r['desde'], $r['hasta']]);
        $this->assertTrue($r['cuadra']);
        $this->assertSame([550.0, -2.0, -2.0, 1030.0], array_column($r['movimientos'], 'monto'));
        $this->assertSame('ZELLE CREDIT ZELLE DERKIS VARGASAMERANT 92522220 ALHAMBRA CIRCL', $r['movimientos'][3]['descripcion']);
        $this->assertSame('GUILLERMOLUENGOV', ConciliacionDivisasService::nombreZelle($r['movimientos'][0]['descripcion']));
        $this->assertSame('DERKISVARGAS', ConciliacionDivisasService::nombreZelle($r['movimientos'][3]['descripcion']));
    }

    public function test_si_falta_un_movimiento_no_cuadra(): void
    {
        $r = (new EstadoCuentaPdfParser)->leerTexto(str_replace("08/10 92.00ZELLECREDIT", "08/10 ZELLECREDIT", self::CITIZENS));

        $this->assertFalse($r['cuadra']);
    }

    public const WELLS = <<<'TXT'
Wells Fargo Bank, N.A.
INVERSIONES DORAL PARAGUANA C.A LLC
31 de octubre de 2026
Resumen de la actividad del período de estado de cuenta
Saldo inicial al 10/1 	$1,000.00
Depósitos/Créditos 	460.00
Retiros/Débitos 	-  130.00
Saldo final al 10/31  (mes/dia) 	$1,330.00
Número de cuenta:   3558846915  (cuenta principal)
Historial de transacciones
Fecha
(mes/dia
Descripción
Depósitos/
Créditos
Retiros/
Débitos
Saldo
diario final
10/1   Zelle From Anibal Eduardo Pacheco Gutierrez on 10/01 Ref #
Jpm99Cybrdsa Para Mary Novia
110.00
10/2   Zelle to Kiras G International LLC on 10/02 Ref #
Wfct22Qdxm26 Factura 8233
   100.00
Historial de transacciones (continuación)
Fecha
10/5   Zelle From Luis Brett on 10/05 Ref # Bacx5Yww59We  	350.00
10/30  Interest Payment  	0.00
10/30  Wire Trans Svc Charge - Sequence: 261006164380
   30.00     1,330.00
Totales 	$460.00 $130.00
El Saldo Diario Final no refleja ningún retiro pendiente
TXT;

    public function test_wells_fargo_lee_historial_y_cuadra_con_los_totales(): void
    {
        $r = (new EstadoCuentaPdfParser)->leerTexto(self::WELLS);

        $this->assertSame('Wells Fargo', $r['banco']);
        $this->assertSame('3558846915', $r['cuenta']);
        $this->assertSame(['2026-10-01', '2026-10-31'], [$r['desde'], $r['hasta']]);
        $this->assertTrue($r['cuadra']);
        $this->assertSame([110.0, -100.0, 350.0, -30.0], array_column($r['movimientos'], 'monto'));
        $this->assertSame('ANIBALEDUARDOPACHECOGUTIERREZ', ConciliacionDivisasService::nombreZelle($r['movimientos'][0]['descripcion']));
    }

    public function test_texto_de_otro_banco_no_se_reconoce(): void
    {
        $this->assertNull((new EstadoCuentaPdfParser)->leerTexto("Chase Bank\nStatement period activity summary\nAccount Activity"));
    }

    public const MERCANTIL_PANAMA = <<<'TXT'
420233875
Fecha: 31/OCT/2026
JOSE LEONARDO JEREZ ANEZ
64,438.50
10,088.68
46,169.95
28,357.16
Nombre :
Cuenta :
Saldo Inicial :
Total Débitos :
Total Créditos :
Total ITBMS :
Saldo Final :
0.07
Moneda:USD
Detalle de Transacciones
01/OCT/2026ACHX-Arelis Vargas ZINLI	119385366	50.00 64,488.50
02/OCT/2026INTERNA GERALDINE ANDREINA M *5731	886204	9.15 64,497.65
05/OCT/2026CAHO A MFTECH, S.A. POR ZIN ANGEL ANTONIO	60.00598534	64,437.65
07/OCT/2026CAHO A MFTECH, S.A. POR ZIN	300.00613893	64,137.65
22/OCT/2026ACH--JERRY ALFARO BANESCO	25,000.00976999	39,137.65
23/OCT/2026INTERNA VENEPAC 2020, C.A. *8521	19,859.95985293	19,277.70
30/OCT/2026ITBMS P/COMISIONES/CUENTAS	0.07	19,277.63
30/OCT/2026INTERESES	6.26 19,283.89
Casa Matriz: Torre Mercantil. Calle 50, con calle 58 de Obarrio.
www.mercantilbanco.com.pa
TXT;

    public function test_mercantil_panama_lee_creditos_debitos_y_monto_pegado_a_referencia(): void
    {
        $r = (new EstadoCuentaPdfParser)->leerTexto(self::MERCANTIL_PANAMA);

        $this->assertSame('Mercantil Panamá', $r['banco']);
        $this->assertSame('420233875', $r['cuenta']);
        $this->assertSame('2026-10-31', $r['hasta']);
        $this->assertSame(50.0, $r['movimientos'][0]['monto']);
        $this->assertSame(-60.0, $r['movimientos'][2]['monto']);
        $this->assertSame(-25000.0, $r['movimientos'][4]['monto']);
        $this->assertSame(-19859.95, $r['movimientos'][5]['monto']);
        $this->assertStringContainsString('ACHX-Arelis Vargas ZINLI', $r['movimientos'][0]['descripcion']);
        $this->assertStringContainsString('ACH--JERRY ALFARO BANESCO', $r['movimientos'][4]['descripcion']);
    }

    public function test_mercantil_panama_pdf_real_cuadra_con_totales(): void
    {
        $path = dirname(__DIR__, 2).'/storage/app/_mercantil_panama.pdf';
        if (! is_file($path)) {
            $this->markTestSkipped('Falta storage/app/_mercantil_panama.pdf');
        }
        $r = (new EstadoCuentaPdfParser)->leer($path);
        $this->assertNotNull($r);
        $this->assertSame('Mercantil Panamá', $r['banco']);
        $this->assertSame('420233875', $r['cuenta']);
        $this->assertTrue($r['cuadra']);
        $this->assertGreaterThan(40, count($r['movimientos']));
        $this->assertEqualsWithDelta(10088.68, $r['totales']['abonos'], 0.01);
    }

    public function test_nombres_cortados_o_pegados_se_reconocen(): void
    {
        $this->assertSame(2, ConciliacionDivisasService::similitudNombre('RICHARDJOSECHIRINOSARCAYA', 'RICHARDJOSECHIRI'));
        $this->assertSame(2, ConciliacionDivisasService::similitudNombre(ConciliacionDivisasService::nombreNormalizado('Neivis Piña Gómez'), 'NEIVISPINAGOMEZ'));
        $this->assertSame(1, ConciliacionDivisasService::similitudNombre('DAVIDCONCEPCIONLUGOLUGO', 'DAVIDCONLUGOLUGO'));
        $this->assertSame(0, ConciliacionDivisasService::similitudNombre('CARLOSARIASMAVO', 'EDILIOPEREAGUARDIA'));
        $this->assertSame(0, ConciliacionDivisasService::similitudNombre('DAVIDJESUSLOPEZMORILLO', 'DAVIDFIGUEREDO'));
    }
}
