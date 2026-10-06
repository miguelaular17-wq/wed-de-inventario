@extends('layouts.app')

@section('title', 'Equipo de compra')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Equipo de compra</h1>
            @include('gerencial._tabs')
            <p class="muted" style="margin:8px 0 0;">Lo que cada comprador cerró en Q Pedir durante {{ $periodo['etiqueta'] }}. Pendientes es lo que sigue abierto.</p>
        </div>
    </div>

    @include('gerencial._filtros', ['modo' => 'operativo', 'action' => route('gerencial.compras')])

    <div class="nomina-kpis" style="margin-top:16px;">
        <div class="nomina-kpi warn">
            <span>Pendientes</span>
            <strong>{{ number_format($kpis['pendientes']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Marcados comprados</span>
            <strong>{{ number_format($kpis['comprados']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Productos comprados</span>
            <strong>{{ number_format($kpis['productos']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Fuera de mercado</span>
            <strong>{{ number_format($kpis['fuera']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Días en atender</span>
            <strong>{{ number_format($kpis['dias'], 1) }}</strong>
        </div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3 style="margin-top:0;">Por comprador</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Comprador</th>
                        <th>Cerrados</th>
                        <th>Comprados</th>
                        <th>Productos</th>
                        <th>Fuera de mercado</th>
                        <th>Días promedio</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($personas as $fila)
                        <tr>
                            <td><strong>{{ $fila['nombre'] }}</strong></td>
                            <td>{{ number_format($fila['cerrados']) }}</td>
                            <td>{{ number_format($fila['comprados']) }}</td>
                            <td>{{ number_format($fila['productos']) }}</td>
                            <td>{{ number_format($fila['fuera']) }}</td>
                            <td>{{ number_format($fila['dias'], 1) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No hay compradores para mostrar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
