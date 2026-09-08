<?php
namespace App\Http\Controllers\Patrimonial;

use App\Http\Controllers\Controller;
use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Propiedad;
use App\Services\Patrimonial\PatrimonioReporteService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TransaccionController extends Controller
{
    public function __construct(
        private readonly PatrimonioReporteService $reportes,
    ) {}

    public function index(Request $request)
    {
        $mes         = (int)$request->get('mes', now()->month);
        $anio        = (int)$request->get('anio', now()->year);
        $propiedadId = $request->get('propiedad_id');

        $query = PatTransaccion::with('propiedad')->where('mes', $mes)->where('anio', $anio);
        if ($propiedadId) $query->where('propiedad_id', $propiedadId);

        $transacciones = $query->orderByDesc('fecha')->paginate(25)->withQueryString();
        $propiedades   = Propiedad::orderBy('nombre')->get(['id', 'nombre']);
        $categorias    = PatTransaccion::categorias();

        // Resumen del mes por propiedad
        $resumenProps = Propiedad::withCount([])
            ->get(['id', 'nombre', 'tipo'])
            ->map(function ($p) use ($mes, $anio) {
                return array_merge(['id' => $p->id, 'nombre' => $p->nombre, 'tipo' => $p->tipo],
                    $p->balanceMes($mes, $anio));
            })
            ->filter(fn($b) => $b['ingresos'] > 0 || $b['gastos'] > 0 || $b['comisiones'] > 0)
            ->values();

        $totales = [
            'ingresos'   => $resumenProps->sum('ingresos'),
            'gastos'     => $resumenProps->sum('gastos'),
            'comisiones' => $resumenProps->sum('comisiones'),
            'balance'    => $resumenProps->sum('balance'),
        ];

        return view('patrimonial.transacciones.index', compact(
            'transacciones', 'propiedades', 'categorias',
            'mes', 'anio', 'propiedadId', 'resumenProps', 'totales'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'propiedad_id' => 'required|exists:pat_propiedades,id',
            'tipo'         => 'required|in:ingreso,gasto,comision',
            'categoria'    => 'required|string|max:128',
            'descripcion'  => 'nullable|string|max:512',
            'monto'        => 'required|numeric|min:0',
            'moneda'       => 'required|in:usd,bs',
            'fecha'        => 'required|date',
            'observaciones'=> 'nullable|string',
        ]);

        $fecha = Carbon::parse($data['fecha']);
        $data['mes']  = $fecha->month;
        $data['anio'] = $fecha->year;

        PatTransaccion::create($data);
        return back()->with('status', '✅ Transacción registrada.');
    }

    public function destroy(PatTransaccion $transaccion)
    {
        $transaccion->delete();
        return back()->with('status', '🗑️ Transacción eliminada.');
    }

    public function reporteMensual(Request $request)
    {
        $mes  = (int) $request->get('mes', now()->month);
        $anio = (int) $request->get('anio', now()->year);
        $data = $this->reportes->mensual($mes, $anio);

        return view('patrimonial.transacciones.reporte_mensual', [
            'reporte' => $data['filas'],
            'totales' => $data['totales'],
            'ingresosPorPropiedad' => $data['ingresosPorPropiedad'],
            'gastosPorCategoria' => $data['gastosPorCategoria'],
            'comisionesPorCategoria' => $data['comisionesPorCategoria'],
            'mes' => $mes,
            'anio' => $anio,
        ]);
    }

    public function reporteMensualPdf(Request $request)
    {
        $mes  = (int) $request->get('mes', now()->month);
        $anio = (int) $request->get('anio', now()->year);
        $data = $this->reportes->mensual($mes, $anio, true);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('patrimonial.pdf.reporte_mensual', [
            'reporte' => $data['filas'],
            'totales' => $data['totales'],
            'ingresosPorPropiedad' => $data['ingresosPorPropiedad'],
            'gastosPorCategoria' => $data['gastosPorCategoria'],
            'comisionesPorCategoria' => $data['comisionesPorCategoria'],
            'mes' => $mes,
            'anio' => $anio,
        ]);
        $pdf->setPaper('a4', 'portrait');
        $nombreMes = Carbon::create($anio, $mes)->translatedFormat('F_Y');

        return $pdf->stream("reporte_patrimonial_{$nombreMes}.pdf");
    }

    public function reportePropiedadPdf(Request $request, Propiedad $propiedad)
    {
        $mes        = (int) $request->get('mes', now()->month);
        $anio       = (int) $request->get('anio', now()->year);
        $anioInicio = (int) $request->get('anio_inicio', now()->year - 1);
        $anioFin    = (int) $request->get('anio_fin', now()->year);

        $data = $this->reportes->propiedad($propiedad, $mes, $anio, $anioInicio, $anioFin);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('patrimonial.pdf.reporte_propiedad', [
            'propiedad' => $propiedad,
            'mesResumen' => $data['mesResumen'],
            'historial' => $data['historial'],
            'totales' => $data['totales'],
            'alquilerActivo' => $data['alquilerActivo'],
            'anioInicio' => $anioInicio,
            'anioFin' => $anioFin,
            'mes' => $mes,
            'anio' => $anio,
        ]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("reporte_propiedad_{$propiedad->codigo}.pdf");
    }
}
