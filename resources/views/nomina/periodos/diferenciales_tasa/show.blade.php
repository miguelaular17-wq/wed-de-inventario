@extends('layouts.app')

@section('title', 'Diferencial por tasa '.$periodo->etiqueta)

@section('content')
@php
    $tasaPagada = (float) $diferencial->tasa_pagada;
    $tasaNueva = (float) $diferencial->tasa_nueva;
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.periodos.diferenciales_tasa.index', $periodo) }}" class="muted" style="font-size:.82rem;">← Diferenciales por tasa {{ $periodo->etiqueta }}</a>
            <h1 style="margin:4px 0 0;">Diferencial de nómina por tasa</h1>
            <p class="muted" style="margin:4px 0 0;">
                {{ $diferencial->motivo }} · Pago el {{ $diferencial->fecha_pago?->format('d/m/Y') }}
                · Tasa pagada {{ number_format($tasaPagada, 4, ',', '.') }} → tasa nueva {{ number_format($tasaNueva, 4, ',', '.') }}
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;align-items:flex-start;">
            <a class="btn primary" href="{{ route('nomina.periodos.diferenciales_tasa.reporte', [$periodo, $diferencial]) }}">Descargar reporte PDF</a>
            <a class="btn" href="{{ route('nomina.periodos.diferenciales_tasa.reporte', [$periodo, $diferencial, 'formato' => 'xlsx']) }}">Descargar Excel</a>
            <form method="POST" action="{{ route('nomina.periodos.diferenciales_tasa.destroy', [$periodo, $diferencial]) }}" onsubmit="return confirm('¿Eliminar este diferencial? Los TXT ya descargados no se anulan en el banco.')">
                @csrf
                @method('DELETE')
                <button class="btn secondary" type="submit">Eliminar</button>
            </form>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Personas</span><strong>{{ $lineas->count() }}</strong></div>
        <div class="nomina-kpi"><span>Ya se les pagó la diferencia</span><strong>{{ $lineas->where('no_pagado', false)->count() }}</strong></div>
        <div class="nomina-kpi"><span>Nómina USD</span><strong>${{ number_format((float) $lineas->sum('total_usd'), 2) }}</strong></div>
        <div class="nomina-kpi"><span>Diferencia de tasa</span><strong>{{ number_format($tasaNueva - $tasaPagada, 4, ',', '.') }}</strong></div>
        <div class="nomina-kpi"><span>A pagar Bs</span><strong>Bs {{ number_format((float) $lineas->sum(fn ($l) => $l->aPagarBs()), 2) }}</strong></div>
    </div>

    <h3 style="margin:20px 0 0;">Personas del diferencial</h3>
    @include('nomina.periodos.diferenciales_tasa._tabla', ['filas' => $lineas, 'tasaPagada' => $tasaPagada, 'tasaNueva' => $tasaNueva])

    <h3 style="margin:24px 0 0;">TXT del banco</h3>
    <p class="muted" style="margin:4px 0 8px;">Paga en Bs la diferencia de quienes no la tenían cancelada, con la fecha de pago de este diferencial.</p>
    <table class="data-table">
        <thead><tr><th>Empresa</th><th>Empleados</th><th>Nómina USD</th><th>Diferencia Bs</th><th></th></tr></thead>
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
                        <td>Bs {{ number_format($fila->bs, 2) }}</td>
                        <td style="text-align:right;">
                            <a class="btn primary" href="{{ route('nomina.periodos.diferenciales_tasa.banco', [$periodo, $diferencial, $fila->empresa]) }}">Descargar TXT</a>
                        </td>
                    </tr>
                @else
                    <tr>
                        <td colspan="5"><strong>Sin empresa</strong> · {{ $fila->empleados }} persona(s) · Bs {{ number_format($fila->bs, 2) }}</td>
                    </tr>
                    @foreach($fila->personas as $persona)
                        <tr>
                            <td style="padding-left:24px;">
                                <a href="{{ route('nomina.empleados.show', $persona->id) }}">{{ $persona->nombre }}</a>
                                <span class="muted">· {{ $persona->cedula !== '' ? $persona->cedula : 'Sin cédula' }}</span>
                            </td>
                            <td>1</td>
                            <td></td>
                            <td>Bs {{ number_format($persona->bs, 2) }}</td>
                            <td class="muted">No se descarga</td>
                        </tr>
                    @endforeach
                @endif
            @empty
                <tr><td colspan="5" class="muted">No hay diferencias a pagar.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
