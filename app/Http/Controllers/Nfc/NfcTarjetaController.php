<?php

namespace App\Http\Controllers\Nfc;

use App\Http\Controllers\Controller;
use App\Models\NfcRecompensa;
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
        $movimientos = $tarjeta->movimientos()
            ->with(['registrador', 'recompensa'])
            ->limit(40)
            ->get();

        $recompensas = NfcRecompensa::query()->activas()->get();

        return view('nfc.show', [
            'tarjeta' => $tarjeta,
            'urlNfc' => $tarjeta->urlPublica(),
            'movimientos' => $movimientos,
            'recompensas' => $recompensas,
        ]);
    }

    public function recargar(Request $request, NfcTarjeta $tarjeta): RedirectResponse
    {
        $data = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ]);

        $this->nfc->recargarSaldo(
            $tarjeta,
            (float) $data['monto'],
            $data['concepto'] ?? null,
            auth()->id()
        );

        return redirect()
            ->route('nfc.show', $tarjeta)
            ->with('status', 'Recarga aplicada. Nuevo saldo: $'.number_format((float) $tarjeta->fresh()->saldo, 2));
    }

    public function restarSaldo(Request $request, NfcTarjeta $tarjeta): RedirectResponse
    {
        $data = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ]);

        $this->nfc->restarSaldo(
            $tarjeta,
            (float) $data['monto'],
            $data['concepto'] ?? null,
            auth()->id()
        );

        return redirect()
            ->route('nfc.show', $tarjeta)
            ->with('status', 'Saldo descontado. Nuevo saldo: $'.number_format((float) $tarjeta->fresh()->saldo, 2));
    }

    public function puntos(Request $request, NfcTarjeta $tarjeta): RedirectResponse
    {
        $data = $request->validate([
            'puntos' => ['required', 'integer', 'min:1', 'max:1000000'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ]);

        $this->nfc->sumarPuntos(
            $tarjeta,
            (int) $data['puntos'],
            $data['concepto'] ?? null,
            auth()->id()
        );

        return redirect()
            ->route('nfc.show', $tarjeta)
            ->with('status', 'Puntos sumados. Total: '.number_format((int) $tarjeta->fresh()->puntos));
    }

    public function restarPuntos(Request $request, NfcTarjeta $tarjeta): RedirectResponse
    {
        $data = $request->validate([
            'puntos' => ['required', 'integer', 'min:1', 'max:1000000'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ]);

        $this->nfc->restarPuntos(
            $tarjeta,
            (int) $data['puntos'],
            $data['concepto'] ?? null,
            auth()->id()
        );

        return redirect()
            ->route('nfc.show', $tarjeta)
            ->with('status', 'Puntos restados. Total: '.number_format((int) $tarjeta->fresh()->puntos));
    }

    public function canjear(Request $request, NfcTarjeta $tarjeta): RedirectResponse
    {
        $data = $request->validate([
            'recompensa_id' => ['required', 'integer', 'exists:nfc_recompensas,id'],
        ]);

        $recompensa = NfcRecompensa::query()->findOrFail((int) $data['recompensa_id']);
        $this->nfc->canjearRecompensa($tarjeta, $recompensa, auth()->id());

        return redirect()
            ->route('nfc.show', $tarjeta)
            ->with('status', 'Canjeado: '.$recompensa->nombre.'. Puntos restantes: '.number_format((int) $tarjeta->fresh()->puntos));
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
