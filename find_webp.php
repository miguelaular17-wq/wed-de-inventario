<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

$supabaseUrl = env('SUPABASE_URL');
$supabaseKey = env('SUPABASE_KEY');

$url = rtrim($supabaseUrl, '/') . '/storage/v1/object/list/imagenes_producto';
$payload = [
    'prefix' => 'imagenes',
    'limit' => 5000,
    'offset' => 0,
    'sortBy' => ['column' => 'name', 'order' => 'asc']
];

$response = Http::withoutVerifying()->withHeaders([
    'Authorization' => "Bearer {$supabaseKey}",
    'Content-Type' => 'application/json'
])->post($url, $payload);

$files = $response->json();
$baseUrl = rtrim($supabaseUrl, '/') . '/storage/v1/object/public/imagenes_producto/';
$webpFiles = [];

echo "Escaneando " . count($files) . " archivos en busca de firmas WebP...\n";

$client = new Client(['verify' => false]);
$promises = [];
$batchSize = 100;
$total = count($files);

for ($i = 0; $i < $total; $i += $batchSize) {
    $batch = array_slice($files, $i, $batchSize);
    $promises = [];
    
    foreach ($batch as $file) {
        if ($file['name'] === '.emptyFolderPlaceholder') continue;
        
        $fileUrl = $baseUrl . str_replace('#', '%23', $file['name']);
        
        $promises[$file['name']] = $client->getAsync($fileUrl, [
            'headers' => ['Range' => 'bytes=0-15']
        ]);
    }
    
    $results = Promise\Utils::settle($promises)->wait();
    
    foreach ($results as $name => $result) {
        if ($result['state'] === 'fulfilled') {
            $data = $result['value']->getBody()->getContents();
            if (strlen($data) >= 12) {
                if (substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP') {
                    $webpFiles[] = $name;
                }
            }
        }
    }
    
    echo "Progreso: " . min($i + $batchSize, $total) . " / $total\n";
}

echo "\n¡Escaneo completado!\n";
echo "Archivos WebP disfrazados encontrados: " . count($webpFiles) . "\n\n";
foreach ($webpFiles as $w) {
    echo "- " . $w . "\n";
}
