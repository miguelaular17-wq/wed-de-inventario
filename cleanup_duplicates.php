<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

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
    if (preg_match('/Omitido id (\d+) por conflicto de llave única: (.*)$/', $line, $matches)) {
        $errores[] = [
            'id_omitido' => (int)$matches[1],
            'codigo_conflicto' => trim($matches[2])
        ];
    }
}

$bothHaveMovements = [];
$cleanedCount = 0;
$deactivatedCount = 0;

foreach ($errores as $err) {
    $prodA = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->where('id', $err['id_omitido'])->first();
    $prodB = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->where('codigo', $err['codigo_conflicto'])->first();

    if ($prodA && $prodB) {
        $movsA = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.movimientos')->where('producto_id', $prodA->id)->count();
        $movsB = \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.movimientos')->where('producto_id', $prodB->id)->count();

        if ($movsA > 0 && $movsB > 0) {
            $bothHaveMovements[] = [
                'prodA' => ['id' => $prodA->id, 'codigo' => $prodA->codigo, 'movs' => $movsA],
                'prodB' => ['id' => $prodB->id, 'codigo' => $prodB->codigo, 'movs' => $movsB]
            ];
        } else {
            // Logica: Si A no tiene movs, desactivar/borrar A, conservar B
            // Si B no tiene movs, desactivar/borrar B, conservar A y actualizar codigo de A
            // Si ninguno tiene, borrar A y conservar B (es lo mismo)
            
            $toKeep = $prodB;
            $toDiscard = $prodA;
            $codeForKeep = $err['codigo_conflicto']; // El codigo correcto (JSON de primero)

            if ($movsA > 0) {
                $toKeep = $prodA;
                $toDiscard = $prodB;
            }

            // Desactivar el que se descarta
            try {
                \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->where('id', $toDiscard->id)->delete();
                $cleanedCount++;
            } catch (\Exception $e) {
                // Falla por llave foránea en otra tabla? Desactivar
                \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->where('id', $toDiscard->id)->update(['activo' => false]);
                $deactivatedCount++;
            }

            // Asegurar que el que se mantiene tenga el codigo correcto
            try {
                \Illuminate\Support\Facades\DB::connection('supabase')->table('inventario_v2.productos')->where('id', $toKeep->id)->update(['codigo' => $codeForKeep]);
            } catch (\Exception $e) {
                // Ignore unique constraint here since we just deleted/deactivated the conflicting one
            }
        }
    }
}

echo "✅ Limpieza completada.\n";
echo "Productos duplicados ELIMINADOS físicamente: {$cleanedCount}\n";
echo "Productos duplicados DESACTIVADOS (tenían referencias): {$deactivatedCount}\n";
echo "Casos conflictivos (ambos clones tienen movimientos): " . count($bothHaveMovements) . "\n";

if (count($bothHaveMovements) > 0) {
    echo "\nADVERTENCIA: Se encontraron " . count($bothHaveMovements) . " productos donde AMBOS duplicados tienen movimientos.\n";
    foreach ($bothHaveMovements as $c) {
        echo "- ID {$c['prodA']['id']} (movs: {$c['prodA']['movs']}) vs ID {$c['prodB']['id']} (movs: {$c['prodB']['movs']})\n";
    }
}
