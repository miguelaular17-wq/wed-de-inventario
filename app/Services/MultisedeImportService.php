<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class MultisedeImportService
{
    public function __construct(
        private InventarioV2Repository $v2,
    ) {}

    public function importFromExcel(string $excelPath, ?int $limit = null): int
    {
        set_time_limit(600);

        $jsonPath = storage_path('app/import_multisede.json');
        $script = base_path('scripts/export_multisede_json.py');
        $python = $this->resolvePythonBinary();

        if (! File::isDirectory(dirname($jsonPath))) {
            File::makeDirectory(dirname($jsonPath), 0755, true);
        }

        $process = new Process([$python, $script, $excelPath, $jsonPath]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $rows = json_decode(File::get($jsonPath), true);
        if (! is_array($rows)) {
            throw new \RuntimeException('JSON de importación inválido.');
        }

        if ($limit !== null && $limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        $count = $this->importFromArray($rows);
        unset($rows);

        return $count;
    }

    public function importFromArray(array $rows): int
    {
        if (config('database.default') === 'pgsql') {
            return $this->v2->importFromArray($rows);
        }

        return $this->importSqlite($rows);
    }

    private function importSqlite(array $rows): int
    {
        $count = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use ($rows, &$count) {
            \App\Models\ProductSedeMetric::query()->delete();
            \App\Models\Product::query()->delete();

            foreach ($rows as $row) {
                $product = \App\Models\Product::create([
                    'cod_centro' => (string) ($row['cod_centro'] ?? ''),
                    'producto' => (string) ($row['producto'] ?? ''),
                    'categoria' => (string) ($row['categoria'] ?? ''),
                    'subcategoria' => (string) ($row['subcategoria'] ?? ''),
                    'proveedor' => (string) ($row['proveedor'] ?? ''),
                ]);

                foreach ($row['sedes'] ?? [] as $sede => $metrics) {
                    \App\Models\ProductSedeMetric::create([
                        'product_id' => $product->id,
                        'sede' => $sede,
                        'existencia' => (int) ($metrics['existencia'] ?? 0),
                        'ventas_60d' => (float) ($metrics['ventas_60d'] ?? 0),
                        'ultima_venta' => $metrics['ultima_venta'] ?? null,
                        'promedio_15d' => (int) ($metrics['promedio_15d'] ?? 0),
                    ]);
                }
                $count++;
            }
        });

        return $count;
    }

    private function resolvePythonBinary(): string
    {
        $candidates = [
            base_path('../.venv/Scripts/python.exe'),
            base_path('../.venv/bin/python'),
            'python',
            'python3',
        ];

        foreach ($candidates as $bin) {
            if ($bin === 'python' || $bin === 'python3') {
                return $bin;
            }
            if (File::exists($bin)) {
                return $bin;
            }
        }

        return 'python';
    }
}
