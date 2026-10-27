<?php

namespace App\Http\Controllers;

use App\Services\Conciliacion\TransferenciasDivisasImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Documento adicional para conciliar: PDF de transferencias en divisas del proveedor de cambio.
 * Primero se muestra la vista previa del match; al confirmar se registran las compras de divisas.
 */
class ConciliacionDivisasController extends Controller
{
    private const DIR = 'conciliacion_divisas';

    public function __construct(
        private TransferenciasDivisasImporter $importer,
    ) {}

    public function preview(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf,xlsx,xls', 'max:30720'],
            'banco' => ['required', 'string', 'max:80'],
            'titular' => ['required', 'string', 'max:120'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ], [
            'archivo.mimes' => 'El documento debe ser el PDF de transferencias o el Excel de pagos móviles C2P.',
            'archivo.uploaded' => 'El PDF no llegó al servidor: pesa más de lo permitido (límite actual: '
                . ini_get('upload_max_filesize') . '). Hay que subir upload_max_filesize y post_max_size en la configuración de PHP.',
            'archivo.max' => 'El PDF no puede pesar más de 30 MB.',
        ]);

        $token = (string) Str::uuid();
        $ext = strtolower($request->file('archivo')->getClientOriginalExtension() ?: 'pdf');
        $ext = in_array($ext, ['pdf', 'xlsx', 'xls'], true) ? $ext : 'pdf';
        $path = $request->file('archivo')->storeAs(self::DIR, $token . '.' . $ext, 'local');

        $plan = [
            'token' => $token,
            'ext' => $ext,
            'archivo' => $request->file('archivo')->getClientOriginalName(),
            'banco' => $data['banco'],
            'titular' => $data['titular'],
            'fecha_desde' => $data['fecha_desde'] ?? null,
            'fecha_hasta' => $data['fecha_hasta'] ?? null,
        ];

        $resultado = $this->calcular($path, $plan);
        if ($resultado['transferencias'] === []) {
            Storage::disk('local')->delete($path);

            return back()->with('error', $plan['ext'] === 'pdf'
                ? 'No se encontraron transferencias en el PDF. ¿Es el reporte de transferencias en divisas (Cloud Mai)?'
                : 'No se encontraron pagos en el Excel. ¿Es el reporte de pagos móviles C2P de Cloud Mai (columnas Pago, Fecha, Importe, Monto USD)?');
        }

        $request->session()->put('conciliacion_divisas.' . $token, $plan);

        return view('finanzas.conciliacion_divisas_preview', $resultado + ['plan' => $plan]);
    }

    public function aplicar(Request $request): RedirectResponse
    {
        $token = (string) $request->validate(['token' => ['required', 'uuid']])['token'];
        $plan = $request->session()->pull('conciliacion_divisas.' . $token);
        $path = self::DIR . '/' . $token . '.' . ($plan['ext'] ?? 'pdf');

        if (! $plan || ! Storage::disk('local')->exists($path)) {
            return redirect()->route('finanzas.conciliaciones')
                ->with('error', 'La vista previa venció. Vuelve a subir el documento.');
        }

        // Se vuelve a emparejar contra lo que está pendiente ahora (puede haber cambiado desde la vista previa).
        $resultado = $this->calcular($path, $plan);
        $hechas = $this->importer->aplicar($resultado['pares']);
        Storage::disk('local')->delete($path);

        return redirect()
            ->route('finanzas.conciliaciones', array_filter([
                'banco_filtro' => $plan['banco'],
                'titular_filtro' => $plan['titular'],
                'fecha_desde' => $plan['fecha_desde'] ?? $resultado['desde'],
                'fecha_hasta' => $plan['fecha_hasta'] ?? $resultado['hasta'],
            ]))
            ->with('success', sprintf(
                ($plan['ext'] ?? 'pdf') === 'pdf'
                    ? 'Transferencias en divisas: %d compras de divisas registradas y conciliadas. %d transferencias quedaron sin pareja en el banco.'
                    : 'Pagos móviles C2P: %d compras de divisas registradas y conciliadas. %d pagos quedaron sin pareja en el banco.',
                $hechas,
                count($resultado['sin_pareja']),
            ));
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function calcular(string $path, array $plan): array
    {
        ini_set('memory_limit', '1G');
        ini_set('max_execution_time', '300');

        $ext = $plan['ext'] ?? 'pdf';
        $transferencias = $ext === 'pdf'
            ? $this->importer->leerPdf(Storage::disk('local')->path($path))
            : $this->importer->leerExcel(Storage::disk('local')->path($path), $ext);
        $fechas = array_column($transferencias, 'fecha');
        $desde = $plan['fecha_desde'] ?? ($fechas ? date('Y-m-d', strtotime(min($fechas) . ' -3 days')) : null);
        $hasta = $plan['fecha_hasta'] ?? ($fechas ? date('Y-m-d', strtotime(max($fechas) . ' +3 days')) : null);

        $r = $transferencias === []
            ? ['pares' => [], 'sin_pareja' => [], 'ya_registradas' => [], 'lineas_pendientes' => 0]
            : $this->importer->emparejar($transferencias, $plan['banco'], $plan['titular'], $desde, $hasta);

        return $r + [
            'transferencias' => $transferencias,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }
}
