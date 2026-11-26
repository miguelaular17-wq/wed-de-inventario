<?php

namespace App\Http\Controllers;

use App\Services\Conciliacion\PagosMovilesCloudVerificador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Reporte de pago móvil Cloud (Banesco / BDV): se verifica contra abonos del extracto
 * y, al confirmar, se registran como ingresos de Tesorería conciliados.
 */
class PagosMovilesCloudController extends Controller
{
    private const DIR = 'pagos_moviles_cloud';

    public function __construct(
        private PagosMovilesCloudVerificador $verificador,
    ) {}

    public function preview(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xls,xlsx,csv,txt', 'max:20480'],
            'banco' => ['required', 'string', 'max:80'],
            'titular' => ['required', 'string', 'max:120'],
        ], [
            'archivo.mimes' => 'El reporte debe ser el Excel de pago móvil de Cloud (.xls / .xlsx).',
            'archivo.uploaded' => 'El archivo no llegó al servidor (límite: ' . ini_get('upload_max_filesize') . ').',
        ]);

        $ext = strtolower($request->file('archivo')->getClientOriginalExtension() ?: 'xls');
        $ext = $ext === 'txt' ? 'csv' : $ext;
        $token = (string) Str::uuid();
        $request->file('archivo')->storeAs(self::DIR, "{$token}.{$ext}", 'local');
        $plan = [
            'token' => $token,
            'ext' => $ext,
            'archivo' => $request->file('archivo')->getClientOriginalName(),
            'banco' => $data['banco'],
            'titular' => $data['titular'],
        ];

        try {
            $r = $this->calcular($plan);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete(self::DIR . "/{$token}.{$ext}");

            return back()->with('error', 'No se pudo leer el reporte: ' . $e->getMessage());
        }

        if ($r['items'] === []) {
            Storage::disk('local')->delete(self::DIR . "/{$token}.{$ext}");

            return back()->with('error', 'No se encontraron pagos. ¿Es el reporte de pago móvil de Cloud (NRO, Fecha, Referencia Pago, Monto) de Banesco o Venezuela?');
        }

        $request->session()->put('pagos_moviles_cloud.' . $token, $plan);

        return view('finanzas.pagos_moviles_cloud_preview', $r + ['plan' => $plan]);
    }

    public function aplicar(Request $request): RedirectResponse
    {
        $token = (string) $request->validate(['token' => ['required', 'uuid']])['token'];
        $plan = $request->session()->pull('pagos_moviles_cloud.' . $token);
        $path = self::DIR . '/' . $token . '.' . ($plan['ext'] ?? 'xls');
        if (! $plan || ! Storage::disk('local')->exists($path)) {
            return redirect()->route('finanzas.conciliaciones')
                ->with('error', 'La vista previa venció. Vuelve a subir el reporte de pago móvil.');
        }

        $r = $this->calcular($plan);
        $hechos = $this->verificador->aplicar($r['items'], $plan['archivo'], $request->user()?->id);
        Storage::disk('local')->delete($path);
        $porEstado = collect($r['items'])->countBy('estado');

        return redirect()
            ->route('finanzas.conciliaciones', array_filter([
                'banco_filtro' => $plan['banco'],
                'titular_filtro' => $plan['titular'],
                'fecha_desde' => $r['desde'],
                'fecha_hasta' => $r['hasta'],
            ]))
            ->with('success', sprintf(
                'Pago móvil Cloud (%s · %s): %d conciliados%s. %d ya estaban conciliados, %d para revisar y %d sin abono en el banco.',
                $plan['banco'],
                $plan['titular'],
                $hechos['hechos'],
                $hechos['ocupados'] ? " ({$hechos['ocupados']} los tomó otro proceso mientras tanto)" : '',
                $porEstado['ya_conciliado'] ?? 0,
                $porEstado['revisar'] ?? 0,
                $porEstado['no_encontrado'] ?? 0,
            ));
    }

    /** @param  array{token: string, ext: string, banco: string, titular: string}  $plan */
    private function calcular(array $plan): array
    {
        ini_set('memory_limit', '1G');
        $pagos = $this->verificador->leer(
            Storage::disk('local')->path(self::DIR . "/{$plan['token']}.{$plan['ext']}"),
            $plan['ext']
        );

        return $this->verificador->verificar($pagos, $plan['banco'], $plan['titular']);
    }
}
