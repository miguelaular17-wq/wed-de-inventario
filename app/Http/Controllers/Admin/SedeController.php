<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SedeController extends Controller
{
    public function index(): View
    {
        $sedes = config('inventario.sedes_locales');

        return view('admin.sedes.index', [
            'sedes' => $sedes,
            'display' => config('inventario.display'),
        ]);
    }

    public function use(Request $request, string $sede)
    {
        $sedeUpper = strtoupper($sede);
        $allowed = config('inventario.sedes_locales');
        if (! in_array($sedeUpper, $allowed, true)) {
            return back()->withErrors(['sede' => 'Sede inválida.']);
        }

        $request->session()->put('sede_local', $sedeUpper);

        return redirect()->route('ventas.index');
    }
}
