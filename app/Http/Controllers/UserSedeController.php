<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class UserSedeController extends Controller
{
    public function edit(): View
    {
        return view('user.sede', [
            'sedes' => config('inventario.sedes_locales'),
            'display' => config('inventario.display'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'sede' => ['required', 'string'],
        ]);

        $sede = strtoupper($data['sede']);
        $allowed = config('inventario.sedes_locales');
        if (! in_array($sede, $allowed, true)) {
            return back()->withErrors(['sede' => 'Sede inválida.']);
        }

        $user = Auth::user();
        $user->sede = $sede;
        $user->save();

        $request->session()->put('sede_local', $sede);

        return redirect()->route('ventas.index')->with('status', 'Sede actualizada.');
    }
}
