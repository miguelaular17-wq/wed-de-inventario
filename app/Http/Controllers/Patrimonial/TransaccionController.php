<?php
namespace App\Http\Controllers\Patrimonial;

use App\Http\Controllers\Controller;
use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Propiedad;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TransaccionController extends Controller
{
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
        $mes  = (int)$request->get('mes', now()->month);
        $anio = (int)$request->get('anio', now()->year);

        $propiedades = Propiedad::orderBy('nombre')->get();
        $reporte = $propiedades->map(function ($p) use ($mes, $anio) {
            return array_merge(
                ['propiedad' => $p->nombre, 'tipo' => $p->tipo, 'codigo' => $p->codigo],
                $p->balanceMes($mes, $anio)
            );
        });

        $totales = [
            'ingresos'   => $reporte->sum('ingresos'),
            'gastos'     => $reporte->sum('gastos'),
            'comisiones' => $reporte->sum('comisiones'),
            'balance'    => $reporte->sum('balance'),
        ];

        return view('patrimonial.transacciones.reporte_mensual', compact('reporte', 'totales', 'mes', 'anio'));
    }
}
