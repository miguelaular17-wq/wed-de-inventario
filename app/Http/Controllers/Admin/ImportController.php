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
        return view('admin.import', [
            'defaultPath' => base_path('../ExelMultiSede (2).xlsx'),
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
        } catch (\Throwable $e) {
            return back()->withErrors(['excel' => 'Error al importar: '.$e->getMessage()]);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('status', "Importación completada: {$count} productos cargados. Los movimientos de requisiciones registrados en la app fueron reaplicados automáticamente sobre el nuevo inventario.");
    }
}
