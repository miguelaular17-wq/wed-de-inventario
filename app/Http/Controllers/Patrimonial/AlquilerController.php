<?php
namespace App\Http\Controllers\Patrimonial;

use App\Http\Controllers\Controller;
use App\Models\Patrimonial\Alquiler;
use App\Models\Patrimonial\AlquilerPago;
use App\Models\Patrimonial\Propiedad;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AlquilerController extends Controller
{
    public function index(Request $request)
    {
        // Update all rentals before displaying to show accurate badges
        foreach (Alquiler::where('estado', 'activo')->get() as $alq) {
            $alq->generarPagosPendientes();
            $alq->actualizarVencimientos();
        }

        $query = Alquiler::with(['propiedad', 'pagos'])->orderByDesc('created_at');

        if ($estado = $request->get('estado')) {
            $query->where('estado', $estado);
        }
        if ($propiedadId = $request->get('propiedad_id')) {
            $query->where('propiedad_id', $propiedadId);
        }

        $alquileres  = $query->paginate(20)->withQueryString();
        $propiedades = Propiedad::orderBy('nombre')->pluck('nombre', 'id');

        // Alertas de pagos
        $hoy     = Carbon::today();
        $en7dias = Carbon::today()->addDays(7);

        $pagosVencidos = AlquilerPago::where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<', $hoy)->with('alquiler.propiedad')->get();

        $pagosProximos = AlquilerPago::where('estado', 'pendiente')
            ->whereBetween('fecha_vencimiento', [$hoy, $en7dias])->with('alquiler.propiedad')->get();

        return view('patrimonial.alquileres.index', compact(
            'alquileres', 'propiedades', 'pagosVencidos', 'pagosProximos'
        ));
    }

    public function create()
    {
        $propiedades = Propiedad::orderBy('nombre')->get(['id', 'nombre', 'tipo', 'estado']);
        return view('patrimonial.alquileres.create', compact('propiedades'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'propiedad_id'      => 'required|exists:pat_propiedades,id',
            'inquilino_nombre'  => 'required|string|max:256',
            'inquilino_contacto'=> 'nullable|string|max:256',
            'contrato_nro'      => 'nullable|string|max:64',
            'fecha_inicio'      => 'required|date',
            'fecha_fin'         => 'nullable|date|after_or_equal:fecha_inicio',
            'tipo_canon'        => 'required|in:mensual,quincenal',
            'canon_mensual'     => 'nullable|numeric|min:0',
            'canon_quincenal'   => 'nullable|numeric|min:0',
            'dia_pago'          => 'nullable|integer|min:1|max:31',
            'forma_pago'        => 'nullable|string|max:64',
            'observaciones'     => 'nullable|string',
        ]);

        $alquiler = Alquiler::create($data);

        // Marcar propiedad como alquilada
        Propiedad::where('id', $data['propiedad_id'])->update(['estado' => 'alquilado']);

        return redirect()->route('patrimonial.alquileres.show', $alquiler)
            ->with('status', "✅ Alquiler registrado para '{$alquiler->propiedad->nombre}'.");
    }

    public function show(Alquiler $alquiler)
    {
        $alquiler->generarPagosPendientes();
        $alquiler->actualizarVencimientos();
        
        $alquiler->load('propiedad', 'pagos');
        return view('patrimonial.alquileres.show', compact('alquiler'));
    }

    public function edit(Alquiler $alquiler)
    {
        $propiedades = Propiedad::orderBy('nombre')->get(['id', 'nombre', 'tipo', 'estado']);
        return view('patrimonial.alquileres.edit', compact('alquiler', 'propiedades'));
    }

    public function update(Request $request, Alquiler $alquiler)
    {
        $data = $request->validate([
            'propiedad_id'      => 'required|exists:pat_propiedades,id',
            'inquilino_nombre'  => 'required|string|max:256',
            'inquilino_contacto'=> 'nullable|string|max:256',
            'contrato_nro'      => 'nullable|string|max:64',
            'fecha_inicio'      => 'required|date',
            'fecha_fin'         => 'nullable|date|after_or_equal:fecha_inicio',
            'tipo_canon'        => 'required|in:mensual,quincenal',
            'canon_mensual'     => 'nullable|numeric|min:0',
            'canon_quincenal'   => 'nullable|numeric|min:0',
            'dia_pago'          => 'nullable|integer|min:1|max:31',
            'forma_pago'        => 'nullable|string|max:64',
            'estado'            => 'required|in:activo,vencido,terminado',
            'observaciones'     => 'nullable|string',
        ]);
        $alquiler->update($data);

        return redirect()->route('patrimonial.alquileres.show', $alquiler)
            ->with('status', '✅ Alquiler actualizado.');
    }

    public function destroy(Alquiler $alquiler)
    {
        $alquiler->delete();
        return redirect()->route('patrimonial.alquileres.index')->with('status', '🗑️ Alquiler eliminado.');
    }

    // Registrar pago de cuota
    public function registrarPago(Request $request, Alquiler $alquiler)
    {
        $data = $request->validate([
            'periodo'           => 'required|string',
            'fecha_vencimiento' => 'required|date',
            'fecha_pago'        => 'nullable|date',
            'monto'             => 'required|numeric|min:0',
            'estado'            => 'required|in:pagado,pendiente,vencido',
            'observaciones'     => 'nullable|string',
        ]);
        $data['alquiler_id'] = $alquiler->id;
        $pago = AlquilerPago::create($data);

        if ($data['estado'] === 'pagado' && !empty($data['fecha_pago'])) {
            \App\Models\Patrimonial\PatTransaccion::create([
                'propiedad_id'  => $alquiler->propiedad_id,
                'tipo'          => 'ingreso',
                'categoria'     => 'Alquiler',
                'descripcion'   => "Pago de alquiler {$data['periodo']} - {$alquiler->inquilino_nombre}",
                'monto'         => $data['monto'],
                'moneda'        => 'usd', // Asumiendo USD como principal para alquileres
                'fecha'         => $data['fecha_pago'],
                'mes'           => \Carbon\Carbon::parse($data['fecha_pago'])->month,
                'anio'          => \Carbon\Carbon::parse($data['fecha_pago'])->year,
                'observaciones' => $data['observaciones'] ?? null,
            ]);
        }

        return back()->with('status', '✅ Pago registrado y actualizado en el balance.');
    }

    public function actualizarPago(Request $request, AlquilerPago $pago)
    {
        $data = $request->validate([
            'fecha_pago'  => 'nullable|date',
            'monto'       => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
            'forma_pago'    => 'nullable|string',
            'tasa_cambio'   => 'nullable|numeric',
            'banco_origen'  => 'nullable|string',
            'banco_destino' => 'nullable|string',
            'referencia'    => 'nullable|string',
            'comentario'    => 'nullable|string',
        ]);
        
        $montoAbonado = $data['monto'] ?? 0;
        unset($data['monto']);
        
        if ($montoAbonado > 0) {
            $pago->monto_pagado += $montoAbonado;
        }
        
        if ($pago->monto_pagado >= $pago->monto) {
            $data['estado'] = 'pagado';
        } elseif ($pago->fecha_vencimiento < now()->startOfDay()) {
            $data['estado'] = 'vencido';
        } else {
            $data['estado'] = 'pendiente';
        }

        $data['user_id'] = auth()->id();
        $pago->update($data);

        if ($montoAbonado > 0 && !empty($data['fecha_pago'])) {
            $alquiler = $pago->alquiler;
            \App\Models\Patrimonial\PatTransaccion::create([
                'propiedad_id'  => $alquiler->propiedad_id,
                'tipo'          => 'ingreso',
                'categoria'     => 'Alquiler',
                'descripcion'   => "Pago de alquiler {$pago->periodo} - {$alquiler->inquilino_nombre}",
                'monto'         => $montoAbonado,
                'moneda'        => 'usd',
                'fecha'         => $data['fecha_pago'],
                'mes'           => \Carbon\Carbon::parse($data['fecha_pago'])->month,
                'anio'          => \Carbon\Carbon::parse($data['fecha_pago'])->year,
                'observaciones' => $data['comentario'] ?? null,
            ]);
        }

        return back()->with('status', '✅ Pago registrado y actualizado en el balance.');
    }

    public function calendario(Request $request)
    {
        $alquileresActivos = Alquiler::with('propiedad')
            ->where('estado', 'activo')
            ->whereNotNull('dia_pago')
            ->get();
            
        $eventos = [];
        $mesFiltro = (int) $request->get('mes', now()->month);
        $anioFiltro = (int) $request->get('anio', now()->year);
        
        // Generar eventos para el mes actual, anterior y siguiente para buena cobertura visual
        $mesesEvaluar = [
            Carbon::create($anioFiltro, $mesFiltro, 1)->subMonth(),
            Carbon::create($anioFiltro, $mesFiltro, 1),
            Carbon::create($anioFiltro, $mesFiltro, 1)->addMonth(),
        ];
        
        foreach ($alquileresActivos as $alquiler) {
            foreach ($mesesEvaluar as $fechaRef) {
                // Si el día de pago es 31 y el mes tiene 30 días, ajustamos al final del mes
                $dia = min($alquiler->dia_pago, $fechaRef->daysInMonth);
                $fechaCobro = Carbon::create($fechaRef->year, $fechaRef->month, $dia);
                
                // Si la fecha de cobro es mayor o igual a la fecha de inicio
                if ($fechaCobro->gte(Carbon::parse($alquiler->fecha_inicio))) {
                    $eventos[] = [
                        'title' => 'Cobro: ' . ($alquiler->propiedad->nombre ?? 'Propiedad') . ' (' . $alquiler->inquilino_nombre . ')',
                        'start' => $fechaCobro->format('Y-m-d'),
                        'url' => route('patrimonial.alquileres.show', $alquiler->id),
                        'color' => '#10b981', // Verde esmeralda para cobros
                        'allDay' => true
                    ];
                }
            }
        }
        
        return view('patrimonial.alquileres.calendario', compact('eventos'));
    }
}
