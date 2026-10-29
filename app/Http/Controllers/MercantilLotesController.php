<?php

namespace App\Http\Controllers;

use App\Services\Conciliacion\MercantilBalanceImagen;
use App\Services\Conciliacion\MercantilLotesConciliador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Capturas del "Balance de Transacciones" de Mercantil: concilian el abono del cierre con sus lotes. */
class MercantilLotesController extends Controller
{
    public function __construct(
        private MercantilBalanceImagen $lector,
        private MercantilLotesConciliador $conciliador,
    ) {}

    public function preview(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'titular' => ['required', 'string', 'max:120'],
            'imagenes' => ['required', 'array', 'min:1', 'max:10'],
            'imagenes.*' => ['file', 'extensions:png,jpg,jpeg,webp', 'max:8192'],
        ], [
            'imagenes.required' => 'Elige al menos una captura del balance.',
            'imagenes.*.extensions' => 'Las capturas deben ser imágenes (.png, .jpg).',
        ]);

        $balances = [];
        foreach ($request->file('imagenes') as $img) {
            try {
                $leido = $this->lector->leer($img->getRealPath(), $img->getMimeType() ?: 'image/png');
                $balances[] = ['archivo' => $img->getClientOriginalName(), 'lotes' => $leido['lotes'], 'error' => null];
            } catch (\Throwable $e) {
                $balances[] = ['archivo' => $img->getClientOriginalName(), 'lotes' => [], 'error' => $e->getMessage()];
            }
        }
        $token = (string) Str::uuid();
        $volver = $request->input('volver') === 'lotes' ? 'finanzas.conciliaciones.lotes' : 'finanzas.conciliaciones';
        $request->session()->put('mercantil_lotes.'.$token, ['titular' => $data['titular'], 'balances' => $balances, 'volver' => $volver]);

        return view('finanzas.mercantil_lotes_preview', [
            'token' => $token,
            'titular' => $data['titular'],
            'planes' => $this->planes($balances, $data['titular']),
            'volver' => $volver,
        ]);
    }

    public function aplicar(Request $request): RedirectResponse
    {
        $token = (string) $request->validate(['token' => ['required', 'uuid']])['token'];
        $sesion = $request->session()->pull('mercantil_lotes.'.$token);
        if (! $sesion) {
            return redirect()->route('finanzas.conciliaciones')->with('error', 'La vista previa venció. Vuelve a subir las capturas.');
        }

        $conciliados = 0;
        $parciales = 0;
        $fallidos = [];
        foreach ($this->planes($sesion['balances'], $sesion['titular']) as $p) {
            if ($p['error'] || $p['plan']['error']) {
                continue;
            }
            match ($this->conciliador->aplicarBalance($p['plan'], $request->user()?->id)) {
                'conciliado' => $conciliados++,
                'parcial' => $parciales++,
                default => $fallidos[] = $p['archivo'],
            };
        }

        $partes = [];
        if ($conciliados > 0) {
            $partes[] = $conciliados.' abono(s) de cierre conciliados con todos sus lotes';
        }
        if ($parciales > 0) {
            $partes[] = $parciales.' conciliado(s) en parte: los lotes que faltan en Tesorería quedan en «LIQ del banco sin lote» y se completan solos al registrarlos';
        }

        return redirect()->route($sesion['volver'] ?? 'finanzas.conciliaciones')->with($partes !== [] ? 'success' : 'error', 'Lotes Mercantil: '
            .($partes !== [] ? implode('; ', $partes).'.' : 'no se concilió nada.')
            .($fallidos !== [] ? ' No se pudo con: '.implode(', ', $fallidos).' (otro proceso ya tomó el abono o un lote).' : ''));
    }

    private function planes(array $balances, string $titular): array
    {
        return array_map(fn ($b) => $b + [
            'plan' => $b['error'] ? null : $this->conciliador->planBalance($b['lotes'], $titular),
        ], $balances);
    }
}
