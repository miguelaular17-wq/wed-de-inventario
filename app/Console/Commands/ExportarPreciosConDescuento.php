<?php

namespace App\Console\Commands;

use App\Services\ExportarPreciosDescuentoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ExportarPreciosConDescuento extends Command
{
    protected $signature = 'inventario:exportar-precios
        {--descuento=25 : Porcentaje de descuento}
        {--output= : Ruta del archivo XLSX}';

    protected $description = 'Exporta productos con existencia, precio unitario y precio con descuento';

    public function handle(ExportarPreciosDescuentoService $exportador): int
    {
        $descuento = (float) $this->option('descuento');
        if ($descuento < 0 || $descuento > 100) {
            $this->error('El descuento debe estar entre 0 y 100.');

            return self::FAILURE;
        }

        try {
            [$xlsx, $filename] = $exportador->generarXlsx($descuento);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $output = $this->outputPath($filename);
        File::ensureDirectoryExists(dirname($output));
        File::put($output, $xlsx);

        $this->info('Excel creado.');
        $this->line($output);

        return self::SUCCESS;
    }

    private function outputPath(string $defaultFilename): string
    {
        $output = trim((string) $this->option('output'));
        if ($output === '') {
            return storage_path('app/exports/'.$defaultFilename);
        }

        if (! preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $output)) {
            $output = base_path($output);
        }

        return str_ends_with(strtolower($output), '.xlsx') ? $output : $output.'.xlsx';
    }
}
