@extends('layouts.app')

@section('title', 'Tesorería · Dashboard')

@php
    $usd = fn ($n) => '$'.number_format((float) $n, 2, ',', '.');
    $esCero = fn ($n) => abs((float) $n) < 0.005;
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

.td-wrap { overflow: auto; max-height: min(68vh, 820px); }
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
    min-width: 170px;
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
.td-forma { font-weight: 700; color: #1e293b; font-size: .78rem; }
.td-num { text-align: right; font-variant-numeric: tabular-nums; color: #334155; }
.td-muted { color: #cbd5e1 !important; font-weight: 500; }
.td-sede { background: #fcfdff; }
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
.td-table tfoot td.td-teso { background: #ccfbf1; color: #115e59; }
.td-table tfoot td.td-dif-cell { background: #fef3c7; }
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
            <p class="lead">
                Comparativo Suite vs lo contado en Tesorería.
                @if($modo === 'sedes')
                    Columnas = sedes · una sola vista con todas las sedes.
                @else
                    Columnas = cajas de {{ $etiquetas[$sede] ?? $sede }}.
                @endif
                Diferencia = Tesorería − Suite.
            </p>
        </div>
        <div class="td-actions">
            <a href="{{ route('tesoreria.dash.pdf', ['desde' => $desde, 'hasta' => $hasta, 'sede' => $sede]) }}" class="btn primary">Descargar PDF</a>
            <a href="{{ route('tesoreria.dashboard') }}" class="btn secondary">Panel ingresos</a>
            <a href="{{ route('tesoreria.caja.index') }}" class="btn secondary">Caja principal</a>
            <a href="{{ route('cuadre_caja.index', ['desde' => $desde, 'hasta' => $hasta, 'sede' => $sede]) }}" class="btn secondary">Arqueo de caja</a>
        </div>
    </div>

    <div class="td-kpis">
        <div class="td-kpi td-kpi-suite">
            <p class="lbl">Suite {{ $modo === 'sedes' ? '(todas las sedes)' : '' }}</p>
            <p class="val">{{ $usd($suiteTotal) }}</p>
            <p class="hint">Declarado en facturadora</p>
        </div>
        <div class="td-kpi td-kpi-teso">
            <p class="lbl">Ingreso Tesorería</p>
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
                            <th colspan="{{ count($columnas) }}">{{ $modo === 'sedes' ? 'Suite por sede' : 'Suite por caja' }}</th>
                        @endif
                        <th>Tesorería</th>
                        <th>Resultado</th>
                    </tr>
                    <tr class="td-cols">
                        <th class="sticky">Forma de pago</th>
                        @foreach($columnas as $col)
                            <th class="td-num">{{ $col['etiqueta'] }}</th>
                        @endforeach
                        <th class="td-num td-teso-h">Ingreso {{ $modo === 'sedes' ? 'global' : '' }}</th>
                        <th class="td-num td-dif-h">Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($filas as $fila)
                        <tr>
                            <td class="sticky"><span class="td-forma">{{ $fila['forma'] }}</span></td>
                            @foreach($columnas as $col)
                                @php $v = $fila['por_columna'][$col['clave']] ?? 0; @endphp
                                <td class="td-num td-sede {{ $esCero($v) ? 'td-muted' : '' }}">{{ $esCero($v) ? '—' : $usd($v) }}</td>
                            @endforeach
                            <td class="td-num td-teso {{ $esCero($fila['tesoreria']) ? 'td-muted' : '' }}">{{ $esCero($fila['tesoreria']) ? '—' : $usd($fila['tesoreria']) }}</td>
                            <td class="td-num td-dif-cell {{ $claseDif($fila['diferencia']) }}">{{ $usd($fila['diferencia']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 3 + count($columnas) }}" class="td-empty">
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
                            <td class="td-num td-teso">{{ $usd($totales['tesoreria']) }}</td>
                            <td class="td-num td-dif-cell {{ $claseDif($totales['diferencia']) }}">{{ $usd($totales['diferencia']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <p class="td-note">
        Montos en USD. Las columnas de sede/caja son lo declarado en Suite.
        Ingreso tesorería = lo contado en el arqueo.
        Diferencia = Tesorería − Suite (negativo = faltante, positivo = sobrante).
    </p>
</div>
@endsection
