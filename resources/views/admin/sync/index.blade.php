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
</script>
@endpush
