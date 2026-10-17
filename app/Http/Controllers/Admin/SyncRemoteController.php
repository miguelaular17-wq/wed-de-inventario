<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SyncHeartbeat;
use App\Models\SyncCommand;
use Illuminate\Http\Request;

class SyncRemoteController extends Controller
{
    // ── Panel principal ─────────────────────────────────────────────────
    public function index()
    {
        // Todos los heartbeats conocidos, los activos primero
        $heartbeats = SyncHeartbeat::orderByRaw("last_seen_at DESC NULLS LAST")->get();

        // Si no hay heartbeats, obtener sedes conocidas del historial de cobranzas
        // para que el dropdown siempre tenga opciones disponibles
        $sedesFallback = collect();
        if ($heartbeats->isEmpty()) {
            $sedesFallback = \App\Models\HistorialCobranza::select('sede_nombre')
                ->distinct()
                ->whereNotNull('sede_nombre')
                ->orderBy('sede_nombre')
                ->pluck('sede_nombre');
        }

        // Comandos recientes (últimos 30)
        $comandos = SyncCommand::recientes()->get();

        // Opciones de comando disponibles
        $opciones = SyncCommand::LABELS;

        // Última auditoría de códigos compuestos (cualquier sede)
        $ultimaAuditoria = SyncCommand::query()
            ->where('comando', 'verificar_codigos_compuestos')
            ->whereIn('estado', ['completado', 'error'])
            ->orderByDesc('ejecutado_at')
            ->orderByDesc('id')
            ->first();

        return view('admin.sync.index', compact(
            'heartbeats',
            'comandos',
            'opciones',
            'sedesFallback',
            'ultimaAuditoria',
        ));
    }

    // ── Enviar comando remoto ───────────────────────────────────────────
    public function sendCommand(Request $request)
    {
        $request->validate([
            'sede'    => ['required', 'string', 'max:20'],
            'comando' => ['required', 'string', 'in:' . implode(',', array_keys(SyncCommand::LABELS))],
        ]);

        SyncCommand::create([
            'sede'           => strtoupper(trim($request->sede)),
            'comando'        => $request->comando,
            'estado'         => 'pendiente',
            'solicitado_por' => auth()->user()->email ?? 'sistema',
        ]);

        return back()->with('success',
            "Comando «{$request->comando}» enviado a la sede {$request->sede}. El sincronizador lo ejecutará en el próximo ciclo (≤ 60 s)."
        );
    }

    // ── JSON para polling Ajax ──────────────────────────────────────────
    public function status()
    {
        $heartbeats = SyncHeartbeat::orderByRaw("last_seen_at DESC NULLS LAST")
            ->get()
            ->map(fn ($h) => [
                'sede'         => $h->sede,
                'es_activo'    => $h->es_activo,
                'tiempo'       => $h->tiempo,
                'ip'           => $h->ip_address,
                'version'      => $h->version,
                'metadata'     => $h->metadata,
                'last_seen_at' => $h->last_seen_at?->toIso8601String(),
            ]);

        $comandos = SyncCommand::recientes()
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'sede'           => $c->sede,
                'comando'        => $c->comando,
                'label'          => $c->label,
                'estado'         => $c->estado,
                'color'          => $c->color,
                'icon'           => $c->icon,
                'mensaje'        => $c->mensaje,
                'solicitado_por' => $c->solicitado_por,
                'created_at'     => $c->created_at?->diffForHumans(),
                'ejecutado_at'   => $c->ejecutado_at?->diffForHumans(),
            ]);

        return response()->json(compact('heartbeats', 'comandos'));
    }

    // ── Resultado de verificación códigos compuestos (hecha por el syncer) ──
    public function codigosCompuestos(Request $request)
    {
        $sede = $request->query('sede');

        $q = SyncCommand::query()
            ->where('comando', 'verificar_codigos_compuestos')
            ->whereIn('estado', ['completado', 'error', 'pendiente', 'ejecutando'])
            ->orderByDesc('id');

        if (is_string($sede) && trim($sede) !== '') {
            $q->where('sede', strtoupper(trim($sede)));
        }

        $cmd = $q->first();

        if (! $cmd) {
            return response()->json([
                'estado' => null,
                'mensaje' => 'Aún no se ha enviado la verificación. Elige una sede y pulsa «Enviar al sincronizador».',
                'detalles' => null,
            ]);
        }

        $parametros = $cmd->parametros;
        if (is_string($parametros)) {
            $parametros = json_decode($parametros, true);
        }
        $detalles = is_array($parametros) ? ($parametros['resultado'] ?? null) : null;

        return response()->json([
            'id' => $cmd->id,
            'sede' => $cmd->sede,
            'estado' => $cmd->estado,
            'mensaje' => $cmd->mensaje,
            'ejecutado_at' => $cmd->ejecutado_at?->toIso8601String(),
            'ejecutado_humano' => $cmd->ejecutado_at?->diffForHumans(),
            'detalles' => $detalles,
        ]);
    }
}
