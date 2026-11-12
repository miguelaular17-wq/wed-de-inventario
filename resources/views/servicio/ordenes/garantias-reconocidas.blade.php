@extends('layouts.app')

@section('title', 'Garantías reconocidas')

@section('content')
@php
    $queryPdf = array_filter([
        'sede' => $filtroSede,
        'empresa' => $empresaFiltro ?: null,
        'motivo' => $motivoFiltro ?: null,
        'formato' => 'pdf',
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Garantías reconocidas por la marca</h1>
            <p class="muted" style="margin:4px 0 0;">
                Agrupadas por empresa de envío. Si se pasan a "Sin estado" vuelven al reporte por marca.
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn secondary" href="{{ route('servicio.ordenes.reporte_marca', ['tipo_gestion' => \App\Models\StOrden::TIPO_GARANTIA]) }}">Reporte por marca</a>
            <a class="btn primary" href="{{ route('servicio.ordenes.garantias_reconocidas', $queryPdf) }}">Descargar PDF</a>
        </div>
    </div>

    <form method="GET" action="{{ route('servicio.ordenes.garantias_reconocidas') }}" class="filter-bar" style="margin-top:16px;">
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
            <label>Empresa de envío</label>
            <select name="empresa">
                <option value="">Todas</option>
                @foreach($empresasEnvio as $key => $label)
                    <option value="{{ $key }}" @selected($empresaFiltro === $key)>{{ $label }}</option>
                @endforeach
                <option value="SIN_EMPRESA" @selected($empresaFiltro === 'SIN_EMPRESA')>Sin empresa asignada</option>
            </select>
        </div>
        <div class="field">
            <label>Motivo</label>
            <select name="motivo">
                <option value="">Todos</option>
                @foreach(\App\Models\StOrden::MOTIVOS_RECONOCIMIENTO_GARANTIA as $key => $label)
                    <option value="{{ $key }}" @selected($motivoFiltro === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="nomina-kpis" style="margin-top:16px;">
        <div class="nomina-kpi"><span>Total reconocidas</span><strong>{{ $total }}</strong></div>
        @foreach(\App\Models\StOrden::MOTIVOS_RECONOCIMIENTO_GARANTIA as $key => $label)
            <div class="nomina-kpi"><span>{{ $label }}</span><strong>{{ $porMotivo[$key] ?? 0 }}</strong></div>
        @endforeach
    </div>

    @forelse($porEmpresa as $bloque)
        <div style="margin-top:22px;">
            <h3 style="margin:0 0 8px;">{{ $bloque['empresa'] }}
                <span class="muted" style="font-size:.85rem;font-weight:500;">
                    · {{ count($bloque['ordenes']) }} órdenes
                    @foreach(\App\Models\StOrden::MOTIVOS_RECONOCIMIENTO_GARANTIA as $key => $label)
                        @if(!empty($bloque['motivos'][$key])) · {{ $label }} {{ $bloque['motivos'][$key] }} @endif
                    @endforeach
                </span>
            </h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Fecha</th>
                            <th>Marca</th>
                            <th>Equipo</th>
                            <th>Sede origen</th>
                            <th>Creada por</th>
                            <th>Fecha de envío</th>
                            <th>Reconocimiento</th>
                            <th>Cliente / Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bloque['ordenes'] as $orden)
                            <tr>
                                <td><a href="{{ route('servicio.ordenes.show', $orden) }}">{{ $orden->codigo() }}</a></td>
                                <td>{{ optional($orden->fecha_ingreso)->format('d/m/Y') ?: '—' }}</td>
                                <td>{{ $orden->marca_reporte }}</td>
                                <td>
                                    <strong>{{ $orden->equipo ?: '—' }}</strong>
                                    @if($orden->esLote())
                                        <strong style="color:#1d4ed8;">× {{ $orden->unidades() }}</strong>
                                    @endif
                                    @if($orden->serial || $orden->imei)
                                        <div class="muted" style="font-size:.75rem;">{{ $orden->serial ?: $orden->imei }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $orden->sedeOrigen() }}
                                    @if($orden->sedeOrigen() !== strtoupper((string) $orden->sede))
                                        <div class="muted" style="font-size:.75rem;">Ahora en {{ $orden->sede }}</div>
                                    @endif
                                </td>
                                <td>{{ $orden->creador?->name ?: '—' }}</td>
                                <td>
                                    @if($orden->garantia_enviado_at)
                                        {{ $orden->garantia_enviado_at->format('d/m/Y') }}
                                    @else
                                        <span class="muted">{{ $orden->etiquetaEstadoGarantiaExterna() }}</span>
                                    @endif
                                </td>
                                <td>@include('servicio.ordenes._reconocimiento-select', ['modo' => 'reconocidas'])</td>
                                <td>{{ $orden->cliente_nombre ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="muted" style="margin-top:22px;text-align:center;padding:28px;">
            No hay garantías reconocidas por la marca para estos filtros.
        </p>
    @endforelse
</div>
@include('servicio.ordenes._reconocimiento-script')
@endsection
