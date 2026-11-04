@extends('layouts.app')

@section('title', 'Diferencial de comisión '.$periodo->etiqueta)

@section('content')
@php
    $porcentajes = \App\Services\Nomina\CommissionDifferentialService::PORCENTAJES;
    $tasa = (float) $diferencial->tasa_bcv;
    $aPagar = (float) $lineas->filter(fn ($l) => (float) $l->diferencia > 0)->sum('diferencia');
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.comisiones.diferenciales.index', $periodo) }}" class="muted" style="font-size:.82rem;">← Diferenciales {{ $periodo->etiqueta }}</a>
            <h1 style="margin:4px 0 0;">Diferencial de comisión</h1>
            <p class="muted" style="margin:4px 0 0;">
                {{ $diferencial->motivo }} · Pago el {{ $diferencial->fecha_pago?->format('d/m/Y') }} · Tasa BCV {{ number_format($tasa, 2) }}
            </p>
        </div>
        <div>
            <form method="POST" action="{{ route('nomina.comisiones.diferenciales.destroy', [$periodo, $diferencial]) }}" onsubmit="return confirm('¿Eliminar este diferencial? Los TXT ya descargados no se anulan en el banco.')">
                @csrf
                @method('DELETE')
                <button class="btn secondary" type="submit">Eliminar</button>
            </form>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Personas</span><strong>{{ $lineas->count() }}</strong></div>
        <div class="nomina-kpi warn"><span>No se les pagó</span><strong>{{ $lineas->where('no_pagado', true)->count() }}</strong></div>
        <div class="nomina-kpi"><span>Diferencia USD</span><strong>${{ number_format($aPagar, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Diferencia Bs</span><strong>Bs {{ number_format($aPagar * $tasa, 2) }}</strong></div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Porcentajes usados</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @foreach($porcentajes as $clave => [$etiqueta, $default])
                @php
                    $usado = (float) ($diferencial->config[$clave] ?? $configActual[$clave]);
                    $cambiado = abs($usado - (float) $configActual[$clave]) > 0.00001;
                @endphp
                <span style="font-size:.8rem;padding:4px 8px;border-radius:999px;border:1px solid {{ $cambiado ? '#f59e0b' : '#e2e8f0' }};background:{{ $cambiado ? '#fffbeb' : '#f8fafc' }};">
                    {{ $etiqueta }}: <strong>{{ rtrim(rtrim(number_format($usado, 4, ',', '.'), '0'), ',') }}</strong>
                    @if($cambiado)
                        <span class="muted">(config. {{ rtrim(rtrim(number_format((float) $configActual[$clave], 4, ',', '.'), '0'), ',') }})</span>
                    @endif
                </span>
            @endforeach
        </div>
    </div>

    <h3 style="margin:20px 0 0;">Personas del diferencial</h3>
    @include('nomina.comisiones.diferenciales._tabla', ['filas' => $lineas, 'tasa' => $tasa])

    <h3 style="margin:24px 0 0;">TXT del banco</h3>
    <p class="muted" style="margin:4px 0 8px;">Solo incluye las diferencias positivas, con la tasa y la fecha de pago de este diferencial.</p>
    <table class="data-table">
        <thead><tr><th>Empresa</th><th>Empleados</th><th>Diferencia USD</th><th>Diferencia Bs</th><th></th></tr></thead>
        <tbody>
            @forelse($bancoPorEmpresa as $fila)
                @if($fila->empresa)
                    <tr>
                        <td>
                            <strong>{{ $fila->empresa->codigo }}</strong>
                            <div class="muted" style="font-size:.78rem;">{{ $fila->empresa->nombre }}</div>
                        </td>
                        <td>{{ $fila->empleados }}</td>
                        <td>${{ number_format($fila->usd, 2) }}</td>
                        <td>Bs {{ number_format($fila->personas->sum(fn ($p) => round($p->usd * $tasa, 2)), 2) }}</td>
                        <td style="text-align:right;">
                            <a class="btn primary" href="{{ route('nomina.comisiones.diferenciales.banco', [$periodo, $diferencial, $fila->empresa]) }}">Descargar TXT</a>
                        </td>
                    </tr>
                @else
                    <tr>
                        <td colspan="5"><strong>Sin empresa</strong> · {{ $fila->empleados }} persona(s) · ${{ number_format($fila->usd, 2) }}</td>
                    </tr>
                    @foreach($fila->personas as $persona)
                        <tr>
                            <td style="padding-left:24px;">
                                <a href="{{ route('nomina.empleados.show', ['empleado' => $persona->id, 'tab' => 'comisiones']) }}">{{ $persona->nombre }}</a>
                                <span class="muted">· {{ $persona->cedula !== '' ? $persona->cedula : 'Sin cédula' }}</span>
                            </td>
                            <td>1</td>
                            <td>${{ number_format($persona->usd, 2) }}</td>
                            <td>Bs {{ number_format($persona->usd * $tasa, 2) }}</td>
                            <td class="muted">No se descarga</td>
                        </tr>
                    @endforeach
                @endif
            @empty
                <tr><td colspan="5" class="muted">No hay diferencias positivas a pagar.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
