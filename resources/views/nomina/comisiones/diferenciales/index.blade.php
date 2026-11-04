@extends('layouts.app')

@section('title', 'Diferenciales de comisión '.$periodo->etiqueta)

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.comisiones.show', $periodo) }}" class="muted" style="font-size:.82rem;">← Comisiones {{ $periodo->etiqueta }}</a>
            <h1 style="margin:4px 0 0;">Diferenciales de comisión</h1>
            <p class="muted" style="margin:4px 0 0;">Pagos complementarios de la quincena para quienes no cobraron o se les pagó con otro porcentaje.</p>
        </div>
        <div>
            <a class="btn primary" href="{{ route('nomina.comisiones.diferenciales.create', $periodo) }}">Nuevo diferencial</a>
        </div>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha de pago</th>
                    <th>Motivo</th>
                    <th>Personas</th>
                    <th>No se les pagó</th>
                    <th>Diferencia a pagar</th>
                    <th>Creado por</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($diferenciales as $diferencial)
                    @php
                        $noPagados = $diferencial->lineas->where('no_pagado', true);
                        $aPagar = (float) $diferencial->lineas->filter(fn ($l) => (float) $l->diferencia > 0)->sum('diferencia');
                    @endphp
                    <tr>
                        <td>{{ $diferencial->fecha_pago?->format('d/m/Y') }}</td>
                        <td>{{ $diferencial->motivo }}</td>
                        <td>{{ $diferencial->lineas->count() }}</td>
                        <td>
                            {{ $noPagados->count() }}
                            @if($noPagados->isNotEmpty())
                                <div class="muted" style="font-size:.72rem;">
                                    {{ $noPagados->map(fn ($l) => $l->empleado?->nombre())->filter()->take(4)->implode(', ') }}{{ $noPagados->count() > 4 ? '…' : '' }}
                                </div>
                            @endif
                        </td>
                        <td><strong>${{ number_format($aPagar, 2) }}</strong></td>
                        <td>
                            {{ $diferencial->creador?->name ?? '—' }}
                            <div class="muted" style="font-size:.72rem;">{{ $diferencial->created_at?->format('d/m/Y H:i') }}</div>
                        </td>
                        <td style="text-align:right;">
                            <a class="btn" href="{{ route('nomina.comisiones.diferenciales.show', [$periodo, $diferencial]) }}">Ver y TXT</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Todavía no hay diferenciales en esta quincena.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
