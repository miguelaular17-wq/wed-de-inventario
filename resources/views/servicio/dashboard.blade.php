@extends('layouts.app')

@section('title', 'Servicio técnico')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Dashboard de taller</h1>
            <p class="muted" style="margin:4px 0 0;">
                Quincena {{ $metricas['quincena']['etiqueta'] ?? '' }}
                {{ $metricas['sede_filtro'] ? ' · '.$metricas['sede_filtro'] : '' }}
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn" href="{{ route('servicio.celulares.hub') }}">Celulares / bitácora</a>
            <a class="btn primary" href="{{ route('servicio.ordenes.create') }}">Registrar equipo</a>
        </div>
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
        <div class="nomina-kpi"><span>Por recibir</span><strong>{{ $metricas['por_recibir_count'] }}</strong></div>
        <div class="nomina-kpi"><span>Cobrado</span><strong>${{ number_format($metricas['ingresos_cobrados'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Por cobrar</span><strong>${{ number_format($metricas['por_cobrar'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Repuestos bajo stock</span><strong>{{ $metricas['stock_bajo'] }}</strong></div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
        <a class="btn" href="{{ route('servicio.celulares.por_recibir') }}">📦 Celulares por recibir</a>
        <a class="btn" href="{{ route('servicio.ordenes.index') }}">Ver órdenes</a>
        <a class="btn" href="{{ route('servicio.facturas.index') }}">Ver facturas</a>
    </div>

    @if(($metricas['por_recibir_count'] ?? 0) > 0)
        <div class="panel" style="padding:16px 20px;margin-top:16px;border-left:4px solid #0ea5e9;">
            <strong>📦 {{ $metricas['por_recibir_count'] }} por recibir</strong>
            <div class="table-wrap" style="margin-top:10px;">
                <table class="data-table">
                    <thead><tr><th>Equipo</th><th>Orden</th><th>Desde</th><th></th></tr></thead>
                    <tbody>
                        @foreach($metricas['por_recibir'] as $orden)
                            <tr>
                                <td>{{ $orden->equipoCelular?->etiqueta() ?: ($orden->equipo ?: '—') }}</td>
                                <td><a href="{{ route('servicio.ordenes.show', $orden) }}">{{ $orden->codigo() }}</a></td>
                                <td>{{ $orden->sede_origen_transfer ?: '—' }}</td>
                                <td>
                                    @if($orden->puedeConfirmarRecepcion(auth()->user()))
                                        <form method="POST" action="{{ route('servicio.ordenes.confirmar_recepcion', $orden) }}" style="display:inline;">
                                            @csrf
                                            <button class="btn primary" type="submit">Recibido</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
        <div class="panel" style="padding:20px;">
            <h3 style="margin:0 0 12px;">Egresos 058 · rango filtrado</h3>
            <p class="muted" style="margin:0 0 10px;font-size:.82rem;">Gastos de servicio técnico por trabajador entre {{ \Carbon\Carbon::parse($filtros['desde'])->format('d/m/Y') }} y {{ \Carbon\Carbon::parse($filtros['hasta'])->format('d/m/Y') }}.</p>
            <table class="data-table">
                <thead><tr><th>Trabajador</th><th>Cant.</th><th>Total USD</th></tr></thead>
                <tbody>
                    @forelse($metricas['egresos_058'] as $row)
                        <tr>
                            <td>{{ $row['nombre'] }}</td>
                            <td>{{ $row['cantidad'] }}</td>
                            <td>${{ number_format($row['monto'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin egresos 058 en el rango.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel" style="padding:20px;">
            <h3 style="margin:0 0 12px;">Facturas ST · ventas del técnico</h3>
            <p class="muted" style="margin:0 0 10px;font-size:.82rem;">Facturas de servicio técnico facturadas a su código de vendedor (mismas que usa la comisión).</p>
            <table class="data-table">
                <thead><tr><th>Técnico</th><th>Cant.</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse($metricas['facturas_por_trabajador'] as $row)
                        <tr>
                            <td>{{ $row['nombre'] }}</td>
                            <td>{{ $row['cantidad'] }}</td>
                            <td>${{ number_format($row['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin facturas en el rango.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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
