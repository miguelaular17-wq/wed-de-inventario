@extends('layouts.app')

@section('title', 'Reporte por marca')

@section('content')
@php
    $queryPdf = array_filter([
        'fecha_desde' => $desde,
        'fecha_hasta' => $hasta,
        'sede' => $filtroSede,
        'tipo_gestion' => $tipoFiltro ?: null,
        'formato' => 'pdf',
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Reporte por marca</h1>
            <p class="muted" style="margin:4px 0 0;">
                Servicio técnico, garantías y reparaciones internas agrupados por marca del equipo.
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn secondary" href="{{ route('servicio.ordenes.index') }}">Órdenes</a>
            <a class="btn primary" href="{{ route('servicio.ordenes.reporte_marca', $queryPdf) }}">Descargar PDF</a>
        </div>
    </div>

    <form method="GET" action="{{ route('servicio.ordenes.reporte_marca') }}" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Desde</label>
            <input type="date" name="fecha_desde" value="{{ $desde }}" required>
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="fecha_hasta" value="{{ $hasta }}" required>
        </div>
        @if($puedeFiltrarSede)
            <div class="field">
                <label>Sede</label>
                <select name="sede">
                    <option value="">Todas</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede }}" @selected(($filtroSede ?? '') === $sede)>{{ $sede }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="field">
            <label>Tipo</label>
            <select name="tipo_gestion">
                <option value="">Todos</option>
                @foreach($tiposGestion as $key => $label)
                    <option value="{{ $key }}" @selected($tipoFiltro === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Ver reporte</button>
        </div>
    </form>

    <div class="nomina-kpis" style="margin-top:16px;">
        <div class="nomina-kpi"><span>Total órdenes</span><strong>{{ $totales['total'] }}</strong></div>
        <div class="nomina-kpi"><span>Servicio técnico</span><strong>{{ $totales['st'] }}</strong></div>
        <div class="nomina-kpi"><span>Garantías</span><strong>{{ $totales['garantia'] }}</strong></div>
        <div class="nomina-kpi"><span>Reparaciones internas</span><strong>{{ $totales['interna'] }}</strong></div>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Marca</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:right;">Servicio técnico</th>
                    <th style="text-align:right;">Garantías</th>
                    <th style="text-align:right;">Internas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($porMarca as $bloque)
                    <tr>
                        <td><strong>{{ $bloque['marca'] }}</strong></td>
                        <td style="text-align:right;">{{ $bloque['total'] }}</td>
                        <td style="text-align:right;">{{ $bloque['st'] }}</td>
                        <td style="text-align:right;">{{ $bloque['garantia'] }}</td>
                        <td style="text-align:right;">{{ $bloque['interna'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted" style="text-align:center;padding:28px;">
                            No hay órdenes en el período seleccionado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach($porMarca as $bloque)
        <div style="margin-top:22px;">
            <h3 style="margin:0 0 8px;">{{ $bloque['marca'] }}
                <span class="muted" style="font-size:.85rem;font-weight:500;">
                    · {{ $bloque['total'] }} órdenes
                    · ST {{ $bloque['st'] }}
                    · Garantía {{ $bloque['garantia'] }}
                    · Interna {{ $bloque['interna'] }}
                </span>
            </h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Equipo</th>
                            <th>Sede</th>
                            <th>Estado</th>
                            <th>Cliente / Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bloque['ordenes'] as $orden)
                            <tr>
                                <td>
                                    <a href="{{ route('servicio.ordenes.show', $orden) }}">{{ $orden->codigo() }}</a>
                                </td>
                                <td>{{ optional($orden->fecha_ingreso)->format('d/m/Y') ?: '—' }}</td>
                                <td>{{ $orden->etiquetaTipoGestion() }}</td>
                                <td>
                                    <strong>{{ $orden->equipo ?: '—' }}</strong>
                                    @if($orden->serial || $orden->imei)
                                        <div class="muted" style="font-size:.75rem;">{{ $orden->serial ?: $orden->imei }}</div>
                                    @endif
                                </td>
                                <td>{{ $orden->sede }}</td>
                                <td>{{ $orden->etiquetaEstado() }}</td>
                                <td>{{ $orden->esReparacionInterna() ? ($orden->falla ?: '—') : ($orden->cliente_nombre ?: '—') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
