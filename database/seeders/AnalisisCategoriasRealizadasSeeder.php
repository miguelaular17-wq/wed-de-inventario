<?php

namespace Database\Seeders;

use App\Services\AnalisisCategoriasImportador;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Marca en "Análisis de categorías" lo ya realizado en CENTRO, DORAL, VIRTUDES y ZAMORA
 * (Excel "Categorías de Artículos", una hoja por sede → data/analisis_categorias_realizadas.json).
 *
 *   php artisan db:seed --class=AnalisisCategoriasRealizadasSeeder
 *
 * No borra ni duplica: lo que ya estaba marcado se queda igual.
 */
class AnalisisCategoriasRealizadasSeeder extends Seeder
{
    public function run(AnalisisCategoriasImportador $importador): void
    {
        if (! Schema::hasTable('analisis_categoria_checks')) {
            $this->command?->warn('Tabla analisis_categoria_checks no existe. Corre migraciones primero.');

            return;
        }

        $path = database_path('seeders/data/analisis_categorias_realizadas.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($data) || $data === []) {
            $this->command?->error('No se encontró data/analisis_categorias_realizadas.json');

            return;
        }

        $resuelto = $importador->resolver($data);
        $nuevas = $importador->guardar($resuelto);

        foreach ($resuelto as $sede => $r) {
            $this->command?->info(sprintf(
                '%s: %d subcategorías marcadas (%d nuevas).',
                $sede,
                count($r['marcas']),
                $nuevas[$sede] ?? 0,
            ));
            if ($r['no_encontradas'] !== []) {
                $this->command?->warn('  No están en el catálogo: ' . implode(', ', $r['no_encontradas']));
            }
        }
    }
}
