@extends('layouts.app')

@section('title', 'Clientes por sede')

@php
    $fmt = fn ($n, $d = 2) => number_format((float) $n, $d);
    $qs = request()->except(['page']);
    $rankUrl = fn (string $campo) => route('gerencial.clientes', array_merge($qs, ['ranking' => $campo]));
    $esGanador = function ($fila, $ganadores, string $campo) {
        $top = $ganadores[$campo] ?? null;
        return $top && $fila->cliente === $top->cliente;
    };
@endphp

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Clientes por sede</h1>
            @include('gerencial._tabs')
            <p class="muted" style="margin:8px 0 0;">Quién factura más, se lleva más unidades y concentra el mayor monto de compra en el período {{ $periodo['etiqueta'] }}.</p>
        </div>
    </div>

    @include('gerencial._filtros', ['modo' => 'clientes', 'action' => route('gerencial.clientes')])

    <div class="nomina-kpis">
        <div class="nomina-kpi">
            <span>Más facturas</span>
            <strong>{{ $kpis['facturas']->cliente ?? '—' }}</strong>
            <div class="muted" style="font-size:.75rem;">{{ number_format((int) ($kpis['facturas']->facturas ?? 0)) }} facturas</div>
        </div>
        <div class="nomina-kpi">
            <span>Más productos</span>
            <strong>{{ $kpis['unidades']->cliente ?? '—' }}</strong>
            <div class="muted" style="font-size:.75rem;">{{ $fmt($kpis['unidades']->unidades ?? 0, 0) }} unds</div>
        </div>
        <div class="nomina-kpi">
            <span>Mayor monto</span>
            <strong>{{ $kpis['monto']->cliente ?? '—' }}</strong>
            <div class="muted" style="font-size:.75rem;">${{ $fmt($kpis['monto']->monto ?? 0) }}</div>
        </div>
        <div class="nomina-kpi">
            <span>Clientes con compra</span>
            <strong>{{ number_format((int) $kpis['clientes']) }}</strong>
        </div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Ranking general</h3>
        <p class="muted" style="margin-top:0;">Ordenado por {{ $ranking === 'facturas' ? 'facturas' : ($ranking === 'unidades' ? 'unidades' : 'monto') }}. Pulsa el encabezado para cambiar.</p>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Sedes</th>
                        <th><a href="{{ $rankUrl('facturas') }}">Facturas{{ $ranking === 'facturas' ? ' ▼' : '' }}</a></th>
                        <th><a href="{{ $rankUrl('unidades') }}">Unidades{{ $ranking === 'unidades' ? ' ▼' : '' }}</a></th>
                        <th><a href="{{ $rankUrl('monto') }}">Monto USD{{ $ranking === 'monto' ? ' ▼' : '' }}</a></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($global as $fila)
                        <tr>
                            <td>
                                <strong>{{ $fila->cliente }}</strong>
                                @if(($kpis['facturas']->cliente ?? null) === $fila->cliente)
                                    <span class="tag" style="background:#dbeafe;color:#1e40af;border-color:#93c5fd;font-size:.68rem;">Más facturas</span>
                                @endif
                                @if(($kpis['unidades']->cliente ?? null) === $fila->cliente)
                                    <span class="tag" style="background:#dcfce7;color:#166534;border-color:#86efac;font-size:.68rem;">Más productos</span>
                                @endif
                                @if(($kpis['monto']->cliente ?? null) === $fila->cliente)
                                    <span class="tag" style="background:#fef3c7;color:#92400e;border-color:#fcd34d;font-size:.68rem;">Mayor monto</span>
                                @endif
                            </td>
                            <td>{{ implode(', ', $fila->sedes) }}</td>
                            <td>{{ number_format($fila->facturas) }}</td>
                            <td>{{ $fmt($fila->unidades, 0) }}</td>
                            <td>${{ $fmt($fila->monto) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Sin ventas de clientes en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach($por_sede as $sedeNombre => $bloque)
        <div class="nomina-card" style="margin-top:16px;">
            <h3>{{ $sedeNombre }}</h3>
            <div class="nomina-kpis" style="margin-bottom:12px;">
                <div class="nomina-kpi">
                    <span>Más facturas</span>
                    <strong>{{ $bloque['ganadores']['facturas']->cliente ?? '—' }}</strong>
                    <div class="muted" style="font-size:.75rem;">{{ number_format((int) ($bloque['ganadores']['facturas']->facturas ?? 0)) }} FAC</div>
                </div>
                <div class="nomina-kpi">
                    <span>Más productos</span>
                    <strong>{{ $bloque['ganadores']['unidades']->cliente ?? '—' }}</strong>
                    <div class="muted" style="font-size:.75rem;">{{ $fmt($bloque['ganadores']['unidades']->unidades ?? 0, 0) }} unds</div>
                </div>
                <div class="nomina-kpi">
                    <span>Mayor monto</span>
                    <strong>{{ $bloque['ganadores']['monto']->cliente ?? '—' }}</strong>
                    <div class="muted" style="font-size:.75rem;">${{ $fmt($bloque['ganadores']['monto']->monto ?? 0) }}</div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Facturas</th>
                            <th>Unidades</th>
                            <th>Monto USD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bloque['filas'] as $fila)
                            <tr>
                                <td>
                                    <strong>{{ $fila->cliente }}</strong>
                                    @if($esGanador($fila, $bloque['ganadores'], 'facturas'))
                                        <span class="tag" style="background:#dbeafe;color:#1e40af;border-color:#93c5fd;font-size:.68rem;">FAC</span>
                                    @endif
                                    @if($esGanador($fila, $bloque['ganadores'], 'unidades'))
                                        <span class="tag" style="background:#dcfce7;color:#166534;border-color:#86efac;font-size:.68rem;">Unds</span>
                                    @endif
                                    @if($esGanador($fila, $bloque['ganadores'], 'monto'))
                                        <span class="tag" style="background:#fef3c7;color:#92400e;border-color:#fcd34d;font-size:.68rem;">$</span>
                                    @endif
                                </td>
                                <td>{{ number_format($fila->facturas) }}</td>
                                <td>{{ $fmt($fila->unidades, 0) }}</td>
                                <td>${{ $fmt($fila->monto) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
