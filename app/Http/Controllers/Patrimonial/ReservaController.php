<?php
namespace App\Http\Controllers\Patrimonial;

use App\Http\Controllers\Controller;
use App\Models\Patrimonial\Reserva;
use App\Models\Patrimonial\Propiedad;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReservaController extends Controller
{
    public function index(Request $request)
    {
        $propiedadId = $request->get('propiedad_id');
        $mes  = (int)$request->get('mes', now()->month);
        $anio = (int)$request->get('anio', now()->year);

        $query = Reserva::with('propiedad');
        if ($propiedadId) $query->where('propiedad_id', $propiedadId);

        // Para el calendario: reservas en ese mes/año
        $inicioMes = Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes    = $inicioMes->copy()->endOfMonth();

        $reservasCalendario = (clone $query)
            ->where('fecha_entrada', '<=', $finMes)
            ->where('fecha_salida',  '>=', $inicioMes)
            ->get();

        // Listado paginado
        $reservas    = $query->orderByDesc('fecha_entrada')->paginate(15)->withQueryString();
        $propiedades = Propiedad::orderBy('nombre')->get(['id', 'nombre', 'tipo']);

        // Días del calendario
        $diasMes = $inicioMes->daysInMonth;

        return view('patrimonial.reservas.index', compact(
            'reservas', 'propiedades', 'propiedadId',
            'mes', 'anio', 'diasMes', 'inicioMes', 'reservasCalendario'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'propiedad_id'     => 'required|exists:pat_propiedades,id',
            'cliente_nombre'   => 'required|string|max:256',
            'cliente_contacto' => 'nullable|string|max:256',
            'fecha_entrada'    => 'required|date',
            'fecha_salida'     => 'required|date|after:fecha_entrada',
            'precio_noche'     => 'required|numeric|min:0',
            'moneda'           => 'required|in:usd,bs',
            'estado'           => 'required|in:confirmada,cancelada,completada',
            'observaciones'    => 'nullable|string',
        ]);

        Reserva::create($data);

        return back()->with('status', '✅ Reserva registrada.');
    }

    public function update(Request $request, Reserva $reserva)
    {
        $data = $request->validate([
            'cliente_nombre'   => 'required|string|max:256',
            'cliente_contacto' => 'nullable|string|max:256',
            'fecha_entrada'    => 'required|date',
            'fecha_salida'     => 'required|date|after:fecha_entrada',
            'precio_noche'     => 'required|numeric|min:0',
            'moneda'           => 'required|in:usd,bs',
            'estado'           => 'required|in:confirmada,cancelada,completada',
            'observaciones'    => 'nullable|string',
        ]);
        $reserva->update($data);
        return back()->with('status', '✅ Reserva actualizada.');
    }

    public function destroy(Reserva $reserva)
    {
        $reserva->delete();
        return back()->with('status', '🗑️ Reserva eliminada.');
    }

    // Bloquear fechas: crear reserva con estado "bloqueado" (uso interno)
    public function bloquear(Request $request)
    {
        $data = $request->validate([
            'propiedad_id'  => 'required|exists:pat_propiedades,id',
            'fecha_entrada' => 'required|date',
            'fecha_salida'  => 'required|date|after:fecha_entrada',
            'observaciones' => 'nullable|string',
        ]);
        $data['cliente_nombre'] = 'BLOQUEADO';
        $data['precio_noche']   = 0;
        $data['moneda']         = 'usd';
        $data['estado']         = 'confirmada';
        Reserva::create($data);
        return back()->with('status', '🔒 Fechas bloqueadas.');
    }
}
