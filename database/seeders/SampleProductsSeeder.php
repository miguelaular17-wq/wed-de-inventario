<?php

namespace Database\Seeders;

use App\Services\MultisedeImportService;
use Illuminate\Database\Seeder;

class SampleProductsSeeder extends Seeder
{
    public function run(MultisedeImportService $import): void
    {
        $excel = database_path('seeders/ExelMultiSede.xlsx');
        if (! is_file($excel)) {
            $this->command?->warn('ExelMultiSede.xlsx no encontrado; seeder omitido.');

            return;
        }

        $count = $import->importFromExcel($excel, 25);
        $this->command?->info("SampleProductsSeeder: {$count} productos de ejemplo.");
    }
}
