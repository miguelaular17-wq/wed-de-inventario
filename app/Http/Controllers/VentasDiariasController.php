<?php

namespace App\Http\Controllers;

use App\Models\VentaDiariaReporte;
use App\Services\VentasDiariasService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VentasDiariasController extends Controller
{
    public function __construct(private VentasDiariasService $service) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $verTodas = $this->service->puedeVerTodas($user);
        $sedeUsuario = $this->service->sedeDelUsuario($user);

        $sede = $verTodas
            ? ($request->query('sede') ? mb_strtoupper(trim((string) $request->query('sede')), 'UTF-8') : null)
            : $sedeUsuario;

        $desde = $request->query('desde', now()->subDays(14)->toDateString());
        $hasta = $request->query('hasta', now()->toDateString());

        $reportes = $sede || $verTodas
            ? $this->service->listar($sede, $desde, $hasta)
            : collect();

        return view('ventas_diarias.index', [
            'reportes' => $reportes,
            'verTodas' => $verTodas,
            'sede' => $sede,
            'sedeUsuario' => $sedeUsuario,
            'sedes' => $this->service->sedesDisponibles(),
            'desde' => $desde,
            'hasta' => $hasta,
            'puedeCrear' => $verTodas || ($sedeUsuario && $user->canAccess('ventas.diarias')),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $verTodas = $this->service->puedeVerTodas($user);
        $sede = $verTodas
            ? mb_strtoupper(trim((string) $request->query('sede', $this->service->sedeDelUsuario($user) ?? 'DORAL')), 'UTF-8')
            : $this->service->sedeDelUsuario($user);

        if (! $sede) {
            return redirect()->route('ventas_diarias.index')
                ->withErrors(['error' => 'No tienes sede asignada.']);
        }
        if (! $this->service->puedeEditar($user, $sede)) {
            abort(403);
        }

        $fecha = $request->query('fecha', now()->toDateString());
        $existente = VentaDiariaReporte::query()
            ->with('cajas')
            ->where('sede', $sede)
            ->whereDate('fecha', $fecha)
            ->first();

        if ($existente) {
            return redirect()->route('ventas_diarias.edit', $existente);
        }

        return $this->formView(null, $sede, $fecha, $verTodas);
    }

    public function edit(Request $request, VentaDiariaReporte $reporte): View|RedirectResponse
    {
        $user = $request->user();
        $verTodas = $this->service->puedeVerTodas($user);

        if (! $verTodas && $this->service->sedeDelUsuario($user) !== $reporte->sede) {
            abort(403);
        }

        if (! $this->service->puedeEditar($user, $reporte->sede)) {
            return redirect()->route('ventas_diarias.show', $reporte);
        }

        $reporte->load('cajas');

        return $this->formView($reporte, $reporte->sede, $reporte->fecha->toDateString(), $verTodas);
    }

    public function show(Request $request, VentaDiariaReporte $reporte): View
    {
        $user = $request->user();
        $verTodas = $this->service->puedeVerTodas($user);
        if (! $verTodas && $this->service->sedeDelUsuario($user) !== $reporte->sede) {
            abort(403);
        }

        $reporte->load('cajas');
        $metaCtx = $this->service->metaPara($reporte->sede, $reporte->fecha);
        $totales = $this->service->calcularTotales($reporte, $metaCtx);
        $totalesCajas = $this->service->totalesCajas($reporte->cajas);
        $puedeEditar = $this->service->puedeEditar($user, $reporte->sede);

        return view('ventas_diarias.show', compact(
            'reporte', 'metaCtx', 'totales', 'totalesCajas', 'puedeEditar', 'verTodas'
        ));
    }

    public function pdf(Request $request, VentaDiariaReporte $reporte): Response
    {
        $user = $request->user();
        $verTodas = $this->service->puedeVerTodas($user);
        if (! $verTodas && $this->service->sedeDelUsuario($user) !== $reporte->sede) {
            abort(403);
        }

        $reporte->load('cajas');
        $metaCtx = $this->service->metaPara($reporte->sede, $reporte->fecha);
        $totales = $this->service->calcularTotales($reporte, $metaCtx);
        $totalesCajas = $this->service->totalesCajas($reporte->cajas);

        $pdf = Pdf::loadView('ventas_diarias.pdf', [
            'reporte' => $reporte,
            'metaCtx' => $metaCtx,
            'totales' => $totales,
            'totalesCajas' => $totalesCajas,
            'logoPath' => $this->logoPdf(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('ventas-diarias-'.$reporte->sede.'-'.$reporte->fecha->format('Y-m-d').'.pdf');
    }

    private function logoPdf(): ?string
    {
        $path = public_path('logo.png');
        if (! is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);

        return ($raw === false || $raw === '') ? null : 'data:image/png;base64,'.base64_encode($raw);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $cajas = $request->input('cajas', []);
        if (! is_array($cajas)) {
            $cajas = [];
        }

        $reporte = $this->service->guardar($request->user(), $data, $cajas);

        return redirect()
            ->route('ventas_diarias.show', $reporte)
            ->with('success', 'Reporte de ventas diarias guardado.');
    }

    public function update(Request $request, VentaDiariaReporte $reporte): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sede'] = $reporte->sede;
        $data['fecha'] = $reporte->fecha->toDateString();

        $cajas = $request->input('cajas', []);
        if (! is_array($cajas)) {
            $cajas = [];
        }

        $reporte = $this->service->guardar($request->user(), $data, $cajas);

        return redirect()
            ->route('ventas_diarias.show', $reporte)
            ->with('success', 'Reporte actualizado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'sede' => ['required', 'string', 'max:32'],
            'fecha' => ['required', 'date'],
            'tasa' => ['required', 'numeric', 'min:0.0001'],
            'divisas_efectivo' => ['nullable', 'numeric'],
            'efectivo_bs' => ['nullable', 'numeric'],
            'punto_venta_bs' => ['nullable', 'numeric'],
            'transf_pm_bs' => ['nullable', 'numeric'],
            'zelle_binance' => ['nullable', 'numeric'],
            'cashea' => ['nullable', 'numeric'],
            'abonos' => ['nullable', 'numeric'],
            'total_creditos' => ['nullable', 'numeric'],
            'z_fiscal_bs' => ['nullable', 'numeric'],
            'productos_vendidos' => ['nullable', 'numeric'],
            'deliverys_pendientes' => ['nullable', 'numeric'],
            'observaciones' => ['nullable', 'string'],
            'cajas' => ['nullable', 'array'],
            'cajas.*.nombre' => ['nullable', 'string', 'max:120'],
            'cajas.*.efectivo_usd' => ['nullable', 'numeric'],
            'cajas.*.efectivo_bs' => ['nullable', 'numeric'],
            'cajas.*.punto_venta' => ['nullable', 'numeric'],
            'cajas.*.transf_pm' => ['nullable', 'numeric'],
            'cajas.*.zelle_binance' => ['nullable', 'numeric'],
            'cajas.*.cashea' => ['nullable', 'numeric'],
            'cajas.*.fact_credito' => ['nullable', 'numeric'],
            'cajas.*.abonos' => ['nullable', 'numeric'],
        ]);
    }

    private function formView(?VentaDiariaReporte $reporte, string $sede, string $fecha, bool $verTodas): View
    {
        $fechaCarbon = Carbon::parse($fecha);
        $metaCtx = $this->service->metaPara($sede, $fechaCarbon);
        $cajas = $reporte?->cajas ?? collect([
            (object) ['nombre' => '', 'efectivo_usd' => 0, 'efectivo_bs' => 0, 'punto_venta' => 0, 'transf_pm' => 0, 'zelle_binance' => 0, 'cashea' => 0, 'fact_credito' => 0, 'abonos' => 0],
            (object) ['nombre' => '', 'efectivo_usd' => 0, 'efectivo_bs' => 0, 'punto_venta' => 0, 'transf_pm' => 0, 'zelle_binance' => 0, 'cashea' => 0, 'fact_credito' => 0, 'abonos' => 0],
            (object) ['nombre' => '', 'efectivo_usd' => 0, 'efectivo_bs' => 0, 'punto_venta' => 0, 'transf_pm' => 0, 'zelle_binance' => 0, 'cashea' => 0, 'fact_credito' => 0, 'abonos' => 0],
        ]);

        $totales = $reporte
            ? $this->service->calcularTotales($reporte, $metaCtx)
            : $this->service->calcularTotales(new VentaDiariaReporte([
                'tasa' => 0, 'divisas_efectivo' => 0, 'efectivo_bs' => 0, 'punto_venta_bs' => 0,
                'transf_pm_bs' => 0, 'zelle_binance' => 0, 'cashea' => 0, 'abonos' => 0,
                'total_creditos' => 0, 'z_fiscal_bs' => 0, 'productos_vendidos' => 0,
            ]), $metaCtx);

        return view('ventas_diarias.form', [
            'reporte' => $reporte,
            'sede' => $sede,
            'fecha' => $fecha,
            'metaCtx' => $metaCtx,
            'cajas' => $cajas,
            'totales' => $totales,
            'verTodas' => $verTodas,
            'sedes' => $this->service->sedesDisponibles(),
            'puedeEditar' => true,
        ]);
    }
}
