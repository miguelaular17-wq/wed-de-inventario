<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MultisedeImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportController extends Controller
{
    public function create(): View
    {
        $defaultPath = base_path('../ExelMultiSede (2).xlsx');
        if (! is_file($defaultPath)) {
            $defaultPath = database_path('seeders/ExelMultiSede.xlsx');
        }

        return view('admin.import', [
            'defaultPath' => $defaultPath,
        ]);
    }

    public function store(Request $request, MultisedeImportService $import): RedirectResponse
    {
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
        ]);

        $stored = $request->file('excel')->store('imports');
        $path = storage_path('app/'.$stored);
        if (! is_file($path)) {
            $path = storage_path('app/private/'.$stored);
        }

        try {
            $count = $import->importFromExcel($path);
            
            // Invalidar solo las claves de caché que dependen del inventario.
            // Se evita cache:clear global para no destruir la tasa BCV ni las sesiones.
            // Las claves con md5(lastStockUpdate) se invalidan solas al cambiar el timestamp.
            \Illuminate\Support\Facades\Cache::forget('inventario_v2.global_products');
            \Illuminate\Support\Facades\Cache::forget('dashboard.productos_activos');
            \Illuminate\Support\Facades\Cache::forget('dashboard.existencia_por_sede');
            \Illuminate\Support\Facades\Cache::forget('dashboard.movimientos_stats');
            \Illuminate\Support\Facades\Cache::forget('cobranza_resumenes');
        } catch (\Throwable $e) {
            if (is_file($path)) {
                @unlink($path);
            }
            return back()->withErrors(['excel' => 'Error al importar: '.$e->getMessage()]);
        }

        if (is_file($path)) {
            @unlink($path);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('status', "Importación completada: {$count} productos cargados. Los movimientos de requisiciones registrados en la app fueron reaplicados automáticamente sobre el nuevo inventario.");
    }
}
