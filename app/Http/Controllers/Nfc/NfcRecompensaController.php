<?php

namespace App\Http\Controllers\Nfc;

use App\Http\Controllers\Controller;
use App\Models\NfcRecompensa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NfcRecompensaController extends Controller
{
    public function index(): View
    {
        $recompensas = NfcRecompensa::query()
            ->orderByDesc('activa')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('nfc.recompensas', [
            'recompensas' => $recompensas,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'puntos_costo' => ['required', 'integer', 'min:1', 'max:1000000'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activa' => ['nullable', 'boolean'],
        ]);

        NfcRecompensa::create([
            'nombre' => trim($data['nombre']),
            'descripcion' => isset($data['descripcion']) ? trim((string) $data['descripcion']) ?: null : null,
            'puntos_costo' => (int) $data['puntos_costo'],
            'orden' => (int) ($data['orden'] ?? 0),
            'activa' => $request->boolean('activa', true),
        ]);

        return redirect()
            ->route('nfc.recompensas.index')
            ->with('status', 'Recompensa creada.');
    }

    public function update(Request $request, NfcRecompensa $recompensa): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'puntos_costo' => ['required', 'integer', 'min:1', 'max:1000000'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activa' => ['nullable', 'boolean'],
        ]);

        $recompensa->fill([
            'nombre' => trim($data['nombre']),
            'descripcion' => isset($data['descripcion']) ? trim((string) $data['descripcion']) ?: null : null,
            'puntos_costo' => (int) $data['puntos_costo'],
            'orden' => (int) ($data['orden'] ?? 0),
            'activa' => $request->boolean('activa'),
        ]);
        $recompensa->save();

        return redirect()
            ->route('nfc.recompensas.index')
            ->with('status', 'Recompensa actualizada.');
    }

    public function destroy(NfcRecompensa $recompensa): RedirectResponse
    {
        $recompensa->delete();

        return redirect()
            ->route('nfc.recompensas.index')
            ->with('status', 'Recompensa eliminada.');
    }
}
