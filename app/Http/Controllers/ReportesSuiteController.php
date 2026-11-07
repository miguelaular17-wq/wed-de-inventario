<?php

namespace App\Http\Controllers;

use App\Services\ReportesSuiteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Reportes de la facturadora (Suite) en la web: facturas, pagos, descuentos, créditos y cuadre de caja. */
class ReportesSuiteController extends Controller
{
    /** Filtros extra de cada reporte (además de sede y fechas). */
    private const FILTROS = [
        'facturas' => ['cliente'],
        'pagos' => ['metodo', 'cuenta', 'cliente', 'usuario', 'tipo_documento'],
        'descuentos' => ['metodo', 'motivo'],
        'creditos' => ['cliente', 'solo_devoluciones'],
        'cuadre' => ['usuario', 'mostrar_total'],
    ];

    /** Filas que se pintan en pantalla; el PDF lleva todas. */
    private const LIMITE_PANTALLA = 1500;

    public function __construct(private ReportesSuiteService $reportes) {}

    public function show(Request $request, string $reporte): View|Response
    {
        abort_unless(isset(ReportesSuiteService::REPORTES[$reporte]), 404);
        $filtros = $this->filtros($request, $reporte);
        $resultado = $this->reportes->generar($reporte, $filtros);
        $datos = [
            'reporte' => $reporte,
            'titulo' => ReportesSuiteService::REPORTES[$reporte],
            'filtros' => $filtros,
            'extra' => self::FILTROS[$reporte],
            'opciones' => $this->reportes->opciones($reporte, $filtros),
            'sedes' => $this->sedesPermitidas($request),
            'etiquetas' => config('inventario.display', []),
            'resultado' => $resultado,
            'reportes' => ReportesSuiteService::REPORTES,
        ];

        if ($request->query('formato') === 'pdf') {
            @ini_set('memory_limit', '512M');
            @set_time_limit(300);

            return Pdf::loadView('reportes.suite.pdf', $datos + [
                'logo' => $this->logo(),
                'generadoPor' => $request->user()?->name,
            ])->setPaper('letter', count($resultado['columnas']) > 8 ? 'landscape' : 'portrait')
                ->stream(str_replace(' ', '_', strtolower($reporte)).'_'.$filtros['desde'].'_'.$filtros['hasta'].'.pdf');
        }

        $datos['resultado']['filas'] = array_slice($resultado['filas'], 0, self::LIMITE_PANTALLA);
        $datos['limite'] = self::LIMITE_PANTALLA;

        return view('reportes.suite.show', $datos);
    }

    public function detalleFactura(Request $request, string $sede, string $numero): JsonResponse
    {
        $sede = strtoupper($sede);
        abort_unless(in_array($sede, $this->sedesPermitidas($request), true), 403);

        return response()->json([
            'renglones' => $this->reportes->detalleFactura($sede, $numero)->map(fn ($r) => [
                'codigo' => $r->codigo_producto,
                'producto' => $r->nombre_producto,
                'cantidad' => round((float) $r->cantidad, 2),
                'precio' => round((float) $r->precio_neto, 2),
            ])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function filtros(Request $request, string $reporte): array
    {
        $desde = $this->fecha($request->query('desde'), now()->startOfMonth());
        $hasta = $this->fecha($request->query('hasta'), now());
        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $sedes = $this->sedesPermitidas($request);
        $sede = strtoupper(trim((string) $request->query('sede', '')));
        if (count($sedes) === 1) {
            $sede = $sedes[0];
        } elseif (! in_array($sede, $sedes, true)) {
            $sede = '';
        }

        $filtros = ['sede' => $sede, 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()];
        foreach (self::FILTROS[$reporte] as $clave) {
            $filtros[$clave] = trim((string) $request->query($clave, $clave === 'mostrar_total' ? '1' : ''));
        }

        return $filtros;
    }

    /** @return list<string> */
    private function sedesPermitidas(Request $request): array
    {
        $user = $request->user();
        $todas = config('inventario.sedes_gerencial', []);
        if ($user && $user->sedeIsLocked() && $user->sede) {
            return [strtoupper((string) $user->sede)];
        }

        return $todas;
    }

    private function fecha(mixed $valor, Carbon $defecto): Carbon
    {
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return Carbon::parse($valor)->startOfDay();
        }

        return $defecto->copy()->startOfDay();
    }

    private function logo(): ?string
    {
        $archivo = public_path('logo.png');
        $raw = is_file($archivo) ? @file_get_contents($archivo) : false;

        return $raw ? 'data:image/png;base64,'.base64_encode($raw) : null;
    }
}
