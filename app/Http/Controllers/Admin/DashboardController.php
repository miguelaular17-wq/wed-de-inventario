<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use App\Services\ProductRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ProductRepository $products, DashboardStatsService $stats): View
    {
        $stockBySede = $stats->existenciaPorSede();

        $sedes = config('inventario.sedes_stock');
        $chartData = [];
        foreach ($sedes as $s) {
            $chartData[$s] = (int) ($stockBySede[strtoupper($s)] ?? 0);
        }

        return view('admin.dashboard', [
            'productCount'  => $stats->productosActivos(),
            'movementStats' => $stats->movimientosStats(),
            'lastImport'    => $products->lastStockUpdate(),
            'chartData'     => $chartData,
        ]);
    }


    public function clearCache(): \Illuminate\Http\RedirectResponse
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            
            // Re-create cache folders just in case view:clear or cache:clear deleted them
            if (!is_dir(storage_path('framework/cache/data'))) {
                @mkdir(storage_path('framework/cache/data'), 0775, true);
            }
            if (!is_dir(storage_path('framework/views'))) {
                @mkdir(storage_path('framework/views'), 0775, true);
            }
            if (!is_dir(storage_path('framework/sessions'))) {
                @mkdir(storage_path('framework/sessions'), 0775, true);
            }
            
            // Clean up old uploads files if any remains
            $importDir = storage_path('app/imports');
            if (is_dir($importDir)) {
                $files = glob($importDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }

            // Clean up temporary JSON if any remains
            $tempJson = storage_path('app/import_multisede.json');
            if (is_file($tempJson)) {
                @unlink($tempJson);
            }

            return back()->with('status', '¡Caché de la aplicación, vistas compiladas y archivos temporales liberados con éxito!');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Error al liberar memoria: ' . $e->getMessage()]);
        }
    }
}
