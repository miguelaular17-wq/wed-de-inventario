<?php
namespace App\Http\Controllers\Patrimonial;

use App\Http\Controllers\Controller;
use App\Models\Patrimonial\Propiedad;
use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Alquiler;
use App\Models\Patrimonial\AlquilerPago;
use Carbon\Carbon;

class DashboardPatrimonialController extends Controller
{
    public function index()
    {
        $ahora = Carbon::now();
        $mes   = $ahora->month;
        $anio  = $ahora->year;

        // Totales de propiedades
        $totalPropiedades     = Propiedad::count();
        $alquiladas           = Propiedad::where('estado', 'alquilado')->count();
        $disponibles          = Propiedad::where('estado', 'disponible')->count();
        $enRemodelacion       = Propiedad::where('estado', 'remodelacion')->count();
        $usoPropio            = Propiedad::where('estado', 'uso_propio')->count();

        // Transacciones del mes actual
        $txMes = PatTransaccion::where('mes', $mes)->where('anio', $anio)->get();
        $ingresosMes   = $txMes->where('tipo', 'ingreso')->sum('monto');
        $gastosMes     = $txMes->where('tipo', 'gasto')->sum('monto');
        $comisionesMes = $txMes->where('tipo', 'comision')->sum('monto');
        $balanceMes    = $ingresosMes - $gastosMes - $comisionesMes;

        // Balance por propiedad en el mes
        $propiedades = Propiedad::orderBy('nombre')->get();
        $balancePorPropiedad = $propiedades->map(function ($p) use ($mes, $anio) {
            return array_merge(['propiedad' => $p->nombre, 'tipo' => $p->tipo], $p->balanceMes($mes, $anio));
        })->filter(fn($b) => $b['ingresos'] > 0 || $b['gastos'] > 0 || $b['comisiones'] > 0)->values();

        // Alertas: pagos vencidos o próximos a vencer
        $hoy = $ahora->toDateString();
        $en7dias = $ahora->copy()->addDays(7)->toDateString();

        $pagosVencidos  = AlquilerPago::where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<', $hoy)->count();
        $pagosProximos  = AlquilerPago::where('estado', 'pendiente')
            ->whereBetween('fecha_vencimiento', [$hoy, $en7dias])->count();

        return view('patrimonial.dashboard', compact(
            'totalPropiedades', 'alquiladas', 'disponibles', 'enRemodelacion', 'usoPropio',
            'ingresosMes', 'gastosMes', 'comisionesMes', 'balanceMes',
            'balancePorPropiedad', 'pagosVencidos', 'pagosProximos', 'mes', 'anio'
        ));
    }
}
