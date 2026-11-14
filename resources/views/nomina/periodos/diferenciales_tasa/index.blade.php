@extends('layouts.app')

@section('title', 'Diferenciales por tasa '.$periodo->etiqueta)

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.periodos.show', $periodo) }}" class="muted" style="font-size:.82rem;">← Quincena {{ $periodo->etiqueta }}</a>
            <h1 style="margin:4px 0 0;">Diferenciales de nómina por tasa</h1>
            <p class="muted" style="margin:4px 0 0;">Pago complementario en Bs cuando la quincena se pagó con una tasa menor a la que correspondía. Los montos en USD no cambian.</p>
        </div>
        <div>
            <a class="btn primary" href="{{ route('nomina.periodos.diferenciales_tasa.create', $periodo) }}">Nuevo diferencial</a>
        </div>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha de pago</th>
                    <th>Motivo</th>
                    <th>Tasa pagada</th>
                    <th>Tasa nueva</th>
                    <th>Personas</th>
                    <th>A pagar Bs</th>
                    <th>Creado por</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($diferenciales as $diferencial)
                    <tr>
                        <td>{{ $diferencial->fecha_pago?->format('d/m/Y') }}</td>
                        <td>{{ $diferencial->motivo }}</td>
                        <td>{{ number_format((float) $diferencial->tasa_pagada, 4, ',', '.') }}</td>
                        <td>{{ number_format((float) $diferencial->tasa_nueva, 4, ',', '.') }}</td>
                        <td>{{ $diferencial->lineas->count() }}</td>
                        <td><strong>Bs {{ number_format((float) $diferencial->lineas->sum(fn ($l) => $l->aPagarBs()), 2) }}</strong></td>
                        <td>
                            {{ $diferencial->creador?->name ?? '—' }}
                            <div class="muted" style="font-size:.72rem;">{{ $diferencial->created_at?->format('d/m/Y H:i') }}</div>
                        </td>
                        <td style="text-align:right;">
                            <a class="btn" href="{{ route('nomina.periodos.diferenciales_tasa.show', [$periodo, $diferencial]) }}">Ver y TXT</a>
                            <a class="btn" href="{{ route('nomina.periodos.diferenciales_tasa.reporte', [$periodo, $diferencial]) }}">PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Todavía no hay diferenciales por tasa en esta quincena.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
