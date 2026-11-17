@extends('layouts.app')

@section('title', 'Servicio técnico resuelto')

@section('content')
@php
    $resultados = \App\Models\StOrden::RESULTADOS_ST;
    $colores = \App\Models\StOrden::COLORES_RESULTADO_ST;
    $filtrosBase = array_filter([
        'sede' => $filtroSede,
        'fecha_desde' => $desde,
        'fecha_hasta' => $hasta,
    ], fn ($v) => $v !== null && $v !== '');
    $queryPdf = $filtrosBase + array_filter(['resultado' => $resultadoFiltro ?: null]) + ['formato' => 'pdf'];
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Servicio técnico resuelto</h1>
            <p class="muted" style="margin:4px 0 0;">
                Órdenes ST ya reconocidas (reparado, sin reparación o sin repuesto). Si se pasan a «Sin estado» vuelven al reporte por marca.
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn secondary" href="{{ route('servicio.ordenes.reporte_marca', ['tipo_gestion' => \App\Models\StOrden::TIPO_ST]) }}">Reporte por marca</a>
            <a class="btn primary" href="{{ route('servicio.ordenes.st_resueltos', $queryPdf) }}">Descargar PDF</a>
        </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;">
        <a href="{{ route('servicio.ordenes.st_resueltos', $filtrosBase) }}" class="btn {{ $resultadoFiltro === '' ? 'primary' : 'secondary' }}">
            Todas · {{ $total }}
        </a>
        @foreach($resultados as $clave => $texto)
            @php [$fondo, $tinta] = $colores[$clave] ?? ['#f1f5f9', '#475569']; @endphp
            <a href="{{ route('servicio.ordenes.st_resueltos', $filtrosBase + ['resultado' => $clave]) }}"
               style="padding:8px 14px;border-radius:999px;font-weight:700;font-size:.85rem;text-decoration:none;background:{{ $fondo }};color:{{ $tinta }};{{ $resultadoFiltro === $clave ? 'outline:3px solid '.$tinta.';' : '' }}">
                {{ $texto }} · {{ $porResultado[$clave] ?? 0 }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('servicio.ordenes.st_resueltos') }}" class="filter-bar" style="margin-top:14px;">
        @if($resultadoFiltro)<input type="hidden" name="resultado" value="{{ $resultadoFiltro }}">@endif
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
        <div class="field"><label>Ingreso desde</label><input type="date" name="fecha_desde" value="{{ $desde }}"></div>
        <div class="field"><label>Hasta</label><input type="date" name="fecha_hasta" value="{{ $hasta }}"></div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>

    @forelse($grupos as $clave => $ordenes)
        <div style="margin-top:22px;">
            <h3 style="margin:0 0 8px;">{{ $resultados[$clave] ?? $clave }} <span class="muted" style="font-size:.85rem;font-weight:500;">· {{ $ordenes->count() }} órdenes</span></h3>
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
                            <th>Estado</th>
                            <th>Reconocimiento</th>
                            <th>Cliente / Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ordenes as $orden)
                            <tr>
                                <td><a href="{{ route('servicio.ordenes.show', $orden) }}">{{ $orden->codigo() }}</a></td>
                                <td>{{ optional($orden->fecha_ingreso)->format('d/m/Y') ?: '—' }}</td>
                                <td>{{ $orden->marca_reporte }}</td>
                                <td>
                                    <strong>{{ $orden->equipo ?: '—' }}</strong>
                                    @if($orden->esLote())<strong style="color:#1d4ed8;">× {{ $orden->unidades() }}</strong>@endif
                                    @if($orden->serial || $orden->imei)<div class="muted" style="font-size:.75rem;">{{ $orden->serial ?: $orden->imei }}</div>@endif
                                </td>
                                <td>
                                    {{ $orden->sedeOrigen() }}
                                    @if($orden->sedeOrigen() !== strtoupper((string) $orden->sede))
                                        <div class="muted" style="font-size:.75rem;">Ahora en {{ $orden->sede }}</div>
                                    @endif
                                </td>
                                <td>{{ $orden->creador?->name ?: '—' }}</td>
                                <td>{{ \App\Models\StOrden::ESTADOS[$orden->estado] ?? ucfirst((string) $orden->estado) }}</td>
                                <td>@include('servicio.ordenes._resultado-st-select', ['modo' => 'resueltos'])</td>
                                <td>{{ $orden->cliente_nombre ?: '—' }}@if($orden->falla)<div class="muted" style="font-size:.75rem;">{{ \Illuminate\Support\Str::limit($orden->falla, 60) }}</div>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="muted" style="margin-top:22px;text-align:center;padding:28px;">No hay órdenes ST resueltas para estos filtros.</p>
    @endforelse
</div>
@include('servicio.ordenes._resultado-st-script')
@endsection
