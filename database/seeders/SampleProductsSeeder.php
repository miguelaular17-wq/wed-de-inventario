<?php

namespace Database\Seeders;

use App\Services\MultisedeImportService;
use Illuminate\Database\Seeder;

class SampleProductsSeeder extends Seeder
{
    public function run(MultisedeImportService $import): void
    {
        $excel = base_path('../ExelMultiSede (2).xlsx');
        if (! is_file($excel)) {
            $this->command?->warn('ExelMultiSede (2).xlsx no encontrado; seeder omitido.');

            return;
        }

        $count = $import->importFromExcel($excel, 25);
        $this->command?->info("SampleProductsSeeder: {$count} productos de ejemplo.");
    }
}
