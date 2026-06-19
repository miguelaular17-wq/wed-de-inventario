<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SedeController extends Controller
{
    public function select(): View|RedirectResponse
    {
        if (session()->has('sede_local')) {
            return redirect()->route('ventas.index');
        }

        return view('sede.select', [
            'sedes' => config('inventario.sedes_locales'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sedes = config('inventario.sedes_locales');
        $sede = strtoupper((string) $request->input('sede_local', ''));

        if (! in_array($sede, $sedes, true)) {
            return back()->withErrors(['sede_local' => 'Seleccione una sede válida.']);
        }

        $request->session()->put('sede_local', $sede);

        return redirect()->route('ventas.index');
    }

    public function change(): RedirectResponse
    {
        session()->forget('sede_local');

        return redirect()->route('sede.select');
    }
}
