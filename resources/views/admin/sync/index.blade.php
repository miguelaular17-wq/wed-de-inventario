@extends('layouts.app')

@section('title', 'Control de Sincronizadores')

@push('head')
<style>
/* ─── Tokens ──────────────────────────────────────────────────────────── */
:root {
  --sync-bg:       #0f172a;
  --sync-panel:    #1e293b;
  --sync-border:   #334155;
  --sync-accent:   #3b82f6;
  --sync-accent2:  #6366f1;
  --sync-success:  #22c55e;
  --sync-danger:   #ef4444;
  --sync-warning:  #f59e0b;
  --sync-text:     #f1f5f9;
  --sync-dim:      #94a3b8;
}

/* ─── Layout ──────────────────────────────────────────────────────────── */
.sync-wrap          { background: var(--sync-bg); border-radius: 14px; padding: 28px; }
.sync-header        { display: flex; align-items: center; gap: 14px; margin-bottom: 28px; }
.sync-header h1     { margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--sync-text); }
.sync-header p      { margin: 4px 0 0; font-size: .85rem; color: var(--sync-dim); }

/* ─── Heartbeat cards ─────────────────────────────────────────────────── */
.hb-grid            { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; margin-bottom: 32px; }
.hb-card            { background: var(--sync-panel); border-radius: 12px; border: 1px solid var(--sync-border);
                       padding: 18px; transition: border-color .2s; position: relative; overflow: hidden; }
