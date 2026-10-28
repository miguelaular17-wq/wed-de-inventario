<?php

namespace App\Http\Controllers;

use App\Services\Conciliacion\CasheaConciliador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Export de Cashea: se cruza con los abonos de las cuentas de Cashea y, al confirmar, se concilian. */
class CasheaConciliacionController extends Controller
{
    private const DIR = 'cashea';

    public function __construct(private CasheaConciliador $conciliador) {}

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:30720'],
        ], [
            'archivo.mimes' => 'Sube el export de Cashea (.xlsx).',
            'archivo.uploaded' => 'El archivo no llegó al servidor (límite: ' . ini_get('upload_max_filesize') . ').',
        ]);
        $ext = strtolower($request->file('archivo')->getClientOriginalExtension() ?: 'xlsx');
        $ext = $ext === 'txt' ? 'csv' : $ext;
        $token = (string) Str::uuid();
        $request->file('archivo')->storeAs(self::DIR, "{$token}.{$ext}", 'local');
        $plan = ['token' => $token, 'ext' => $ext, 'archivo' => $request->file('archivo')->getClientOriginalName()];

        try {
            $r = $this->calcular($plan);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete(self::DIR . "/{$token}.{$ext}");

            return back()->with('error', 'No se pudo leer el export de Cashea: ' . $e->getMessage());
        }
        if ($r['pagos'] === []) {
            Storage::disk('local')->delete(self::DIR . "/{$token}.{$ext}");

            return back()->with('error', 'No se encontraron pagos. ¿Es el export de Cashea (Fecha, Referencia, Debito bs, Usd…)?');
        }
        $request->session()->put('cashea.' . $token, $plan);

        return view('finanzas.cashea_preview', $r + ['plan' => $plan]);
    }

    public function aplicar(Request $request): RedirectResponse
    {
        $token = (string) $request->validate(['token' => ['required', 'uuid']])['token'];
        $plan = $request->session()->pull('cashea.' . $token);
        $path = self::DIR . '/' . $token . '.' . ($plan['ext'] ?? 'xlsx');
        if (! $plan || ! Storage::disk('local')->exists($path)) {
            return redirect()->route('finanzas.conciliaciones')->with('error', 'La vista previa venció. Vuelve a subir el export de Cashea.');
        }

        $r = $this->calcular($plan);
        $hecho = $this->conciliador->aplicar($r['pagos'], $plan['archivo'], $request->user()?->id);
        // Después de conciliar: así la copia que queda de cada movimiento es la que quedó ligada.
        $copias = $request->boolean('quitar_copias') && $r['desde']
            ? $this->conciliador->quitarCopias($r['desde'], $r['hasta'])
            : ['borradas' => 0, 'respaldo' => null];
        Storage::disk('local')->delete($path);
        $estados = collect($r['pagos'])->countBy('estado');

        return redirect()->route('finanzas.conciliaciones')->with('success', sprintf(
            'Cashea: %d abonos conciliados (Bs %s) en %d ingresos de Tesorería, uno por día. %d ya estaban conciliados, %d para revisar y %d sin abono en el banco.%s',
            $hecho['lineas'], number_format($hecho['monto'], 2, ',', '.'), $hecho['ingresos'],
            $estados['ya_conciliado'] ?? 0, $estados['revisar'] ?? 0, $estados['no_encontrado'] ?? 0,
            $copias['borradas'] > 0 ? sprintf(' Se quitaron %s líneas repetidas del extracto (respaldo: %s).', number_format($copias['borradas'], 0, ',', '.'), $copias['respaldo']) : '',
        ));
    }

    private function calcular(array $plan): array
    {
        ini_set('memory_limit', '2G');
        ini_set('max_execution_time', '300');
        $filas = $this->conciliador->leer(Storage::disk('local')->path(self::DIR . "/{$plan['token']}.{$plan['ext']}"), $plan['ext']);

        return $this->conciliador->verificar($filas);
    }
}
