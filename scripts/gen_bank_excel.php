<?php
/**
 * Genera un archivo CSV compatible con el parser del sistema BANESCO
 * (que usa el formato de columnas del FinanzasController)
 * y lo guarda en storage/app/
 *
 * BANESCO format (desde FinanzasController):
 *   start_row=5, col_fecha=2, col_referencia=4, col_descripcion=5, col_cargo=6, col_abono=7
 *   Indexados desde 0, fila 0 = fila 1 en Excel
 *   La librería lee xlsx con PhpSpreadsheet, pero podemos crear el xlsx directamente
 *   usando un CSV guardado como .xlsx - sin embargo el parser espera xlsx real.
 *
 * ALTERNATIVA: Crear directamente las ConciliacionLinea simulando lo que haría el parser,
 * como si el usuario ya hubiera subido el archivo Excel.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ConciliacionLinea;

$HOY        = date('Y-m-d');
$HACE_1_DIA = date('Y-m-d', strtotime('-1 day'));
$BANCO      = 'BANESCO';
$TITULAR    = 'GRUPO JRZ';
$SESSION_ID = 'test-ui-' . time();

echo "Limpiando líneas de prueba anteriores...\n";
ConciliacionLinea::where('descripcion', 'LIKE', '%[UIPROOF]%')->delete();

$lineas = [
    [
        'fecha'       => $HOY,
        'referencia'  => 'TRF20260806-101',
        'descripcion' => 'TRANSFERENCIA SALIENTE PAPELERIA [UIPROOF]',
        'monto'       => -200.00,
        'tipo'        => 'debito',
    ],
    [
        'fecha'       => $HOY,
        'referencia'  => null,
        'descripcion' => 'PAGO SERVICIO LUZ [UIPROOF]',
        'monto'       => -450.00,
        'tipo'        => 'debito',
    ],
    [
        'fecha'       => $HOY,
        'referencia'  => 'PM202608060018899',
        'descripcion' => 'PAGO MOVIL RECIBIDO 0412-12345 [UIPROOF]',
        'monto'       => 100.00,
        'tipo'        => 'credito',
    ],
    [
        'fecha'       => $HOY,
        'referencia'  => 'L2244',
        'descripcion' => 'ACREDITACION LOTE POS TARDE [UIPROOF]',
        'monto'       => 750.00,
        'tipo'        => 'credito',
    ],
    [
        'fecha'       => $HOY,         // Banco registra HOY, tesorería lo registró AYER
        'referencia'  => 'TRF202608050016677',
        'descripcion' => 'TRANSFERENCIA RECIBIDA [UIPROOF]',
        'monto'       => 320.00,
        'tipo'        => 'credito',
    ],
    [
        'fecha'       => $HOY,
        'referencia'  => null,
        'descripcion' => 'COMISION BANESCO TRANSFERENCIA [UIPROOF]',
        'monto'       => -4.50,
        'tipo'        => 'debito',
    ],
];

echo "\nLíneas de banco creadas:\n";
foreach ($lineas as $l) {
    $linea = ConciliacionLinea::create(array_merge($l, [
        'banco'      => $BANCO,
        'titular'    => $TITULAR,
        'estado'     => 'pendiente',
        'session_id' => $SESSION_ID,
    ]));
    $dir = $l['monto'] >= 0 ? '⬆️  CREDITO' : '⬇️  DEBITO';
    echo "  ID {$linea->id} - {$dir} \$" . abs($l['monto']) . " - {$l['descripcion']}\n";
}

echo "\n✅ Líneas bancarias inyectadas con session_id={$SESSION_ID}\n";
echo "   Ahora ve a la UI → Finanzas → Conciliaciones\n";
echo "   y verifica que el motor empareja automáticamente.\n";
