@extends('layouts.app')

@section('title', 'Calcular nómina '.$periodo->etiqueta)

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.periodos.show', $periodo) }}" class="muted" style="font-size:.82rem;">← Quincena</a>
            <h1 style="margin:4px 0 0;">Calcular {{ $periodo->etiqueta }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                Se congelan sueldos, adelantos, faltas y lo que ya armaste en
                <a href="{{ route('nomina.prestamos.index') }}">Préstamos</a>.
                Aquí no se marcan cuotas.
            </p>
        </div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Préstamos de esta quincena</h3>
        @php $pendientes = ($planes ?? collect())->where('estado', 'PENDIENTE'); @endphp
        @if($pendientes->isEmpty())
            <p class="muted" style="margin-bottom:0;">
                No hay cuotas programadas. Nadie se le descuenta préstamo al calcular.
                Si hace falta, márcalas en <a href="{{ route('nomina.prestamos.index') }}">Préstamos</a> y vuelve.
            </p>
        @else
            <p class="muted">Esto es lo que se va a descontar. Para cambiarlo, ve al escritorio de préstamos.</p>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Cuota</th>
                            <th>Monto</th>
                            <th>Se descuenta de</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendientes as $plan)
                            <tr>
                                <td>
                                    <strong>{{ $plan->empleado?->nombre() ?? '—' }}</strong>
                                    @if($plan->empleado?->cedula())
                                        <div class="muted" style="font-size:.75rem;">C.I. {{ $plan->empleado->cedula() }}</div>
                                    @endif
                                </td>
                                <td>
                                    #{{ $plan->cuota?->numero ?? $plan->cuota_id }}
                                    @if($plan->cuota?->prestamo?->motivo)
                                        <span class="muted">{{ $plan->cuota->prestamo->motivo }}</span>
                                    @endif
                                </td>
                                <td>${{ number_format((float) $plan->monto, 2) }}</td>
                                <td>{{ $plan->etiquetaDestino() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('nomina.periodos.calcular', $periodo) }}" style="margin-top:16px;display:flex;gap:8px;">
        @csrf
        <a class="btn secondary" href="{{ route('nomina.periodos.show', $periodo) }}">Cancelar</a>
        <a class="btn" href="{{ route('nomina.prestamos.index') }}">Ir a préstamos</a>
        <button class="btn primary" type="submit">Calcular nómina</button>
    </form>
</div>
@endsection
