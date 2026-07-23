<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class UploadLocalImages extends Command
{
    protected $signature = 'imagenes:subir-locales {directorio : Ruta de la carpeta con las imágenes}';
    protected $description = 'Sube imágenes de una carpeta local a Supabase para los productos que no tienen foto';

    public function handle()
    {
        $dir = $this->argument('directorio');

        if (!File::isDirectory($dir)) {
            $this->error("El directorio no existe: $dir");
            return 1;
        }

        $supabaseUrl = env('SUPABASE_URL');
        $supabaseKey = env('SUPABASE_KEY');

        if (!$supabaseUrl || !$supabaseKey) {
            $this->error('Faltan credenciales de Supabase en .env');
            return 1;
        }
        $supabaseUrl = rtrim($supabaseUrl, '/');

        // 1. Obtener productos sin imagen
        $this->info("Cargando productos sin imagen desde la base de datos...");
        
        $productos = DB::connection('pgsql')->table('inventario_v2.productos')
            ->whereNull('url_imagen')
            ->select('id', 'codigo')
            ->get();

        if ($productos->isEmpty()) {
            $this->info("¡Todos los productos ya tienen imagen!");
            return 0;
        }

        // 2. Construir mapa de SKUs para búsqueda rápida
        // Soportando SKUs compartidos como "00070044 / ARO044"
        $skuMap = [];
        foreach ($productos as $p) {
            $partesSlash = explode('/', $p->codigo);
            foreach ($partesSlash as $parte) {
                $partesEspacio = explode(' ', trim($parte));
                foreach ($partesEspacio as $pe) {
                    $pe = trim($pe);
                    if ($pe !== '') {
                        // Guardamos el codigo base -> ID producto
                        // Si hay colisiones (dos productos con mismo codigo base), guardamos el primero
                        if (!isset($skuMap[strtolower($pe)])) {
                            $skuMap[strtolower($pe)] = $p->id;
                        }
                    }
                }
            }
        }

        // 3. Leer archivos de la carpeta
        $this->info("Leyendo archivos en: $dir");
        $archivos = File::files($dir);
        
        $subidas = 0;
        $ignoradas = 0;

        $this->output->progressStart(count($archivos));

        foreach ($archivos as $archivo) {
            $this->output->progressAdvance();
            
            $ext = strtolower($archivo->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                continue; // No es imagen soportada
            }

            $nombreBase = $archivo->getFilenameWithoutExtension();
            $skuBuscado = strtolower(trim($nombreBase));

            $productoId = null;

            // a) Buscar coincidencia exacta
            if (isset($skuMap[$skuBuscado])) {
                $productoId = $skuMap[$skuBuscado];
            } else {
                // b) Buscar coincidencia por guion (ej. SKU-ROJO.jpg -> SKU)
                $partesGuion = explode('-', $skuBuscado);
                if (count($partesGuion) > 1) {
                    $baseSku = trim($partesGuion[0]);
                    if (isset($skuMap[$baseSku])) {
                        $productoId = $skuMap[$baseSku];
                    }
                }
            }

            if ($productoId) {
                // Subir a Supabase
                $contenido = File::get($archivo->getPathname());
                $mime = mime_content_type($archivo->getPathname());
                
                // Nombre final en supabase (asegura url safe)
                $fileName = rawurlencode($nombreBase) . '.' . $ext;
                $uploadUrl = "{$supabaseUrl}/storage/v1/object/imagenes_producto/imagenes/{$fileName}";

                $response = Http::withoutVerifying()->withHeaders([
                    'Authorization' => "Bearer {$supabaseKey}",
                    'Content-Type' => $mime,
                    'x-upsert' => 'true'
                ])->withBody($contenido, $mime)->post($uploadUrl);

                if ($response->successful()) {
                    $publicUrl = "{$supabaseUrl}/storage/v1/object/public/imagenes_producto/imagenes/{$fileName}";
                    
                    DB::connection('pgsql')->table('inventario_v2.productos')
                        ->where('id', $productoId)
                        ->update(['url_imagen' => $publicUrl]);
                    
                    $subidas++;
                    
                    // Remover del mapa para no volver a asignarle otra si hay duplicados
                    // (Opcional: podríamos buscar todas las variaciones de SKU que apunten a este ID y borrarlas)
                    foreach (array_keys($skuMap, $productoId) as $key) {
                        unset($skuMap[$key]);
                    }
                } else {
                    $this->error("\nError subiendo {$nombreBase}: " . $response->body());
                }
            } else {
                $ignoradas++;
            }
        }

        $this->output->progressFinish();
        $this->info("Proceso completado.");
        $this->info("✅ Imágenes subidas y asignadas: $subidas");
        $this->info("⏭️ Imágenes ignoradas (no match o ya tenían foto): $ignoradas");

        return 0;
    }
}
