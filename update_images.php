<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

$base_url = "https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/object/public/imagenes_producto/imagenes/";

echo "Obteniendo productos sin url_imagen...\n";
$productos = DB::connection('pgsql')->table('inventario_v2.productos')
    ->where('activo', true)
    ->where(function($q) {
        $q->whereNull('url_imagen')->orWhere('url_imagen', '');
    })
    ->select('id', 'codigo', 'nombre')
    ->get();

echo "Total a verificar: " . $productos->count() . "\n";

$validUrls = [];
$chunkSize = 50;
$chunks = $productos->chunk($chunkSize);

foreach ($chunks as $index => $chunk) {
    echo "Procesando lote " . ($index + 1) . " de " . $chunks->count() . "...\n";
    
    $responses = Http::pool(function (Pool $pool) use ($chunk, $base_url) {
        $requests = [];
        foreach ($chunk as $p) {
            if (empty($p->codigo)) continue;
            
            $codigos = explode('/', $p->codigo);
            if(count($codigos) === 1) {
                $codigos = explode(' ', $p->codigo);
            }
            $primary_code = trim($codigos[0]);
            
            if ($primary_code) {
                $url = $base_url . rawurlencode($primary_code) . ".jpg";
                $requests[] = $pool->as("prod_{$p->id}")->withoutVerifying()->head($url);
            }
        }
        return $requests;
    });

    // Procesar respuestas
    foreach ($responses as $key => $response) {
        if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
            $id = str_replace('prod_', '', $key);
            // Reconstruir la url
            $p = $chunk->firstWhere('id', $id);
            $codigos = explode('/', $p->codigo);
            if(count($codigos) === 1) { $codigos = explode(' ', $p->codigo); }
            $primary_code = trim($codigos[0]);
            $url = $base_url . rawurlencode($primary_code) . ".jpg";
            
            $validUrls[$id] = $url;
        }
    }
}

echo "Encontradas " . count($validUrls) . " imagenes válidas en Supabase.\n";
echo "Actualizando base de datos...\n";

$updatedCount = 0;
foreach ($validUrls as $id => $url) {
    $p = $chunk->firstWhere('id', $id);
    echo "  [Encontrado y Enlazado] " . ($p ? $p->nombre : 'ID '.$id) . "\n";
    
    DB::connection('pgsql')->table('inventario_v2.productos')
        ->where('id', $id)
        ->update(['url_imagen' => $url]);
    $updatedCount++;
}

echo "Proceso completado. $updatedCount productos actualizados exitosamente.\n";
