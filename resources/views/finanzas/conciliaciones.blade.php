@extends('layouts.app')
@section('title', 'Conciliación Bancaria')
@section('content')

<style>
    body:has(.conc-page) main {
        max-width: none;
        width: auto;
        margin: 8px 10px 16px;
        padding: 0;
    }
    .conc-page { padding: 4px 0 8px; font-family: 'Inter', sans-serif; background: transparent; min-height: 0; }
    .conc-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 14px; }
    .conc-title { margin: 0; font-size: 1.6rem; color: #0f172a; font-weight: 800; letter-spacing: -0.5px; }
    .conc-title span { color: #2563eb; }

    /* Toolbar */
    .conc-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .btn-upload { background: linear-gradient(135deg,#2563eb,#1d4ed8); color: white; padding: 10px 18px; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(37,99,235,.3); transition: transform .15s; }
    .btn-upload:hover { transform: translateY(-1px); }
    .btn-clear { background: white; color: #ef4444; border: 1.5px solid #fca5a5; padding: 10px 18px; border-radius: 9px; font-weight: 600; cursor: pointer; font-size: 0.95rem; }
    .btn-clear:hover { background: #fef2f2; }
    .select-banco { padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-size: 0.95rem; color: #334155; background: white; min-width: 160px; }
    .btn-filtrar { background: #f8fafc; color: #475569; border: 1.5px solid #cbd5e1; padding: 10px 16px; border-radius: 9px; font-weight: 600; cursor: pointer; }

    /* Alert */
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 14px 18px; border-radius: 10px; margin-bottom: 22px; font-weight: 500; }
    .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 14px 18px; border-radius: 10px; margin-bottom: 22px; font-weight: 500; text-align: center; }

    /* Bank card */
    .bank-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,.06); margin-bottom: 16px; overflow: hidden; width: 100%; }
    .bank-card-header { background: linear-gradient(135deg, #1e3a5f 0%, #1a4273 100%); padding: 18px 24px; display: flex; align-items: center; gap: 14px; justify-content: space-between; flex-wrap: wrap; }
    .bank-name { color: white; font-size: 1.25rem; font-weight: 800; letter-spacing: .5px; display: flex; align-items: center; gap: 10px; }
    .bank-totals-row { display: flex; gap: 18px; flex-wrap: wrap; }
    .bank-stat { background: rgba(255,255,255,.12); border-radius: 8px; padding: 8px 14px; text-align: center; min-width: 120px; }
    .bank-stat-label { font-size: 0.7rem; color: rgba(255,255,255,.7); text-transform: uppercase; letter-spacing: .5px; display: block; }
    .bank-stat-value { font-size: 1rem; font-weight: 700; color: white; }
    .bank-stat-value.green { color: #6ee7b7; }
    .bank-stat-value.yellow { color: #fcd34d; }
    .bank-stat-value.red { color: #fca5a5; }
    .bank-stat-value.orange { color: #fdba74; }

    /* Sections inside bank */
    .sections-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
    @media(max-width: 900px) { .sections-grid { grid-template-columns: 1fr; } }

    .section-block { border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; min-width: 0; }
    .section-block:nth-child(even) { border-right: none; }

    .section-header { display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-bottom: 1px solid #f1f5f9; }
    .section-badge { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    .badge-green { background: #10b981; }
    .badge-yellow { background: #f59e0b; }
    .badge-red { background: #ef4444; }
    .badge-purple { background: #8b5cf6; }

    .section-title { font-size: 0.88rem; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: .5px; }
    .section-count { margin-left: auto; font-size: 0.75rem; background: #f1f5f9; color: #64748b; border-radius: 20px; padding: 2px 9px; font-weight: 600; }
    .section-header { cursor: pointer; user-select: none; }
    .section-toggle { border: 1px solid #e2e8f0; background: white; color: #475569; border-radius: 6px; font-size: 0.72rem; font-weight: 700; padding: 3px 8px; cursor: pointer; font-family: inherit; }
    .section-toggle:hover { background: #f8fafc; }
    .section-block.is-collapsed .section-body { display: none; }
    .section-body { max-height: 480px; overflow-y: auto; }
    .sections-toolbar { display: flex; justify-content: flex-end; padding: 10px 16px; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }

    /* Tables */
    .mini-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .mini-table thead tr { background: #f8fafc; }
    .mini-table th { padding: 9px 14px; font-weight: 600; color: #64748b; text-align: left; font-size: 0.78rem; text-transform: uppercase; letter-spacing: .3px; border-bottom: 1px solid #f1f5f9; position: sticky; top: 0; z-index: 1; background: #f8fafc; }
    .mini-table td { padding: 10px 14px; border-bottom: 1px solid #f8fafc; color: #334155; vertical-align: top; }
    .mini-table tr:last-child td { border-bottom: none; }
    .mini-table tr:hover td { background: #f8fafc; }
    .monto-cell { font-weight: 700; text-align: right; font-family: 'Courier New', monospace; }
    .monto-green { color: #059669; }
    .monto-red { color: #dc2626; }
    .monto-yellow { color: #d97706; }
    .monto-purple { color: #7c3aed; }
    .ref-chip { font-size: 0.75rem; color: #64748b; background: #f1f5f9; border-radius: 4px; padding: 1px 6px; display: inline-block; margin-top: 2px; font-family: monospace; }
    .tipo-chip { font-size: 0.72rem; border-radius: 4px; padding: 2px 7px; font-weight: 600; display: inline-block; }
    .chip-cargo { background: #fef2f2; color: #dc2626; }
    .chip-abono { background: #f0fdf4; color: #16a34a; }

    .empty-row td { text-align: center; color: #94a3b8; padding: 24px; font-size: 0.85rem; }

    /* Section footer */
    .section-footer { padding: 10px 14px; background: #f8fafc; border-top: 1px solid #f1f5f9; text-align: right; font-weight: 700; font-size: 0.9rem; color: #1e293b; position: sticky; bottom: 0; z-index: 1; }

    /* Upload modal */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px; }
    .modal-box { background: white; border-radius: 14px; width: 100%; max-width: 500px; box-shadow: 0 20px 40px rgba(0,0,0,.2); overflow: hidden; }
    .modal-head { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .modal-title { margin: 0; font-weight: 800; color: #1a4273; font-size: 1.2rem; }
    .modal-close { background: none; border: none; font-size: 1.6rem; cursor: pointer; color: #64748b; line-height: 1; }
    .modal-body { padding: 24px; }
    .modal-foot { padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; }
    .form-label { display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 0.9rem; }
    .form-control { width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; background: white; font-family: inherit; font-size: 0.95rem; margin-bottom: 18px; box-sizing: border-box; }
    .file-wrap { display: flex; }
    .file-label { background: #f1f5f9; color: #334155; padding: 10px 16px; border: 1.5px solid #cbd5e1; border-radius: 9px 0 0 9px; cursor: pointer; margin: 0; font-weight: 500; white-space: nowrap; font-size: 0.9rem; }
    .file-name { flex: 1; min-width: 0; padding: 10px; border: 1.5px solid #cbd5e1; border-left: none; border-radius: 0 9px 9px 0; background: white; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; font-family: inherit; }
    .file-hint { font-size: 0.82rem; color: #94a3b8; margin-top: 10px; }
    .btn-cancel { background: white; border: 1.5px solid #cbd5e1; padding: 10px 20px; border-radius: 9px; font-weight: 600; cursor: pointer; color: #475569; font-family: inherit; }
    .btn-submit { background: linear-gradient(135deg,#2563eb,#1d4ed8); color: white; padding: 10px 22px; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; font-family: inherit; }
    .btn-dash-action {
        width: 28px; height: 28px; padding: 0; border-radius: 6px; border: 1.5px solid #bfdbfe;
        background: #eff6ff; color: #2563eb; font-size: 1rem; font-weight: 800; line-height: 1;
        cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
        font-family: inherit;
    }
    .btn-dash-action:hover { background: #dbeafe; }
    .btn-dash-comision { color: #2563eb; border-color: #bfdbfe; background: #eff6ff; }
    .btn-dash-ok { color: #059669; border-color: #a7f3d0; background: #ecfdf5; font-size: 0.95rem; }
    .btn-dash-ok:hover { background: #d1fae5; }
    .btn-dash-menu { font-size: 1.15rem; letter-spacing: 0; }
    .mov-menu-flotante { position: fixed; z-index: 1040; min-width: 210px; background: white; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 10px 28px rgba(15,23,42,.16); padding: 5px; }
    .mov-menu-item { display: block; width: 100%; text-align: left; padding: 9px 12px; border: 0; background: none; border-radius: 7px; font-family: inherit; font-size: 0.86rem; font-weight: 600; color: #334155; cursor: pointer; }
    .mov-menu-item:hover { background: #f1f5f9; }
    .mov-menu-item small { display: block; font-weight: 400; color: #64748b; font-size: 0.75rem; margin-top: 2px; }
    .dev-mov { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; display: flex; gap: 10px; align-items: flex-start; font-size: 0.86rem; }
    .dev-mov.is-origen { background: #f8fafc; }
    label.dev-mov { cursor: pointer; }
    label.dev-mov:has(input:checked) { border-color: #2563eb; background: #eff6ff; }
    .dev-mov-info { flex: 1; min-width: 0; }
    .dev-mov-info strong { color: #0f172a; }
    .dev-mov-meta { color: #64748b; font-size: 0.78rem; margin-top: 2px; }
    .dev-mov-monto { font-weight: 800; white-space: nowrap; }
    .dev-paso { font-weight: 700; color: #334155; font-size: 0.9rem; margin: 16px 0 8px; }
    .dev-vacio { color: #64748b; font-size: 0.85rem; font-style: italic; margin: 4px 0 0; }
    .dev-nulo { color: #b91c1c; font-weight: 700; font-size: 0.8rem; white-space: nowrap; display: flex; gap: 6px; align-items: center; }
</style>

<div class="conc-page">

    {{-- Top bar --}}
    <div class="conc-topbar">
        <h2 class="conc-title">Conciliación <span>de egresos</span></h2>
        <div class="conc-toolbar">
            <button type="button" class="btn-upload" onclick="document.getElementById('uploadModal').style.display='flex'">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Añadir Movimientos
            </button>
            <a href="{{ route('finanzas.conciliaciones.lotes') }}" class="btn-upload" style="background:linear-gradient(135deg,#0f766e,#0d9488);text-decoration:none;">
                Lotes punto de venta
            </a>
            <button type="button" class="btn-upload" style="background:linear-gradient(135deg,#7c3aed,#6366f1);" onclick="document.getElementById('divisasModal').style.display='flex'">
                Transferencias en divisas / Pago móvil C2P
            </button>
            <button type="button" class="btn-upload" style="background:linear-gradient(135deg,#b45309,#d97706);" onclick="document.getElementById('pagosManualesModal').style.display='flex'">
                Pagos manuales (Cloud)
            </button>
            <button type="button" class="btn-upload" style="background:linear-gradient(135deg,#be185d,#db2777);" onclick="document.getElementById('casheaModal').style.display='flex'">
                Pagos Cashea
            </button>

            <form action="{{ route('finanzas.conciliaciones') }}" method="GET" style="display:flex;align-items:center;gap:8px;margin:0;flex-wrap:wrap;">
                <input type="date" name="fecha_desde" class="select-banco" value="{{ $filtrosConciliacion['fecha_desde'] ?? '' }}" title="Fecha Desde" required style="min-width: 130px;">
                <input type="date" name="fecha_hasta" class="select-banco" value="{{ $filtrosConciliacion['fecha_hasta'] ?? '' }}" title="Fecha Hasta" required style="min-width: 130px;">
                <select name="banco_filtro" class="select-banco" required>
                    <option value="" @selected(empty($filtrosConciliacion['banco_filtro']))>Selecciona un banco</option>
                    @foreach($bancos as $b)
                        <option value="{{ $b }}" {{ ($filtrosConciliacion['banco_filtro'] ?? '') == $b ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
                @php
                    $titsFiltro = $titularesPorBanco[$filtrosConciliacion['banco_filtro'] ?? ''] ?? [];
                    $titActual = $filtrosConciliacion['titular_filtro'] ?? '';
                    if ($titActual !== '' && ! in_array($titActual, $titsFiltro, true)) {
                        $titsFiltro[] = $titActual;
                    }
                @endphp
                <select name="titular_filtro" class="select-banco">
                    <option value="">Todos los titulares</option>
                    @foreach($titsFiltro as $tit)
                        <option value="{{ $tit }}" @selected($titActual === $tit)>{{ $tit }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-filtrar">Filtrar</button>
            </form>

            @if($lineas->count() > 0)
            <form action="{{ route('finanzas.conciliaciones.clear') }}" method="POST" onsubmit="return confirm('¿Borrar todos los movimientos cargados?');">
                @csrf
                <button type="submit" class="btn-clear">Limpiar Todo</button>
            </form>
            @endif
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert-success">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-success" style="background:#fef2f2;color:#991b1b;border-color:#fca5a5;">⚠️ {{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-success" style="background:#fef2f2;color:#991b1b;border-color:#fca5a5;">⚠️ {{ $errors->first() }}</div>
    @endif

    @if(($importacionesRecientes ?? collect())->isNotEmpty())
        <div class="bank-card" id="importaciones-panel" style="padding:14px 18px;">
            <div style="font-weight:800;color:#0f172a;margin-bottom:8px;">Archivos cargados recientemente</div>
            <table class="mini-table">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Estado</th>
                        <th style="text-align:right">Leídas</th>
                        <th style="text-align:right">Importadas</th>
                        <th style="text-align:right">Ya cargadas</th>
                        <th style="text-align:right">Conciliadas</th>
                        <th style="text-align:right">Por conciliar</th>
                        <th style="text-align:right">Rechazadas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($importacionesRecientes as $imp)
                        @php
                            $colorEstado = match ($imp->estado) {
                                \App\Models\ConciliacionImportacion::COMPLETADA => '#059669',
                                \App\Models\ConciliacionImportacion::COMPLETADA_CON_PENDIENTES => '#b45309',
                                \App\Models\ConciliacionImportacion::FALLIDA => '#dc2626',
                                default => '#2563eb',
                            };
                        @endphp
                        @php $impTerminada = $imp->terminada() && ! \Illuminate\Support\Facades\Cache::has(\App\Jobs\ProcesarImportacionExtracto::llaveConciliando($imp->id)); @endphp
                        <tr data-importacion="{{ $imp->id }}" data-terminada="{{ $impTerminada ? '1' : '0' }}"
                            data-url="{{ route('finanzas.conciliaciones.importaciones.show', $imp) }}">
                            <td style="font-size:0.82rem;">
                                <strong>{{ \Illuminate\Support\Str::limit($imp->archivo_nombre, 40) }}</strong>
                                <div style="color:#64748b;">{{ $imp->banco }} · {{ $imp->titular }} · {{ $imp->created_at?->format('d/m H:i') }}</div>
                                <div class="imp-mensaje" style="color:#64748b;">{{ $imp->mensaje }}</div>
                            </td>
                            <td><span class="imp-estado" style="font-weight:800;color:{{ $colorEstado }};">{{ $imp->etiquetaEstado() }}</span></td>
                            <td class="imp-leidas" style="text-align:right">{{ number_format($imp->filas_leidas) }}</td>
                            <td class="imp-importadas" style="text-align:right">{{ number_format($imp->filas_importadas) }}</td>
                            <td class="imp-duplicadas" style="text-align:right">{{ number_format($imp->filas_duplicadas) }}</td>
                            <td class="imp-conciliadas" style="text-align:right">{{ number_format($imp->filas_conciliadas) }}</td>
                            <td class="imp-pendientes" style="text-align:right">{{ number_format($imp->filas_pendientes) }}</td>
                            <td class="imp-rechazadas" style="text-align:right">
                                {{ number_format($imp->filas_rechazadas) }}
                                @if(! empty($imp->rechazos))
                                    <details style="text-align:left;font-size:0.78rem;">
                                        <summary style="cursor:pointer;color:#b45309;">Ver motivos</summary>
                                        @foreach(array_slice($imp->rechazos, 0, 50) as $r)
                                            <div>Fila {{ $r['fila'] }}: {{ $r['motivo'] }}</div>
                                        @endforeach
                                        @if(count($imp->rechazos) > 50 || $imp->filas_rechazadas > count($imp->rechazos))
                                            <div style="color:#64748b;">…y otras {{ $imp->filas_rechazadas - min(50, count($imp->rechazos)) }}</div>
                                        @endif
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($requiereFiltro ?? false)
        <div class="alert-info">
            Elige la fecha desde, la fecha hasta y el banco, y pulsa Filtrar.<br>
            <small>Solo se carga ese banco, aunque el período ya esté cerrado.</small>
        </div>
    @elseif($bancosActivos->isEmpty())
        <div class="alert-info">
            📂 No hay movimientos cargados en este rango para ese banco.<br>
            <small>Usa "Añadir Movimientos" para subir el estado de cuenta.</small>
        </div>
    @endif

    {{-- Per-bank sections --}}
    @foreach($bancosActivos as $bk_key)
    @php
        $d   = $data_por_banco[$bk_key];
        $bk  = $d['banco'];
        $tit = $d['titular'];
    @endphp

    <div class="bank-card">
        {{-- Bank header --}}
        <div class="bank-card-header">
            <div>
                <div class="bank-name">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    {{ $bk }}
                    <a href="{{ route('finanzas.conciliaciones.reporte-banco', [
                        'banco' => $bk,
                        'titular' => $tit,
                        'fecha_desde' => $filtrosConciliacion['fecha_desde'] ?? null,
                        'fecha_hasta' => $filtrosConciliacion['fecha_hasta'] ?? null,
                    ]) }}" style="margin-left: 15px; font-size: 0.75rem; background-color: rgba(255,255,255,0.15); color: #fff; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-weight: normal; vertical-align: middle;">PDF</a>
                    @php
                        $cierreMarcado = ($fecha_desde && $fecha_hasta && $tit)
                            ? ($cierresMarcados[$d['clave'] ?? ($bk.'|'.$tit)] ?? null)
                            : null;
                    @endphp
                    @if($fecha_desde && $fecha_hasta && $tit)
                        <form action="{{ route('finanzas.conciliaciones.recalcular') }}" method="POST" style="display:inline; margin-left:8px;"
                              title="Vuelve a buscar coincidencias para lo pendiente de esta cuenta en el período"
                              onsubmit="const b = this.querySelector('button'); b.disabled = true; b.textContent = '↻ Recalculando…';">
                            @csrf
                            <input type="hidden" name="banco" value="{{ $bk }}">
                            <input type="hidden" name="titular" value="{{ $tit }}">
                            <input type="hidden" name="fecha_desde" value="{{ $fecha_desde }}">
                            <input type="hidden" name="fecha_hasta" value="{{ $fecha_hasta }}">
                            <input type="hidden" name="banco_filtro" value="{{ $filtrosConciliacion['banco_filtro'] ?? '' }}">
                            <input type="hidden" name="titular_filtro" value="{{ $filtrosConciliacion['titular_filtro'] ?? '' }}">
                            <button type="submit" style="font-size: 0.75rem; background-color: rgba(255,255,255,0.15); color: #fff; border: none; padding: 4px 10px; border-radius: 4px; font-weight: 700; cursor: pointer;">↻ Recalcular</button>
                        </form>
                        @if($cierreMarcado)
                            <form action="{{ route('finanzas.conciliaciones.periodos.destroy', ['cierre' => $cierreMarcado->id, 'origen' => 'conciliaciones']) }}" method="POST" style="display:inline; margin-left:8px;" onsubmit="return confirm('¿Quitar el cierre de {{ $bk }} · {{ $tit }}?');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="fecha_desde" value="{{ $fecha_desde }}">
                                <input type="hidden" name="fecha_hasta" value="{{ $fecha_hasta }}">
                                <input type="hidden" name="banco_filtro" value="{{ $filtrosConciliacion['banco_filtro'] ?? '' }}">
                                <input type="hidden" name="titular_filtro" value="{{ $filtrosConciliacion['titular_filtro'] ?? '' }}">
                                <button type="submit" style="font-size: 0.75rem; background-color: #dcfce7; color: #166534; border: none; padding: 4px 10px; border-radius: 4px; font-weight: 700; cursor: pointer;">Cierre marcado</button>
                            </form>
                        @else
                            <form action="{{ route('finanzas.conciliaciones.periodos.store') }}" method="POST" style="display:inline; margin-left:8px;" onsubmit="return confirm('¿Marcar conciliado {{ $bk }} · {{ $tit }} del {{ \Carbon\Carbon::parse($fecha_desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fecha_hasta)->format('d/m/Y') }}?');">
                                @csrf
                                <input type="hidden" name="origen" value="conciliaciones">
                                <input type="hidden" name="banco" value="{{ $bk }}">
                                <input type="hidden" name="titular" value="{{ $tit }}">
                                <input type="hidden" name="fecha_desde" value="{{ $fecha_desde }}">
                                <input type="hidden" name="fecha_hasta" value="{{ $fecha_hasta }}">
                                <input type="hidden" name="banco_filtro" value="{{ $filtrosConciliacion['banco_filtro'] ?? '' }}">
                                <input type="hidden" name="titular_filtro" value="{{ $filtrosConciliacion['titular_filtro'] ?? '' }}">
                                <button type="submit" style="font-size: 0.75rem; background-color: rgba(255,255,255,0.15); color: #fff; border: none; padding: 4px 10px; border-radius: 4px; font-weight: 700; cursor: pointer;">Marcar cierre</button>
                            </form>
                        @endif
                    @endif
                </div>
                @if($tit)
                    <div style="color:rgba(255,255,255,.65); font-size:0.82rem; font-weight:600; margin-top:4px; letter-spacing:.3px;">
                        Titular: {{ $tit }}
                        @if(!empty($d['cuenta_digitos']))
                            · {{ $d['cuenta_digitos'] }}
                        @endif
                    </div>
                @endif
            </div>
            <div class="bank-totals-row">
                <div class="bank-stat">
                    <span class="bank-stat-label">✅ Consolidados</span>
                    <span class="bank-stat-value green">Bs. {{ number_format($d['total_conciliados'], 2) }}</span>
                </div>
                <div class="bank-stat">
                    <span class="bank-stat-label">🕐 En Tránsito</span>
                    <span class="bank-stat-value yellow">Bs. {{ number_format($d['total_transito'], 2) }}</span>
                </div>
                <div class="bank-stat">
                    <span class="bank-stat-label">⚠️ Movimientos en el Banco</span>
                    <span class="bank-stat-value red">Bs. {{ number_format($d['total_sin_registrar'], 2) }}</span>
                </div>
                <div class="bank-stat">
                    <span class="bank-stat-label">🏛️ Comisiones</span>
                    <span class="bank-stat-value orange">Bs. {{ number_format($d['total_comisiones'], 2) }}</span>
                    <span class="bank-stat-value" style="color:#bbf7d0; font-size:0.85rem;">${{ number_format($d['total_comisiones_usd'] ?? 0, 2) }}</span>
                </div>
                <div class="bank-stat">
                    <span class="bank-stat-label">💱 Compra divisas</span>
                    <span class="bank-stat-value" style="color:#38bdf8;">Bs. {{ number_format($d['total_compras_divisas'] ?? 0, 2) }}</span>
                </div>
                <div class="bank-stat">
                    <span class="bank-stat-label">💳 Pago crédito</span>
                    <span class="bank-stat-value" style="color:#fb7185;">Bs. {{ number_format(abs($d['total_pagos_credito'] ?? 0), 2) }}</span>
                </div>
            </div>

            {{-- New row for manual reconciliation calculation --}}
            <div style="margin-top: 10px; padding: 6px 12px; background: rgba(255,255,255,0.05); border-radius: 6px; display: inline-flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div style="display:flex; flex-direction:column; gap: 2px;">
                    <label style="font-size: 0.65rem; color: rgba(255,255,255,0.6); text-transform: uppercase; margin: 0;">Saldo Inicial (Manual)</label>
                    <input type="number" step="0.01" class="saldo-inicial-input" data-idx="{{ $loop->index }}"
                           data-movimiento-neto="{{ $d['movimiento_neto_sistema'] }}"
                           placeholder="0.00"
                           onkeyup="calcularDiferencia({{ $loop->index }})"
                           onchange="calcularDiferencia({{ $loop->index }})"
                           style="background: white; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 6px; font-weight: bold; width: 120px; font-size: 0.85rem; color: #334155; margin: 0; height: auto;">
                </div>

                <div style="font-size: 1.1rem; color: rgba(255,255,255,0.3);"> + </div>

                <div style="display:flex; flex-direction:column; gap: 2px;">
                    <label style="font-size: 0.65rem; color: rgba(255,255,255,0.6); text-transform: uppercase; margin: 0;">Movimiento Neto Sistema</label>
                    <span style="font-size: 0.9rem; font-weight: bold; color: white;">Bs. {{ number_format($d['movimiento_neto_sistema'], 2) }}</span>
                    <small style="font-size: 0.6rem; color: rgba(255,255,255,0.55);">Abonos {{ number_format($d['total_abonos_sistema'], 2) }} − cargos {{ number_format($d['total_cargos_sistema'], 2) }}</small>
                </div>

                <div style="font-size: 1.1rem; color: rgba(255,255,255,0.3);"> = </div>

                <div style="display:flex; flex-direction:column; gap: 2px;">
                    <label style="font-size: 0.65rem; color: rgba(255,255,255,0.6); text-transform: uppercase; margin: 0;">Saldo Sistema (Calc)</label>
                    <span id="saldo_sis_{{ $loop->index }}" style="font-size: 0.9rem; font-weight: bold; color: white;">Bs. {{ number_format($d['movimiento_neto_sistema'], 2) }}</span>
                </div>

                <div style="font-size: 1.1rem; color: rgba(255,255,255,0.3);"> | </div>

                <div style="display:flex; flex-direction:column; gap: 2px;">
                    <label style="font-size: 0.65rem; color: rgba(255,255,255,0.6); text-transform: uppercase; margin: 0;">Saldo Banco Final (Manual)</label>
                    <input type="number" step="0.01" class="saldo-banco-input" data-idx="{{ $loop->index }}"
                           placeholder="0.00"
                           onkeyup="calcularDiferencia({{ $loop->index }})"
                           onchange="calcularDiferencia({{ $loop->index }})"
                           style="background: white; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 6px; font-weight: bold; width: 120px; font-size: 0.85rem; color: #334155; margin: 0; height: auto;">
                </div>

                <div style="display:flex; flex-direction:column; gap: 2px;">
                    <label style="font-size: 0.65rem; color: rgba(255,255,255,0.6); text-transform: uppercase; margin: 0;">Diferencia</label>
                    <span id="dif_{{ $loop->index }}" style="font-size: 0.9rem; font-weight: bold; color: white;">Bs. 0.00</span>
                </div>
                
                <div id="status_{{ $loop->index }}" style="margin-left: 5px; font-size: 0.75rem; padding: 2px 8px; border-radius: 12px; font-weight: bold; display: none;"></div>
            </div>
        </div>


        <div class="sections-toolbar">
            <button type="button" class="section-toggle" onclick="toggleListasBanco(this)">Ocultar movimientos</button>
        </div>

        {{-- 4 sections grid --}}
        <div class="sections-grid">

            {{-- 1. CONSOLIDADOS --}}
            <div class="section-block" data-sec="{{ $bk_key }}|consolidados" style="order:3">
                <div class="section-header" onclick="toggleSeccion(this)">
                    <span class="section-badge badge-green"></span>
                    <span class="section-title">Consolidados</span>
                    <span class="section-count">{{ $d['conciliados']->count() }}</span>
                    <button type="button" class="section-toggle">Ocultar</button>
                </div>
                <div class="section-body">
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th>Motivo</th>
                            <th style="text-align:right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($d['conciliados'] as $row)
                        <tr>
                            <td>{{ ($row['fecha'] instanceof \DateTimeInterface ? $row['fecha']->format('d/m/Y') : substr((string) $row['fecha'], 8, 2).'/'.substr((string) $row['fecha'], 5, 2).'/'.substr((string) $row['fecha'], 0, 4)) }}</td>
                            <td>
                                <span class="ref-chip">{{ $row['referencia'] ?: '—' }}</span>
                            </td>
                            <td style="max-width:200px;font-size:0.82rem;">
                                {{ Str::limit($row['motivo'], 50) }}
                                @if($row['tipo_gasto'])
                                    <br><span class="tipo-chip chip-cargo">{{ $row['tipo_gasto'] }}</span>
                                @endif
                                @if(!empty($row['manual']))
                                    <br><span class="tipo-chip" style="background:#ecfdf5;color:#059669;">Manual</span>
                                @endif
                            </td>
                            @php $esEgreso = ($row['tipo'] ?? 'cargo') === 'cargo'; @endphp
                            <td class="monto-cell {{ $esEgreso ? 'monto-red' : 'monto-green' }}">
                                {{ $esEgreso ? '−' : '+' }}Bs. {{ number_format(abs((float) $row['monto']), 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr class="empty-row"><td colspan="4">Sin movimientos consolidados</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($d['conciliados']->count() > 0)
                @php
                    $ingresosCons = (float) $d['conciliados']->where('tipo', 'abono')->sum(fn ($r) => abs((float) $r['monto']));
                    $egresosCons = (float) $d['conciliados']->where('tipo', '!=', 'abono')->sum(fn ($r) => abs((float) $r['monto']));
                @endphp
                <div class="section-footer">
                    <span class="monto-green">Ingresos +Bs. {{ number_format($ingresosCons, 2) }}</span>
                    · <span class="monto-red">Egresos −Bs. {{ number_format($egresosCons, 2) }}</span>
                    · Neto: Bs. {{ number_format($ingresosCons - $egresosCons, 2) }}
                </div>
                @endif
                </div>
            </div>

            {{-- 2. EN TRÁNSITO --}}
            <div class="section-block" data-sec="{{ $bk_key }}|transito" style="order:2">
                <div class="section-header" onclick="toggleSeccion(this)">
                    <span class="section-badge badge-yellow"></span>
                    <span class="section-title">En Tránsito</span>
                    <span class="section-count">{{ $d['en_transito']->count() }}</span>
                    <button type="button" class="section-toggle">Ocultar</button>
                </div>
                <div class="section-body">
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th>Concepto / Motivo</th>
                            <th style="text-align:right">Monto Bs.</th>
                            <th style="width:44px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($d['en_transito'] as $row)
                        <tr>
                            <td>{{ ($row['fecha'] instanceof \DateTimeInterface ? $row['fecha']->format('d/m/Y') : substr((string) $row['fecha'], 8, 2).'/'.substr((string) $row['fecha'], 5, 2).'/'.substr((string) $row['fecha'], 0, 4)) }}</td>
                            <td><span class="ref-chip">{{ $row['referencia'] ?: '—' }}</span></td>
                            <td style="max-width:200px;font-size:0.82rem;">
                                {{ Str::limit($row['concepto'] ?: $row['motivo'], 50) }}
                                @if($row['tipo_gasto'])
                                    <br><span class="tipo-chip chip-cargo">{{ $row['tipo_gasto'] }}</span>
                                @endif
                                @if(!empty($row['duplicado_de']))
                                    <br><span class="tipo-chip" style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;margin-top:4px;display:inline-block;"
                                              title="{{ $row['duplicado_de']['motivo'] }}">Posible duplicado del #{{ $row['duplicado_de']['id'] }} (ya conciliado)</span>
                                @endif
                            </td>
                            <td class="monto-cell monto-yellow">{{ number_format($row['monto_bs'], 2) }}</td>
                            <td style="text-align:center;">
                                @if(!empty($row['flujo_id']) || !empty($row['compra_id']))
                                <button type="button" class="btn-dash-action btn-dash-menu" title="Opciones"
                                        data-flujo="{{ $row['flujo_id'] ?? '' }}" data-compra="{{ $row['compra_id'] ?? '' }}"
                                        onclick="abrirMenuTransito(event, this)">⋮</button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr class="empty-row"><td colspan="5">Sin movimientos en tránsito</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($d['en_transito']->count() > 0)
                <div class="section-footer">Total: Bs. {{ number_format($d['total_transito'], 2) }}</div>
                @endif
                </div>
            </div>

            {{-- 3. SIN REGISTRAR --}}
            <div class="section-block" data-sec="{{ $bk_key }}|banco" style="order:1">
                <div class="section-header" onclick="toggleSeccion(this)">
                    <span class="section-badge badge-red"></span>
                    <span class="section-title">Movimientos en el Banco</span>
                    <span class="section-count">{{ $d['sin_registrar']->count() }}</span>
                    <button type="button" class="section-toggle">Ocultar</button>
                </div>
                <div class="section-body">
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th>Descripción del Banco</th>
                            <th style="text-align:right">Monto</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // Cuentas con miles de pagos (Cashea): pintar todo tumba la página. Totales y contador usan todos.
                            $limiteMovimientosBanco ??= 600;
                            $filasBancoVisibles = $d['sin_registrar']->take($limiteMovimientosBanco);
                        @endphp
                        @forelse($filasBancoVisibles as $row)
                        <tr>
                            <td>{{ ($row['fecha'] instanceof \DateTimeInterface ? $row['fecha']->format('d/m/Y') : substr((string) $row['fecha'], 8, 2).'/'.substr((string) $row['fecha'], 5, 2).'/'.substr((string) $row['fecha'], 0, 4)) }}</td>
                            <td><span class="ref-chip">{{ $row['referencia'] ?: '—' }}</span></td>
                            <td style="max-width:200px;font-size:0.82rem;">
                                {{ Str::limit($row['descripcion'], 55) }}
                                <br>
                                <span class="tipo-chip {{ $row['tipo'] == 'cargo' ? 'chip-cargo' : 'chip-abono' }}">
                                    {{ $row['tipo'] == 'cargo' ? 'CARGO' : 'ABONO' }}
                                </span>
                            </td>
                            @php $esEgreso = ($row['tipo'] ?? 'cargo') === 'cargo'; @endphp
                            <td class="monto-cell {{ $esEgreso ? 'monto-red' : 'monto-green' }}">
                                {{ $esEgreso ? '−' : '+' }}Bs. {{ number_format(abs((float) $row['monto']), 2) }}
                            </td>
                            <td style="text-align:center;">
                                <button type="button" class="btn-dash-action btn-dash-menu" title="Opciones"
                                        data-linea="{{ $row['id'] }}" data-cuenta="{{ $bk_key }}"
                                        onclick="abrirMenuMovimiento(event, this)">⋮</button>
                            </td>
                        </tr>
                        @empty
                        <tr class="empty-row"><td colspan="5">Sin movimientos detectados</td></tr>
                        @endforelse
                        @if($d['sin_registrar']->count() > $filasBancoVisibles->count())
                        <tr class="empty-row"><td colspan="5" style="color:#b45309;">
                            Se muestran {{ number_format($filasBancoVisibles->count()) }} de {{ number_format($d['sin_registrar']->count()) }} movimientos (los totales sí incluyen todos).
                            Acorta el rango de fechas para ver el resto, o concilia en bloque con «Pagos Cashea», «Pagos manuales» o «Lotes punto de venta».
                        </td></tr>
                        @endif
                    </tbody>
                </table>
                @if($d['sin_registrar']->count() > 0)
                @php
                    $ingresosBanco = (float) $d['sin_registrar']->where('tipo', 'abono')->sum(fn ($r) => abs((float) $r['monto']));
                    $egresosBanco = (float) $d['sin_registrar']->where('tipo', '!=', 'abono')->sum(fn ($r) => abs((float) $r['monto']));
                @endphp
                <div class="section-footer">
                    <span class="monto-green">Ingresos +Bs. {{ number_format($ingresosBanco, 2) }}</span>
                    · <span class="monto-red">Egresos −Bs. {{ number_format($egresosBanco, 2) }}</span>
                    · Neto: Bs. {{ number_format($ingresosBanco - $egresosBanco, 2) }}
                </div>
                @endif
                </div>
            </div>

            {{-- 4. COMISIONES BANCARIAS --}}
            <div class="section-block" data-sec="{{ $bk_key }}|comisiones" style="order:4">
                <div class="section-header" onclick="toggleSeccion(this)">
                    <span class="section-badge badge-purple"></span>
                    <span class="section-title">Comisiones Bancarias</span>
                    <span class="section-count">{{ $d['comisiones']->count() }}</span>
                    <button type="button" class="section-toggle">Ocultar</button>
                </div>
                <div class="section-body">
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Descripción</th>
                            <th>Referencia</th>
                            <th style="text-align:right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($d['comisiones'] as $row)
                        <tr>
                            <td>{{ ($row['fecha'] instanceof \DateTimeInterface ? $row['fecha']->format('d/m/Y') : substr((string) $row['fecha'], 8, 2).'/'.substr((string) $row['fecha'], 5, 2).'/'.substr((string) $row['fecha'], 0, 4)) }}</td>
                            <td style="max-width:200px;font-size:0.82rem;">{{ Str::limit($row['descripcion'], 55) }}</td>
                            <td><span class="ref-chip">{{ $row['referencia'] ?: '—' }}</span></td>
                            <td class="monto-cell monto-purple">
                                Bs. {{ number_format($row['monto'], 2) }}
                                @if(($row['monto_usd'] ?? null) !== null)
                                    <div style="color:#047857; font-size:0.78rem;">${{ number_format($row['monto_usd'], 2) }}</div>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr class="empty-row"><td colspan="4">Sin comisiones detectadas</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($d['comisiones']->count() > 0)
                <div class="section-footer">
                    Total: Bs. {{ number_format($d['total_comisiones'], 2) }}
                    · ${{ number_format($d['total_comisiones_usd'] ?? 0, 2) }}
                </div>
                @endif
                </div>
            </div>

            {{-- 5. COMPRAS DE DIVISAS (extracto, pendientes de registrar/conciliar) --}}
            <div class="section-block" data-sec="{{ $bk_key }}|divisas">
                <div class="section-header" onclick="toggleSeccion(this)">
                    <span class="section-badge" style="background:#0ea5e9;"></span>
                    <span class="section-title">Compra de Divisas</span>
                    <span class="section-count">{{ ($d['compras_divisas_banco'] ?? collect())->count() }}</span>
                    <button type="button" class="section-toggle">Ocultar</button>
                </div>
                <div class="section-body">
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th>Descripción</th>
                            <th style="text-align:right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($d['compras_divisas_banco'] ?? collect()) as $row)
                        <tr>
                            <td>{{ ($row['fecha'] instanceof \DateTimeInterface ? $row['fecha']->format('d/m/Y') : substr((string) $row['fecha'], 8, 2).'/'.substr((string) $row['fecha'], 5, 2).'/'.substr((string) $row['fecha'], 0, 4)) }}</td>
                            <td><span class="ref-chip">{{ $row['referencia'] ?: '—' }}</span></td>
                            <td style="max-width:200px;font-size:0.82rem;">
                                {{ Str::limit($row['descripcion'], 55) }}
                                <br><span class="tipo-chip chip-cargo">Compra de divisas</span>
                            </td>
                            <td class="monto-cell" style="color:#0284c7;">Bs. {{ number_format(abs($row['monto']), 2) }}</td>
                        </tr>
                        @empty
                        <tr class="empty-row"><td colspan="4">Sin compras de divisas en el extracto</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if(($d['compras_divisas_banco'] ?? collect())->count() > 0)
                <div class="section-footer">Total: Bs. {{ number_format(abs($d['total_compras_divisas'] ?? 0), 2) }} · Regístralas en Flujo de caja → Compra de divisas</div>
                @endif
                </div>
            </div>

            {{-- 6. PAGO DE CRÉDITO BANCARIO (CREDITO DIGITAL, etc.) --}}
            <div class="section-block" data-sec="{{ $bk_key }}|credito">
                <div class="section-header" onclick="toggleSeccion(this)">
                    <span class="section-badge" style="background:#f43f5e;"></span>
                    <span class="section-title">Pago de Crédito</span>
                    <span class="section-count">{{ ($d['pagos_credito'] ?? collect())->count() }}</span>
                    <button type="button" class="section-toggle">Ocultar</button>
                </div>
                <div class="section-body">
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th>Descripción</th>
                            <th style="text-align:right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($d['pagos_credito'] ?? collect()) as $row)
                        <tr>
                            <td>{{ ($row['fecha'] instanceof \DateTimeInterface ? $row['fecha']->format('d/m/Y') : substr((string) $row['fecha'], 8, 2).'/'.substr((string) $row['fecha'], 5, 2).'/'.substr((string) $row['fecha'], 0, 4)) }}</td>
                            <td><span class="ref-chip">{{ $row['referencia'] ?: '—' }}</span></td>
                            <td style="max-width:200px;font-size:0.82rem;">
                                {{ Str::limit($row['descripcion'], 55) }}
                                <br><span class="tipo-chip chip-cargo">Pago crédito</span>
                            </td>
                            <td class="monto-cell monto-red">Bs. {{ number_format(abs($row['monto']), 2) }}</td>
                        </tr>
                        @empty
                        <tr class="empty-row"><td colspan="4">Sin pagos de crédito en el extracto</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if(($d['pagos_credito'] ?? collect())->count() > 0)
                <div class="section-footer">Total: Bs. {{ number_format(abs($d['total_pagos_credito'] ?? 0), 2) }}</div>
                @endif
                </div>
            </div>

        </div>{{-- end sections-grid --}}
    </div>{{-- end bank-card --}}
    @endforeach

</div>

{{-- UPLOAD MODAL --}}
<div id="uploadModal" class="modal-overlay">
    <div class="modal-box">
        <form action="{{ route('finanzas.conciliaciones.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-head">
                <h5 class="modal-title">Subir Archivo del Banco</h5>
                <button type="button" class="modal-close" onclick="document.getElementById('uploadModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">

                {{-- PASO 1: Banco --}}
                <label class="form-label">1. Selecciona el Banco:</label>
                <select name="banco_seleccionado" id="selectBanco" required class="form-control"
                        onchange="filtrarTitulares(this.value)">
                    <option value="">-- Seleccione el Banco --</option>
                    @foreach($bancos as $b)
                        <option value="{{ $b }}">{{ $b }}</option>
                    @endforeach
                </select>

                {{-- PASO 2: Titular (se filtra según el banco) --}}
                <label class="form-label">2. Selecciona el Titular:</label>
                <select name="titular_seleccionado" id="selectTitular" required class="form-control"
                        disabled style="background:#f8fafc; color:#94a3b8;">
                    <option value="">-- Primero seleccione un Banco --</option>
                </select>

                {{-- Archivo --}}
                <label class="form-label">3. Archivo (Excel o CSV):</label>
                <div class="file-wrap">
                    <label class="file-label">
                        Elegir Archivo
                        <input type="file" name="file[]" multiple accept=".csv,.txt,.xls,.xlsx,.pdf,image/jpeg,image/png,.jpg,.jpeg,.png" required
                               id="csvUploadInput"
                               onchange="document.getElementById('csvFileName').value = this.files.length > 1 ? this.files.length + ' archivos' : (this.files[0] ? this.files[0].name : '');"
                               style="display:none;">
                    </label>
                    <input type="text" id="csvFileName" placeholder="Ningún archivo seleccionado" readonly class="file-name">
                </div>
                <p class="file-hint">Formatos: CSV, TXT, Excel (.xlsx / .xls) o imagen (PNG / JPG). El sistema detecta el formato según el banco.</p>
                <p class="file-hint" style="color:#0f766e;">Los abonos LIQ.TARJETA / LIQUIDACION T/ no se concilian aquí. Van en <strong>Lotes punto de venta</strong>.</p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="document.getElementById('uploadModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-submit">Subir y Analizar</button>
            </div>
        </form>
    </div>
</div>

{{-- EXPORT DE CASHEA (documento adicional) --}}
<div id="casheaModal" class="modal-overlay">
    <div class="modal-box">
        <form action="{{ route('finanzas.conciliaciones.cashea.preview') }}" method="POST" enctype="multipart/form-data"
              onsubmit="const b = this.querySelector('.btn-submit'); b.disabled = true; b.innerText = 'Cruzando con el banco…';">
            @csrf
            <div class="modal-head">
                <h5 class="modal-title">Pagos Cashea</h5>
                <button type="button" class="modal-close" onclick="document.getElementById('casheaModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <p class="file-hint" style="margin-top:0;">
                    Sube el export de Cashea (Fecha, Referencia, Debito bs, Usd, Tipo pago, Orden…). Cada pago móvil se busca por
                    <strong>referencia y monto</strong> en los abonos de las cuentas de Cashea (BNC · L.S. CASHEA). Las cuotas pagadas con
                    un mismo pago móvil se suman. Primero verás el resultado; no se guarda nada hasta que confirmes.
                </p>
                <label class="form-label">Export de Cashea:</label>
                <div class="file-wrap">
                    <label class="file-label">
                        Elegir archivo
                        <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required style="display:none;"
                               onchange="document.getElementById('casheaFileName').value = this.files[0] ? this.files[0].name : '';">
                    </label>
                    <input type="text" id="casheaFileName" placeholder="Ningún archivo seleccionado" readonly class="file-name">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="document.getElementById('casheaModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-submit">Verificar</button>
            </div>
        </form>
    </div>
</div>

{{-- PAGOS MANUALES DE CLOUD (documento adicional) --}}
<div id="pagosManualesModal" class="modal-overlay">
    <div class="modal-box">
        <form action="{{ route('finanzas.conciliaciones.pagos_manuales.preview') }}" method="POST" enctype="multipart/form-data"
              onsubmit="const b = this.querySelector('.btn-submit'); b.disabled = true; b.innerText = 'Buscando en los bancos…';">
            @csrf
            <div class="modal-head">
                <h5 class="modal-title">Pagos manuales (Cloud)</h5>
                <button type="button" class="modal-close" onclick="document.getElementById('pagosManualesModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <p class="file-hint" style="margin-top:0;">
                    Sube el «Reporte específico de pagos manuales». El reporte no dice a qué cuenta llegó cada pago,
                    así que se busca por <strong>referencia y monto</strong> en los abonos de <strong>todos los bancos</strong>:
                    los de «Banco Nacional» en las cuentas en bolívares, y los de Binance, Zelle o Mercantil Panamá en las de divisas.
                    Primero verás el resultado; no se guarda nada hasta que confirmes.
                </p>
                <label class="form-label">Reporte de pagos manuales:</label>
                <div class="file-wrap">
                    <label class="file-label">
                        Elegir archivo
                        <input type="file" name="archivo" accept=".xls,.xlsx,.csv" required style="display:none;"
                               onchange="document.getElementById('pagosManualesFileName').value = this.files[0] ? this.files[0].name : '';">
                    </label>
                    <input type="text" id="pagosManualesFileName" placeholder="Ningún archivo seleccionado" readonly class="file-name">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="document.getElementById('pagosManualesModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-submit">Verificar</button>
            </div>
        </form>
    </div>
</div>

{{-- TRANSFERENCIAS EN DIVISAS (documento adicional) --}}
<div id="divisasModal" class="modal-overlay">
    <div class="modal-box">
        <form action="{{ route('finanzas.conciliaciones.divisas.preview') }}" method="POST" enctype="multipart/form-data"
              onsubmit="const b = this.querySelector('.btn-submit'); b.disabled = true; b.innerText = 'Leyendo documento…';">
            @csrf
            <div class="modal-head">
                <h5 class="modal-title">Transferencias en divisas / Pago móvil C2P</h5>
                <button type="button" class="modal-close" onclick="document.getElementById('divisasModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <p class="file-hint" style="margin-top:0;">
                    Sube el PDF de transferencias del proveedor de cambio (Wells Fargo, Citizens, Mercantil Panamá, efectivo…)
                    o el Excel de pagos móviles C2P (Dashboard Cloud Mai: Pago, Fecha, C2P, Ref, Importe…).
                    Cada envío se busca en los débitos pendientes del banco por referencia y por <strong>USD × tasa interna</strong>;
                    cada pago móvil, por su <strong>Importe</strong> en el débito PAGO MOVIL (±3 días).
                    Primero verás el resultado; no se guarda nada hasta que confirmes.
                </p>

                <label class="form-label">1. Banco del extracto:</label>
                <select name="banco" id="divisasBanco" required class="form-control" onchange="divisasTitulares(this.value)">
                    <option value="">-- Seleccione el Banco --</option>
                    @foreach($bancos as $b)
                        <option value="{{ $b }}" @selected(($filtrosConciliacion['banco_filtro'] ?? '') === $b)>{{ $b }}</option>
                    @endforeach
                </select>

                <label class="form-label">2. Titular de la cuenta:</label>
                <select name="titular" id="divisasTitular" required class="form-control"></select>

                <label class="form-label">3. Débitos del banco entre (opcional):</label>
                <div style="display:flex; gap:8px;">
                    <input type="date" name="fecha_desde" class="form-control" value="{{ $filtrosConciliacion['fecha_desde'] ?? '' }}">
                    <input type="date" name="fecha_hasta" class="form-control" value="{{ $filtrosConciliacion['fecha_hasta'] ?? '' }}">
                </div>
                <p class="file-hint">Si lo dejas vacío, se usan las fechas del documento (±3 días).</p>

                <label class="form-label">4. PDF de transferencias o Excel de pagos móviles C2P:</label>
                <div class="file-wrap">
                    <label class="file-label">
                        Elegir archivo
                        <input type="file" name="archivo" accept=".pdf,application/pdf,.xlsx,.xls" required style="display:none;"
                               onchange="document.getElementById('divisasFileName').value = this.files[0] ? this.files[0].name : '';">
                    </label>
                    <input type="text" id="divisasFileName" placeholder="Ningún archivo seleccionado" readonly class="file-name">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="document.getElementById('divisasModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-submit">Ver match</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    const titulares = @json($titularesPorBanco ?? []);
    const actual = @json($filtrosConciliacion['titular_filtro'] ?? '');
    window.divisasTitulares = function (banco) {
        const sel = document.getElementById('divisasTitular');
        const lista = titulares[banco] || [];
        sel.innerHTML = lista.length
            ? lista.map(t => `<option value="${String(t).replace(/"/g, '&quot;')}" ${t === actual ? 'selected' : ''}>${t}</option>`).join('')
            : '<option value="">-- Primero seleccione un Banco --</option>';
    };
    divisasTitulares(document.getElementById('divisasBanco').value);
})();
</script>

{{-- Un solo formulario para «pasar a comisiones»: el menú pone el id del movimiento. --}}
<form id="form-comision" action="{{ route('finanzas.conciliaciones.marcar_comision') }}" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="fecha_desde" value="{{ $filtrosConciliacion['fecha_desde'] ?? '' }}">
    <input type="hidden" name="fecha_hasta" value="{{ $filtrosConciliacion['fecha_hasta'] ?? '' }}">
    <input type="hidden" name="banco_filtro" value="{{ $filtrosConciliacion['banco_filtro'] ?? '' }}">
    <input type="hidden" name="titular_filtro" value="{{ $filtrosConciliacion['titular_filtro'] ?? '' }}">
    <input type="hidden" name="linea_id" value="">
</form>

{{-- Menú ⋮ de "Movimientos en el banco" (uno solo, flotante: la tabla tiene scroll y lo recortaría) --}}
<div id="movMenu" class="mov-menu-flotante" hidden>
    <button type="button" class="mov-menu-item" onclick="marcarComisionDesdeMenu()">
        Marcar como comisión
        <small>Pasa el movimiento a Comisiones bancarias</small>
    </button>
    <button type="button" class="mov-menu-item" onclick="abrirDevolucionDesdeMenu()">
        Devolución
        <small>Une el cargo con el abono que lo devolvió</small>
    </button>
    <button type="button" class="mov-menu-item" onclick="abrirJustificarLinea()">
        Justificar
        <small>Lo concilia explicando qué es el movimiento</small>
    </button>
</div>

{{-- JUSTIFICAR un movimiento del banco --}}
<div id="lineaJustModal" class="modal-overlay">
    <div class="modal-box" style="max-width:600px;">
        <form action="{{ route('finanzas.conciliaciones.linea.justificar') }}" method="POST" onsubmit="return confirmarJustificarLinea()">
            @csrf
            <input type="hidden" name="fecha_desde" value="{{ $filtrosConciliacion['fecha_desde'] ?? '' }}">
            <input type="hidden" name="fecha_hasta" value="{{ $filtrosConciliacion['fecha_hasta'] ?? '' }}">
            <input type="hidden" name="banco_filtro" value="{{ $filtrosConciliacion['banco_filtro'] ?? '' }}">
            <input type="hidden" name="titular_filtro" value="{{ $filtrosConciliacion['titular_filtro'] ?? '' }}">
            <input type="hidden" name="linea_id" id="ljLinea">
            <div class="modal-head">
                <h5 class="modal-title">Justificar movimiento del banco</h5>
                <button type="button" class="modal-close" onclick="cerrarJustificarLinea()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="dev-paso" style="margin-top:0;">Movimiento</div>
                <div id="ljOrigen"></div>
                <div class="dev-paso">¿Qué es?</div>
                <div id="ljSugerencias" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;"></div>
                <textarea name="justificacion" id="ljNota" rows="3" maxlength="500" required
                          style="width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:8px 10px;font-family:inherit;font-size:.86rem;"
                          placeholder="Explica qué es este movimiento y por qué no tiene registro en el sistema"></textarea>
                <p class="dev-vacio" style="font-style:normal;">Pasa a Consolidados con esta justificación como motivo. Queda guardado quién y cuándo lo justificó.</p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="cerrarJustificarLinea()">Cancelar</button>
                <button type="submit" class="btn-submit" id="ljGuardar">Conciliar con justificación</button>
            </div>
        </form>
    </div>
</div>

{{-- Menú ⋮ de "En tránsito" --}}
<div id="transitoMenu" class="mov-menu-flotante" hidden>
    <button type="button" class="mov-menu-item" onclick="transitoAutomatico()">
        Conciliar automáticamente
        <small>Busca en el banco el movimiento con el mismo monto</small>
    </button>
    <button type="button" class="mov-menu-item" onclick="abrirTransito('justificar')">
        Justificar
        <small>Elige el movimiento del banco o explica qué es</small>
    </button>
    <button type="button" class="mov-menu-item" onclick="abrirTransito('buscar')">
        Buscar el error
        <small>Busca el pago por monto y referencia en todos los bancos</small>
    </button>
</div>

<form id="form-transito-auto" action="{{ route('finanzas.conciliaciones.transito_conciliar') }}" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="fecha_desde" value="{{ $filtrosConciliacion['fecha_desde'] ?? '' }}">
    <input type="hidden" name="fecha_hasta" value="{{ $filtrosConciliacion['fecha_hasta'] ?? '' }}">
    <input type="hidden" name="banco_filtro" value="{{ $filtrosConciliacion['banco_filtro'] ?? '' }}">
    <input type="hidden" name="titular_filtro" value="{{ $filtrosConciliacion['titular_filtro'] ?? '' }}">
    <input type="hidden" name="flujo_id" value="">
    <input type="hidden" name="compra_id" value="">
</form>

<form id="form-transito-duplicado" action="{{ route('finanzas.conciliaciones.transito.anular_duplicado') }}" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="fecha_desde" value="{{ $filtrosConciliacion['fecha_desde'] ?? '' }}">
    <input type="hidden" name="fecha_hasta" value="{{ $filtrosConciliacion['fecha_hasta'] ?? '' }}">
    <input type="hidden" name="banco_filtro" value="{{ $filtrosConciliacion['banco_filtro'] ?? '' }}">
    <input type="hidden" name="titular_filtro" value="{{ $filtrosConciliacion['titular_filtro'] ?? '' }}">
    <input type="hidden" name="flujo_id" value="">
    <input type="hidden" name="duplicado_de" value="">
</form>

{{-- JUSTIFICAR / BUSCAR EL ERROR (movimiento en tránsito) --}}
<div id="transitoModal" class="modal-overlay">
    <div class="modal-box" style="max-width:760px;">
        <form id="transitoForm" action="{{ route('finanzas.conciliaciones.transito.justificar') }}" method="POST" onsubmit="return confirmarTransito()">
            @csrf
            <input type="hidden" name="fecha_desde" value="{{ $filtrosConciliacion['fecha_desde'] ?? '' }}">
            <input type="hidden" name="fecha_hasta" value="{{ $filtrosConciliacion['fecha_hasta'] ?? '' }}">
            <input type="hidden" name="banco_filtro" value="{{ $filtrosConciliacion['banco_filtro'] ?? '' }}">
            <input type="hidden" name="titular_filtro" value="{{ $filtrosConciliacion['titular_filtro'] ?? '' }}">
            <input type="hidden" name="flujo_id" id="trFlujo">
            <input type="hidden" name="compra_id" id="trCompra">
            <input type="hidden" name="mover_cuenta" id="trMover" value="0">
            <div class="modal-head">
                <h5 class="modal-title" id="trTitulo">Justificar</h5>
                <button type="button" class="modal-close" onclick="cerrarTransito()">&times;</button>
            </div>
            <div class="modal-body" style="max-height:68vh;overflow-y:auto;">
                <div class="dev-paso" style="margin-top:0;">Movimiento en tránsito</div>
                <div id="trOrigen"><p class="dev-vacio">Cargando…</p></div>
                <div id="trCuerpo"></div>
                <div class="dev-paso">Justificación <span id="trNotaAyuda" style="font-weight:400;color:#64748b;font-size:.8rem;"></span></div>
                <textarea name="justificacion" id="trNota" rows="2" maxlength="500" style="width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:8px 10px;font-family:inherit;font-size:.86rem;"
                          placeholder="Ej.: se pagó en efectivo, comisión incluida en otro cargo, el banco lo reflejó en octubre…"></textarea>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="cerrarTransito()">Cancelar</button>
                <button type="submit" class="btn-submit" id="trGuardar" disabled>Conciliar</button>
            </div>
        </form>
    </div>
</div>

{{-- DEVOLUCIÓN --}}
<div id="devolucionModal" class="modal-overlay">
    <div class="modal-box" style="max-width:640px;">
        <form id="devolucionForm" action="{{ route('finanzas.conciliaciones.devolucion') }}" method="POST" onsubmit="return confirmarDevolucion()">
            @csrf
            <input type="hidden" name="fecha_desde" value="{{ $filtrosConciliacion['fecha_desde'] ?? '' }}">
            <input type="hidden" name="fecha_hasta" value="{{ $filtrosConciliacion['fecha_hasta'] ?? '' }}">
            <input type="hidden" name="banco_filtro" value="{{ $filtrosConciliacion['banco_filtro'] ?? '' }}">
            <input type="hidden" name="titular_filtro" value="{{ $filtrosConciliacion['titular_filtro'] ?? '' }}">
            <input type="hidden" name="linea_id" id="devLineaId">
            <div class="modal-head">
                <h5 class="modal-title">Marcar devolución</h5>
                <button type="button" class="modal-close" onclick="cerrarDevolucion()">&times;</button>
            </div>
            <div class="modal-body" style="max-height:65vh;overflow-y:auto;">
                <div class="dev-paso" style="margin-top:0;">Movimiento elegido</div>
                <div id="devOrigen"></div>

                <div class="dev-paso" id="devPasoContraparte"></div>
                <div id="devContrapartes"></div>

                <div id="devBloqueEgresos" hidden>
                    <div class="dev-paso">Egresos del sistema con la misma referencia del cargo</div>
                    <div id="devEgresos"></div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="cerrarDevolucion()">Cancelar</button>
                <button type="submit" class="btn-submit" id="devGuardar" disabled>Registrar devolución</button>
            </div>
        </form>
    </div>
</div>

{{-- MEDIOS DE PAGO BDV se carga en Lotes punto de venta --}}
{{-- Mapa banco → titulares (generado desde PHP/BD) --}}
<script>
// Avance de importaciones en segundo plano: consulta cada 3 s mientras alguna siga en curso.
(function () {
    const filas = () => [...document.querySelectorAll('tr[data-importacion][data-terminada="0"]')];
    if (!filas().length) return;
    const colores = { completada: '#059669', completada_con_pendientes: '#b45309', fallida: '#dc2626' };
    const fmt = n => Number(n || 0).toLocaleString('es-VE');
    let terminoAlguna = false;
    const tick = async () => {
        const pendientes = filas();
        if (!pendientes.length) {
            if (terminoAlguna) window.location.reload();
            return;
        }
        await Promise.all(pendientes.map(async tr => {
            try {
                const r = await fetch(tr.dataset.url, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                tr.querySelector('.imp-estado').textContent = d.estado_texto;
                tr.querySelector('.imp-estado').style.color = colores[d.estado] || '#2563eb';
                tr.querySelector('.imp-leidas').textContent = fmt(d.filas_leidas);
                tr.querySelector('.imp-importadas').textContent = fmt(d.filas_importadas);
                tr.querySelector('.imp-duplicadas').textContent = fmt(d.filas_duplicadas);
                tr.querySelector('.imp-conciliadas').textContent = fmt(d.filas_conciliadas);
                tr.querySelector('.imp-pendientes').textContent = fmt(d.filas_pendientes);
                tr.querySelector('.imp-mensaje').textContent = d.mensaje || '';
                if (d.terminada) {
                    tr.dataset.terminada = '1';
                    terminoAlguna = true;
                }
            } catch (e) { /* se reintenta en el siguiente ciclo */ }
        }));
        setTimeout(tick, 3000);
    };
    setTimeout(tick, 2000);
})();

const titularesPorBanco = @json($titularesPorBanco);

@php
    // Solo lo que se ve en la tabla y todos los cargos (contraparte de una devolución de abono): con miles de abonos el JSON no cabe.
    $limiteMovimientosBanco ??= 600;
    $movimientosPendientesJs = collect($data_por_banco ?? [])->map(fn ($d) => $d['sin_registrar']->take($limiteMovimientosBanco)
        ->concat($d['sin_registrar']->slice($limiteMovimientosBanco)->filter(fn ($r) => ($r['tipo'] ?? 'cargo') !== 'abono'))
        ->map(fn ($r) => [
        'id' => $r['id'],
        'fecha' => $r['fecha'] instanceof \DateTimeInterface ? $r['fecha']->format('d/m/Y') : \Carbon\Carbon::parse($r['fecha'])->format('d/m/Y'),
        'referencia' => $r['referencia'],
        'descripcion' => $r['descripcion'],
        'monto' => round(abs((float) $r['monto']), 2),
        'tipo' => ($r['tipo'] ?? 'cargo') === 'abono' ? 'abono' : 'cargo',
    ])->values());
    $urlEgresosDevolucion = route('finanzas.conciliaciones.devolucion.egresos');
@endphp
// Movimientos pendientes por cuenta, para elegir la otra mitad de una devolución.
const movimientosPendientes = @json($movimientosPendientesJs);
const urlEgresosDevolucion = @json($urlEgresosDevolucion);
let menuMovimiento = { linea: null, cuenta: null };

const fmtBs = n => Number(n || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const htmlMovimiento = (m, extra = '') => `
    <div class="dev-mov-info">
        <strong>${esc(m.descripcion)}</strong>
        <div class="dev-mov-meta">${esc(m.fecha)} · Ref. ${esc(m.referencia || '—')} · ${m.tipo === 'abono' ? 'ABONO' : 'CARGO'}</div>
        ${extra}
    </div>
    <div class="dev-mov-monto" style="color:${m.tipo === 'abono' ? '#059669' : '#dc2626'}">${m.tipo === 'abono' ? '+' : '−'}Bs. ${fmtBs(m.monto)}</div>`;

function abrirMenuMovimiento(ev, boton) {
    ev.stopPropagation();
    const menu = document.getElementById('movMenu');
    const mismo = !menu.hidden && menuMovimiento.linea === boton.dataset.linea;
    menu.hidden = true;
    if (mismo) return;
    menuMovimiento = { linea: boton.dataset.linea, cuenta: boton.dataset.cuenta };
    menu.hidden = false;
    const r = boton.getBoundingClientRect();
    const ancho = menu.offsetWidth;
    const alto = menu.offsetHeight;
    menu.style.left = Math.max(8, r.right - ancho) + 'px';
    menu.style.top = (r.bottom + 6 + alto > window.innerHeight ? r.top - alto - 6 : r.bottom + 6) + 'px';
}
function cerrarMenuMovimiento() { document.getElementById('movMenu').hidden = true; }
document.addEventListener('click', e => { if (!e.target.closest('#movMenu')) cerrarMenuMovimiento(); });
window.addEventListener('scroll', cerrarMenuMovimiento, true);

function marcarComisionDesdeMenu() {
    cerrarMenuMovimiento();
    if (confirm('¿Pasar este movimiento a Comisiones bancarias?')) {
        const form = document.getElementById('form-comision');
        form.querySelector('[name=linea_id]').value = menuMovimiento.linea;
        form.submit();
    }
}

const sugerenciasJustificacion = {
    abono: ['Cobro Cashea', 'Pago móvil de cliente', 'Venta por transferencia', 'Traslado desde otra cuenta propia', 'Reintegro / devolución de proveedor', 'Intereses del banco'],
    cargo: ['Traslado a otra cuenta propia', 'Pago a proveedor sin registrar', 'Impuesto / retención', 'Débito automático del banco', 'Pago de nómina'],
};
function abrirJustificarLinea() {
    cerrarMenuMovimiento();
    const lista = movimientosPendientes[menuMovimiento.cuenta] || [];
    const mov = lista.find(m => String(m.id) === String(menuMovimiento.linea));
    if (!mov) return;
    document.getElementById('ljLinea').value = mov.id;
    document.getElementById('ljOrigen').innerHTML = `<div class="dev-mov is-origen">${htmlMovimiento(mov)}</div>`;
    document.getElementById('ljNota').value = '';
    document.getElementById('ljGuardar').disabled = false;
    document.getElementById('ljSugerencias').innerHTML = (sugerenciasJustificacion[mov.tipo] || []).map(s =>
        `<button type="button" class="tipo-chip" style="border:1px solid #e2e8f0;background:#f8fafc;color:#334155;cursor:pointer;" onclick="usarSugerencia(this)">${esc(s)}</button>`).join('');
    document.getElementById('lineaJustModal').style.display = 'flex';
    document.getElementById('ljNota').focus();
}
function usarSugerencia(boton) {
    const nota = document.getElementById('ljNota');
    nota.value = boton.textContent + (nota.value.trim() ? ' — ' + nota.value.trim() : ': ');
    nota.focus();
}
function confirmarJustificarLinea() {
    if (document.getElementById('ljNota').value.trim().length < 5) {
        alert('Escribe qué es el movimiento (mínimo 5 caracteres).');
        return false;
    }
    document.getElementById('ljGuardar').disabled = true;
    return true;
}
function cerrarJustificarLinea() { document.getElementById('lineaJustModal').style.display = 'none'; }

// ── En tránsito: ⋮ conciliar automáticamente / justificar / buscar el error ──
const urlBuscarTransito = @json(route('finanzas.conciliaciones.transito.buscar'));
let menuTransito = { flujo: '', compra: '' };

function abrirMenuTransito(ev, boton) {
    ev.stopPropagation();
    const menu = document.getElementById('transitoMenu');
    const clave = boton.dataset.flujo + '|' + boton.dataset.compra;
    const mismo = !menu.hidden && (menuTransito.flujo + '|' + menuTransito.compra) === clave;
    menu.hidden = true;
    if (mismo) return;
    menuTransito = { flujo: boton.dataset.flujo, compra: boton.dataset.compra };
    menu.hidden = false;
    const r = boton.getBoundingClientRect();
    menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
    menu.style.top = (r.bottom + 6 + menu.offsetHeight > window.innerHeight ? r.top - menu.offsetHeight - 6 : r.bottom + 6) + 'px';
}
function cerrarMenuTransito() { document.getElementById('transitoMenu').hidden = true; }
document.addEventListener('click', e => { if (!e.target.closest('#transitoMenu')) cerrarMenuTransito(); });
window.addEventListener('scroll', cerrarMenuTransito, true);

function transitoAutomatico() {
    cerrarMenuTransito();
    const form = document.getElementById('form-transito-auto');
    form.querySelector('[name=flujo_id]').value = menuTransito.flujo;
    form.querySelector('[name=compra_id]').value = menuTransito.compra;
    form.submit();
}

const chip = (texto, color, fondo) => `<span class="tipo-chip" style="background:${fondo};color:${color};margin:4px 4px 0 0;display:inline-block;">${texto}</span>`;
function htmlLineaTransito(l, conRadio) {
    const chips = (l.mismo_monto ? chip('Mismo monto', '#059669', '#ecfdf5') : '')
        + (l.misma_ref ? chip('Misma referencia', '#2563eb', '#eff6ff') : '')
        + (l.x100 ? chip('Monto ×100 / ÷100', '#b45309', '#fffbeb') : '')
        + (!l.misma_cuenta ? chip('Otra cuenta', '#7c3aed', '#f5f3ff') : '');
    const anular = l.duplicado_de && menuTransito.flujo
        ? `<button type="button" class="tipo-chip" style="border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;cursor:pointer;margin-top:6px;" onclick="anularDuplicado(event, ${l.duplicado_de})">Anular este egreso (duplicado del #${l.duplicado_de})</button>`
        : '';
    const info = `<div class="dev-mov-info">
            <strong>${esc(l.descripcion)}</strong>
            <div class="dev-mov-meta">${esc(l.fecha)} (${esc(l.cuando)}) · Ref. ${esc(l.referencia || '—')} · ${esc(l.cuenta)} · ${l.tipo === 'abono' ? 'ABONO' : 'CARGO'}</div>
            ${l.ligado ? `<div class="dev-mov-meta" style="color:#b45309;">${esc(l.ligado)}</div>` : ''}
            <div class="dev-mov-meta" style="color:#334155;">${esc(l.pista)}</div>
            <div>${chips}</div>
            ${anular}
        </div>
        <div class="dev-mov-monto" style="color:${l.tipo === 'abono' ? '#059669' : '#dc2626'}">Bs. ${fmtBs(l.monto)}</div>`;
    if (!conRadio || !l.accion) return `<div class="dev-mov" style="opacity:${l.accion ? 1 : .75}">${info}</div>`;
    const etiqueta = l.accion === 'mover' ? '<div class="dev-mov-meta" style="color:#7c3aed;font-weight:700;">Al elegirla, el egreso pasa a esta cuenta</div>' : '';
    return `<label class="dev-mov"><input type="radio" name="linea_id" value="${l.id}" data-mover="${l.accion === 'mover' ? 1 : 0}" data-nota="${l.pide_nota ? 1 : 0}" onchange="elegirLineaTransito(this)">${info.replace('</strong>', '</strong>' + etiqueta)}</label>`;
}

async function abrirTransito(modo) {
    cerrarMenuTransito();
    document.getElementById('trFlujo').value = menuTransito.flujo;
    document.getElementById('trCompra').value = menuTransito.compra;
    document.getElementById('trMover').value = '0';
    document.getElementById('trNota').value = '';
    document.getElementById('trGuardar').disabled = true;
    document.getElementById('trTitulo').textContent = modo === 'buscar' ? 'Buscar el error' : 'Justificar';
    document.getElementById('trOrigen').innerHTML = '<p class="dev-vacio">Buscando…</p>';
    document.getElementById('trCuerpo').innerHTML = '';
    document.getElementById('trNotaAyuda').textContent = '';
    document.getElementById('transitoModal').style.display = 'flex';

    let d;
    try {
        const params = new URLSearchParams({ flujo_id: menuTransito.flujo, compra_id: menuTransito.compra });
        const r = await fetch(urlBuscarTransito + '?' + params, { headers: { 'Accept': 'application/json' } });
        d = await r.json();
        if (!r.ok) throw new Error(d.message || '');
    } catch (err) {
        document.getElementById('trOrigen').innerHTML = `<p class="dev-vacio" style="color:#b91c1c;">No se pudo buscar. ${esc(err.message)}</p>`;
        return;
    }
    const m = d.movimiento;
    document.getElementById('trOrigen').innerHTML = `<div class="dev-mov is-origen"><div class="dev-mov-info"><strong>${esc(m.concepto)}</strong>
        <div class="dev-mov-meta">${esc(m.fecha)} · Ref. ${esc(m.referencia || '—')} · ${esc(m.cuenta)}</div></div>
        <div class="dev-mov-monto" style="color:#b45309;">Bs. ${fmtBs(m.monto)}</div></div>`;

    let html = '';
    if (modo === 'justificar') {
        html += '<div class="dev-paso">¿Con qué movimiento del banco se pagó? (cargos pendientes de esta cuenta, ±30 días)</div>';
        html += d.pendientes_cuenta.length
            ? d.pendientes_cuenta.map(l => htmlLineaTransito(l, true)).join('')
            : '<p class="dev-vacio">No hay cargos pendientes en esta cuenta cerca de esa fecha.</p>';
        html += `<label class="dev-mov"><input type="radio" name="linea_id" value="" data-mover="0" data-nota="1" onchange="elegirLineaTransito(this)">
            <div class="dev-mov-info"><strong>No aparece en el banco</strong><div class="dev-mov-meta">Se da por conciliado solo con la justificación que escribas abajo.</div></div></label>`;
    } else {
        const titulos = ['Mismo monto y misma referencia', 'Mismo monto', 'Misma referencia (otro monto)', 'Posible error de coma (monto ×100 / ÷100)'];
        if (d.duplicados.length) {
            html += '<div class="dev-paso">Otros egresos registrados en el sistema con el mismo monto o la misma referencia</div>';
            html += d.duplicados.map(e => `<div class="dev-mov"${e.duplicado ? ' style="border-color:#fca5a5;"' : ''}><div class="dev-mov-info"><strong>#${e.id} · ${esc(e.motivo)}</strong>
                <div class="dev-mov-meta">${esc(e.fecha)} (${esc(e.cuando)}) · Ref. ${esc(e.referencia || '—')} · ${esc(e.cuenta)}</div>
                <div class="dev-mov-meta" style="color:${e.conciliado ? '#059669' : '#b45309'};">${e.conciliado ? 'Conciliado' : 'En tránsito'}${e.duplicado ? ' · <strong style="color:#b91c1c;">Posible duplicado de este egreso</strong>' : ''}</div>
                <div>${(e.mismo_monto ? chip('Mismo monto', '#059669', '#ecfdf5') : '') + (e.misma_ref ? chip('Misma referencia', '#2563eb', '#eff6ff') : '')}</div>
                ${e.duplicado ? `<button type="button" class="tipo-chip" style="border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;cursor:pointer;margin-top:6px;" onclick="anularDuplicado(event, ${e.id})">Anular este egreso en tránsito (es copia del #${e.id})</button>` : ''}
                </div>
                <div class="dev-mov-monto" style="color:#dc2626;">Bs. ${fmtBs(e.monto)}</div></div>`).join('');
        }
        html += '<div class="dev-paso">Dónde puede estar el pago en los bancos</div>';
        if (!d.resultados.length) {
            html += '<p class="dev-vacio">No hay ningún movimiento en los extractos con ese monto ni esa referencia. Puede que el extracto de ese período aún no esté cargado, o que el pago no haya salido.</p>';
        }
        let grupoActual = null;
        d.resultados.forEach(l => {
            if (l.grupo !== grupoActual) {
                grupoActual = l.grupo;
                html += `<div style="font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.4px;margin:12px 0 6px;">${titulos[l.grupo]}</div>`;
            }
            html += htmlLineaTransito(l, true);
        });
    }
    document.getElementById('trCuerpo').innerHTML = html;
    document.getElementById('trNotaAyuda').textContent = modo === 'buscar' ? '(opcional; obligatoria si el monto no coincide)' : '';
}

function anularDuplicado(ev, originalId) {
    ev.preventDefault();
    ev.stopPropagation();
    if (!confirm(`Este egreso en tránsito se marcará como NULO por estar duplicado con el egreso #${originalId}. No se borra: queda anulado con el motivo y sale de En tránsito. ¿Continuar?`)) return;
    const form = document.getElementById('form-transito-duplicado');
    form.querySelector('[name=flujo_id]').value = menuTransito.flujo;
    form.querySelector('[name=duplicado_de]').value = originalId;
    form.submit();
}

function elegirLineaTransito(input) {
    document.getElementById('trMover').value = input.dataset.mover;
    document.getElementById('trGuardar').disabled = false;
    document.getElementById('trNotaAyuda').textContent = input.dataset.nota === '1'
        ? (input.value ? '(obligatoria: el monto del banco no coincide)' : '(obligatoria: explica qué es)')
        : '(opcional)';
}

function confirmarTransito() {
    const elegido = document.querySelector('#trCuerpo input[name="linea_id"]:checked');
    if (!elegido) return false;
    const nota = document.getElementById('trNota').value.trim();
    if (elegido.dataset.nota === '1' && nota.length < 5) {
        alert('Escribe la justificación (mínimo 5 caracteres).');
        document.getElementById('trNota').focus();
        return false;
    }
    if (elegido.dataset.mover === '1' && !confirm('El egreso se cambiará a la cuenta de ese movimiento y quedará conciliado. ¿Continuar?')) return false;
    document.getElementById('trGuardar').disabled = true;
    return true;
}
function cerrarTransito() { document.getElementById('transitoModal').style.display = 'none'; }

function abrirDevolucionDesdeMenu() {
    cerrarMenuMovimiento();
    const lista = movimientosPendientes[menuMovimiento.cuenta] || [];
    const origen = lista.find(m => String(m.id) === String(menuMovimiento.linea));
    if (!origen) return;

    document.getElementById('devLineaId').value = origen.id;
    document.getElementById('devOrigen').innerHTML = `<div class="dev-mov is-origen">${htmlMovimiento(origen)}</div>`;
    const buscado = origen.tipo === 'abono' ? 'cargo' : 'abono';
    document.getElementById('devPasoContraparte').textContent = buscado === 'cargo'
        ? 'Marca el movimiento del banco que se devolvió (el cargo):'
        : 'Marca el movimiento del banco donde está el abono de la devolución:';

    // Mercantil: la devolución trae la misma cola de 8 dígitos que el cargo devuelto (171…03730500 / 204…03730500).
    const candidatos = lista
        .filter(m => m.tipo === buscado && Math.abs(m.monto - origen.monto) < 0.005)
        .map(m => ({ ...m, mismaRef: referenciasCoinciden(m.referencia, origen.referencia) }))
        .sort((a, b) => Number(b.mismaRef) - Number(a.mismaRef));
    const conRef = candidatos.filter(m => m.mismaRef);
    document.getElementById('devContrapartes').innerHTML = candidatos.length
        ? candidatos.map(m => `<label class="dev-mov"><input type="radio" name="contraparte_id" value="${m.id}" onchange="buscarEgresosDevolucion()"${conRef.length === 1 && m.mismaRef ? ' checked' : ''}>${htmlMovimiento(m, m.mismaRef ? '<span class="tipo-chip" style="background:#ecfdf5;color:#059669;margin-top:4px;display:inline-block;">Misma referencia</span>' : '')}</label>`).join('')
        : `<p class="dev-vacio">No hay ${buscado === 'cargo' ? 'un cargo' : 'un abono'} pendiente de Bs. ${fmtBs(origen.monto)} en esta cuenta y período.</p>`;

    document.getElementById('devBloqueEgresos').hidden = true;
    document.getElementById('devEgresos').innerHTML = '';
    document.getElementById('devGuardar').disabled = true;
    document.getElementById('devolucionModal').style.display = 'flex';
    if (conRef.length === 1) buscarEgresosDevolucion();
}

function referenciasCoinciden(a, b) {
    const da = String(a || '').replace(/\D+/g, '').replace(/^0+/, '');
    const db = String(b || '').replace(/\D+/g, '').replace(/^0+/, '');
    if (!da || !db) return false;
    if (da === db) return true;
    return da.length >= 8 && db.length >= 8 && da.slice(-8) === db.slice(-8);
}

function cerrarDevolucion() { document.getElementById('devolucionModal').style.display = 'none'; }

async function buscarEgresosDevolucion() {
    const elegido = document.querySelector('#devContrapartes input[name="contraparte_id"]:checked');
    if (!elegido) return;
    document.getElementById('devGuardar').disabled = false;
    const bloque = document.getElementById('devBloqueEgresos');
    const caja = document.getElementById('devEgresos');
    bloque.hidden = false;
    caja.innerHTML = '<p class="dev-vacio">Buscando egresos…</p>';

    const params = new URLSearchParams({ linea_id: document.getElementById('devLineaId').value, contraparte_id: elegido.value });
    try {
        const r = await fetch(urlEgresosDevolucion + '?' + params, { headers: { 'Accept': 'application/json' } });
        const d = await r.json();
        if (!r.ok) {
            caja.innerHTML = `<p class="dev-vacio" style="color:#b91c1c;">${esc(d.message || 'No se pudo buscar los egresos.')}</p>`;
            document.getElementById('devGuardar').disabled = true;
            return;
        }
        if (!d.egresos.length) {
            caja.innerHTML = '<p class="dev-vacio">Ningún egreso del sistema tiene la referencia del cargo. Solo se unirán los dos movimientos del banco.</p>';
            return;
        }
        caja.innerHTML = d.egresos.map(e => {
            const meta = `<div class="dev-mov-meta">${esc(e.fecha)} · Ref. ${esc(e.referencia || '—')}${e.tipo_gasto ? ' · ' + esc(e.tipo_gasto) : ''}</div>`
                + (e.conciliado_con ? `<div class="dev-mov-meta" style="color:#b45309;">${esc(e.conciliado_con)}</div>` : '');
            const marca = e.conciliado_con
                ? '<span class="dev-mov-meta">No se puede anular</span>'
                : `<label class="dev-nulo"><input type="checkbox" name="anular[]" value="${e.id}"> Marcar como nulo</label>`;
            return `<div class="dev-mov">
                <div class="dev-mov-info"><strong>${esc(e.motivo)}</strong>${meta}</div>
                <div style="text-align:right;">
                    <div class="dev-mov-monto" style="color:#dc2626;">Bs. ${fmtBs(e.monto_bs)}</div>
                    ${marca}
                </div>
            </div>`;
        }).join('');
    } catch (err) {
        caja.innerHTML = '<p class="dev-vacio" style="color:#b91c1c;">No se pudo buscar los egresos. Intenta de nuevo.</p>';
    }
}

function confirmarDevolucion() {
    const nulos = document.querySelectorAll('#devEgresos input[name="anular[]"]:checked').length;
    return confirm(nulos
        ? `Se unirán los dos movimientos del banco como devolución y ${nulos} egreso(s) quedarán NULOS (dejan de contar en el flujo de caja). ¿Continuar?`
        : 'Se unirán los dos movimientos del banco como devolución. ¿Continuar?');
}

function filtrarTitulares(banco) {
    const selTit = document.getElementById('selectTitular');
    selTit.innerHTML = '<option value="">-- Seleccione el Titular --</option>';

    if (!banco || !titularesPorBanco[banco]) {
        selTit.disabled = true;
        selTit.style.background = '#f8fafc';
        selTit.style.color = '#94a3b8';
        return;
    }

    titularesPorBanco[banco].forEach(function(tit) {
        const opt = document.createElement('option');
        opt.value = tit;
        opt.textContent = tit;
        selTit.appendChild(opt);
    });

    selTit.disabled = false;
    selTit.style.background = 'white';
    selTit.style.color = '#334155';

    // Si solo hay un titular, seleccionarlo automáticamente
    if (titularesPorBanco[banco].length === 1) {
        selTit.value = titularesPorBanco[banco][0];
    }
}

function aplicarEstadoSeccion(block, collapsed) {
    block.classList.toggle('is-collapsed', collapsed);
    const btn = block.querySelector('.section-toggle');
    if (btn) btn.textContent = collapsed ? 'Mostrar' : 'Ocultar';
    if (block.dataset.sec) {
        localStorage.setItem('conc-oculto-' + block.dataset.sec, collapsed ? '1' : '0');
    }
}

function sincronizarBotonBanco(card) {
    const master = card.querySelector('.sections-toolbar .section-toggle');
    if (!master) return;
    const blocks = card.querySelectorAll('.section-block');
    const todosOcultos = blocks.length > 0 && Array.from(blocks).every(function (block) {
        return block.classList.contains('is-collapsed');
    });
    master.dataset.abierto = todosOcultos ? '0' : '1';
    master.textContent = todosOcultos ? 'Mostrar movimientos' : 'Ocultar movimientos';
}

function toggleSeccion(header) {
    const block = header.closest('.section-block');
    if (!block) return;
    aplicarEstadoSeccion(block, !block.classList.contains('is-collapsed'));
    const card = block.closest('.bank-card');
    if (card) sincronizarBotonBanco(card);
}

function toggleListasBanco(btn) {
    const card = btn.closest('.bank-card');
    if (!card) return;
    const ocultar = btn.dataset.abierto !== '0';
    card.querySelectorAll('.section-block').forEach(function (block) {
        aplicarEstadoSeccion(block, ocultar);
    });
    sincronizarBotonBanco(card);
}

document.querySelectorAll('.bank-card').forEach(function (card) {
    card.querySelectorAll('.section-block[data-sec]').forEach(function (block) {
        if (localStorage.getItem('conc-oculto-' + block.dataset.sec) === '1') {
            aplicarEstadoSeccion(block, true);
        }
    });
    sincronizarBotonBanco(card);
});

function calcularDiferencia(idx) {
    const inputInicial = document.querySelector(`.saldo-inicial-input[data-idx="${idx}"]`);
    const inputBanco = document.querySelector(`.saldo-banco-input[data-idx="${idx}"]`);
    const saldoSistemaSpan = document.getElementById(`saldo_sis_${idx}`);
    const difSpan = document.getElementById(`dif_${idx}`);
    const statusDiv = document.getElementById(`status_${idx}`);
    
    if (!inputInicial || !inputBanco || !saldoSistemaSpan || !difSpan) return;

    const saldoInicial = parseFloat(inputInicial.value) || 0;
    const saldoBanco = parseFloat(inputBanco.value) || 0;
    const movimientoNeto = parseFloat(inputInicial.getAttribute('data-movimiento-neto')) || 0;
    const saldoSistema = saldoInicial + movimientoNeto;
    const diferencia = saldoBanco - saldoSistema;

    const formater = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    saldoSistemaSpan.textContent = 'Bs. ' + formater.format(saldoSistema);
    difSpan.textContent = 'Bs. ' + formater.format(diferencia);

    // Colores
    const datosCompletos = inputInicial.value !== '' && inputBanco.value !== '';
    if (Math.abs(diferencia) < 0.01 && datosCompletos) {
        difSpan.style.color = '#6ee7b7'; // Verde si cuadra exacto
        statusDiv.textContent = '¡CUADRA!';
        statusDiv.style.background = '#065f46';
        statusDiv.style.color = '#a7f3d0';
        statusDiv.style.display = 'inline-block';
    } else if (datosCompletos) {
        difSpan.style.color = '#fca5a5'; // Rojo si hay diferencia
        statusDiv.textContent = 'DIFERENCIA';
        statusDiv.style.background = '#7f1d1d';
        statusDiv.style.color = '#fecaca';
        statusDiv.style.display = 'inline-block';
    } else {
        difSpan.style.color = 'white';
        statusDiv.style.display = 'none';
    }
}
</script>

@endsection
