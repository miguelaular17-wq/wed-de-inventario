<?php

namespace App\Http\Controllers\ServicioTecnico;

use App\Http\Controllers\Controller;
use App\Models\StEquipo;
use App\Models\StOrden;
use App\Services\ServicioTecnico\StEquipoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CelularesController extends Controller
{
    public function __construct(
        private readonly StEquipoService $equipos,
    ) {}

    public function hub(Request $request): View
    {
        $user = $request->user();
        $porRecibir = 0;
        $esTecnico = $user && $user->canAccess('servicio');

        if ($esTecnico) {
            $porRecibir = StOrden::query()
                ->visiblePara($user)
                ->where('transfer_estado', StOrden::TRANSFER_PENDIENTE)
                ->when($user->veSoloSusFacturasTaller(), fn ($q) => $q->where('tecnico_id', $user->id))
                ->when($user->scopesServicioToOwnSede(), fn ($q) => $q->where('sede', strtoupper((string) $user->sede)))
                ->count();
        }

        return view('servicio.celulares.hub', [
            'porRecibir' => $porRecibir,
            'puedeOperar' => (bool) $user,
            'esTecnico' => $esTecnico,
        ]);
    }

    public function bitacora(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $resultados = $q !== '' ? $this->equipos->buscar($q) : collect();

        return view('servicio.celulares.bitacora', [
            'q' => $q,
            'resultados' => $resultados,
        ]);
    }

    public function porRecibir(Request $request): View
    {
        $user = $request->user();
        $query = StOrden::query()
            ->with(['equipoCelular'])
            ->where('transfer_estado', StOrden::TRANSFER_PENDIENTE)
            ->orderByDesc('updated_at');

        if ($user->veSoloSusFacturasTaller()) {
            $query->where('tecnico_id', $user->id);
        }
        if ($user->scopesServicioToOwnSede()) {
            $query->where('sede', strtoupper((string) $user->sede));
        } elseif ($request->filled('sede')) {
            $query->where('sede', strtoupper((string) $request->query('sede')));
        }

        return view('servicio.celulares.por-recibir', [
            'ordenes' => $query->get(),
            'sedes' => config('inventario.sedes_locales'),
            'puedeFiltrarSede' => ! $user->scopesServicioToOwnSede(),
            'filtroSede' => $request->query('sede'),
        ]);
    }

    public function show(Request $request, StEquipo $equipo): View
    {
        $equipo->load([
            'eventos.usuario',
            'eventos.orden',
            'ordenes' => fn ($q) => $q->orderByDesc('fecha_ingreso')->limit(20),
        ]);

        return view('servicio.celulares.show', [
            'equipo' => $equipo,
        ]);
    }

    public function lookupImei(Request $request): JsonResponse
    {
        $imei = $this->equipos->normalizarImei((string) $request->query('imei', ''));
        if (! $imei) {
            return response()->json(['exists' => false]);
        }

        $equipo = $this->equipos->encontrarPorImei($imei);
        if (! $equipo) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'equipo' => [
                'id' => $equipo->id,
                'etiqueta' => $equipo->etiqueta(),
                'imei' => $equipo->imei,
                'serial' => $equipo->serial,
                'sede_actual' => $equipo->sede_actual,
                'estado_actual' => $equipo->etiquetaEstado(),
                'url' => route('servicio.celulares.show', $equipo),
            ],
            'mensaje' => 'Este equipo ya existe en el sistema. ¿Desea utilizar el equipo existente y crear una nueva orden?',
        ]);
    }
}
