@extends('layouts.app')

@section('title', 'Cuadre de caja')

@php
    $dinero = fn ($n) => $n === null ? '—' : (round((float) $n, 2) < 0 ? '-' : '').'$'.number_format(abs((float) $n), 2, ',', '.');
    $tasa = fn ($n) => number_format((float) $n, 2, ',', '.');
    $colorDif = fn ($d) => $d === null ? 'inherit' : ($d < -0.004 ? '#b91c1c' : ($d > 0.004 ? '#15803d' : '#334155'));
    $volver = request()->fullUrl();
@endphp

@section('content')
<div class="page-header">
    <h1>Cuadre de caja</h1>
    <p class="lead">
        Lo que cobró cada caja en el día según Suite, por forma de pago, contra lo que contó Tesorería.
        Cada día aparece a partir del día siguiente, sin esperar el cierre de Suite.
        Diferencia = Tesorería − Suite: negativa es faltante, positiva es sobrante.
    </p>
    @if($ultimaCarga)
        <p class="muted" style="font-size:.8rem;margin:4px 0 0;">
            Última subida del sincronizador:
            @foreach($ultimaCarga as $s => $cuando)
                <span style="white-space:nowrap;">{{ $etiquetas[$s] ?? $s }} {{ $cuando }}</span>@if(! $loop->last) · @endif
            @endforeach
        </p>
    @endif
</div>

<form method="GET" action="{{ route('cuadre_caja.index') }}" class="nomina-card gerencial-filtros" style="margin-bottom:14px;">
    <div class="nomina-form-grid">
        <div class="field">
            <label for="cc-desde">Desde</label>
            <input id="cc-desde" type="date" name="desde" value="{{ $desde }}" required>
        </div>
        <div class="field">
            <label for="cc-hasta">Hasta</label>
            <input id="cc-hasta" type="date" name="hasta" value="{{ $hasta }}" required>
        </div>
        <div class="field">
            <label for="cc-sede">Sede</label>
            <select id="cc-sede" name="sede">
                <option value="">Todas</option>
                @foreach($sedes as $s)
                    <option value="{{ $s }}" @selected($sede === $s)>{{ $etiquetas[$s] ?? $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="cc-cajera">Cajera</label>
            <input id="cc-cajera" type="search" name="cajera" value="{{ $cajera }}" placeholder="Nombre o cédula">
        </div>
        <div class="field">
            <label for="cc-estado">Estado</label>
            <select id="cc-estado" name="estado">
                <option value="">Todos</option>
                @foreach($estados as $clave => $label)
                    <option value="{{ $clave }}" @selected($estado === $clave)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn" type="submit">Aplicar</button>
        </div>
    </div>
</form>

<div class="nomina-kpis" style="margin-bottom:14px;">
    <div class="nomina-kpi"><span>Días de caja</span><strong>{{ $totales['cierres'] }}</strong></div>
    <div class="nomina-kpi"><span>Por conciliar</span><strong style="color:{{ $totales['pendientes'] ? '#b45309' : 'inherit' }};">{{ $totales['pendientes'] }}</strong></div>
    <div class="nomina-kpi"><span>Ingresos en Suite</span><strong>{{ $dinero($totales['ingresos_usd']) }}</strong></div>
    <div class="nomina-kpi"><span>Faltantes</span><strong style="color:#b91c1c;">{{ $dinero($totales['faltantes_usd']) }}</strong></div>
    <div class="nomina-kpi"><span>Sobrantes</span><strong style="color:#15803d;">{{ $dinero($totales['sobrantes_usd']) }}</strong></div>
    <div class="nomina-kpi"><span>Diferencia neta</span><strong style="color:{{ $colorDif($totales['diferencia_usd']) }};">{{ $dinero($totales['diferencia_usd']) }}</strong></div>
</div>

<div class="panel">
    <div class="table-wrap" style="max-height:70vh;overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Sede</th>
                    <th>Caja</th>
                    <th>Cajera</th>
                    <th style="text-align:right;">Tasa</th>
                    <th style="text-align:right;">Ingresos Suite</th>
                    <th style="text-align:right;">Tesorería</th>
                    <th style="text-align:right;">Diferencia</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $fila)
                    @php $c = $fila['cierre']; @endphp
                    <tr>
                        <td style="white-space:nowrap;">{{ $c->fecha_cierre->format('d/m/Y') }}</td>
                        <td>{{ $etiquetas[$c->sede] ?? $c->sede }}</td>
                        <td>{{ $c->descripcion_caja ?: $c->codigo_caja }}</td>
                        <td>
                            <strong>{{ $c->usuario_nombre ?: $c->usuario }}</strong>
                            @if($c->usuario_nombre)
                                <div class="muted" style="font-size:.72rem;">{{ $c->usuario }}</div>
                            @endif
                        </td>
                        <td style="text-align:right;">{{ $tasa($c->factor_cambio) }}</td>
                        <td style="text-align:right;">{{ $dinero($fila['ingresos_usd']) }}</td>
                        <td style="text-align:right;">{{ $dinero($fila['tesoreria_usd']) }}</td>
                        <td style="text-align:right;font-weight:700;color:{{ $colorDif($fila['diferencia_usd']) }};">{{ $dinero($fila['diferencia_usd']) }}</td>
                        <td>
                            @if($c->conciliado())
                                <span style="background:#dcfce7;color:#166534;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:999px;">Conciliado</span>
                            @else
                                <span style="background:#fef3c7;color:#92400e;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:999px;">Pendiente</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            <a class="btn {{ $c->conciliado() || ! $puedeConciliar ? 'secondary' : 'primary' }}" style="padding:4px 10px;font-size:.78rem;"
                               href="{{ route('cuadre_caja.show', ['cuadre' => $c->id, 'volver' => $volver]) }}">
                                {{ $c->conciliado() || ! $puedeConciliar ? 'Ver' : 'Conciliar' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="muted" style="text-align:center;padding:24px;">
                            No hay días de caja con estos filtros. El sincronizador de cada sede sube los cobros del día anterior.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($filas)
                <tfoot>
                    <tr>
                        <th colspan="5">Total</th>
                        <th style="text-align:right;">{{ $dinero($totales['ingresos_usd']) }}</th>
                        <th style="text-align:right;">{{ $dinero($totales['tesoreria_usd']) }}</th>
                        <th style="text-align:right;color:{{ $colorDif($totales['diferencia_usd']) }};">{{ $dinero($totales['diferencia_usd']) }}</th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
