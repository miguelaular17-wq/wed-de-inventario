<?php

namespace App\Http\Controllers;

use App\Services\Conciliacion\MercantilBalanceExcel;
use App\Services\Conciliacion\MercantilLiqBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Excel del "Balance de Transacciones" de Mercantil: cada lote entra como LIQ del banco y se concilia con su lote de Tesorería. */
class MercantilLotesController extends Controller
{
    public function __construct(
        private MercantilBalanceExcel $lector,
        private MercantilLiqBalance $liq,
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

        $ligadas = 0;
        $sinLote = 0;
        // Se recalcula: si entre la vista previa y la confirmación alguien concilió un lote, no se pisa.
        foreach ($this->planes($sesion['balances'], $sesion['titular']) as $p) {
            if ($p['error']) {
                continue;
            }
            $r = $this->liq->aplicar($p['filas'], $sesion['titular']);
            $ligadas += $r['ligadas'];
            $sinLote += $r['sin_lote'];
        }

        $partes = [];
        if ($ligadas > 0) {
            $partes[] = $ligadas.' lote(s) conciliados con su LIQ';
        }
        if ($sinLote > 0) {
            $partes[] = $sinLote.' LIQ sin su lote en Tesorería: quedan en «LIQ del banco sin lote» para conciliarlas o registrar el lote desde ahí';
        }

        return redirect()->route($sesion['volver'] ?? 'finanzas.conciliaciones')->with($partes !== [] ? 'success' : 'error', 'Balance Mercantil: '
            .($partes !== [] ? implode('; ', $partes).'.' : 'no había nada nuevo que cargar (esos lotes ya estaban).'));
    }

    private function planes(array $balances, string $titular): array
    {
        return array_map(fn ($b) => $b + [
            'filas' => $b['error'] ? [] : $this->liq->plan($b['lotes'], $titular),
        ], $balances);
    }
}
