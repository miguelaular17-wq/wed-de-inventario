@extends('layouts.app')

@section('title', 'Servicios realizados')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Servicios realizados</h1>
            <p class="muted" style="margin:4px 0 0;">
                Órdenes con hoja de conformidad o entregadas al cliente, con el técnico que hizo la reparación.
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn secondary" href="{{ route('servicio.ordenes.index') }}">Órdenes</a>
            <a class="btn secondary" href="{{ route('servicio.ordenes.internas') }}">Reparaciones internas</a>
        </div>
    </div>

    <form method="GET" action="{{ route('servicio.ordenes.realizados') }}" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Desde</label>
            <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] }}" required>
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] }}" required>
        </div>
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
            <label>Técnico</label>
            <select name="reparado_por">
                <option value="">Todos</option>
                @foreach($tecnicos as $tecnico)
                    <option value="{{ $tecnico['id'] }}" @selected((int) $filtros['reparado_por'] === $tecnico['id'])>{{ $tecnico['nombre'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Tipo</label>
            <select name="tipo_gestion">
                <option value="">Todos</option>
                @foreach($tiposGestion as $key => $label)
                    <option value="{{ $key }}" @selected($filtros['tipo_gestion'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Buscar</label>
            <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="Cliente, equipo, IMEI, N°">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="nomina-kpis" style="margin-top:16px;">
        <div class="nomina-kpi"><span>Servicios realizados</span><strong>{{ $ordenes->total() }}</strong></div>
        @foreach($resumenTecnicos as $fila)
            <div class="nomina-kpi"><span>{{ $fila['nombre'] }}</span><strong>{{ $fila['n'] }}</strong></div>
        @endforeach
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Realizado</th>
                    <th>Sede</th>
                    <th>Tipo</th>
                    <th>Equipo</th>
                    <th>Cliente</th>
                    <th>Trabajo realizado</th>
                    <th>Técnico</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ordenes as $orden)
                    <tr>
                        <td><a href="{{ route('servicio.ordenes.show', $orden) }}"><strong>{{ $orden->codigo() }}</strong></a></td>
                        <td style="white-space:nowrap;">{{ $orden->realizado_at?->format('d/m/Y H:i') ?: '—' }}</td>
                        <td>{{ $orden->sede }}</td>
                        <td>{{ $orden->etiquetaTipoGestion() }}</td>
                        <td>{{ $orden->equipo ?: ($orden->equipoCelular?->etiqueta() ?? '—') }}@if($orden->esLote()) <strong>× {{ $orden->unidades() }}</strong>@endif</td>
                        <td>{{ $orden->cliente_nombre ?: '—' }}</td>
                        <td style="max-width:280px;">{{ \Illuminate\Support\Str::limit((string) ($orden->conformidad_trabajo ?: $orden->diagnostico), 120) ?: '—' }}</td>
                        <td>{{ $orden->nombreReparador() ?? '—' }}</td>
                        <td>
                            @if($orden->entregada())
                                <span style="color:#15803d;font-weight:600;">Entregado</span>
                            @else
                                <span style="color:#b45309;font-weight:600;">Por entregar</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="muted" style="text-align:center;padding:28px;">No hay servicios realizados en el período.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $ordenes->links() }}</div>
</div>
@endsection
