<?php
/**
 * Inyecta datos de prueba en egreso + tesorería
 * para probar la conciliación desde la interfaz visual.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FlujoCaja;
use App\Models\TesoreriaIngreso;

$HOY         = date('Y-m-d');
$HACE_1_DIA  = date('Y-m-d', strtotime('-1 day'));
$BANCO       = 'BANESCO';
$TITULAR     = 'GRUPO JRZ';

echo "Limpiando datos de prueba anteriores...\n";
FlujoCaja::where('motivo', 'LIKE', '%[UIPROOF]%')->delete();
TesoreriaIngreso::where('descripcion', 'LIKE', '%[UIPROOF]%')->delete();

// EGRESOS en Flujo de Caja
$egresos = [
    ['monto_usd'=>200.00, 'monto_bs'=>200.00, 'referencia'=>'TRF20260806-101', 'motivo'=>'Pago papeleria [UIPROOF]'],
    ['monto_usd'=>450.00, 'monto_bs'=>450.00, 'referencia'=>null,              'motivo'=>'Pago luz [UIPROOF]'],
];

echo "\nEgresos creados:\n";
foreach ($egresos as $d) {
    $f = FlujoCaja::create(array_merge($d, [
        'fecha'            => $HOY,
        'tipo'             => 'egreso',
        'banco'            => $BANCO,
        'titular'          => $TITULAR,
        'categoria_egreso' => 'egreso_realizado',
        'es_conciliado'    => false,
    ]));
    echo "  ID {$f->id} - {$f->motivo} - \${$f->monto_usd}\n";
}

// INGRESOS Tesorería
$ingresos = [
    ['tipo'=>'banco',       'monto'=>100.00, 'lote_referencia'=>'8899', 'descripcion'=>'Pago movil cliente Lopez [UIPROOF]', 'fecha'=>$HOY],
    ['tipo'=>'punto_venta', 'monto'=>750.00, 'lote_referencia'=>'L2244', 'descripcion'=>'Lote POS tarde [UIPROOF]',           'fecha'=>$HOY],
    ['tipo'=>'banco',       'monto'=>320.00, 'lote_referencia'=>'6677', 'descripcion'=>'Transferencia con desfase [UIPROOF]','fecha'=>$HACE_1_DIA],
];

echo "\nIngresos Tesorería creados:\n";
foreach ($ingresos as $d) {
    $t = TesoreriaIngreso::create(array_merge($d, [
        'banco'         => $BANCO,
        'titular'       => $TITULAR,
        'es_conciliado' => false,
    ]));
    echo "  ID {$t->id} - {$t->tipo} - Ref '{$t->lote_referencia}' - \${$t->monto}\n";
}

echo "\n✅ Datos de prueba inyectados.\n";
echo "   Ahora sube el archivo de banco desde la UI de Conciliaciones.\n";
