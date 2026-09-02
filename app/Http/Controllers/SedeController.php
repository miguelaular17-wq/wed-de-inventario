<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SedeController extends Controller
{
    public function select(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user && ! $user->requiresSede()) {
            return redirect('/');
        }

        if ($user && $user->sedeIsLocked()) {
            if ($user->sede) {
                session()->put('sede_local', strtoupper($user->sede));
                return $this->redirectAfterSede();
            }
            abort(403, 'No tienes una sede asignada. Por favor, contacta al administrador.');
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
        if (auth()->user()?->sedeIsLocked()) {
            return $this->redirectAfterSede()->withErrors(['error' => 'No tienes permiso para cambiar de sede.']);
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
        if (auth()->user()?->sedeIsLocked()) {
            return $this->redirectAfterSede()->withErrors(['error' => 'No tienes permiso para cambiar de sede.']);
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

        if ($user && $user->isTecnico()) {
            return redirect()->route('servicio.ordenes.index');
        }

        return redirect()->route('ventas.index');
    }
}
