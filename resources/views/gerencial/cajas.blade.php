@extends('layouts.app')

@section('title', 'Efectividad de caja')

@php
    $fmt = fn ($n) => number_format((float) $n, 2);
@endphp

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Efectividad de caja</h1>
            @include('gerencial._tabs')
            <p class="muted" style="margin:8px 0 0;">Facturas y dinero facturado por cada cajera, por sede, en {{ $periodo['etiqueta'] }}.</p>
        </div>
    </div>

    @include('gerencial._filtros', ['modo' => 'operativo', 'action' => route('gerencial.cajas')])

    <div class="nomina-kpis" style="margin-top:16px;">
        <div class="nomina-kpi">
            <span>Facturas</span>
            <strong>{{ number_format($kpis['facturas']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Dinero facturado</span>
            <strong>${{ $fmt($kpis['monto']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Ticket promedio</span>
            <strong>${{ $fmt($kpis['ticket']) }}</strong>
        </div>
    </div>

    @forelse($porCaja as $bloque)
        <div class="nomina-card" style="margin-top:16px;">
            <h3 style="margin:0 0 4px;">{{ $bloque['sede'] }}</h3>
            <p class="muted" style="margin:0 0 10px;">{{ number_format($bloque['facturas']) }} facturas · ${{ $fmt($bloque['monto']) }}</p>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cajera</th>
                            <th>Facturas</th>
                            <th>Dinero facturado</th>
                            <th>Ticket promedio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bloque['cajeras'] as $fila)
                            <tr>
                                <td><strong>{{ $fila['cajera'] }}</strong></td>
                                <td>{{ number_format($fila['facturas']) }}</td>
                                <td>${{ $fmt($fila['monto']) }}</td>
                                <td>${{ $fmt($fila['ticket']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="muted" style="margin-top:16px;">No hay facturas de cajera en el período.</p>
    @endforelse
</div>
@endsection
