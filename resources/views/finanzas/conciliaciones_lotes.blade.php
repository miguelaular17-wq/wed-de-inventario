@extends('layouts.app')
@section('title', 'Lotes de punto de venta')
@section('content')
<style>
    .conc-page { padding: 28px; font-family: 'Inter', sans-serif; background: #f1f5f9; min-height: 100vh; }
    .conc-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 14px; }
    .conc-title { margin: 0; font-size: 1.6rem; color: #0f172a; font-weight: 800; letter-spacing: -0.5px; }
    .conc-title span { color: #0f766e; }
    .conc-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .btn-upload { background: linear-gradient(135deg,#0f766e,#0d9488); color: white; padding: 10px 18px; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; text-decoration: none; }
    .btn-link { background: white; color: #1e3a8a; border: 1.5px solid #bfdbfe; padding: 10px 16px; border-radius: 9px; font-weight: 700; text-decoration: none; }
    .select-banco { padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-size: 0.95rem; color: #334155; background: white; min-width: 140px; }
    .btn-filtrar { background: #f8fafc; color: #475569; border: 1.5px solid #cbd5e1; padding: 10px 16px; border-radius: 9px; font-weight: 600; cursor: pointer; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 14px 18px; border-radius: 10px; margin-bottom: 22px; font-weight: 500; }
    .kpis { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 22px; }
    .kpi { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; min-width: 160px; }
    .kpi span { display: block; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: .4px; }
    .kpi strong { font-size: 1.35rem; color: #0f172a; }
    .bank-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,.06); margin-bottom: 28px; overflow: hidden; }
    .bank-card-header { background: linear-gradient(135deg, #134e4a 0%, #0f766e 100%); padding: 16px 22px; color: white; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 3; }
    .header-manual { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
    .header-manual .manual-resumen { font-size: 0.85rem; font-weight: 600; opacity: .95; }
    .header-manual .diff-ok { color: #bbf7d0; font-weight: 800; }
    .header-manual .diff-bad { color: #fde68a; font-weight: 800; }
    .header-manual .btn-conciliar { background: white; color: #0f766e; }
    .section-scroll { height: 460px; overflow-y: auto; }
    .section-scroll thead th { position: sticky; top: 0; z-index: 1; }
    .sections-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; }
    @media(max-width: 1100px) { .sections-grid { grid-template-columns: 1fr; } }
    .section-block { border-right: 1px solid #f1f5f9; }
    .section-header { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
    .section-title { font-size: 0.82rem; font-weight: 800; text-transform: uppercase; color: #1e293b; }
    .section-count { margin-left: auto; background: #f1f5f9; color: #64748b; border-radius: 20px; padding: 2px 8px; font-size: 0.75rem; font-weight: 700; }
    .mini-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
    .mini-table th { padding: 8px 12px; text-align: left; color: #64748b; font-size: 0.74rem; text-transform: uppercase; background: #f8fafc; }
    .mini-table td { padding: 9px 12px; border-top: 1px solid #f8fafc; vertical-align: top; }
    .empty-row td { text-align: center; color: #94a3b8; padding: 22px; }
    .section-footer { padding: 8px 12px; text-align: right; font-weight: 800; background: #f8fafc; }
    .row-pick { cursor: pointer; }
    .row-pick:hover { background: #f0fdfa; }
    .row-pick.is-selected { background: #ccfbf1; }
    .pick-cell { width: 28px; }
    .manual-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; padding: 12px 16px; background: #ecfdf5; border-top: 1px solid #a7f3d0; }
    .manual-bar .hint { color: #115e59; font-size: 0.88rem; font-weight: 600; }
    .manual-bar .diff-ok { color: #166534; font-weight: 800; }
    .manual-bar .diff-bad { color: #991b1b; font-weight: 800; }
    /* Grupos armados antes de conciliar: cada uno con su color. */
    .row-pick.en-grupo { pointer-events: none; }
    .row-pick.en-grupo td:first-child { box-shadow: inset 4px 0 0 var(--g); }
    .row-pick.en-grupo { background: var(--gbg) !important; }
    .g-0 { --g: #2563eb; --gbg: #dbeafe; } .g-1 { --g: #d97706; --gbg: #fef3c7; } .g-2 { --g: #7c3aed; --gbg: #ede9fe; }
    .g-3 { --g: #db2777; --gbg: #fce7f3; } .g-4 { --g: #059669; --gbg: #d1fae5; } .g-5 { --g: #dc2626; --gbg: #fee2e2; }
    .grupo-tag { display: inline-block; margin-left: 6px; padding: 0 6px; border-radius: 10px; font-size: .68rem; font-weight: 800; color: white; background: var(--g); }
    .manual-grupos { display: flex; flex-wrap: wrap; gap: 6px; width: 100%; }
    .grupo-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 4px 3px 10px; border-radius: 20px; font-size: .76rem; font-weight: 700; background: var(--gbg); color: #0f172a; border: 2px solid var(--g); }
    .grupo-chip button { border: 0; background: rgba(0,0,0,.08); border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-weight: 800; line-height: 1; }
    .btn-agregar { background: transparent; color: white; border: 1.5px solid rgba(255,255,255,.7); padding: 9px 12px; border-radius: 9px; font-weight: 800; cursor: pointer; }
    .btn-agregar:disabled { opacity: .45; cursor: not-allowed; }
    .btn-ampliar { background: rgba(255,255,255,.15); color: white; border: 1px solid rgba(255,255,255,.35); padding: 4px 10px; border-radius: 7px; font-size: .78rem; font-weight: 700; cursor: pointer; margin-top: 6px; }
    /* Banco ampliado a pantalla completa para conciliar más cómodo. */
    .bank-card.is-expanded { position: fixed; inset: 12px; z-index: 1040; margin: 0; overflow: auto; box-shadow: 0 0 0 100vmax rgba(15,23,42,.55); }
    .bank-card.is-expanded .section-scroll { height: calc(100vh - 230px); }
    .bank-card.is-expanded .mini-table { font-size: .92rem; }
    body.hay-ampliado { overflow: hidden; }
    .btn-registrar-lote { display: block; margin-top: 3px; font-size: .68rem; padding: 1px 6px; border: 1px solid #a7f3d0; border-radius: 4px; background: #ecfdf5; color: #047857; cursor: pointer; font-weight: 700; }
    .row-pick.en-grupo .btn-registrar-lote { display: none; }
    .btn-conciliar { background: #0f766e; color: white; border: none; padding: 10px 16px; border-radius: 9px; font-weight: 800; cursor: pointer; }
    .btn-conciliar:disabled { opacity: .45; cursor: not-allowed; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px; }
    .modal-box { background: white; border-radius: 14px; width: 100%; max-width: 520px; }
    .modal-head, .modal-foot { padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .modal-foot { border-bottom: none; border-top: 1px solid #e2e8f0; justify-content: flex-end; gap: 10px; }
    .modal-body { padding: 22px; }
    .form-label { display: block; font-weight: 700; margin-bottom: 6px; }
    .form-control { width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 9px; margin-bottom: 14px; box-sizing: border-box; }
    .file-wrap { display: flex; }
    .file-label { background: #f1f5f9; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px 0 0 9px; cursor: pointer; }
    .file-name { flex: 1; border: 1.5px solid #cbd5e1; border-left: none; border-radius: 0 9px 9px 0; padding: 10px; }
    .btn-cancel { background: white; border: 1.5px solid #cbd5e1; padding: 10px 16px; border-radius: 9px; font-weight: 700; cursor: pointer; }
    .btn-submit { background: #0f766e; color: white; border: none; padding: 10px 16px; border-radius: 9px; font-weight: 800; cursor: pointer; }
</style>
<div class="conc-page">
    <div class="conc-topbar">
        <div>
            <h2 class="conc-title">Lotes de <span>punto de venta</span></h2>
            <p style="margin:4px 0 0;color:#64748b;">Cruza el Excel de medios de pago con las liquidaciones LIQ del extracto. También puedes marcar un lote con varias LIQ del banco a mano.</p>
        </div>
        <div class="conc-toolbar">
            <a class="btn-link" href="{{ route('finanzas.conciliaciones') }}">Egresos</a>
            <button type="button" class="btn-upload" onclick="document.getElementById('mediosPagoModal').style.display='flex'">Medios de pago BDV</button>
            <button type="button" class="btn-upload" style="background:#1d4ed8;" onclick="document.getElementById('mercantilLotesModal').style.display='flex'">Balance Mercantil (Excel)</button>
            <form action="{{ route('finanzas.conciliaciones.lotes') }}" method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin:0;">
                <input type="date" name="fecha_desde" class="select-banco" value="{{ $filtros['fecha_desde'] ?? '' }}">
                <input type="date" name="fecha_hasta" class="select-banco" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                <select name="banco_filtro" class="select-banco">
                    <option value="">Todos los bancos</option>
                    @foreach($bancos as $banco)
                        <option value="{{ $banco }}" @selected(($filtros['banco_filtro'] ?? '') === $banco)>{{ $banco }}</option>
                    @endforeach
                </select>
                <button class="btn-filtrar" type="submit">Filtrar</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-success" style="background:#fef2f2;color:#b91c1c;border-color:#fecaca;">{{ session('error') }}</div>
    @endif
    @if(($mercantilAuto ?? 0) > 0)
        <div class="alert-success">Se conciliaron automáticamente {{ $mercantilAuto }} cierre(s) de Mercantil: los lotes del día suman exacto el abono «CANCELACION RESUMEN DE VENTAS».</div>
    @endif
    @if(($lotesAutoConciliados ?? 0) > 0)
        <div class="alert-success">Se conciliaron automáticamente {{ $lotesAutoConciliados }} lote(s) Banesco por el número de lote en la descripción del banco.</div>
    @endif
    @if(session('error'))
        <div class="alert-success" style="background:#fef2f2;color:#991b1b;border-color:#fca5a5;">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-success" style="background:#fef2f2;color:#991b1b;border-color:#fca5a5;">{{ $errors->first() }}</div>
    @endif

    <div class="kpis">
        <div class="kpi"><span>Lotes</span><strong>{{ number_format($totalLotes) }}</strong></div>
        <div class="kpi"><span>Conciliados</span><strong>{{ number_format($totalConciliados) }}</strong></div>
        <div class="kpi"><span>Sin liquidar</span><strong>{{ number_format($totalPendientes) }}</strong></div>
        <div class="kpi"><span>LIQ sin lote</span><strong>{{ number_format($totalLiq) }}</strong></div>
    </div>

    @forelse($tarjetas as $idx => $tarjeta)
        <div class="bank-card" data-card="{{ $idx }}">
            <form action="{{ route('finanzas.conciliaciones.lotes.manual') }}" method="POST" class="manual-lote-form" data-card="{{ $idx }}">
                @csrf
                <input type="hidden" name="aceptar_diferencia" value="0">
                <input type="hidden" name="grupos" value="">
                <div class="bank-card-header">
                    <div>
                        <strong>{{ $tarjeta['banco'] }}</strong>
                        @if($tarjeta['titular'])
                            <div style="opacity:.8;font-size:.85rem;">Titular: {{ $tarjeta['titular'] }}</div>
                        @endif
                        <button type="button" class="btn-ampliar" onclick="ampliarBanco(this)" title="Ver este banco a pantalla completa (Esc para cerrar)">⛶ Ampliar</button>
                    </div>
                    @if($tarjeta['pendientes']->count() > 0 && $tarjeta['liq_pendientes']->count() > 0)
                        <div class="header-manual">
                            <span class="manual-resumen">Marca 1 lote y una o varias LIQ</span>
                            <span class="manual-diff"></span>
                            <button type="button" class="btn-agregar" disabled onclick="agregarGrupo(this.closest('form'))"
                                    title="Guarda esta selección como un grupo (con su color) y sigue marcando otro lote">+ Agregar</button>
                            <button type="submit" class="btn-conciliar" disabled>Conciliar manualmente</button>
                            <div class="manual-grupos"></div>
                        </div>
                    @endif
                </div>
                <div class="sections-grid">
                    <div class="section-block">
                        <div class="section-header"><span class="section-title">Lotes conciliados</span><span class="section-count">{{ $tarjeta['conciliados']->count() }}</span></div>
                        <div class="section-scroll">
                        <table class="mini-table">
                            <thead><tr><th>Fecha</th><th>Lote</th><th>Neto</th><th style="text-align:right;">Comisión</th></tr></thead>
                            <tbody>
                                @forelse($tarjeta['conciliados'] as $lote)
                                    @php $com = $tarjeta['comisiones'][$lote->id] ?? null; @endphp
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($lote->fecha)->format('d/m/Y') }}</td>
                                        <td>{{ $lote->lote_referencia ?: '—' }}</td>
                                        <td>Bs. {{ number_format((float) $lote->monto, 2) }}
                                            @if($com && $com['abonado'] !== null && $com['nota'] === null)
                                                <div style="font-size:.7rem; color:#64748b;">Banco: Bs. {{ number_format($com['abonado'], 2) }}</div>
                                            @endif
                                        </td>
                                        <td style="text-align:right; white-space:nowrap;">
                                            @if(! $com || $com['nota'])
                                                <span style="font-size:.72rem; color:#94a3b8;">{{ $com['nota'] ?? '—' }}</span>
                                            @elseif(abs($com['comision']) < 0.01)
                                                <span style="color:#94a3b8;">—</span>
                                            @else
                                                <span style="font-weight:700; color:{{ $com['comision'] > 0 ? '#b45309' : '#1d4ed8' }};"
                                                      title="{{ $com['comision'] > 0 ? 'Lo que descontó el banco' : 'El banco abonó de más' }}">
                                                    {{ $com['comision'] > 0 ? '' : '+' }}Bs. {{ number_format(abs($com['comision']), 2) }}
                                                </span>
                                                <div style="font-size:.7rem; color:#64748b;">{{ number_format(abs($com['pct']), 2, ',', '.') }} %{{ $com['comision'] < 0 ? ' de más' : '' }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="empty-row"><td colspan="4">Sin lotes conciliados</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>
                        @if($tarjeta['conciliados']->count() > 0)
                            <div class="section-footer">
                                Bs. {{ number_format($tarjeta['total_conciliado'], 2) }}
                                @if(abs($tarjeta['total_comision']) >= 0.01)
                                    <div style="font-size:.74rem; font-weight:700; color:#b45309;">
                                        Comisiones: Bs. {{ number_format($tarjeta['total_comision'], 2) }}
                                        @if($tarjeta['total_conciliado'] > 0)({{ number_format($tarjeta['total_comision'] / $tarjeta['total_conciliado'] * 100, 2, ',', '.') }} %)@endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="section-block">
                        <div class="section-header"><span class="section-title">Lotes sin liquidación</span><span class="section-count">{{ $tarjeta['pendientes']->count() }}</span></div>
                        <div class="section-scroll">
                        <table class="mini-table">
                            <thead><tr><th class="pick-cell"></th><th>Fecha</th><th>Lote</th><th>Neto</th></tr></thead>
                            <tbody>
                                @forelse($tarjeta['pendientes'] as $lote)
                                    @php
                                        $original = isset($copiasLotes[$lote->id]) ? ($lotesPorId[$copiasLotes[$lote->id]] ?? null) : null;
                                    @endphp
                                    @if($original)
                                        <tr style="opacity:.6; background:#fff7ed;" title="Copia del lote #{{ $original->id }}">
                                            <td class="pick-cell"></td>
                                            <td>{{ \Carbon\Carbon::parse($lote->fecha)->format('d/m/Y') }}</td>
                                            <td>{{ $lote->lote_referencia ?: '—' }}</td>
                                            <td>Bs. {{ number_format((float) $lote->monto, 2) }}
                                                <div style="font-size:.7rem; color:#c2410c; font-weight:700;">Repetido · {{ $original->es_conciliado ? 'el otro ya está conciliado' : 'copia del #'.$original->id }}</div>
                                            </td>
                                        </tr>
                                        @continue
                                    @endif
                                    <tr class="row-pick" data-role="lote" onclick="this.querySelector('input[type=radio]').checked = true; actualizarManualBar(this.closest('form'));">
                                        <td class="pick-cell">
                                            <input type="radio"
                                                   name="tesoreria_ingreso_id"
                                                   value="{{ $lote->id }}"
                                                   data-monto="{{ round(abs((float) $lote->monto), 2) }}"
                                                   data-lote="{{ $lote->lote_referencia ?: ('#'.$lote->id) }}"
                                                   onclick="event.stopPropagation(); actualizarManualBar(this.closest('form'));">
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($lote->fecha)->format('d/m/Y') }}</td>
                                        <td>{{ $lote->lote_referencia ?: '—' }}</td>
                                        <td>Bs. {{ number_format((float) $lote->monto, 2) }}
                                            @if(($conDiferidos ?? false) && $lote->conciliar_en && ($filtroDesde ?? null) && substr((string) $lote->fecha, 0, 10) < $filtroDesde)
                                                <div style="font-size:.7rem; color:#1d4ed8; font-weight:700;">Del mes anterior
                                                    <button type="submit" formaction="{{ route('finanzas.conciliaciones.lotes.diferir') }}" name="volver_id" value="{{ $lote->id }}" formnovalidate
                                                            onclick="event.stopPropagation();" style="font-size:.68rem; border:0; background:none; color:#64748b; text-decoration:underline; cursor:pointer;">devolver</button>
                                                </div>
                                            @elseif($conDiferidos ?? false)
                                                <button type="submit" formaction="{{ route('finanzas.conciliaciones.lotes.diferir') }}" name="diferir_id" value="{{ $lote->id }}" formnovalidate
                                                        onclick="event.stopPropagation(); return confirm('¿Pasar el lote {{ $lote->lote_referencia }} al mes siguiente? Se concilia ahí con la LIQ del banco del día 1.');"
                                                        title="El banco lo abona el mes siguiente (lote del cierre de mes)"
                                                        style="display:block; margin-top:2px; font-size:.68rem; padding:1px 6px; border:1px solid #bfdbfe; border-radius:4px; background:#eff6ff; color:#1d4ed8; cursor:pointer;">→ Mes sig.</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="empty-row"><td colspan="4">Todos los lotes tienen LIQ</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @if(($tarjeta['diferidos'] ?? collect())->isNotEmpty())
                            <div style="padding:8px 12px; background:#eff6ff; border-top:1px solid #bfdbfe; font-size:.78rem;">
                                <div style="font-weight:800; color:#1d4ed8; margin-bottom:4px;">Pasan al mes siguiente ({{ $tarjeta['diferidos']->count() }})</div>
                                @foreach($tarjeta['diferidos'] as $lote)
                                    <div style="display:flex; justify-content:space-between; gap:6px; padding:2px 0;">
                                        <span>{{ \Carbon\Carbon::parse($lote->fecha)->format('d/m/Y') }} · lote {{ $lote->lote_referencia ?: '—' }} · Bs. {{ number_format((float) $lote->monto, 2) }}
                                            <span style="color:#64748b;">→ {{ \Carbon\Carbon::parse($lote->conciliar_en)->locale('es')->translatedFormat('F Y') }}{{ $lote->es_conciliado ? ' · conciliado' : '' }}</span></span>
                                        @unless($lote->es_conciliado)
                                            <button type="submit" formaction="{{ route('finanzas.conciliaciones.lotes.diferir') }}" name="volver_id" value="{{ $lote->id }}" formnovalidate
                                                    style="font-size:.7rem; border:0; background:none; color:#64748b; text-decoration:underline; cursor:pointer;">devolver</button>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        </div>
                        @if($tarjeta['pendientes']->count() > 0)
                            <div class="section-footer">Bs. {{ number_format($tarjeta['total_pendiente'], 2) }}</div>
                        @endif
                    </div>
                    <div class="section-block">
                        <div class="section-header"><span class="section-title">LIQ del banco sin lote</span><span class="section-count">{{ $tarjeta['liq_pendientes']->count() }}</span></div>
                        <div class="section-scroll">
                        <table class="mini-table">
                            <thead><tr><th class="pick-cell"></th><th>Fecha</th><th>Descripción</th><th>Monto</th></tr></thead>
                            <tbody>
                                @forelse($tarjeta['liq_pendientes'] as $linea)
                                    @php $parcial = ($parcialesLiq ?? collect())->get($linea->id); @endphp
                                    <tr class="row-pick" data-role="liq" onclick="var c=this.querySelector('input[type=checkbox]'); c.checked=!c.checked; actualizarManualBar(this.closest('form'));">
                                        <td class="pick-cell">
                                            <input type="checkbox"
                                                   name="linea_ids[]"
                                                   value="{{ $linea->id }}"
                                                   data-monto="{{ $parcial ? $parcial['pendiente'] : round(abs((float) $linea->monto), 2) }}"
                                                   onclick="event.stopPropagation(); actualizarManualBar(this.closest('form'));">
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($linea->fecha)->format('d/m/Y') }}</td>
                                        <td title="{{ $linea->descripcion }}">
                                            {{ \Illuminate\Support\Str::limit($linea->descripcion, 42) }}
                                            @if($parcial)
                                                <div style="font-size:.72rem; color:#b45309; font-weight:700; margin-top:2px;">
                                                    Falta {{ $parcial['faltan']->count() === 1 ? 'el lote' : 'los lotes' }}
                                                    {{ $parcial['faltan']->map(fn ($f) => $f->lote.' (Bs. '.number_format((float) $f->monto, 2).')')->implode(', ') }}
                                                </div>
                                                <div style="font-size:.7rem; color:#64748b;">
                                                    Ya conciliados: lotes {{ $parcial['ligados']->pluck('lote')->implode(', ') }} · abono total Bs. {{ number_format((float) $linea->monto, 2) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>Bs. {{ number_format($parcial ? $parcial['pendiente'] : (float) $linea->monto, 2) }}
                                            @unless($parcial)
                                                <button type="button" class="btn-registrar-lote"
                                                        onclick="event.stopPropagation(); abrirRegistrarLote(this);"
                                                        data-linea="{{ $linea->id }}" data-fecha="{{ \Carbon\Carbon::parse($linea->fecha)->format('Y-m-d') }}"
                                                        data-fecha-txt="{{ \Carbon\Carbon::parse($linea->fecha)->format('d/m/Y') }}"
                                                        data-monto="{{ round(abs((float) $linea->monto), 2) }}" data-desc="{{ $linea->descripcion }}"
                                                        data-cuenta="{{ $tarjeta['banco'] }} · {{ $tarjeta['titular'] }}"
                                                        title="Esta LIQ no tiene su lote en Tesorería: regístralo y queda conciliado">+ Registrar lote</button>
                                            @endunless
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="empty-row"><td colspan="4">Sin liquidaciones pendientes</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>
                        @if($tarjeta['liq_pendientes']->count() > 0)
                            <div class="section-footer">Bs. {{ number_format($tarjeta['total_liq'], 2) }}</div>
                        @endif
                    </div>
                </div>
            </form>
            @php $repetidos = $tarjeta['pendientes']->filter(fn ($l) => isset($copiasLotes[$l->id])); @endphp
            @if($repetidos->isNotEmpty())
                <form method="POST" action="{{ route('finanzas.conciliaciones.lotes.quitar_repetidos') }}"
                      onsubmit="return confirm('¿Quitar de Tesorería {{ $repetidos->count() }} lote(s) registrados dos veces? Queda uno de cada lote y se guarda un respaldo.');"
                      style="display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 16px; background:#fff7ed; border-top:1px solid #fed7aa; font-size:.84rem; color:#9a3412;">
                    @csrf
                    @foreach($repetidos as $l)<input type="hidden" name="ids[]" value="{{ $l->id }}">@endforeach
                    <span><strong>{{ $repetidos->count() }} lote(s) repetidos</strong> en Tesorería (misma fecha, número y monto que otro). El banco los abona una sola vez: la copia nunca se concilia y duplica los ingresos.</span>
                    <button type="submit" class="btn-conciliar" style="background:#c2410c; color:white; border:none;">Quitar repetidos</button>
                </form>
            @endif
        </div>
    @empty
        <div class="bank-card" style="padding:28px;color:#64748b;">No hay lotes ni liquidaciones en ese rango. Sube el Excel de medios de pago o carga primero el extracto en Conciliación de egresos.</div>
    @endforelse
</div>

<div id="registrarLoteModal" class="modal-overlay">
    <div class="modal-box">
        <form action="{{ route('finanzas.conciliaciones.lotes.registrar_lote') }}" method="POST"
              onsubmit="const b = this.querySelector('.btn-submit'); b.disabled = true; b.innerText = 'Registrando…';">
            @csrf
            <input type="hidden" name="linea_id" id="rlLinea">
            <div class="modal-head">
                <h3 style="margin:0;">Registrar el lote que falta</h3>
                <button type="button" class="btn-cancel" onclick="document.getElementById('registrarLoteModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:14px; font-size:.86rem;">
                    <div style="font-weight:800;" id="rlDesc"></div>
                    <div style="color:#64748b;"><span id="rlCuenta"></span> · LIQ del <span id="rlFechaLiq"></span> · <strong id="rlMontoLiq"></strong></div>
                </div>
                <label class="form-label">Número de lote</label>
                <input type="text" name="lote_referencia" class="form-control" required maxlength="40" placeholder="Ej. 204">
                <label class="form-label">Fecha del lote (cierre)</label>
                <input type="date" name="fecha" id="rlFecha" class="form-control" required>
                <label class="form-label">Neto del lote (Bs.)</label>
                <input type="number" name="monto" id="rlMonto" class="form-control" required step="0.01" min="0.01" oninput="rlComision()">
                <div id="rlComision" style="font-size:.8rem; color:#b45309; font-weight:700; margin:-8px 0 12px;"></div>
                <label class="form-label">Sede / descripción</label>
                <input type="text" name="descripcion" class="form-control" list="rlSedes" maxlength="255" placeholder="Ej. SEDE DORAL">
                <datalist id="rlSedes">@foreach(($descripcionesLote ?? []) as $d)<option value="{{ $d }}">@endforeach</datalist>
                <p style="color:#64748b; font-size:.8rem; margin:0;">El lote se guarda en Tesorería y queda conciliado con esta LIQ. Si el neto es mayor que lo que abonó el banco, la diferencia sale como comisión.</p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="document.getElementById('registrarLoteModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-submit">Registrar y conciliar</button>
            </div>
        </form>
    </div>
</div>

<div id="mercantilLotesModal" class="modal-overlay">
    <div class="modal-box">
        <form action="{{ route('finanzas.conciliaciones.mercantil_lotes.preview') }}" method="POST" enctype="multipart/form-data"
              onsubmit="const b = this.querySelector('.btn-submit'); b.disabled = true; b.innerText = 'Leyendo el Excel…';">
            @csrf
            <input type="hidden" name="volver" value="lotes">
            <div class="modal-head">
                <h3 style="margin:0;">Balance de transacciones — Mercantil</h3>
                <button type="button" class="btn-cancel" onclick="document.getElementById('mercantilLotesModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color:#64748b;margin-top:0;">
                    Excel del <strong>«Balance de Transacciones»</strong>: columnas <strong>Lote</strong>, <strong>Importe Bruto</strong>
                    y <strong>Total Abonado</strong>, una fila por lote (un cierre o el mes completo). Cada lote entra como LIQ del banco:
                    el que está en Tesorería con el mismo número y monto queda conciliado, y el que no, va a «LIQ del banco sin lote».
                    Subir otra vez el mismo archivo no repite nada. Primero verás el resultado.
                </p>
                <label class="form-label">Cuenta</label>
                <select name="titular" class="form-control" required>
                    @foreach(($titularesPorBanco['MERCANTIL'] ?? []) as $titular)
                        <option value="{{ $titular }}" @selected(str_contains(strtoupper($titular), 'JRZ'))>MERCANTIL · {{ $titular }}</option>
                    @endforeach
                </select>
                <label class="form-label">Excel del balance</label>
                <div class="file-wrap">
                    <label class="file-label">Elegir
                        <input type="file" name="archivos[]" accept=".xlsx,.xls,.csv" multiple required style="display:none;" onchange="document.getElementById('mercantilFileName').value = [...this.files].map(f => f.name).join(', ');">
                    </label>
                    <input id="mercantilFileName" class="file-name" placeholder="Ningún archivo" readonly>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="document.getElementById('mercantilLotesModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-submit">Verificar</button>
            </div>
        </form>
    </div>
</div>

<div id="mediosPagoModal" class="modal-overlay">
    <div class="modal-box">
        <form action="{{ route('finanzas.conciliaciones.medios_pago') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-head">
                <h3 style="margin:0;">Medios de pago — Banco de Venezuela</h3>
                <button type="button" class="btn-cancel" onclick="document.getElementById('mediosPagoModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color:#64748b;margin-top:0;">Excel con Fecha, N° Lote y <strong>Monto Neto</strong>. Se cruza con los abonos LIQ.TARJETA / LIQUIDACION T/ del extracto.</p>
                <label class="form-label">Titular (opcional)</label>
                <select name="titular_seleccionado" class="form-control">
                    <option value="">El del extracto</option>
                    @foreach(($titularesPorBanco['VENEZUELA'] ?? []) as $titular)
                        <option value="{{ $titular }}">{{ $titular }}</option>
                    @endforeach
                </select>
                <label class="form-label">Archivo</label>
                <div class="file-wrap">
                    <label class="file-label">Elegir
                        <input type="file" name="file" accept=".xls,.xlsx,.csv" required style="display:none;" onchange="document.getElementById('mediosFileName').value = this.files[0] ? this.files[0].name : '';">
                    </label>
                    <input id="mediosFileName" class="file-name" placeholder="Ningún archivo" readonly>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="document.getElementById('mediosPagoModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-submit">Conciliar lotes</button>
            </div>
        </form>
    </div>
</div>
<script>
function fmtBs(n) {
    return 'Bs. ' + Number(n || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
const COLORES_GRUPO = 6;
function pct(delta, base) {
    return base > 0 ? (Math.abs(delta) / base * 100).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' %' : '—';
}
// Selección en curso: el lote marcado y sus LIQ (sin las que ya están en un grupo).
function seleccionActual(form) {
    const radio = form.querySelector('input[name="tesoreria_ingreso_id"]:checked:not(:disabled)');
    const checks = [...form.querySelectorAll('input[name="linea_ids[]"]:checked:not(:disabled)')];
    return {
        radio, checks,
        loteId: radio ? radio.value : null,
        loteRef: radio ? (radio.dataset.lote || '—') : '—',
        loteMonto: radio ? parseFloat(radio.dataset.monto || '0') : 0,
        suma: checks.reduce((acc, el) => acc + parseFloat(el.dataset.monto || '0'), 0),
        lista: !!radio && checks.length > 0,
    };
}
function textoDiferencia(loteMonto, suma) {
    const delta = suma - loteMonto;
    return Math.abs(delta) <= 0.05 ? 'Coincide' : 'Diferencia ' + fmtBs(Math.abs(delta)) + ' (' + pct(delta, loteMonto) + ')';
}
function actualizarManualBar(form) {
    if (!form) return;
    const grupos = form._grupos || [];
    const s = seleccionActual(form);

    form.querySelectorAll('tr[data-role="lote"], tr[data-role="liq"]').forEach(tr => {
        const input = tr.querySelector('input[type=radio], input[type=checkbox]');
        tr.classList.toggle('is-selected', !!(input && input.checked && !input.disabled));
    });

    const resumen = form.querySelector('.manual-resumen');
    const diff = form.querySelector('.manual-diff');
    const btn = form.querySelector('.btn-conciliar');
    const btnAgregar = form.querySelector('.btn-agregar');
    if (resumen) {
        resumen.textContent = s.radio || s.checks.length
            ? 'Lote ' + s.loteRef + ': ' + fmtBs(s.loteMonto) + ' · LIQ seleccionadas: ' + fmtBs(s.suma)
            : (grupos.length ? 'Marca otro lote y sus LIQ, o concilia los grupos' : 'Marca 1 lote y una o varias LIQ');
    }
    if (diff) {
        if (!s.lista) {
            diff.textContent = '';
            diff.className = 'manual-diff';
        } else {
            const ok = Math.abs(s.suma - s.loteMonto) <= 0.05;
            diff.textContent = ' · ' + textoDiferencia(s.loteMonto, s.suma);
            diff.className = 'manual-diff ' + (ok ? 'diff-ok' : 'diff-bad');
        }
    }
    if (btnAgregar) btnAgregar.disabled = !s.lista;
    if (btn) {
        const total = grupos.length + (s.lista ? 1 : 0);
        btn.disabled = total === 0;
        btn.textContent = total > 1 ? 'Conciliar ' + total + ' grupos' : 'Conciliar manualmente';
    }

    const caja = form.querySelector('.manual-grupos');
    if (caja) {
        caja.innerHTML = grupos.map((g, i) => '<span class="grupo-chip g-' + g.color + '">G' + (i + 1) + ' · Lote ' + g.loteRef
            + ' · ' + fmtBs(g.loteMonto) + ' · ' + textoDiferencia(g.loteMonto, g.suma)
            + ' <button type="button" title="Quitar este grupo" onclick="quitarGrupo(this.closest(\'form\'), ' + i + ')">×</button></span>').join('');
    }
}
// Guarda la selección como un grupo con su color y deja las filas marcadas y bloqueadas.
function agregarGrupo(form) {
    const s = seleccionActual(form);
    if (!s.lista) return;
    form._grupos = form._grupos || [];
    const usados = form._grupos.map(g => g.color);
    let color = 0;
    while (usados.includes(color) && color < COLORES_GRUPO - 1) color++;
    const grupo = { lote: s.loteId, loteRef: s.loteRef, loteMonto: s.loteMonto, suma: s.suma, lineas: s.checks.map(c => c.value), color: color % COLORES_GRUPO };
    form._grupos.push(grupo);
    [s.radio, ...s.checks].forEach(input => marcarFila(input, grupo, form._grupos.length));
    actualizarManualBar(form);
}
function marcarFila(input, grupo, numero) {
    input.checked = false;
    input.disabled = true;
    const tr = input.closest('tr');
    tr.classList.add('en-grupo', 'g-' + grupo.color);
    tr.classList.remove('is-selected');
    const celda = tr.querySelector('td:last-child');
    celda.insertAdjacentHTML('beforeend', '<span class="grupo-tag" data-grupo-tag>G' + numero + '</span>');
}
function quitarGrupo(form, indice) {
    const grupos = form._grupos || [];
    grupos.splice(indice, 1);
    form.querySelectorAll('tr.en-grupo').forEach(tr => {
        tr.className = tr.className.replace(/\ben-grupo\b|\bg-\d\b/g, '').trim();
        tr.querySelectorAll('[data-grupo-tag]').forEach(el => el.remove());
        const input = tr.querySelector('input[type=radio], input[type=checkbox]');
        if (input) input.disabled = false;
    });
    // Se vuelven a pintar los que quedan (la numeración se corre).
    grupos.forEach((g, i) => {
        const inputs = [form.querySelector('input[name="tesoreria_ingreso_id"][value="' + g.lote + '"]'),
            ...g.lineas.map(id => form.querySelector('input[name="linea_ids[]"][value="' + id + '"]'))].filter(Boolean);
        inputs.forEach(input => marcarFila(input, g, i + 1));
    });
    actualizarManualBar(form);
}
// LIQ sin lote: se abre el formulario con los datos de la liquidación (el lote suele ser del día anterior).
function abrirRegistrarLote(boton) {
    const d = boton.dataset;
    const fecha = new Date(d.fecha + 'T12:00:00');
    fecha.setDate(fecha.getDate() - 1);
    document.getElementById('rlLinea').value = d.linea;
    document.getElementById('rlDesc').textContent = d.desc;
    document.getElementById('rlCuenta').textContent = d.cuenta;
    document.getElementById('rlFechaLiq').textContent = d.fechaTxt;
    document.getElementById('rlMontoLiq').textContent = fmtBs(d.monto);
    document.getElementById('rlMontoLiq').dataset.monto = d.monto;
    document.getElementById('rlFecha').value = fecha.toISOString().slice(0, 10);
    document.getElementById('rlMonto').value = d.monto;
    rlComision();
    const modal = document.getElementById('registrarLoteModal');
    modal.querySelector('[name=lote_referencia]').value = '';
    modal.style.display = 'flex';
    modal.querySelector('[name=lote_referencia]').focus();
}
function rlComision() {
    const liq = parseFloat(document.getElementById('rlMontoLiq').dataset.monto || '0');
    const neto = parseFloat(document.getElementById('rlMonto').value || '0');
    const dif = neto - liq;
    document.getElementById('rlComision').textContent = Math.abs(dif) < 0.01 ? ''
        : (dif > 0 ? 'Comisión del banco: ' : 'El banco abonó de más: ') + fmtBs(Math.abs(dif)) + ' (' + pct(dif, neto) + ')';
}
function ampliarBanco(boton) {
    const card = boton.closest('.bank-card');
    const abierta = card.classList.toggle('is-expanded');
    document.body.classList.toggle('hay-ampliado', abierta);
    boton.textContent = abierta ? '✕ Cerrar' : '⛶ Ampliar';
}
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const card = document.querySelector('.bank-card.is-expanded');
    if (card) ampliarBanco(card.querySelector('.btn-ampliar'));
});
document.querySelectorAll('form.manual-lote-form').forEach(function (form) {
    actualizarManualBar(form);
    form.addEventListener('submit', function (e) {
        // «→ Mes sig.» / «devolver» usan formaction propio: no pasan por la conciliación.
        if (e.submitter && e.submitter.hasAttribute('formaction')) return;
        const s = seleccionActual(form);
        const grupos = [...(form._grupos || [])];
        if (s.lista) grupos.push({ lote: s.loteId, loteRef: s.loteRef, loteMonto: s.loteMonto, suma: s.suma, lineas: s.checks.map(c => c.value) });
        if (!grupos.length) { e.preventDefault(); return; }

        const aceptar = form.querySelector('input[name="aceptar_diferencia"]');
        aceptar.value = '0';
        const conDiferencia = grupos.filter(g => Math.abs(g.suma - g.loteMonto) > 0.05);
        if (conDiferencia.length) {
            const lineas = conDiferencia.map(g => '• Lote ' + g.loteRef + ': ' + fmtBs(g.loteMonto) + ' vs LIQ ' + fmtBs(g.suma)
                + ' → ' + fmtBs(Math.abs(g.suma - g.loteMonto)) + ' (' + pct(g.suma - g.loteMonto, g.loteMonto) + ')' + (g.suma > g.loteMonto ? ' de más' : ' de menos'));
            if (!confirm((conDiferencia.length === 1 ? 'Este grupo tiene diferencia:' : 'Estos grupos tienen diferencia:') + '\n\n' + lineas.join('\n') + '\n\n¿Conciliar con esa diferencia?')) {
                e.preventDefault();
                return;
            }
            aceptar.value = '1';
        }
        form.querySelector('input[name="grupos"]').value = JSON.stringify(grupos.map(g => ({ lote: Number(g.lote), lineas: g.lineas.map(Number) })));
        // Las filas bloqueadas no viajan en el formulario: todo va en «grupos».
        form.querySelectorAll('input[name="tesoreria_ingreso_id"], input[name="linea_ids[]"]').forEach(i => i.disabled = true);
    });
});
</script>
@endsection
