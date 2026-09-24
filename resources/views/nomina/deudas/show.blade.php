@extends('layouts.app')

@section('title', 'Deudas — '.$empleado->nombre())

@section('content')
@php
    $ficha = $ficha ?? [];
    $items = $ficha['items'] ?? collect();
@endphp
<div class="panel nomina-page">
    <div class="nomina-ficha-head">
        <div>
            <a href="{{ route('nomina.deudas.index') }}" class="muted" style="font-size:.82rem;">← Deudas del Personal</a>
            <h1 style="margin:4px 0 0;">{{ $empleado->nombre() }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                {{ $empleado->cedula() }} · {{ $empleado->nombreSede() ?: 'Sin sede' }}
                @if(!empty($ficha['cobranza_es_personal']))
                    · <span style="color:#92400e;font-weight:600;">Cliente PERSONAL en cobranza</span>
                    @if(!empty($ficha['cobranza_codigo']))
                        ({{ $ficha['cobranza_codigo'] }}{{ !empty($ficha['cobranza_nombre']) ? ' · '.$ficha['cobranza_nombre'] : '' }})
                    @endif
                @endif
            </p>
        </div>
        <div class="nomina-ficha-meta">
            <a href="{{ route('nomina.empleados.show', $empleado) }}" class="btn secondary">Ficha empleado</a>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Total adeudado</span><strong>${{ number_format($ficha['saldo_pendiente'] ?? 0, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Faltante caja</span><strong>${{ number_format($ficha['faltante_caja'] ?? 0, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Préstamos</span><strong>${{ number_format($ficha['prestamos'] ?? 0, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Cobranza</span><strong>${{ number_format($ficha['cobranza'] ?? 0, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Otros</span><strong>${{ number_format($ficha['otros'] ?? 0, 2) }}</strong></div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px; display:flex; flex-wrap:wrap; gap:12px; align-items:end;">
        <div class="field">
            <label>Tipo</label>
            <select name="tipo">
                <option value="">Todos</option>
                @foreach($tipos as $key => $label)
                    <option value="{{ $key }}" @selected(($filtros['tipo'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select name="estado">
                <option value="">Todos</option>
                @foreach($estados as $key => $label)
                    <option value="{{ $key }}" @selected(($filtros['estado'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
        </div>
        <div class="field" style="display:flex; gap:8px;">
            <button class="btn primary" type="submit">Filtrar</button>
            <a class="btn" href="{{ route('nomina.deudas.show', $empleado) }}">Limpiar</a>
        </div>
    </form>

    <div class="nomina-card" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Concepto</th>
                    <th>Fecha</th>
                    <th class="text-right">Monto</th>
                    <th class="text-right">Saldo</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>
                            {{ $item['tipo_label'] }}
                            @if(!empty($item['es_personal']))
                                <span class="tag" style="background:#fef3c7;color:#92400e;font-size:.7rem;">PERSONAL</span>
                            @endif
                        </td>
                        <td>{{ $item['concepto'] }}</td>
                        <td>{{ $item['fecha'] ? \Carbon\Carbon::parse($item['fecha'])->format('d/m/Y') : '—' }}</td>
                        <td class="text-right">${{ number_format($item['monto'], 2) }}</td>
                        <td class="text-right"><strong>${{ number_format($item['saldo'], 2) }}</strong></td>
                        <td>{{ $estados[$item['estado']] ?? $item['estado'] }}</td>
                        <td>
                            @if(!empty($item['url']))
                                <a class="btn secondary" href="{{ $item['url'] }}">Abrir</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">Este empleado no tiene deudas con esos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
