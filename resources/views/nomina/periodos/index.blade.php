@extends('layouts.app')

@section('title', 'Períodos de nómina')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Períodos de nómina</h1>
        </div>
    </div>

    <div class="nomina-kpis">
        @foreach($estados as $estado)
            <div class="nomina-kpi">
                <span>{{ ucfirst(strtolower($estado)) }}</span>
                <strong>{{ $periodos->where('estado', $estado)->count() }}</strong>
            </div>
        @endforeach
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Abrir quincena</h3>
        <form method="POST" action="{{ route('nomina.periodos.store') }}" class="nomina-inline-form">
            @csrf
            <div class="field">
                <label>Fecha dentro de la quincena</label>
                <input type="date" name="fecha" value="{{ old('fecha', $fechaSugerida) }}" required>
            </div>
            <div class="field" style="display:flex;align-items:flex-end;">
                <button class="btn primary" type="submit">Abrir quincena</button>
            </div>
        </form>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Quincena</th>
                    <th>Estado</th>
                    <th>Empleados</th>
                    <th>Total a pagar</th>
                    <th>Abierta</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($periodos as $periodo)
                    @php
                        $tag = match($periodo->estado) {
                            'CERRADO', 'PAGADO' => 'ok',
                            'ABIERTO', 'CALCULADO' => 'warn',
                            default => '',
                        };
                    @endphp
                    <tr>
                        <td><strong>{{ $periodo->etiqueta }}</strong></td>
                        <td><span class="tag {{ $tag }}">{{ $periodo->estado }}</span></td>
                        <td>{{ $periodo->registros_count }}</td>
                        <td>
                            {{ $periodo->registros_count
                                ? '$'.number_format($periodo->registros_sum_total_pagar ?? 0, 2)
                                : '—' }}
                        </td>
                        <td>{{ $periodo->created_at?->format('d/m/Y H:i') }}</td>
                        <td><a class="btn secondary" href="{{ route('nomina.periodos.show', $periodo) }}">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Todavía no hay períodos de nómina.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
