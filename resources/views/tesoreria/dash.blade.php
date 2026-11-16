@extends('layouts.app')

@section('title', 'Tesorería · Dashboard')

@php
    $usd = fn ($n) => '$'.number_format((float) $n, 2, ',', '.');
    $colorDif = fn ($d) => abs((float) $d) < 0.005 ? '#334155' : ((float) $d < 0 ? '#b91c1c' : '#15803d');
@endphp

@push('head')
<style>
.td-page { max-width: 100%; }
.td-head { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start; margin-bottom:14px; }
.td-head h1 { margin:0; font-size:1.4rem; }
.td-filter { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:14px; padding:12px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:12px; }
.td-filter label { display:flex; flex-direction:column; gap:4px; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
.td-filter input, .td-filter select { padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; min-width:140px; }
.td-wrap { overflow:auto; background:#fff; border:1px solid #e2e8f0; border-radius:12px; }
.td-table { width:100%; border-collapse:collapse; font-size:.82rem; min-width:720px; }
.td-table th, .td-table td { padding:8px 10px; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.td-table th { background:#f8fafc; font-size:.68rem; text-transform:uppercase; letter-spacing:.03em; color:#64748b; position:sticky; top:0; z-index:1; }
.td-table th.sticky, .td-table td.sticky { position:sticky; left:0; background:#fff; z-index:2; box-shadow:1px 0 0 #e2e8f0; }
.td-table th.sticky { background:#f1f5f9; z-index:3; }
.td-table tfoot td { font-weight:800; background:#f8fafc; border-top:2px solid #cbd5e1; }
.td-num { text-align:right; font-variant-numeric:tabular-nums; }
.td-muted { color:#94a3b8; }
</style>
@endpush

@section('content')
<div class="td-page">
    <div class="td-head">
        <div>
            <h1>Dashboard Tesorería</h1>
            <p class="muted" style="margin:4px 0 0;">
                Filas = formas de pago.
                @if($modo === 'sedes')
                    Columnas = sedes (Suite $) · Ingreso tesorería global · Diferencia (Tesorería − Suite).
                @else
                    Columnas = cajas de {{ $etiquetas[$sede] ?? $sede }} (Suite $) · Ingreso tesorería · Diferencia.
                @endif
            </p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('tesoreria.dash.pdf', ['desde' => $desde, 'hasta' => $hasta, 'sede' => $sede]) }}" class="btn primary">Descargar PDF</a>
            <a href="{{ route('tesoreria.dashboard') }}" class="btn secondary">Panel ingresos</a>
            <a href="{{ route('tesoreria.caja.index') }}" class="btn secondary">Caja Bs/Divisas</a>
            <a href="{{ route('cuadre_caja.index', ['desde' => $desde, 'hasta' => $hasta, 'sede' => $sede]) }}" class="btn secondary">Cuadre de caja</a>
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

    <div class="td-wrap">
        <table class="td-table">
            <thead>
                <tr>
                    <th class="sticky">Forma de pago</th>
                    @foreach($columnas as $col)
                        <th class="td-num">{{ $col['etiqueta'] }}</th>
                    @endforeach
                    <th class="td-num">Ingreso tesorería {{ $modo === 'sedes' ? 'global' : '' }}</th>
                    <th class="td-num">Diferencia</th>
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $fila)
                    <tr>
                        <td class="sticky"><strong>{{ $fila['forma'] }}</strong></td>
                        @foreach($columnas as $col)
                            @php $v = $fila['por_columna'][$col['clave']] ?? 0; @endphp
                            <td class="td-num {{ abs($v) < 0.005 ? 'td-muted' : '' }}">{{ $usd($v) }}</td>
                        @endforeach
                        <td class="td-num">{{ $usd($fila['tesoreria']) }}</td>
                        <td class="td-num" style="color:{{ $colorDif($fila['diferencia']) }}; font-weight:700;">{{ $usd($fila['diferencia']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 3 + count($columnas) }}" class="muted" style="padding:24px; text-align:center;">
                            No hay cierres de cuadre en ese rango{{ $sede ? ' para esa sede' : '' }}.
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
                        <td class="td-num">{{ $usd($totales['tesoreria']) }}</td>
                        <td class="td-num" style="color:{{ $colorDif($totales['diferencia']) }};">{{ $usd($totales['diferencia']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <p class="muted" style="font-size:.78rem; margin-top:10px;">
        Montos en USD. Las columnas de sede/caja son lo declarado en Suite.
        Ingreso tesorería = lo contado en el cuadre.
        Diferencia = Tesorería − Suite (negativo = faltante, positivo = sobrante).
    </p>
</div>
@endsection
