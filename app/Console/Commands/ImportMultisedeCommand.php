<?php

namespace App\Console\Commands;

use App\Services\InventarioV2Repository;
use App\Services\MultisedeImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportMultisedeCommand extends Command
{
    protected $signature = 'inventario:import
        {path? : Ruta al Excel multisede}
        {--sample=25 : Cantidad de productos de ejemplo (0 = todos)}';

    protected $description = 'Importa ExelMultiSede.xlsx a PostgreSQL o SQLite';

    public function handle(MultisedeImportService $import): int
    {
        $path = $this->argument('path');
        if (! $path) {
            $path = base_path('../ExelMultiSede (2).xlsx');
            if (! is_file($path)) {
                $path = database_path('seeders/ExelMultiSede.xlsx');
            }
        }

        if (! is_file($path)) {
            $this->error("No se encontró: {$path}");

            return self::FAILURE;
        }

        $limit = (int) $this->option('sample');
        $limit = $limit > 0 ? $limit : null;

        $this->info("Importando {$path}".($limit ? " ({$limit} productos de ejemplo)" : ' (completo)').'...');
        $count = $import->importFromExcel($path, $limit);
        $this->info("Importados {$count} productos.");

        return self::SUCCESS;
    }
}
