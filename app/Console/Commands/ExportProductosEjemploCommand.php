<?php

namespace App\Console\Commands;

use App\Services\InventarioV2Repository;
use App\Services\MultisedeImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportProductosEjemploCommand extends Command
{
    protected $signature = 'inventario:export-ejemplo
        {--limit=25 : Número de productos}
        {--import : Importar muestra desde Excel si la BD está vacía}';

    protected $description = 'Exporta productos de ejemplo a JSON y CSV (formato ExelMultiSede)';

    public function handle(
        InventarioV2Repository $v2,
        MultisedeImportService $import,
    ): int {
        $limit = max(1, (int) $this->option('limit'));
        $excel = base_path('../ExelMultiSede (2).xlsx');
        if (! is_file($excel)) {
            $excel = database_path('seeders/ExelMultiSede.xlsx');
        }

        if ($this->option('import') || ($v2->isActive() && \App\Models\V2\Producto::query()->count() === 0)) {
            if (! is_file($excel)) {
                $this->error('No hay productos en BD y falta ExelMultiSede.xlsx');

                return self::FAILURE;
            }
            $this->info("Importando {$limit} productos de ejemplo...");
            $import->importFromExcel($excel, $limit);
        }

        $rows = $v2->isActive()
            ? $v2->sampleForExport($limit)
            : $this->sampleFromSqlite($limit);

        if ($rows === []) {
            $this->error('No hay productos para exportar. Use --import o inventario:import');

            return self::FAILURE;
        }

        $dir = storage_path('app/exports');
        File::ensureDirectoryExists($dir);

        $jsonPath = $dir.'/productos_ejemplo.json';
        $csvPath = $dir.'/productos_ejemplo.csv';

        File::put($jsonPath, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $csv = $this->toCsvMultisede($rows);
        File::put($csvPath, $csv);

        $this->info("Exportados {$limit} productos:");
        $this->line("  JSON: {$jsonPath}");
        $this->line("  CSV:  {$csvPath}");

        return self::SUCCESS;
    }

    private function sampleFromSqlite(int $limit): array
    {
        return \App\Models\Product::query()
            ->with('sedeMetrics')
            ->orderBy('cod_centro')
            ->limit($limit)
            ->get()
            ->map(function ($p) {
                $sedes = [];
                foreach (config('inventario.sedes_stock') as $sede) {
                    $m = $p->sedeMetrics->firstWhere('sede', $sede);
                    $sedes[$sede] = [
                        'existencia' => $m?->existencia ?? 0,
                        'promedio_15d' => $m?->promedio_15d ?? 0,
                        'ventas_60d' => $m?->ventas_60d ?? 0,
                    ];
                }

                return [
                    'cod_centro' => $p->cod_centro,
                    'producto' => $p->producto,
                    'categoria' => $p->categoria,
                    'subcategoria' => $p->subcategoria,
                    'proveedor' => $p->proveedor,
                    'sedes' => $sedes,
                ];
            })
            ->all();
    }

    private function toCsvMultisede(array $rows): string
    {
        $display = config('inventario.display');
        $headers = ['PRODUCTO', 'COD CENTRO', 'Categoría', 'Subcategoría', 'Proveedor'];
        foreach ($display as $sede => $label) {
            $headers[] = "{$label} existencia";
            if ($sede !== 'JRZ') {
                $headers[] = "{$label} ventas";
            }
            $headers[] = "{$label} promedio 15 días (60d)";
        }

        $lines = [implode(';', $headers)];
        foreach ($rows as $row) {
            $line = [
                $row['producto'],
                $row['cod_centro'],
                $row['categoria'],
                $row['subcategoria'],
                $row['proveedor'] ?? '',
            ];
            foreach ($display as $sede => $label) {
                $m = $row['sedes'][$sede] ?? [];
                $line[] = $m['existencia'] ?? 0;
                if ($sede !== 'JRZ') {
                    $line[] = $m['ventas_60d'] ?? 0;
                }
                $line[] = $m['promedio_15d'] ?? 0;
            }
            $lines[] = implode(';', $line);
        }

        return implode("\n", $lines)."\n";
    }
}
