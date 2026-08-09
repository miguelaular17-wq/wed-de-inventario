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
        $query = Alquiler::with('propiedad')->orderByDesc('created_at');

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
        AlquilerPago::create($data);

        return back()->with('status', '✅ Pago registrado.');
    }

    public function actualizarPago(Request $request, AlquilerPago $pago)
    {
        $pago->update($request->validate([
            'estado'      => 'required|in:pagado,pendiente,vencido',
            'fecha_pago'  => 'nullable|date',
            'monto'       => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]));
        return back()->with('status', '✅ Pago actualizado.');
    }
}
