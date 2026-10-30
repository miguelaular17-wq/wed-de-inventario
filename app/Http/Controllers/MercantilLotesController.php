<?php

namespace App\Http\Controllers;

use App\Services\Conciliacion\MercantilBalanceExcel;
use App\Services\Conciliacion\MercantilLotesConciliador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Excel del "Balance de Transacciones" de Mercantil: concilia los lotes del cierre con su movimiento del banco. */
class MercantilLotesController extends Controller
{
    public function __construct(
        private MercantilBalanceExcel $lector,
        private MercantilLotesConciliador $conciliador,
    ) {}

    public function preview(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'titular' => ['required', 'string', 'max:120'],
            'archivos' => ['required', 'array', 'min:1', 'max:10'],
            'archivos.*' => ['file', 'extensions:xlsx,xls,csv', 'max:8192'],
        ], [
            'archivos.required' => 'Elige al menos un Excel del balance.',
            'archivos.*.extensions' => 'El balance debe ser Excel (.xlsx / .xls) o CSV.',
        ]);

        $balances = [];
        foreach ($request->file('archivos') as $archivo) {
            try {
                $ext = strtolower($archivo->getClientOriginalExtension() ?: 'xlsx');
                $leido = $this->lector->leer($archivo->getRealPath(), $ext);
                $balances[] = ['archivo' => $archivo->getClientOriginalName(), 'lotes' => $leido['lotes'], 'error' => null];
            } catch (\Throwable $e) {
                $balances[] = ['archivo' => $archivo->getClientOriginalName(), 'lotes' => [], 'error' => $e->getMessage()];
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
            return redirect()->route('finanzas.conciliaciones')->with('error', 'La vista previa venció. Vuelve a subir el Excel.');
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
            $partes[] = $conciliados.' cierre(s) conciliados con todos sus lotes';
        }
        if ($parciales > 0) {
            $partes[] = $parciales.' cierre(s) conciliados en parte: los lotes que faltan en Tesorería se concilian solos al registrarlos';
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
