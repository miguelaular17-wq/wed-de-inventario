@extends('layouts.app')

@section('title', 'Ventas al mayor · Stock')

@section('content')
@php
    $jrz = \App\Services\Mayor\VentasMayorService::SEDE_FACTURA;
    $otras = array_values(array_filter($sedes, fn ($s) => $s !== $jrz));
    $nombreSede = fn ($s) => config('inventario.display.'.$s, $s);
    $filtros = ['alguna' => 'Con existencia', 'jrz' => 'Con stock en JRZ', 'todos' => 'Todos'];
@endphp
<style>
    .vm-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin: 16px 0; }
    .vm-kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; }
    .vm-kpi .t { font-size: .7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
    .vm-kpi .v { font-size: 1.45rem; font-weight: 800; color: #0f172a; margin-top: 2px; }
    .vm-kpi.jrz { background: linear-gradient(135deg, #1e3a8a, #2563eb); border: 0; }
    .vm-kpi.jrz .t, .vm-kpi.jrz .v { color: #fff; }
    .vm-filtros { display: flex; gap: 6px; flex-wrap: wrap; }
    .vm-filtros a { padding: 7px 14px; border-radius: 999px; border: 1px solid #cbd5e1; color: #475569; font-size: .82rem; font-weight: 600; text-decoration: none; background: #fff; }
    .vm-filtros a.on { background: #1e3a8a; border-color: #1e3a8a; color: #fff; }
    .vm-tabla { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .84rem; }
    .vm-tabla thead th { position: sticky; top: 0; z-index: 2; background: #f8fafc; color: #475569; font-size: .72rem; text-transform: uppercase; letter-spacing: .3px; padding: 9px 10px; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
    .vm-tabla thead tr.grupo th { top: 0; padding: 6px 10px; font-size: .7rem; }
    .vm-tabla thead tr.cols th { top: 29px; }
    .vm-tabla tbody td { padding: 9px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .vm-tabla tbody tr:hover td { background: #f8fafc; }
    .vm-tabla tbody tr:hover td.jrz { background: #dbeafe; }
    .vm-num { text-align: right; font-variant-numeric: tabular-nums; }
    th.jrz-h { background: #1e3a8a !important; color: #fff !important; text-align: center; }
    td.jrz { background: #eff6ff; }
    td.jrz-first, th.jrz-first { border-left: 2px solid #2563eb !important; }
    td.jrz-last, th.jrz-last { border-right: 2px solid #2563eb !important; }
    .vm-disp { display: inline-block; min-width: 42px; text-align: center; padding: 3px 10px; border-radius: 999px; font-weight: 800; }
    .vm-disp.si { background: #dcfce7; color: #166534; }
    .vm-disp.no { background: #f1f5f9; color: #94a3b8; }
    .vm-cero { color: #d6dde6; }
    .vm-cat { display: inline-block; margin-top: 2px; padding: 0 7px; border-radius: 999px; background: #f1f5f9; color: #64748b; font-size: .68rem; }
    .vm-btn-fact { padding: 4px 10px; border-radius: 6px; background: #2563eb; color: #fff; font-size: .75rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
</style>
<div class="panel">
    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
        <div>
            <h1 style="margin:0;">Ventas al mayor · Stock</h1>
            <p class="muted" style="margin:4px 0 0;">Se ve el stock de todas las sedes, pero <strong>solo se factura del de JRZ</strong>.</p>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('ventas_mayor.facturas') }}" class="btn secondary">Facturas</a>
            <a href="{{ route('ventas_mayor.create') }}" class="btn primary">+ Nueva factura</a>
        </div>
    </div>

    <div class="vm-kpis">
        <div class="vm-kpi jrz"><div class="t">Productos con stock en JRZ</div><div class="v">{{ number_format($resumen['productos']) }}</div></div>
        <div class="vm-kpi"><div class="t">Unidades en JRZ</div><div class="v">{{ number_format($resumen['unidades']) }}</div></div>
        <div class="vm-kpi"><div class="t">Apartadas en facturas</div><div class="v" style="color:#b45309;">{{ number_format($resumen['apartadas']) }}</div></div>
        <div class="vm-kpi"><div class="t">Valor al mayor en JRZ</div><div class="v" style="color:#15803d;">${{ number_format($resumen['valor_mayor'], 2) }}</div></div>
    </div>

    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
        <form method="GET" action="{{ route('ventas_mayor.stock') }}" style="flex:1; display:flex; gap:8px; min-width:280px;">
            <input type="hidden" name="ver" value="{{ $ver }}">
            <input type="search" name="q" value="{{ $q }}" placeholder="🔍 Buscar por nombre o código…" style="flex:1; padding:9px 12px; border:1px solid #cbd5e1; border-radius:8px;" autofocus>
            <button type="submit" class="btn primary">Buscar</button>
        </form>
        <div class="vm-filtros">
            @foreach($filtros as $clave => $texto)
                <a href="{{ route('ventas_mayor.stock', array_filter(['q' => $q, 'ver' => $clave])) }}" class="{{ $ver === $clave ? 'on' : '' }}">{{ $texto }}</a>
            @endforeach
        </div>
    </div>

    <div style="margin-top:12px; border:1px solid #e2e8f0; border-radius:12px; overflow:auto; max-height:70vh;">
        <table class="vm-tabla">
            <thead>
                <tr class="grupo">
                    <th colspan="2" style="text-align:left;"></th>
                    <th colspan="3" class="jrz-h jrz-first jrz-last">JRZ · se factura de aquí</th>
                    <th colspan="{{ count($otras) + 1 }}" style="text-align:center;">Otras sedes</th>
                    <th></th>
                </tr>
                <tr class="cols">
                    <th style="text-align:left;">Producto</th>
                    <th class="vm-num">Precio mayor</th>
                    <th class="vm-num jrz-h jrz-first">Stock</th>
                    <th class="vm-num jrz-h">Apartado</th>
                    <th class="jrz-h jrz-last">Disponible</th>
                    @foreach($otras as $s)
                        <th class="vm-num">{{ $nombreSede($s) }}</th>
                    @endforeach
                    <th class="vm-num">Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($productos as $p)
                    <tr>
                        <td style="min-width:260px; max-width:380px;">
                            <div style="font-weight:600; color:#0f172a;">{{ $p->nombre }}</div>
                            <div style="font-size:.72rem; color:#64748b;"><code style="font-size:.72rem;">{{ $p->codigo }}</code>@if($p->categoria) <span class="vm-cat">{{ $p->categoria }}</span>@endif</div>
                        </td>
                        <td class="vm-num" style="font-weight:700;">${{ number_format($p->precio_mayor, 2) }}
                            @if($p->precio_unidad > $p->precio_mayor)<div class="muted" style="font-size:.7rem; font-weight:400;">detal ${{ number_format($p->precio_unidad, 2) }}</div>@endif
                        </td>
                        <td class="vm-num jrz jrz-first" style="font-weight:700;">{{ $p->stocks[$jrz] ?: '·' }}</td>
                        <td class="vm-num jrz" style="color:#b45309;">{{ $p->apartado_jrz ?: '·' }}</td>
                        <td class="jrz jrz-last" style="text-align:center;"><span class="vm-disp {{ $p->disponible_jrz > 0 ? 'si' : 'no' }}">{{ $p->disponible_jrz }}</span></td>
                        @foreach($otras as $s)
                            <td class="vm-num {{ $p->stocks[$s] ? '' : 'vm-cero' }}">{{ $p->stocks[$s] ?: '·' }}</td>
                        @endforeach
                        <td class="vm-num" style="font-weight:700;">{{ $p->stock_total }}</td>
                        <td>
                            @if($p->disponible_jrz > 0)
                                <a href="{{ route('ventas_mayor.create', ['productos' => $p->id]) }}" class="vm-btn-fact">+ Facturar</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 7 + count($otras) }}" class="muted" style="text-align:center; padding:28px;">No hay productos con ese criterio.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($productos->count() >= $limite)
        <p class="muted" style="font-size:.78rem; margin-top:6px;">Se muestran los primeros {{ $productos->count() }} (los de más stock en JRZ primero). Busca por nombre o código para ver otros.</p>
    @endif
</div>
@endsection
