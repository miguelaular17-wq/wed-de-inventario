<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\V2\Producto;

class GenerarCatalogoAuto extends Command
{
    protected $signature = 'catalogo:generar {--id= : El ID del catálogo específico a generar (si se omite se generan todos)}';
    protected $description = 'Genera los catálogos automáticos y los sube a Supabase';

    public function handle(): int
    {
        $this->info('Iniciando generación de catálogos automáticos...');

        $id = $this->option('id');

        $query = DB::connection('pgsql')->table('catalogo_config');
        if ($id) {
            $query->where('id', $id);
        }

        $catalogos = $query->get();

        if ($catalogos->isEmpty()) {
            $this->error('No hay automatizadores de catálogo configurados.');
            return 1;
        }

        $hasError = false;

        foreach ($catalogos as $config) {
            $this->info("========================================");
            $this->info("Generando catálogo: {$config->nombre} ({$config->archivo})");
            
            try {
                $this->generarCatalogoUnico($config);
                $this->info("✅ Catálogo '{$config->nombre}' generado exitosamente.");
            } catch (\Exception $e) {
                $this->error("❌ Error al generar '{$config->nombre}': " . $e->getMessage());
                Log::error("catalogo:generar - Error en '{$config->nombre}': " . $e->getMessage());
                $hasError = true;
            }
        }

        $this->info("========================================");
        if ($hasError) {
            $this->warn("Proceso completado con algunos errores.");
            return 1;
        }

        $this->info("Proceso completado exitosamente.");
        return 0;
    }

    private function generarCatalogoUnico($config)
    {
        $categorias    = json_decode($config->categorias, true) ?? [];
        $subcategorias = json_decode($config->subcategorias, true) ?? [];
        $soloExistencia = (bool) $config->solo_existencia;

        // Build product query
        $query = Producto::select('id', 'codigo', 'nombre as descripcion', 'categoria', 'subcategoria', 'precio_unidad', 'precio_mayor')
            ->where('activo', true);

        if (!empty($categorias)) {
            $query->whereIn('categoria', $categorias);
        }

        if (!empty($subcategorias)) {
            $query->whereIn('subcategoria', $subcategorias);
        }

        // Add stock sum
        $query->withSum('stock as existencia', 'existencia');

        // Filter by stock if needed
        if ($soloExistencia) {
            $query->whereHas('stock', function ($q) {
                $q->where('existencia', '>', 0);
            });
        }

        $query->orderBy('nombre', 'asc');

        $productos = $query->get();

        if ($productos->isEmpty()) {
            throw new \Exception('No se encontraron productos con los filtros actuales para este catálogo.');
        }

        // Build filter labels
        $filtrosActivos = [
            'Categorías'    => !empty($categorias) ? implode(', ', $categorias) : 'Todas',
            'Subcategorías' => !empty($subcategorias) ? implode(', ', $subcategorias) : 'Todas',
            'Existencia'    => $soloExistencia ? 'Solo con stock' : 'Todos',
            'Generado'      => now()->format('d/m/Y H:i'),
        ];

        // Generate PDF
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 300);

        $pdf = Pdf::setOptions(['isRemoteEnabled' => true, 'defaultFont' => 'sans-serif'])
            ->loadView('catalogo.pdf', compact('productos', 'filtrosActivos'));
        $pdf->setPaper('A4', 'portrait');
        $pdfContent = $pdf->output();

        // Upload to Supabase (always overwrite same filename)
        $supabaseUrl = env('SUPABASE_URL');
        $supabaseKey = env('SUPABASE_KEY');

        if (!$supabaseUrl || !$supabaseKey) {
            throw new \Exception('Faltan credenciales SUPABASE_URL o SUPABASE_KEY en el archivo .env.');
        }

        $supabaseUrl = rtrim($supabaseUrl, '/');
        $fixedFileName = $config->archivo; // Usar el nombre de archivo de la bd
        $uploadUrl = "{$supabaseUrl}/storage/v1/object/catalogos/{$fixedFileName}";

        $response = Http::withoutVerifying()->withHeaders([
            'Authorization' => "Bearer {$supabaseKey}",
            'Content-Type'  => 'application/pdf',
            'x-upsert'      => 'true', // overwrite existing file
        ])->withBody($pdfContent, 'application/pdf')->post($uploadUrl);

        if (!$response->successful()) {
            throw new \Exception('Error Supabase: ' . $response->body());
        }

        $publicUrl = "{$supabaseUrl}/storage/v1/object/public/catalogos/{$fixedFileName}";

        // Update config record with url and timestamp
        DB::connection('pgsql')->table('catalogo_config')
            ->where('id', $config->id)
            ->update([
                'url_supabase'      => $publicUrl,
                'ultima_generacion' => now(),
                'updated_at'        => now(),
            ]);

        Log::info("Catálogo '{$config->nombre}' generado. Productos: {$productos->count()}. URL: {$publicUrl}");
    }
}
