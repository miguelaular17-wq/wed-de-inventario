@extends('layouts.app')

@section('title', 'Comisiones')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Liquidación de comisiones</h1>
            <p class="muted" style="margin:4px 0 0;">Se pagan 3 días después de cerrar cada quincena. No forman parte del recibo de sueldo.</p>
        </div>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Quincena</th>
                    <th>Estado nómina</th>
                    <th>Pago comisión</th>
                    <th>Empleados</th>
                    <th>Comisión bruta</th>
                    <th>A pagar</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($periodos as $periodo)
                    <tr>
                        <td>{{ $periodo->etiqueta }}</td>
                        <td>{{ $periodo->estado }}</td>
                        <td>{{ $periodo->fecha_pago_comision?->format('d/m/Y') ?: $periodo->fecha_fin?->copy()->addDays(3)->format('d/m/Y') }}</td>
                        <td>{{ $periodo->liquidaciones_comision_count }}</td>
                        <td>${{ number_format($periodo->liquidaciones_comision_sum_comision_total ?? 0, 2) }}</td>
                        <td><strong>${{ number_format($periodo->liquidaciones_comision_sum_total_pagar ?? 0, 2) }}</strong></td>
                        <td><a class="btn secondary" href="{{ route('nomina.comisiones.show', $periodo) }}">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Todavía no hay quincenas. Ábrelas desde Períodos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
