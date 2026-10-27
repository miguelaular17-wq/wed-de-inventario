<?php

namespace App\Http\Controllers;

use App\Services\Conciliacion\PagosManualesVerificador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Reporte de pagos manuales de Cloud: se verifica cada pago contra los abonos de todas las cuentas
 * y, al confirmar, se concilian los que aparecen con su referencia y monto.
 */
class PagosManualesController extends Controller
{
    private const DIR = 'pagos_manuales';

    public function __construct(
        private PagosManualesVerificador $verificador,
    ) {}

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xls,xlsx,csv,txt', 'max:20480'],
        ], [
            'archivo.mimes' => 'El reporte debe ser el Excel de pagos manuales de Cloud (.xls / .xlsx).',
            'archivo.uploaded' => 'El archivo no llegó al servidor (límite: ' . ini_get('upload_max_filesize') . ').',
        ]);
        $ext = strtolower($request->file('archivo')->getClientOriginalExtension() ?: 'xls');
        $ext = $ext === 'txt' ? 'csv' : $ext;
        $token = (string) Str::uuid();
        $path = $request->file('archivo')->storeAs(self::DIR, "{$token}.{$ext}", 'local');
        $plan = ['token' => $token, 'ext' => $ext, 'archivo' => $request->file('archivo')->getClientOriginalName()];

        try {
            $r = $this->calcular($plan);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            return back()->with('error', 'No se pudo leer el reporte: ' . $e->getMessage());
        }
        if ($r['items'] === []) {
            Storage::disk('local')->delete($path);

            return back()->with('error', 'No se encontraron pagos. ¿Es el «Reporte específico de pagos manuales» de Cloud (NRO, Banco Receptor, Ref, Monto Bs.)?');
        }
        $request->session()->put('pagos_manuales.' . $token, $plan);

        return view('finanzas.pagos_manuales_preview', $r + ['plan' => $plan]);
    }

    public function aplicar(Request $request): RedirectResponse
    {
        $token = (string) $request->validate(['token' => ['required', 'uuid']])['token'];
        $plan = $request->session()->pull('pagos_manuales.' . $token);
        if (! $plan || ! Storage::disk('local')->exists(self::DIR . "/{$token}.{$plan['ext']}")) {
            return redirect()->route('finanzas.conciliaciones')->with('error', 'La vista previa venció. Vuelve a subir el reporte de pagos manuales.');
        }

        // Se vuelve a verificar contra lo que está pendiente ahora.
        $r = $this->calcular($plan);
        $hechos = $this->verificador->aplicar($r['items'], $plan['archivo'], $request->user()?->id);
        Storage::disk('local')->delete(self::DIR . "/{$token}.{$plan['ext']}");

        $porEstado = collect($r['items'])->countBy('estado');

        return redirect()->route('finanzas.conciliaciones')->with('success', sprintf(
            'Pagos manuales: %d conciliados en bolívares y %d en divisas%s. %d ya estaban conciliados, %d para revisar y %d sin abono en ningún banco.',
            $hechos['bs'], $hechos['usd'], $hechos['ocupados'] ? " ({$hechos['ocupados']} los tomó otro proceso mientras tanto)" : '',
            $porEstado['ya_conciliado'] ?? 0, $porEstado['revisar'] ?? 0, $porEstado['no_encontrado'] ?? 0,
        ));
    }

    /** @param  array{token: string, ext: string}  $plan */
    private function calcular(array $plan): array
    {
        ini_set('memory_limit', '1G');
        $pagos = $this->verificador->leer(Storage::disk('local')->path(self::DIR . "/{$plan['token']}.{$plan['ext']}"), $plan['ext']);

        return $this->verificador->verificar($pagos);
    }
}
