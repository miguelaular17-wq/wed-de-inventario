@extends('layouts.app')

@section('title', 'Calcular nómina '.$periodo->etiqueta)

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.periodos.show', $periodo) }}" class="muted" style="font-size:.82rem;">← Quincena</a>
            <h1 style="margin:4px 0 0;">Calcular {{ $periodo->etiqueta }}</h1>
            <p class="muted" style="margin:4px 0 0;">Las cuotas de préstamo no se descuentan solas. Marca solo a quienes sí les vas a descontar en esta quincena.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('nomina.periodos.calcular', $periodo) }}">
        @csrf

        <div class="nomina-card" style="margin-top:16px;">
            <h3>Empleados con cuotas en esta quincena</h3>
            @if($candidatos->isEmpty())
                <p class="muted" style="margin-bottom:0;">Nadie tiene cuotas pendientes en este rango. La nómina se calculará sin descuentos de préstamo.</p>
            @else
                <p class="muted">Si no marcas a nadie, las cuotas quedan pendientes para un pago extra o para otra quincena.</p>
                <div style="display:flex;gap:8px;margin-bottom:12px;">
                    <button type="button" class="btn secondary" onclick="marcarCuotas(true)">Marcar todos</button>
                    <button type="button" class="btn secondary" onclick="marcarCuotas(false)">Ninguno</button>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:48px;">Descontar</th>
                                <th>Empleado</th>
                                <th>Cuotas</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($candidatos as $fila)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="cuota-empleado" name="descontar_empleado_ids[]" value="{{ $fila['empleado']->id }}">
                                    </td>
                                    <td>
                                        <strong>{{ $fila['empleado']->nombre() }}</strong>
                                        @if($fila['empleado']->cedula())
                                            <div class="muted" style="font-size:.75rem;">C.I. {{ $fila['empleado']->cedula() }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @foreach($fila['cuotas'] as $cuota)
                                            <div>
                                                #{{ $cuota->numero }}
                                                · {{ $cuota->fecha_programada?->format('d/m/Y') }}
                                                · ${{ number_format($cuota->saldo(), 2) }}
                                                <span class="muted">{{ $cuota->prestamo->motivo ?: 'Préstamo #'.$cuota->prestamo_id }}</span>
                                            </div>
                                        @endforeach
                                    </td>
                                    <td><strong>${{ number_format($fila['total'], 2) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div style="display:flex;gap:8px;margin-top:16px;">
            <a class="btn secondary" href="{{ route('nomina.periodos.show', $periodo) }}">Cancelar</a>
            <button class="btn primary" type="submit">Calcular nómina</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function marcarCuotas(valor) {
    document.querySelectorAll('.cuota-empleado').forEach((el) => { el.checked = valor; });
}
</script>
@endpush
