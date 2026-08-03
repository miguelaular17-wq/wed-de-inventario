<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Configurar conexión a la BD de producción de Supabase
config(['database.connections.supabase' => [
    'driver' => 'pgsql',
    'host' => 'aws-1-us-east-2.pooler.supabase.com',
    'port' => '6543',
    'database' => 'postgres',
    'username' => 'postgres.hbhqbmzixgcvxkilwsau',
    'password' => 'W@mqkdhf#snW@68',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]]);

$logPath = 'C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/.system_generated/tasks/task-2377.log';
$lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$errores = [];
foreach ($lines as $line) {
    // Ejemplo de linea: Omitido id 28088 por conflicto de llave única: 707285 / MONB728
    if (preg_match('/Omitido id (\d+) por conflicto de llave única: (.*)$/', $line, $matches)) {
        $errores[] = [
            'id_omitido' => (int)$matches[1],
            'codigo_conflicto' => trim($matches[2])
        ];
    }
}

$sameNameAndPrice = 0;
$diffNameOrPrice = 0;
$details = [];

foreach ($errores as $err) {
    // Producto que se intentó cambiar
    $prodA = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->where('id', $err['id_omitido'])->first();
    // Producto que ya existía y bloqueó el cambio
    $prodB = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->where('codigo', $err['codigo_conflicto'])->first();

    if ($prodA && $prodB) {
        $nameA = trim(strtoupper($prodA->nombre));
        $nameB = trim(strtoupper($prodB->nombre));
        $priceA = (float)$prodA->precio_unidad;
        $priceB = (float)$prodB->precio_unidad;

        if ($nameA === $nameB && $priceA === $priceB) {
            $sameNameAndPrice++;
        } else {
            $diffNameOrPrice++;
            $details[] = [
                'prodA' => ['codigo' => $prodA->codigo, 'nombre' => $prodA->nombre, 'precio' => $priceA],
                'prodB' => ['codigo' => $prodB->codigo, 'nombre' => $prodB->nombre, 'precio' => $priceB],
            ];
        }
    }
}

$md = "# Comparación de Productos Duplicados\n\n";
$md .= "De los " . count($errores) . " productos conflictivos analizados:\n\n";
$md .= "- **{$sameNameAndPrice} pares** son **EXACTAMENTE IGUALES** (tienen el mismo nombre y el mismo precio de unidad).\n";
$md .= "- **{$diffNameOrPrice} pares** tienen **DIFERENCIAS** en el nombre o en el precio.\n\n";

if ($diffNameOrPrice > 0) {
    $md .= "### Detalles de los que tienen diferencias:\n\n";
    $md .= "| Código Original (A) | Nombre (A) | Precio (A) | Código Bloqueante (B) | Nombre (B) | Precio (B) |\n";
    $md .= "|---|---|---|---|---|---|\n";
    foreach ($details as $d) {
        $md .= "| `{$d['prodA']['codigo']}` | {$d['prodA']['nombre']} | $ {$d['prodA']['precio']} | `{$d['prodB']['codigo']}` | {$d['prodB']['nombre']} | $ {$d['prodB']['precio']} |\n";
    }
}

$artifactPath = 'C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/reporte_comparacion_duplicados.md';
file_put_contents($artifactPath, $md);
echo "Reporte guardado en reporte_comparacion_duplicados.md\n";
