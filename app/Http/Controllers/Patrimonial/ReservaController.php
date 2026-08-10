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
        // 1. Actualización automática de estados
        $hoy = now()->startOfDay();
        $reservasActivas = Reserva::whereIn('estado', ['confirmada', 'en_curso'])->get();
        foreach ($reservasActivas as $r) {
            $inicio = Carbon::parse($r->fecha_entrada)->startOfDay();
            $fin = Carbon::parse($r->fecha_salida)->startOfDay();
            
            $nuevoEstado = $r->estado;
            if ($hoy->gte($fin)) {
                $nuevoEstado = 'completada';
            } elseif ($hoy->gte($inicio) && $hoy->lt($fin)) {
                $nuevoEstado = 'en_curso';
            }
            
            if ($nuevoEstado !== $r->estado) {
                $r->update(['estado' => $nuevoEstado]);
                $this->sincronizarEstadoPropiedad($r->propiedad_id);
            }
        }

        // 2. Consulta y visualización normal
        $propiedadId = $request->get('propiedad_id');
        $mes  = (int)$request->get('mes', now()->month);
        $anio = (int)$request->get('anio', now()->year);

        $query = Reserva::with(['propiedad', 'pagos']);
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
            'estado'           => 'required|in:confirmada,cancelada,completada,en_curso',
            'observaciones'    => 'nullable|string',
        ]);

        Reserva::create($data);

        $this->sincronizarEstadoPropiedad($data['propiedad_id']);

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
            'estado'           => 'required|in:confirmada,cancelada,completada,en_curso',
            'observaciones'    => 'nullable|string',
        ]);
        $reserva->update($data);
        $this->sincronizarEstadoPropiedad($reserva->propiedad_id);
        return back()->with('status', '✅ Reserva actualizada.');
    }

    public function destroy(Reserva $reserva)
    {
        $propiedadId = $reserva->propiedad_id;
        $reserva->delete();
        $this->sincronizarEstadoPropiedad($propiedadId);
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
        $this->sincronizarEstadoPropiedad($data['propiedad_id']);
        return back()->with('status', '🔒 Fechas bloqueadas.');
    }

    public function registrarPago(Request $request, Reserva $reserva)
    {
        $data = $request->validate([
            'monto_pagado'  => 'required|numeric|min:0.01',
            'forma_pago'    => 'required|string',
            'fecha_pago'    => 'required|date',
            'tasa_cambio'   => 'nullable|numeric',
            'banco_destino' => 'nullable|string',
            'banco_origen'  => 'nullable|string',
            'referencia'    => 'nullable|string',
            'comentario'    => 'nullable|string',
        ]);

        $data['user_id'] = auth()->id();
        
        $reserva->pagos()->create($data);

        // Registrar ingreso en el balance (PatTransaccion)
        \App\Models\Patrimonial\PatTransaccion::create([
            'propiedad_id'  => $reserva->propiedad_id,
            'tipo'          => 'ingreso',
            'categoria'     => 'Reserva temporal',
            'descripcion'   => "Pago de reserva - {$reserva->cliente_nombre} (" . $data['forma_pago'] . ")",
            'monto'         => $data['monto_pagado'],
            'moneda'        => $reserva->moneda,
            'fecha'         => $data['fecha_pago'],
            'mes'           => \Carbon\Carbon::parse($data['fecha_pago'])->month,
            'anio'          => \Carbon\Carbon::parse($data['fecha_pago'])->year,
            'observaciones' => $data['comentario'] ?? null,
        ]);

        return back()->with('status', '💰 Pago registrado exitosamente y añadido al balance.');
    }

    private function sincronizarEstadoPropiedad($propiedadId)
    {
        $tieneAlquiler = \App\Models\Patrimonial\Alquiler::where('propiedad_id', $propiedadId)
            ->where('estado', 'activo')->exists();

        if ($tieneAlquiler) {
            \App\Models\Patrimonial\Propiedad::where('id', $propiedadId)->update(['estado' => 'alquilado']);
            return;
        }

        $tieneReservas = \App\Models\Patrimonial\Reserva::where('propiedad_id', $propiedadId)
            ->whereIn('estado', ['confirmada', 'en_curso'])->exists();

        if ($tieneReservas) {
            \App\Models\Patrimonial\Propiedad::where('id', $propiedadId)->update(['estado' => 'reservado']);
        } else {
            \App\Models\Patrimonial\Propiedad::where('id', $propiedadId)->update(['estado' => 'disponible']);
        }
    }
}
