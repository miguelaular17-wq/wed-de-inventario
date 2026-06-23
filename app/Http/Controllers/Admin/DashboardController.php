<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MovimientoQueryService;
use App\Services\ProductRepository;
use App\Services\VentasCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ProductRepository $products, MovimientoQueryService $movimientos): View
    {
        return view('admin.dashboard', [
            'productCount' => $this->productCount($products),
            'movementStats' => $movimientos->stats(),
            'lastImport' => $products->lastStockUpdate(),
        ]);
    }

    private function productCount(ProductRepository $products): int
    {
        if (config('database.default') === 'pgsql') {
            return \App\Models\V2\Producto::query()->where('activo', true)->count();
        }

        return \App\Models\Product::query()->count();
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
