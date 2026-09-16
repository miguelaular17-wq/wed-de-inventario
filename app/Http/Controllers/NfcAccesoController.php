<?php

namespace App\Http\Controllers;

use App\Models\NfcTarjeta;
use App\Services\Nfc\NfcTarjetaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Entrada pública del chip NFC: exige usuario logueado y permiso NFC.
 */
class NfcAccesoController extends Controller
{
    public function __construct(private NfcTarjetaService $nfc)
    {
    }

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $tarjeta = NfcTarjeta::query()->where('token', $token)->firstOrFail();

        if (! $tarjeta->isActiva()) {
            abort(410, 'Esta tarjeta NFC está inactiva.');
        }

        if (! $request->user()->canAccess('nfc')) {
            return redirect('/')
                ->with('error', 'Tu usuario no tiene permiso para ver fichas NFC.');
        }

        $this->nfc->registrarAcceso($tarjeta);
        $tarjeta->load('asignador');

        return view('nfc.acceso', [
            'tarjeta' => $tarjeta,
            'urlNfc' => $tarjeta->urlPublica(),
        ]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $tarjeta = NfcTarjeta::query()->where('token', $token)->firstOrFail();

        if (! $tarjeta->isActiva()) {
            abort(410, 'Esta tarjeta NFC está inactiva.');
        }

        if (! $request->user()->canAccess('nfc')) {
            return redirect('/')->with('error', 'Sin permiso para editar.');
        }

        $data = $request->validate([
            'cliente_nombre' => ['required', 'string', 'max:160'],
            'cliente_cedula' => ['nullable', 'string', 'max:32'],
            'cliente_telefono' => ['nullable', 'string', 'max:40'],
            'cliente_email' => ['nullable', 'email', 'max:160'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->nfc->actualizarCliente($tarjeta, $data);

        return redirect()
            ->route('nfc.acceso', $tarjeta->token)
            ->with('status', 'Datos del cliente guardados.');
    }
}
