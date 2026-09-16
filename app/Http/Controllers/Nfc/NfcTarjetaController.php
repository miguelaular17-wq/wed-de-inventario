<?php

namespace App\Http\Controllers\Nfc;

use App\Http\Controllers\Controller;
use App\Models\NfcTarjeta;
use App\Services\Nfc\NfcTarjetaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NfcTarjetaController extends Controller
{
    public function __construct(private NfcTarjetaService $nfc)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $query = NfcTarjeta::query()->with('asignador')->orderByDesc('id');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like, $q) {
                $w->where('cliente_nombre', 'like', $like)
                    ->orWhere('cliente_cedula', 'like', $like)
                    ->orWhere('cliente_telefono', 'like', $like)
                    ->orWhere('token', 'like', $like)
                    ->orWhere('uid', 'like', $like);
                $uidDigits = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $q) ?? '');
                if ($uidDigits !== '') {
                    $w->orWhere('uid', $uidDigits);
                }
            });
        }

        $tarjetas = $query->paginate(30)->withQueryString();

        return view('nfc.index', [
            'tarjetas' => $tarjetas,
            'q' => $q,
            'kpis' => [
                'activas' => NfcTarjeta::query()->where('estado', NfcTarjeta::ACTIVA)->count(),
                'inactivas' => NfcTarjeta::query()->where('estado', NfcTarjeta::INACTIVA)->count(),
                'total' => NfcTarjeta::query()->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cliente_nombre' => ['required', 'string', 'max:160'],
            'cliente_cedula' => ['nullable', 'string', 'max:32'],
            'cliente_telefono' => ['nullable', 'string', 'max:40'],
            'cliente_email' => ['nullable', 'email', 'max:160'],
            'uid' => ['nullable', 'string', 'max:64'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $tarjeta = $this->nfc->asignar($data, auth()->id());

        return redirect()
            ->route('nfc.show', $tarjeta)
            ->with('status', 'Tarjeta asignada. Copia o escribe la URL en el chip NFC.');
    }

    public function show(NfcTarjeta $tarjeta): View
    {
        $tarjeta->load('asignador');

        return view('nfc.show', [
            'tarjeta' => $tarjeta,
            'urlNfc' => $tarjeta->urlPublica(),
        ]);
    }

    public function update(Request $request, NfcTarjeta $tarjeta): RedirectResponse
    {
        $data = $request->validate([
            'cliente_nombre' => ['required', 'string', 'max:160'],
            'cliente_cedula' => ['nullable', 'string', 'max:32'],
            'cliente_telefono' => ['nullable', 'string', 'max:40'],
            'cliente_email' => ['nullable', 'email', 'max:160'],
            'uid' => ['nullable', 'string', 'max:64'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->nfc->actualizarCliente($tarjeta, $data);

        return redirect()
            ->route('nfc.show', $tarjeta)
            ->with('status', 'Datos del cliente actualizados.');
    }

    public function desactivar(NfcTarjeta $tarjeta): RedirectResponse
    {
        $this->nfc->desactivar($tarjeta);

        return redirect()
            ->route('nfc.index')
            ->with('status', 'Tarjeta desactivada. El enlace ya no abrirá la ficha.');
    }

    public function reactivar(NfcTarjeta $tarjeta): RedirectResponse
    {
        $this->nfc->reactivar($tarjeta);

        return redirect()
            ->route('nfc.show', $tarjeta)
            ->with('status', 'Tarjeta reactivada.');
    }
}
