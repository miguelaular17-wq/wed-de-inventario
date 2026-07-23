<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Product;

class VerifySupabaseImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verify:supabase-images {--all : Revisa todos los productos, no solo los activos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica las imagenes en Supabase y actualiza url_imagen en la BD';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $supabaseUrl = env('SUPABASE_URL');
        $supabaseKey = env('SUPABASE_KEY');

        if (!$supabaseUrl || !$supabaseKey) {
            $this->error('Faltan credenciales de Supabase en .env (SUPABASE_URL, SUPABASE_KEY)');
            return 1;
        }

        $supabaseUrl = rtrim($supabaseUrl, '/');
        
        $this->info("Listando archivos en el bucket 'imagenes_producto/imagenes' de Supabase...");
        
        // Use Supabase Storage API to list files
        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => "Bearer {$supabaseKey}",
            'Content-Type' => 'application/json',
        ])->post("{$supabaseUrl}/storage/v1/object/list/imagenes_producto", [
            'prefix' => 'imagenes',
            'limit' => 5000,
            'offset' => 0,
            'sortBy' => [
                'column' => 'name',
                'order' => 'asc'
            ]
        ]);

        if (!$response->successful()) {
            $this->error("Error al obtener la lista de imagenes: " . $response->body());
            return 1;
        }

        $files = $response->json();
        $this->info("Se encontraron " . count($files) . " archivos o carpetas.");

        // Crear mapa de archivos: "codigo.jpg" -> true
        $fileMap = [];
        foreach ($files as $file) {
            if (isset($file['name']) && $file['name'] !== 'imagenes') {
                $fileMap[$file['name']] = true;
            }
        }

        $baseUrl = "{$supabaseUrl}/storage/v1/object/public/imagenes_producto/imagenes/";

        $this->info("Actualizando productos en PostgreSQL...");
        
        $all = $this->option('all');
        
        if (config('database.default') === 'pgsql') {
            $query = DB::connection('pgsql')->table('inventario_v2.productos');
            if (!$all) {
                $query->where('activo', true);
            }
            
            $productos = $query->get(['id', 'codigo']);
            
            $this->output->progressStart($productos->count());
            $updated = 0;

            foreach ($productos as $p) {
                $url = null;
                
                // Extraer todos los códigos posibles (separados por '/' o ' ')
                $codigos_finales = [];
                $partesSlash = explode('/', $p->codigo);
                foreach ($partesSlash as $parte) {
                    $partesEspacio = explode(' ', trim($parte));
                    foreach ($partesEspacio as $pe) {
                        if (trim($pe) !== '') {
                            $codigos_finales[] = trim($pe);
                        }
                    }
                }

                // Buscar imagen para cualquiera de los códigos
                foreach ($codigos_finales as $c_final) {
                    $fileName = rawurlencode($c_final) . ".jpg";
                    $pngName = rawurlencode($c_final) . ".png";
                    
                    if (isset($fileMap[$fileName])) {
                        $url = $baseUrl . $fileName;
                        break;
                    } elseif (isset($fileMap[$pngName])) {
                        $url = $baseUrl . $pngName;
                        break;
                    }
                    
                    // Si no encuentra exacto, busca la base antes del guión
                    $partesGuion = explode('-', $c_final);
                    if (count($partesGuion) > 1) {
                        $base_code = trim($partesGuion[0]);
                        $baseFileName = rawurlencode($base_code) . ".jpg";
                        $basePngName = rawurlencode($base_code) . ".png";
                        
                        if (isset($fileMap[$baseFileName])) {
                            $url = $baseUrl . $baseFileName;
                            break;
                        } elseif (isset($fileMap[$basePngName])) {
                            $url = $baseUrl . $basePngName;
                            break;
                        }
                    }
                }

                DB::connection('pgsql')->table('inventario_v2.productos')
                    ->where('id', $p->id)
                    ->update(['url_imagen' => $url]);
                
                if ($url) $updated++;
                $this->output->progressAdvance();
            }

            $this->output->progressFinish();
            $this->info("Productos en PgSQL actualizados. Encontradas $updated imagenes.");
        } else {
            // SQLite
            $query = Product::query();
            // Assuming Product has an 'activo' column, though maybe not. 
            // We'll update all for SQLite to be safe if no activo column exists.
            $productos = $query->get(['id', 'cod_centro']);
            
            $this->output->progressStart($productos->count());
            $updated = 0;

            foreach ($productos as $p) {
                $url = null;
                
                // Extraer todos los códigos posibles (separados por '/' o ' ')
                $codigos_finales = [];
                $partesSlash = explode('/', $p->cod_centro);
                foreach ($partesSlash as $parte) {
                    $partesEspacio = explode(' ', trim($parte));
                    foreach ($partesEspacio as $pe) {
                        if (trim($pe) !== '') {
                            $codigos_finales[] = trim($pe);
                        }
                    }
                }

                // Buscar imagen para cualquiera de los códigos
                foreach ($codigos_finales as $c_final) {
                    $fileName = rawurlencode($c_final) . ".jpg";
                    $pngName = rawurlencode($c_final) . ".png";
                    
                    if (isset($fileMap[$fileName])) {
                        $url = $baseUrl . $fileName;
                        break;
                    } elseif (isset($fileMap[$pngName])) {
                        $url = $baseUrl . $pngName;
                        break;
                    }
                    
                    // Si no encuentra exacto, busca la base antes del guión
                    $partesGuion = explode('-', $c_final);
                    if (count($partesGuion) > 1) {
                        $base_code = trim($partesGuion[0]);
                        $baseFileName = rawurlencode($base_code) . ".jpg";
                        $basePngName = rawurlencode($base_code) . ".png";
                        
                        if (isset($fileMap[$baseFileName])) {
                            $url = $baseUrl . $baseFileName;
                            break;
                        } elseif (isset($fileMap[$basePngName])) {
                            $url = $baseUrl . $basePngName;
                            break;
                        }
                    }
                }

                Product::where('id', $p->id)->update(['url_imagen' => $url]);
                
                if ($url) $updated++;
                $this->output->progressAdvance();
            }

            $this->output->progressFinish();
            $this->info("Productos actualizados. Encontradas $updated imagenes.");
        }

        $this->info("Verificacion completa.");
        return 0;
    }
}
