@extends('layouts.app')

@section('title', 'Servicio técnico')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Dashboard de taller</h1>
            <p class="muted" style="margin:4px 0 0;">
                Resumen{{ $metricas['sede_filtro'] ? ' · '.$metricas['sede_filtro'] : '' }}
            </p>
        </div>
        <a class="btn primary" href="{{ route('servicio.ordenes.create') }}">Nueva orden</a>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        @if($puedeFiltrarSede)
            <div class="field">
                <label>Sede</label>
                <select name="sede">
                    <option value="">Todas</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede }}" @selected($filtros['sede'] === $sede)>{{ $sede }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="field">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $filtros['desde'] }}">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $filtros['hasta'] }}">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="nomina-kpis" style="margin-top:16px;">
        <div class="nomina-kpi"><span>Órdenes</span><strong>{{ $metricas['total_ordenes'] }}</strong></div>
        <div class="nomina-kpi"><span>Pendientes</span><strong>{{ $metricas['pendientes'] }}</strong></div>
        <div class="nomina-kpi"><span>Garantías / interno</span><strong>{{ $metricas['reparaciones'] }}</strong></div>
        <div class="nomina-kpi"><span>Cobrado</span><strong>${{ number_format($metricas['ingresos_cobrados'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Por cobrar</span><strong>${{ number_format($metricas['por_cobrar'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Repuestos bajo stock</span><strong>{{ $metricas['stock_bajo'] }}</strong></div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
        <a class="btn" href="{{ route('servicio.reparaciones.index') }}">Ver garantías</a>
        <a class="btn" href="{{ route('servicio.facturas.index') }}">Ver facturas</a>
        <a class="btn primary" href="{{ route('servicio.reparaciones.create') }}">+ Garantía</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
        <div class="panel" style="padding:20px;">
            <h3 style="margin:0 0 12px;">Órdenes por estado</h3>
            <table class="data-table">
                <thead><tr><th>Estado</th><th>Cantidad</th></tr></thead>
                <tbody>
                    @foreach($metricas['por_estado'] as $estado => $cantidad)
                        <tr><td>{{ \App\Models\StOrden::ESTADOS[$estado] ?? $estado }}</td><td>{{ $cantidad }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel" style="padding:20px;">
            <h3 style="margin:0 0 12px;">Actividad reciente</h3>
            @forelse($metricas['actividad'] as $item)
                <div style="padding:8px 0;border-bottom:1px solid #f1f5f9;">
                    <a href="{{ $item['url'] }}"><strong>{{ $item['titulo'] }}</strong></a>
                    <div class="muted" style="font-size:.8rem;">{{ ucfirst($item['tipo']) }} · {{ $item['estado'] }} · {{ $item['fecha']?->format('d/m/Y H:i') }}</div>
                </div>
            @empty
                <p class="muted">Sin actividad en el rango.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
