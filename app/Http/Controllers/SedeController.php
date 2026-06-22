<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SedeController extends Controller
{
    public function select(): View|RedirectResponse
    {
        if (auth()->user() && in_array(auth()->user()->role, ['supervisor', 'telefonia'], true)) {
            if (auth()->user()->sede) {
                session()->put('sede_local', strtoupper(auth()->user()->sede));
                return $this->redirectAfterSede();
            }
            return redirect()->route('ventas.index')->withErrors(['error' => 'No tienes permiso para seleccionar sede.']);
        }

        if (session()->has('sede_local')) {
            return $this->redirectAfterSede();
        }

        return view('sede.select', [
            'sedes' => config('inventario.sedes_locales'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (auth()->user() && in_array(auth()->user()->role, ['supervisor', 'telefonia'], true)) {
            return redirect()->route('ventas.index')->withErrors(['error' => 'No tienes permiso para cambiar de sede.']);
        }

        $sedes = config('inventario.sedes_locales');
        $sede = strtoupper((string) $request->input('sede_local', ''));

        if (! in_array($sede, $sedes, true)) {
            return back()->withErrors(['sede_local' => 'Seleccione una sede válida.']);
        }

        $request->session()->put('sede_local', $sede);

        return $this->redirectAfterSede();
    }

    public function change(): RedirectResponse
    {
        if (auth()->user() && in_array(auth()->user()->role, ['supervisor', 'telefonia'], true)) {
            return redirect()->route('ventas.index')->withErrors(['error' => 'No tienes permiso para cambiar de sede.']);
        }

        session()->forget('sede_local');

        return redirect()->route('sede.select');
    }

    /**
     * Redirect the user to the appropriate dashboard after selecting a sede.
     * Comprador and marketing go to their own dashboard; everyone else to ventas.
     */
    private function redirectAfterSede(): RedirectResponse
    {
        $user = auth()->user();

        if ($user && ($user->isComprador() || $user->isMarketing())) {
            return redirect()->route('comprador.dashboard');
        }

        return redirect()->route('ventas.index');
    }
}
