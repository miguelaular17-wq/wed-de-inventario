@extends('layouts.app')

@section('title', $empleado ? 'Historial faltante · '.$empleado->nombre() : 'Historial de faltante de caja')

@section('content')
@php $dinero = fn ($v) => '$'.number_format((float) $v, 2); @endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.faltante_caja.index') }}" class="muted" style="font-size:.82rem;">← Faltante de caja</a>
            <h1 style="margin:4px 0 0;">Historial de faltante de caja</h1>
            <p class="muted" style="margin:4px 0 0;">Busca a cualquier persona y ve todos sus movimientos: sumas, restas, descuentos y cancelados.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('nomina.faltante_caja.historial') }}" class="filter-bar" style="margin-top:16px;">
        <div class="field field-wide">
            <label>Buscar persona</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nombre o cédula" autofocus>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $desde }}">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}">
        </div>
        @if($empleado)
            <input type="hidden" name="empleado_id" value="{{ $empleado->id }}">
        @endif
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Ver historial</button>
        </div>
    </form>

    @if($candidatos->isNotEmpty())
        <div class="nomina-card" style="margin-top:16px;">
            <h3 style="margin-top:0;">Elige a la persona</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Cédula</th>
                        <th>Sede</th>
                        <th>Cargo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($candidatos as $candidato)
                        <tr>
                            <td><strong>{{ $candidato->nombre() }}</strong></td>
                            <td>{{ $candidato->cedula() ?: '—' }}</td>
                            <td>{{ $candidato->nombreSede() }}</td>
                            <td>{{ $candidato->nombreCargo() }}</td>
                            <td style="text-align:right;">
                                <a class="btn primary" href="{{ route('nomina.faltante_caja.historial', array_filter([
                                    'empleado' => $candidato->id,
                                    'desde' => $desde,
                                    'hasta' => $hasta,
                                ])) }}">Ver historial</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif(! $empleado)
        <p class="muted" style="margin-top:20px;">Escribe un nombre o cédula para ver el historial de esa persona.</p>
    @else
        <div class="nomina-card" style="margin-top:16px;">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
                <div>
                    <h3 style="margin:0;">
                        <a href="{{ route('nomina.empleados.show', $empleado) }}">{{ $empleado->nombre() }}</a>
                    </h3>
                    <p class="muted" style="margin:4px 0 0;">
                        {{ $empleado->cedula() ?: 'Sin cédula' }} · {{ $empleado->nombreSede() }} · {{ $empleado->nombreCargo() }}
                    </p>
                </div>
                <a class="btn secondary" href="{{ route('nomina.faltante_caja.index', ['q' => $empleado->nombre()]) }}">Ir a su cuenta</a>
            </div>
        </div>

        <div class="nomina-kpis" style="margin-top:16px;">
            <div class="nomina-kpi"><span>Movimientos</span><strong>{{ $totales['movimientos'] }}</strong></div>
            <div class="nomina-kpi"><span>Sumado</span><strong>{{ $dinero($totales['sumado']) }}</strong></div>
            <div class="nomina-kpi"><span>Restado</span><strong>{{ $dinero($totales['restado']) }}</strong></div>
            <div class="nomina-kpi"><span>En cuenta ahora</span><strong>{{ $dinero($cuenta['cuenta'] ?? 0) }}</strong></div>
            <div class="nomina-kpi"><span>A descontar</span><strong>{{ $dinero($cuenta['a_descontar'] ?? 0) }}</strong></div>
            <div class="nomina-kpi"><span>Ya aplicado</span><strong>{{ $dinero($totales['aplicado']) }}</strong></div>
        </div>

        <div class="table-wrap" style="margin-top:16px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th style="text-align:right;">Monto</th>
                        <th>Tipo</th>
                        <th>Decisión</th>
                        <th>Estado</th>
                        <th>Quincena</th>
                        <th>Detalle</th>
                        <th>Registró</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lineas as $item)
                        @php
                            $esResta = (float) $item->monto < 0;
                            $tipo = $esResta ? 'Resta' : 'Suma';
                        @endphp
                        <tr>
                            <td>{{ $item->fecha?->format('d/m/Y') }}</td>
                            <td style="text-align:right;{{ $esResta ? 'color:#b91c1c;font-weight:700;' : '' }}">
                                {{ $dinero($item->monto) }}
                            </td>
                            <td>{{ $tipo }}</td>
                            <td>{{ $esResta ? 'Ajuste' : $item->etiquetaDecision() }}</td>
                            <td>
                                {{ $item->estado }}
                                @if($item->estado === 'CANCELADO')
                                    <span class="muted" style="font-size:.72rem;">(no cuenta)</span>
                                @endif
                            </td>
                            <td>
                                @if($item->periodo)
                                    #{{ $item->periodo->id }} · {{ $item->periodo->etiqueta }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $item->motivo ?: '—' }}</td>
                            <td>
                                {{ $item->creador?->name ?: '—' }}
                                <div class="muted" style="font-size:.72rem;">{{ $item->created_at?->format('d/m/Y H:i') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">
                                @if($desde || $hasta)
                                    No hay movimientos en ese rango de fechas.
                                @else
                                    Esta persona todavía no tiene faltantes de caja registrados.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
