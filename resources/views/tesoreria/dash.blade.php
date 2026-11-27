@extends('layouts.app')

@section('title', 'Tesorería · Dashboard')

@php
    $usd = function ($n) {
        $n = (float) $n;

        return ($n < -0.004 ? '-' : '').'$'.number_format(abs($n), 2, ',', '.');
    };
    $esCero = fn ($n) => abs((float) $n) < 0.005;
    $claseMonto = fn ($n) => (float) $n < -0.004 ? 'td-dif-neg' : '';
    $claseDif = function ($d) use ($esCero) {
        if ($esCero($d)) {
            return 'td-dif-ok';
        }

        return (float) $d < 0 ? 'td-dif-neg' : 'td-dif-pos';
    };
    $suiteTotal = (float) ($totales['suite'] ?? 0);
    $tesoTotal = (float) ($totales['tesoreria'] ?? 0);
    $difTotal = (float) ($totales['diferencia'] ?? 0);
@endphp

@push('head')
<style>
.td-page { max-width: 100%; }
.td-head {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    align-items: flex-start;
    margin-bottom: 16px;
}
.td-head h1 {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -.02em;
}
.td-head .lead {
    margin: 6px 0 0;
    color: #64748b;
    font-size: .86rem;
    line-height: 1.45;
    max-width: 52rem;
}
.td-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.td-kpis {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}
@media (max-width: 900px) {
    .td-kpis { grid-template-columns: 1fr; }
}
.td-kpi {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    position: relative;
    overflow: hidden;
}
.td-kpi::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
}
.td-kpi-suite::before { background: #1a4480; }
.td-kpi-teso::before { background: #0f766e; }
.td-kpi-dif::before { background: #b45309; }
.td-kpi-dif.is-neg::before { background: #b91c1c; }
.td-kpi-dif.is-pos::before { background: #15803d; }
.td-kpi-dif.is-ok::before { background: #64748b; }
.td-kpi .lbl {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #64748b;
    margin: 0 0 6px;
}
.td-kpi .val {
    font-size: 1.35rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: #0f172a;
    margin: 0;
    line-height: 1.1;
}
.td-kpi .hint {
    margin: 6px 0 0;
    font-size: .75rem;
    color: #94a3b8;
}
.td-kpi-dif.is-neg .val { color: #b91c1c; }
.td-kpi-dif.is-pos .val { color: #15803d; }
.td-dif-btn {
    appearance: none;
    border: 0;
    background: transparent;
    padding: 0;
    margin: 0;
    font: inherit;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    color: inherit;
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 2px;
}
.td-dif-btn:hover { opacity: .85; }
#td-dif-modal {
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    padding: 16px 18px;
    max-width: min(920px, 96vw);
    width: 920px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, .18);
}
#td-dif-modal::backdrop { background: rgba(15, 23, 42, .35); }
#td-dif-modal h3 { margin: 0 0 4px; font-size: 1.05rem; color: #0f172a; }
#td-dif-modal .td-dif-sub { margin: 0 0 12px; font-size: .8rem; color: #64748b; }
#td-dif-modal table { width: 100%; border-collapse: collapse; font-size: .82rem; }
#td-dif-modal th, #td-dif-modal td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; }
#td-dif-modal th { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
#td-dif-modal .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; }
#td-dif-modal .pos { color: #15803d; }
#td-dif-modal .neg { color: #b91c1c; }
#td-dif-modal .ok { color: #64748b; }
#td-dif-modal .abrir {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 6px;
    background: #1a4480;
    color: #fff;
    font-weight: 800;
    font-size: .7rem;
    text-decoration: none;
    white-space: nowrap;
}
#td-dif-modal .abrir:hover { background: #163a6d; color: #fff; }

.td-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
    margin-bottom: 14px;
    padding: 12px 14px;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.td-filter label {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #64748b;
}
.td-filter input,
.td-filter select {
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font: inherit;
    min-width: 148px;
    background: #fff;
    color: #0f172a;
}

.td-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
    overflow: hidden;
}
.td-card-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px 14px;
    background: #0f172a;
    color: #e2e8f0;
}
.td-card-bar strong { font-size: .82rem; font-weight: 700; color: #fff; }
.td-card-bar span { font-size: .72rem; color: #94a3b8; }
.td-legend { display: flex; gap: 12px; flex-wrap: wrap; font-size: .72rem; }
.td-legend i {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 2px;
    margin-right: 5px;
    vertical-align: middle;
}
.td-legend .neg i { background: #b91c1c; }
.td-legend .pos i { background: #15803d; }
.td-legend .ok i { background: #94a3b8; }

.td-wrap { overflow-x: auto; overflow-y: visible; }
.td-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .8rem; min-width: 760px; }
.td-table th, .td-table td {
    padding: 9px 11px;
    border-bottom: 1px solid #eef2f7;
    white-space: nowrap;
    vertical-align: middle;
}
.td-table thead th {
    position: sticky;
    top: 0;
    z-index: 4;
    background: #f8fafc;
    font-size: .66rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #64748b;
    font-weight: 700;
    border-bottom: 1px solid #dbe3ef;
}
.td-table thead tr.td-group th {
    top: 0;
    z-index: 5;
    background: #1a4480;
    color: #fff;
    text-align: center;
    font-size: .7rem;
    letter-spacing: .06em;
    border-bottom: none;
    padding: 7px 10px;
}
.td-table thead tr.td-cols th { top: 30px; }
.td-table th.sticky, .td-table td.sticky {
    position: sticky;
    left: 0;
    z-index: 3;
    background: #fff;
    box-shadow: 1px 0 0 #e2e8f0;
    min-width: 200px;
    max-width: 260px;
}
.td-table thead th.sticky {
    z-index: 6;
    background: #f1f5f9;
    color: #334155;
}
.td-table thead tr.td-group th.sticky {
    background: #163a6b;
    text-align: left;
}
.td-table tbody tr:hover td { background: #f8fafc; }
.td-table tbody tr:hover td.sticky { background: #f8fafc; }
.td-table tbody tr:hover td.td-teso { background: #f0fdfa; }
.td-table tbody tr:hover td.td-dif-cell { background: #fffbeb; }
.td-forma { font-weight: 700; color: #1e293b; font-size: .78rem; display: block; }
.td-cuenta { margin-top: 2px; font-size: .68rem; font-weight: 500; color: #64748b; line-height: 1.25; }
.td-num { text-align: right; font-variant-numeric: tabular-nums; color: #334155; }
.td-muted { color: #cbd5e1 !important; font-weight: 500; }
.td-sede { background: #fcfdff; }
.td-suite-tot {
    background: #eef2ff;
    font-weight: 800;
    color: #1e3a8a;
    border-left: 1px solid #c7d2fe;
}
.td-teso {
    background: #f0fdfa;
    font-weight: 700;
    color: #0f766e;
    border-left: 1px solid #ccfbf1;
}
.td-dif-cell {
    background: #fffbeb;
    border-left: 1px solid #fde68a;
    font-weight: 800;
}
.td-dif-neg { color: #b91c1c; }
.td-dif-pos { color: #15803d; }
.td-dif-ok { color: #64748b; font-weight: 600; }
.td-table thead th.td-suite-tot-h {
    background: #e0e7ff;
    color: #1e3a8a;
}
.td-table thead th.td-teso-h {
    background: #ecfdf5;
    color: #0f766e;
}
.td-table thead th.td-dif-h {
    background: #fffbeb;
    color: #92400e;
}
.td-table tfoot td {
    position: sticky;
    bottom: 0;
    z-index: 2;
    font-weight: 800;
    background: #f1f5f9;
    border-top: 2px solid #cbd5e1;
    box-shadow: 0 -1px 0 #cbd5e1;
}
.td-table tfoot td.sticky { background: #e2e8f0; z-index: 4; }
.td-table tfoot td.td-suite-tot { background: #c7d2fe; color: #1e3a8a; }
.td-table tfoot td.td-teso { background: #ccfbf1; color: #115e59; }
.td-table tfoot td.td-dif-cell { background: #fef3c7; }
.td-table tbody tr:hover td.td-suite-tot { background: #e0e7ff; }
.td-empty { padding: 36px 16px !important; text-align: center; color: #94a3b8; }
.td-note {
    margin: 12px 0 0;
    font-size: .76rem;
    color: #94a3b8;
    line-height: 1.45;
}
</style>
@endpush

@section('content')
<div class="td-page">
    <div class="td-head">
        <div>
            <h1>Dashboard Tesorería</h1>
        </div>
    </div>

    <div class="td-kpis">
        <div class="td-kpi td-kpi-suite">
            <p class="lbl">Ingresos caja {{ $modo === 'sedes' ? '(todas las sedes)' : '' }}</p>
            <p class="val">{{ $usd($suiteTotal) }}</p>
            <p class="hint">Declarado en facturadora</p>
        </div>
        <div class="td-kpi td-kpi-teso">
            <p class="lbl">Ingresos tesorería</p>
            <p class="val">{{ $usd($tesoTotal) }}</p>
            <p class="hint">Contado en arqueo de caja</p>
        </div>
        <div class="td-kpi td-kpi-dif {{ $claseDif($difTotal) === 'td-dif-neg' ? 'is-neg' : ($claseDif($difTotal) === 'td-dif-pos' ? 'is-pos' : 'is-ok') }}">
            <p class="lbl">Diferencia</p>
            <p class="val">{{ $usd($difTotal) }}</p>
            <p class="hint">{{ $esCero($difTotal) ? 'Cuadra' : ($difTotal < 0 ? 'Faltante' : 'Sobrante') }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('tesoreria.dash') }}" class="td-filter">
        <label>Desde
            <input type="date" name="desde" value="{{ $desde }}" required>
        </label>
        <label>Hasta
            <input type="date" name="hasta" value="{{ $hasta }}" required>
        </label>
        <label>Sede
            <select name="sede" onchange="this.form.submit()">
                <option value="">Todas (ver por sede)</option>
                @foreach($sedes as $s)
                    <option value="{{ $s }}" @selected($sede === $s)>{{ $etiquetas[$s] ?? $s }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn primary">Ver</button>
        @if($sede !== '')
            <a class="btn secondary" href="{{ route('tesoreria.dash', ['desde' => $desde, 'hasta' => $hasta]) }}">Quitar sede</a>
        @endif
    </form>

    <div class="td-card">
        <div class="td-card-bar">
            <div>
                <strong>{{ $modo === 'sedes' ? 'Consolidado por sede' : 'Detalle por caja' }}</strong>
                <span style="margin-left:8px;">{{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</span>
            </div>
            <div class="td-legend">
                <span class="neg"><i></i>Faltante</span>
                <span class="pos"><i></i>Sobrante</span>
                <span class="ok"><i></i>Cuadra</span>
            </div>
        </div>

        <div class="td-wrap">
            <table class="td-table">
                <thead>
                    <tr class="td-group">
                        <th class="sticky">Forma de pago</th>
                        @if(count($columnas))
                            <th colspan="{{ count($columnas) }}">{{ $modo === 'sedes' ? 'Ingresos caja por sede' : 'Ingresos caja por caja' }}</th>
                        @endif
                        <th>Ingresos caja</th>
                        <th>Ingresos tesorería</th>
                        <th>Resultado</th>
                    </tr>
                    <tr class="td-cols">
                        <th class="sticky">Forma de pago</th>
                        @foreach($columnas as $col)
                            <th class="td-num">{{ $col['etiqueta'] }}</th>
                        @endforeach
                        <th class="td-num td-suite-tot-h">Total</th>
                        <th class="td-num td-teso-h">{{ $modo === 'sedes' ? 'Global' : 'Total' }}</th>
                        <th class="td-num td-dif-h">Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($filas as $fila)
                        @php $soloSuite = !empty($fila['excluir_de_diferencia']); @endphp
                        <tr>
                            <td class="sticky">
                                <span class="td-forma">{{ $fila['forma'] }}</span>
                                @if(!empty($fila['cuenta']))
                                    <div class="td-cuenta">{{ $fila['cuenta'] }}</div>
                                @endif
                                @if($soloSuite)
                                    <div class="td-cuenta">No entra en arqueo / diferencia</div>
                                @endif
                            </td>
                            @foreach($columnas as $col)
                                @php $v = $fila['por_columna'][$col['clave']] ?? 0; @endphp
                                <td class="td-num td-sede {{ $esCero($v) ? 'td-muted' : '' }} {{ $claseMonto($v) }}">{{ $esCero($v) ? '—' : $usd($v) }}</td>
                            @endforeach
                            <td class="td-num td-suite-tot {{ $esCero($fila['suite']) ? 'td-muted' : '' }} {{ $claseMonto($fila['suite']) }}">{{ $esCero($fila['suite']) ? '—' : $usd($fila['suite']) }}</td>
                            <td class="td-num td-teso {{ ($soloSuite || $esCero($fila['tesoreria'])) ? 'td-muted' : '' }}">{{ $soloSuite ? '—' : ($esCero($fila['tesoreria']) ? '—' : $usd($fila['tesoreria'])) }}</td>
                            <td class="td-num td-dif-cell {{ $soloSuite ? 'td-muted' : $claseDif($fila['diferencia']) }}">
                                @if($soloSuite)
                                    —
                                @elseif($esCero($fila['diferencia']))
                                    {{ $usd($fila['diferencia']) }}
                                @else
                                    <button type="button" class="td-dif-btn"
                                        data-clave="{{ $fila['clave'] }}"
                                        data-forma="{{ $fila['forma'] }}"
                                        data-cuenta="{{ $fila['cuenta'] ?? '' }}"
                                        title="Ver de qué cajas sale esta diferencia">{{ $usd($fila['diferencia']) }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 4 + count($columnas) }}" class="td-empty">
                                No hay cierres de arqueo en ese rango{{ $sede ? ' para esa sede' : '' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($filas)
                    <tfoot>
                        <tr>
                            <td class="sticky">Total</td>
                            @foreach($columnas as $col)
                                <td class="td-num">{{ $usd($totales['por_columna'][$col['clave']] ?? 0) }}</td>
                            @endforeach
                            <td class="td-num td-suite-tot">{{ $usd($totales['suite']) }}</td>
                            <td class="td-num td-teso">{{ $usd($totales['tesoreria']) }}</td>
                            <td class="td-num td-dif-cell {{ $claseDif($totales['diferencia']) }}">{{ $usd($totales['diferencia']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <p class="td-note">
        Montos en USD. Las columnas de sede/caja son ingresos caja (facturadora).
        Ingresos tesorería = lo contado en el arqueo.
        Diferencia = Ingresos tesorería − Ingresos caja (negativo = faltante, positivo = sobrante).
        Haz clic en una diferencia distinta de cero para ver de qué cierres sale.
        «Factura a crédito» suma al total de ingresos caja, pero no a tesorería ni a la diferencia.
    </p>
</div>

<dialog id="td-dif-modal">
    <h3 id="td-dif-titulo">Diferencia</h3>
    <p class="td-dif-sub" id="td-dif-sub"></p>
    <div id="td-dif-cuerpo">Cargando…</div>
    <form method="dialog" style="text-align:right;margin-top:14px;"><button class="btn secondary">Cerrar</button></form>
</dialog>
<script>
(function () {
    const modal = document.getElementById('td-dif-modal');
    const cuerpo = document.getElementById('td-dif-cuerpo');
    const titulo = document.getElementById('td-dif-titulo');
    const sub = document.getElementById('td-dif-sub');
    const baseUrl = @json(route('tesoreria.dash.diferencia'));
    const filtros = @json(['desde' => $desde, 'hasta' => $hasta, 'sede' => $sede]);
    const usd = (n) => {
        const v = Number(n) || 0;
        return (v < -0.004 ? '-' : '') + '$' + Math.abs(v).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    };
    const cls = (n) => Math.abs(Number(n)) < 0.005 ? 'ok' : (Number(n) < 0 ? 'neg' : 'pos');
    const esc = (t) => String(t ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

    document.querySelectorAll('.td-dif-btn').forEach(btn => btn.addEventListener('click', async () => {
        const forma = btn.dataset.forma || 'Forma de pago';
        const cuenta = btn.dataset.cuenta || '';
        titulo.textContent = forma;
        sub.textContent = (cuenta ? cuenta + ' · ' : '') + 'Cierres que componen esta diferencia';
        cuerpo.textContent = 'Cargando…';
        modal.showModal();
        const q = new URLSearchParams({...filtros, clave: btn.dataset.clave || ''});
        if (!filtros.sede) q.delete('sede');
        try {
            const r = await fetch(baseUrl + '?' + q.toString(), {headers: {'Accept': 'application/json'}});
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const data = await r.json();
            if (!data.filas || !data.filas.length) {
                cuerpo.innerHTML = '<p class="td-dif-sub">No hay cierres con movimiento en esta forma.</p>';
                return;
            }
            const rows = data.filas.map(f => {
                const marcar = Math.abs(Number(f.diferencia)) >= 0.005;
                return `<tr>
                    <td>${esc(f.fecha)}</td>
                    <td>${esc(f.sede)}</td>
                    <td>${esc(f.caja)}</td>
                    <td>${esc(f.cajero)}</td>
                    <td class="num">${usd(f.suite)}</td>
                    <td class="num">${usd(f.tesoreria)}</td>
                    <td class="num ${cls(f.diferencia)}">${usd(f.diferencia)}</td>
                    <td>${marcar ? `<a class="abrir" href="${esc(f.url)}">Abrir</a>` : ''}</td>
                </tr>`;
            }).join('');
            const t = data.totales || {};
            cuerpo.innerHTML = `<div style="overflow:auto;max-height:60vh;">
                <table>
                    <thead><tr>
                        <th>Fecha</th><th>Sede</th><th>Caja</th><th>Cajero</th>
                        <th class="num">Ingresos caja</th><th class="num">Tesorería</th><th class="num">Diferencia</th><th></th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                    <tfoot><tr>
                        <td colspan="4"><strong>Total</strong></td>
                        <td class="num">${usd(t.suite)}</td>
                        <td class="num">${usd(t.tesoreria)}</td>
                        <td class="num ${cls(t.diferencia)}">${usd(t.diferencia)}</td>
                        <td></td>
                    </tr></tfoot>
                </table>
            </div>`;
        } catch (e) {
            cuerpo.textContent = 'No se pudo cargar el desglose.';
        }
    }));
})();
</script>
@endsection