.hb-card:hover      { border-color: var(--sync-accent); }
.hb-card.online::before  { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--sync-success); }
.hb-card.offline::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--sync-danger); }
.hb-top             { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.hb-sede            { font-weight: 700; font-size: 1.1rem; color: var(--sync-text); }
.hb-badge           { font-size: .7rem; font-weight: 700; padding: 3px 10px; border-radius: 99px; text-transform: uppercase; letter-spacing: .5px; }
.hb-badge.online    { background: rgba(34,197,94,.15); color: var(--sync-success); border: 1px solid rgba(34,197,94,.3); }
.hb-badge.offline   { background: rgba(239,68,68,.15); color: var(--sync-danger);  border: 1px solid rgba(239,68,68,.3); }
.hb-meta            { font-size: .78rem; color: var(--sync-dim); line-height: 1.7; }
.hb-meta strong     { color: var(--sync-text); }
.hb-modules         { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 10px; }
.hb-mod             { font-size: .68rem; padding: 2px 8px; border-radius: 6px; font-weight: 600; }
.hb-mod.on          { background: rgba(59,130,246,.2); color: #93c5fd; }
.hb-mod.off         { background: rgba(148,163,184,.1); color: var(--sync-dim); text-decoration: line-through; }

/* ─── Command form ────────────────────────────────────────────────────── */
.cmd-form-box       { background: var(--sync-panel); border: 1px solid var(--sync-border); border-radius: 12px; padding: 22px; margin-bottom: 28px; }
.cmd-form-box h2    { margin: 0 0 16px; font-size: 1rem; font-weight: 700; color: var(--sync-text); display: flex; align-items: center; gap: 8px; }
.cmd-row            { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
.cmd-field          { display: flex; flex-direction: column; gap: 5px; }
.cmd-field label    { font-size: .75rem; font-weight: 600; color: var(--sync-dim); text-transform: uppercase; letter-spacing: .4px; }
.cmd-select         { background: #0f172a; color: var(--sync-text); border: 1px solid var(--sync-border);
                       border-radius: 8px; padding: 8px 14px; font-size: .9rem; outline: none;
                       transition: border-color .2s; min-width: 160px; }
.cmd-select:focus   { border-color: var(--sync-accent); }
.cmd-btn            { background: var(--sync-accent); color: #fff; border: none; border-radius: 8px;
                       padding: 9px 20px; font-weight: 700; font-size: .9rem; cursor: pointer;
                       transition: background .15s; }
.cmd-btn:hover      { background: #2563eb; }
.cmd-btn:disabled   { opacity: .5; cursor: not-allowed; }

/* ─── History table ───────────────────────────────────────────────────── */
.hist-box           { background: var(--sync-panel); border: 1px solid var(--sync-border); border-radius: 12px; overflow: hidden; }
.hist-box h2        { margin: 0; font-size: 1rem; font-weight: 700; color: var(--sync-text);
                       padding: 18px 22px; border-bottom: 1px solid var(--sync-border);
                       display: flex; align-items: center; justify-content: space-between; }
.hist-table         { width: 100%; border-collapse: collapse; font-size: .82rem; }
.hist-table th      { background: #0f172a; color: var(--sync-dim); font-weight: 600; text-transform: uppercase;
                       letter-spacing: .5px; padding: 10px 16px; text-align: left; font-size: .7rem; }
.hist-table td      { padding: 10px 16px; border-bottom: 1px solid rgba(51,65,85,.5); color: var(--sync-text); vertical-align: middle; }
.hist-table tr:last-child td { border-bottom: none; }
.hist-table tr:hover td { background: rgba(255,255,255,.02); }
.estado-pill        { display: inline-flex; align-items: center; gap: 4px; font-size: .72rem; font-weight: 700;
                       padding: 3px 10px; border-radius: 99px; }

/* ─── Alert flash ─────────────────────────────────────────────────────── */
.sync-alert         { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.3); color: #86efac;
                       border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; font-size: .88rem; }

/* ─── Refresh indicator ───────────────────────────────────────────────── */
.refresh-dot        { width: 8px; height: 8px; border-radius: 50%; background: var(--sync-success);
                       animation: pulse 1.5s infinite; display: inline-block; margin-right: 6px; }
@keyframes pulse    { 0%,100% { opacity: 1; } 50% { opacity: .3; } }

/* ─── Section title ───────────────────────────────────────────────────── */
.sync-section-title { font-size: .7rem; font-weight: 700; color: var(--sync-dim); text-transform: uppercase;
                       letter-spacing: 1px; margin: 0 0 10px; }

/* ─── Códigos compuestos audit ────────────────────────────────────────── */
.cc-stats           { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.cc-stat            { background: #0f172a; border: 1px solid var(--sync-border); border-radius: 8px;
                       padding: 10px 14px; min-width: 120px; }
.cc-stat .n         { font-size: 1.25rem; font-weight: 700; color: var(--sync-text); }
.cc-stat .l         { font-size: .7rem; color: var(--sync-dim); text-transform: uppercase; letter-spacing: .4px; }
.cc-stat.warn .n    { color: var(--sync-warning); }
.cc-stat.danger .n  { color: var(--sync-danger); }
.cc-filters         { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin-bottom: 14px; }
.cc-table-wrap      { max-height: 420px; overflow: auto; border: 1px solid var(--sync-border); border-radius: 8px; }
.cc-badge           { font-size: .68rem; font-weight: 700; padding: 2px 8px; border-radius: 6px; }
.cc-badge.suelto    { background: rgba(239,68,68,.15); color: #fca5a5; }
.cc-badge.compartido{ background: rgba(245,158,11,.15); color: #fcd34d; }
.cc-tramo           { font-family: ui-monospace, monospace; font-weight: 700; color: #93c5fd; }
.cc-delta-up        { color: var(--sync-danger); font-weight: 700; }
.cc-delta-dn        { color: var(--sync-success); }
.cc-empty           { color: var(--sync-dim); font-size: .85rem; padding: 18px; text-align: center; }
.cc-loading         { color: var(--sync-dim); font-size: .85rem; padding: 12px 0; }
</style>
@endpush

@section('content')
<div class="sync-wrap">

    {{-- Header --}}
    <div class="sync-header">
        <span style="font-size:2rem;">⚡</span>
        <div>
            <h1>Control Remoto de Sincronizadores</h1>
            <p>
                <span class="refresh-dot"></span>
                Actualizando cada 30 s &nbsp;|&nbsp;
                {{ now()->format('d/m/Y H:i:s') }}
            </p>
        </div>
    </div>

    {{-- Flash success --}}
    @if(session('success'))
        <div class="sync-alert">✅ {{ session('success') }}</div>
    @endif

    {{-- ── Tarjetas de sincronizadores ─────────────────────────────── --}}
    <p class="sync-section-title">Sincronizadores conectados</p>
    <div class="hb-grid" id="hb-grid">
        @forelse($heartbeats as $hb)
            @php
                $online    = $hb->es_activo;
                $statusCls = $online ? 'online' : 'offline';
                $meta      = $hb->metadata ?? [];
            @endphp
            <div class="hb-card {{ $statusCls }}" data-sede="{{ $hb->sede }}">
                <div class="hb-top">
                    <span class="hb-sede">{{ $hb->sede }}</span>
                    <span class="hb-badge {{ $statusCls }}">{{ $online ? '● ACTIVO' : '○ offline' }}</span>
                </div>
                <div class="hb-meta">
                    <strong>Último pulso:</strong> {{ $hb->tiempo }}<br>
                    <strong>IP:</strong> {{ $hb->ip_address ?? '—' }}<br>
                    <strong>Versión:</strong> {{ $hb->version ?? '—' }}
                </div>
                <div class="hb-modules">
                    @foreach(['sync_stock' => '📦 Stock', 'sync_precios' => '💲 Precios', 'sync_cobranzas' => '📋 Cobranzas', 'sync_compras' => '🛒 Compras'] as $key => $lbl)
                        <span class="hb-mod {{ ($meta[$key] ?? true) ? 'on' : 'off' }}">{{ $lbl }}</span>
                    @endforeach
                </div>
            </div>
        @empty
            <div style="color:var(--sync-dim); font-size:.9rem; padding:16px 0; grid-column:1/-1;">
                Sin sincronizadores registrados aún. En cuanto uno inicie, aparecerá aquí automáticamente.
            </div>
        @endforelse
    </div>

    {{-- ── Publicar versión del escritorio ─────────────────────────── --}}
    @php
        $manifestPath = storage_path('app/'.trim(config('sync_desktop.storage_dir'), '/').'/'.config('sync_desktop.manifest_name'));
        $manifest = is_file($manifestPath) ? json_decode(@file_get_contents($manifestPath), true) : null;
    @endphp
    <div class="cmd-form-box" style="margin-bottom:20px;">
        <h2>Publicar nueva versión del sincronizador</h2>
        <p style="margin:0 0 14px;color:var(--sync-dim);font-size:.85rem;">
            Sube el <code>WinSyncService.exe</code> (o un ZIP con el exe). Las sedes lo reciben con el botón «Actualizar» de la app.
            @if($manifest)
                · Actual: <strong style="color:var(--sync-text);">v{{ $manifest['version'] ?? '—' }}</strong>
                @if(!empty($manifest['published_at']))
                    ({{ \Carbon\Carbon::parse($manifest['published_at'])->format('d/m/Y H:i') }})
                @endif
            @else
                · Aún no hay paquete publicado.
            @endif
        </p>
        <form method="POST" action="{{ route('admin.sync.publish') }}" enctype="multipart/form-data">
            @csrf
            <div class="cmd-row">
                <div class="cmd-field">
                    <label>Versión</label>
                    <input type="text" name="version" class="cmd-select" placeholder="2.2.0" required
                           pattern="\d+\.\d+\.\d+" value="{{ old('version', $manifest['version'] ?? '2.2.0') }}"
                           style="min-width:120px;">
                </div>
                <div class="cmd-field" style="flex:1;min-width:220px;">
                    <label>Notas</label>
                    <input type="text" name="notes" class="cmd-select" style="width:100%;"
                           placeholder="Qué cambia en esta versión" value="{{ old('notes') }}">
                </div>
                <div class="cmd-field">
                    <label>Paquete (.exe o .zip)</label>
                    <input type="file" name="paquete" accept=".exe,.zip" required
                           style="color:var(--sync-text);font-size:.82rem;max-width:240px;">
                </div>
                <button type="submit" class="cmd-btn">Publicar</button>
            </div>
            @error('paquete')
                <p style="color:var(--sync-danger);font-size:.82rem;margin-top:8px;">{{ $message }}</p>
            @enderror
            @error('version')
                <p style="color:var(--sync-danger);font-size:.82rem;margin-top:8px;">{{ $message }}</p>
            @enderror
        </form>
    </div>

    {{-- ── Formulario de comando ───────────────────────────────────── --}}
    <div class="cmd-form-box">
        <h2>🎮 Enviar Comando Remoto</h2>
        <form method="POST" action="{{ route('admin.sync.command') }}" id="cmd-form">
            @csrf
            <div class="cmd-row">
                <div class="cmd-field">
                    <label>Sede destino</label>
                    <select name="sede" class="cmd-select" required id="cmd-sede">
                        <option value="">— Seleccionar —</option>
                        @if($heartbeats->isNotEmpty())
                            @foreach($heartbeats as $hb)
                                <option value="{{ $hb->sede }}">
                                    {{ $hb->sede }} {{ $hb->es_activo ? '🟢' : '🔴' }}
                                </option>
                            @endforeach
                        @else
                            {{-- Fallback: sedes del historial de cobranzas --}}
                            @foreach($sedesFallback as $sede)
                                <option value="{{ $sede }}">{{ $sede }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div class="cmd-field">
                    <label>Módulo a ejecutar</label>
                    <select name="comando" class="cmd-select" required>
                        <option value="">— Seleccionar —</option>
                        @foreach($opciones as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="cmd-btn" id="cmd-submit">
                    Enviar ▶
                </button>
            </div>
            @if($errors->any())
                <p style="color:var(--sync-danger); font-size:.82rem; margin-top:8px;">
                    {{ $errors->first() }}
                </p>
            @endif
        </form>
    </div>

    {{-- ── Códigos compuestos (lo ejecuta el sincronizador local) ──── --}}
    <div class="cmd-form-box" id="cc-box">
        <h2>🔎 Verificar códigos compuestos</h2>
        <p style="margin:0 0 14px;color:var(--sync-dim);font-size:.85rem;">
            Lo ejecuta el <strong style="color:var(--sync-text);">sincronizador de la sede</strong>:
            compara cada tramo de códigos tipo <code>55 / PROWAX100 / 000055</code> en
            <em>Suite (SQL Server)</em> contra el producto compuesto en la web.
            Marca cuando un tramo (ej. <code>000055</code>) tiene <em>precio mayor más alto</em> que el resto.
        </p>
        <div class="cc-filters">
            <div class="cmd-field">
                <label>Sede (sincronizador)</label>
                <select class="cmd-select" id="cc-sede">
                    <option value="">— Seleccionar —</option>
                    @if($heartbeats->isNotEmpty())
                        @foreach($heartbeats as $hb)
                            <option value="{{ $hb->sede }}">{{ $hb->sede }} {{ $hb->es_activo ? '🟢' : '🔴' }}</option>
                        @endforeach
                    @else
                        @foreach($sedesFallback as $sede)
                            <option value="{{ $sede }}">{{ $sede }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <form method="POST" action="{{ route('admin.sync.command') }}" id="cc-form-verify" style="display:contents;">
                @csrf
                <input type="hidden" name="comando" value="verificar_codigos_compuestos">
                <input type="hidden" name="sede" id="cc-sede-verify" value="">
                <button type="submit" class="cmd-btn" id="cc-send">Verificar</button>
            </form>
            <form method="POST" action="{{ route('admin.sync.command') }}" id="cc-form-separar" style="display:contents;"
                  onsubmit="return confirm('¿Separar en la web todos los compuestos con precios distintos en Suite?');">
                @csrf
                <input type="hidden" name="comando" value="separar_codigos_compuestos">
                <input type="hidden" name="sede" id="cc-sede-separar" value="">
                <button type="submit" class="cmd-btn" id="cc-separar" style="background:var(--sync-danger);">Separar en la web</button>
            </form>
            <button type="button" class="cmd-btn" id="cc-refresh" style="background:#475569;">Ver último resultado</button>
        </div>
        <div class="cc-stats" id="cc-stats" hidden>
            <div class="cc-stat"><div class="n" id="cc-n-comp">—</div><div class="l">Compuestos web</div></div>
            <div class="cc-stat"><div class="n" id="cc-n-cmp">—</div><div class="l">Comparables</div></div>
            <div class="cc-stat warn"><div class="n" id="cc-n-prob">—</div><div class="l">Inconsistencias</div></div>
            <div class="cc-stat danger"><div class="n" id="cc-n-alto">—</div><div class="l">Mayor distinto</div></div>
        </div>
        <div id="cc-status" class="cc-loading" hidden></div>
        <div class="cc-table-wrap" id="cc-table-wrap" hidden></div>
    </div>

    {{-- ── Historial de comandos ────────────────────────────────────── --}}
    <div class="hist-box">
        <h2>
            <span>📋 Historial de Comandos</span>
            <span style="font-size:.75rem; color:var(--sync-dim); font-weight:400;">Últimos 30</span>
        </h2>
        <div id="hist-table-wrap">
            @include('admin.sync._tabla_comandos', ['comandos' => $comandos])
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// ── Auto-refresh via Ajax cada 30 s ────────────────────────────────────
const STATUS_URL = "{{ route('admin.sync.status') }}";
const CC_URL     = "{{ route('admin.sync.codigos_compuestos') }}";
const CSRF       = document.querySelector('meta[name="csrf-token"]').content;

function refreshStatus() {
    fetch(STATUS_URL, { headers: { 'X-CSRF-TOKEN': CSRF } })
        .then(r => r.json())
        .then(data => {
            updateHeartbeats(data.heartbeats);
            updateHistorial(data.comandos);
        })
        .catch(() => {});
}

function updateHeartbeats(heartbeats) {
    heartbeats.forEach(h => {
        const card = document.querySelector(`.hb-card[data-sede="${h.sede}"]`);
        if (!card) return;
        card.className = `hb-card ${h.es_activo ? 'online' : 'offline'}`;
        const badge = card.querySelector('.hb-badge');
        if (badge) {
            badge.className = `hb-badge ${h.es_activo ? 'online' : 'offline'}`;
            badge.textContent = h.es_activo ? '● ACTIVO' : '○ offline';
        }
        const meta = card.querySelector('.hb-meta');
        if (meta && h.tiempo) {
            meta.innerHTML = `<strong>Último pulso:</strong> ${h.tiempo}<br>
                              <strong>IP:</strong> ${h.ip || '—'}<br>
                              <strong>Versión:</strong> ${h.version || '—'}`;
        }
    });
}

function updateHistorial(comandos) {
    const wrap = document.getElementById('hist-table-wrap');
    if (!wrap || !comandos.length) return;

    const rows = comandos.map(c => `
        <tr>
            <td><strong style="color:var(--sync-accent)">${c.sede}</strong></td>
            <td>${c.label}</td>
            <td>
                <span class="estado-pill"
                      style="background:${c.color}22; color:${c.color}; border:1px solid ${c.color}44">
                    ${c.icon} ${c.estado}
                </span>
            </td>
            <td style="color:var(--sync-dim)">${c.solicitado_por || '—'}</td>
            <td style="color:var(--sync-dim)">${c.created_at}</td>
            <td style="color:var(--sync-dim)">${c.ejecutado_at || '—'}</td>
            <td style="color:${c.estado === 'error' ? 'var(--sync-danger)' : 'var(--sync-dim)'}; font-size:.75rem; max-width:200px; word-break:break-word">
                ${c.mensaje || ''}
            </td>
        </tr>`).join('');

    wrap.innerHTML = `
        <table class="hist-table">
            <thead>
                <tr>
                    <th>Sede</th><th>Módulo</th><th>Estado</th>
                    <th>Solicitado por</th><th>Enviado</th><th>Ejecutado</th><th>Resultado</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>`;
}

// Confirmar antes de enviar
document.getElementById('cmd-form').addEventListener('submit', function(e) {
    const sede    = document.getElementById('cmd-sede').value;
    const btn     = document.getElementById('cmd-submit');
    if (!sede) return;
    btn.disabled    = true;
    btn.textContent = 'Enviando...';
});

// Arrancar polling
setInterval(refreshStatus, 30000);

// ── Auditoría códigos compuestos (resultado del sincronizador) ─────────
function money(n) {
    const v = Number(n);
    return Number.isFinite(v) ? v.toFixed(2) : '—';
}

function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function loadCodigosCompuestosResultado() {
    const sede = document.getElementById('cc-sede').value.trim();
    const status = document.getElementById('cc-status');
    const stats = document.getElementById('cc-stats');
    const wrap = document.getElementById('cc-table-wrap');

    status.hidden = false;
    status.textContent = 'Consultando último resultado del sincronizador…';
    wrap.hidden = true;
    stats.hidden = true;

    const url = new URL(CC_URL, window.location.origin);
    if (sede) url.searchParams.set('sede', sede);

    fetch(url.toString(), { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            if (!data.estado) {
                status.textContent = data.mensaje || 'Sin resultados aún.';
                return;
            }

            const d = data.detalles;
            if (data.estado === 'pendiente' || data.estado === 'ejecutando') {
                status.textContent = `Sede ${data.sede}: ${data.estado}. El sincronizador lo toma en ≤ 60 s…`;
                return;
            }

            if (data.estado === 'error') {
                status.textContent = `Error en ${data.sede}: ${data.mensaje || 'fallo'}`;
                return;
            }

            if (!d) {
                status.textContent = `${data.sede}: ${data.mensaje || 'Completado sin detalle.'}`;
                return;
            }

            document.getElementById('cc-n-comp').textContent = d.total_compuestos_web ?? 0;
            document.getElementById('cc-n-cmp').textContent = d.compuestos_comparables ?? 0;
            document.getElementById('cc-n-prob').textContent = d.total_problemas ?? 0;
            document.getElementById('cc-n-alto').textContent = d.con_mayor_distinto ?? 0;
            stats.hidden = false;

            const items = d.items || [];
            const alias0 = d.ignorados_alias_cero != null ? ` · ${d.ignorados_alias_cero} alias a $0 ignorados` : '';
            status.textContent = `${data.sede} · ${data.mensaje || ''} · ${data.ejecutado_humano || ''}${alias0}`;

            if (!items.length) {
                wrap.hidden = false;
                wrap.innerHTML = `<div class="cc-empty">Nada que separar: no hay tramos con precios reales distintos en Suite.</div>`;
                return;
            }

            const rows = items.map(it => {
                const tramos = (it.tramos_suite || []).map(t => {
                    const alto = t.tramo === it.tramo_mayor_alto;
                    return `<div style="font-size:.75rem;margin:2px 0;">
                        <span class="cc-tramo">${escapeHtml(t.tramo)}</span>
                        u ${money(t.precio_unidad)} · m ${money(t.precio_mayor)}
                        ${alto ? '<span class="cc-delta-up"> ▲ mayor alto</span>' : ''}
                    </div>`;
                }).join('');
                return `<tr>
                    <td><span class="cc-badge suelto">SEPARAR</span></td>
                    <td>
                        <div style="font-family:ui-monospace,monospace;font-size:.78rem;">${escapeHtml(it.web_codigo)}</div>
                        <div style="color:var(--sync-dim);font-size:.72rem;">${escapeHtml(it.web_nombre || '')}</div>
                        <div style="font-size:.72rem;margin-top:2px;">web u ${money(it.web_precio_unidad)} · m ${money(it.web_precio_mayor)}</div>
                    </td>
                    <td>${tramos}</td>
                    <td class="cc-delta-up">${money(it.delta_mayor)}</td>
                </tr>`;
            }).join('');

            wrap.hidden = false;
            wrap.innerHTML = `
                <table class="hist-table">
                    <thead>
                        <tr>
                            <th>Acción</th>
                            <th>Compuesto (web)</th>
                            <th>Precios Suite (solo &gt; 0)</th>
                            <th>Δ mayor</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>`;
        })
        .catch(err => {
            status.hidden = false;
            status.textContent = 'Error: ' + (err.message || err);
        });
}

function syncCcSedeInputs() {
    const sede = document.getElementById('cc-sede').value;
    document.getElementById('cc-sede-verify').value = sede;
    document.getElementById('cc-sede-separar').value = sede;
}
document.getElementById('cc-sede').addEventListener('change', syncCcSedeInputs);
syncCcSedeInputs();

['cc-form-verify', 'cc-form-separar'].forEach(id => {
    document.getElementById(id).addEventListener('submit', function (e) {
        syncCcSedeInputs();
        if (!document.getElementById('cc-sede').value) {
            e.preventDefault();
            alert('Elige una sede');
            return;
        }
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Enviando…';
        }
    });
});
document.getElementById('cc-refresh').addEventListener('click', loadCodigosCompuestosResultado);

// Cargar último resultado al abrir
loadCodigosCompuestosResultado();
</script>
@endpush
