@extends('layouts.app')

@section('title', 'Calcular nómina '.$periodo->etiqueta)

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.periodos.show', $periodo) }}" class="muted" style="font-size:.82rem;">← Quincena</a>
            <h1 style="margin:4px 0 0;">Calcular {{ $periodo->etiqueta }}</h1>
            <p class="muted" style="margin:4px 0 0;">Marca la cuota (o varias) de cada empleado y, si hace falta, escribe un monto parcial. Cada descuento se registra en el préstamo al calcular.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('nomina.periodos.calcular', $periodo) }}" id="form-calcular-nomina">
        @csrf

        <div class="nomina-card" style="margin-top:16px;">
            <h3>Empleados con cuotas en esta quincena</h3>
            @if($candidatos->isEmpty())
                <p class="muted" style="margin-bottom:0;">Nadie tiene cuotas pendientes en este rango. La nómina se calculará sin descuentos de préstamo.</p>
            @else
                <p class="muted">Si no marcas nada, las cuotas quedan para un pago extra o para otra quincena. Un parcial deja el resto en el préstamo.</p>
                <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                    <button type="button" class="btn secondary" onclick="marcarCuotas(true)">Marcar todos</button>
                    <button type="button" class="btn secondary" onclick="marcarCuotas(false)">Ninguno</button>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:72px;">Descontar</th>
                                <th>Empleado</th>
                                <th>Cuotas</th>
                                <th>A descontar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($candidatos as $fila)
                                <tr data-empleado-id="{{ $fila['empleado']->id }}">
                                    <td>
                                        <button type="button" class="btn secondary" style="padding:4px 8px;font-size:.75rem;" onclick="marcarEmpleado({{ $fila['empleado']->id }}, true)">Todas</button>
                                    </td>
                                    <td>
                                        <strong>{{ $fila['empleado']->nombre() }}</strong>
                                        @if($fila['empleado']->cedula())
                                            <div class="muted" style="font-size:.75rem;">C.I. {{ $fila['empleado']->cedula() }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @foreach($fila['cuotas'] as $cuota)
                                            @php $saldo = $cuota->saldo(); @endphp
                                            <div class="cuota-linea" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:4px 0;">
                                                <label style="display:flex;align-items:center;gap:6px;margin:0;">
                                                    <input type="checkbox"
                                                        class="cuota-check"
                                                        name="descuentos[{{ $cuota->id }}][aplicar]"
                                                        value="1"
                                                        data-empleado="{{ $fila['empleado']->id }}"
                                                        data-nombre="{{ $fila['empleado']->nombre() }}"
                                                        data-cuota="{{ $cuota->numero }}"
                                                        data-motivo="{{ $cuota->prestamo->motivo ?: 'Préstamo #'.$cuota->prestamo_id }}"
                                                        data-max="{{ number_format($saldo, 2, '.', '') }}"
                                                        onchange="sincronizarCuota(this)">
                                                    <input type="hidden" name="descuentos[{{ $cuota->id }}][cuota_id]" value="{{ $cuota->id }}">
                                                    <span>
                                                        #{{ $cuota->numero }}
                                                        · {{ $cuota->fecha_programada?->format('d/m/Y') }}
                                                        · saldo ${{ number_format($saldo, 2) }}
                                                        <span class="muted">{{ $cuota->prestamo->motivo ?: 'Préstamo #'.$cuota->prestamo_id }}</span>
                                                    </span>
                                                </label>
                                                <span class="muted" style="font-size:.75rem;">Parcial $</span>
                                                <input type="number"
                                                    class="cuota-monto"
                                                    name="descuentos[{{ $cuota->id }}][monto]"
                                                    min="0.01"
                                                    max="{{ number_format($saldo, 2, '.', '') }}"
                                                    step="0.01"
                                                    value="{{ number_format($saldo, 2, '.', '') }}"
                                                    data-empleado="{{ $fila['empleado']->id }}"
                                                    disabled
                                                    style="width:110px;"
                                                    oninput="actualizarRegistro()">
                                            </div>
                                        @endforeach
                                    </td>
                                    <td>
                                        <strong class="total-empleado" data-empleado="{{ $fila['empleado']->id }}">$0.00</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="nomina-card" style="margin-top:12px;background:#f8fafc;">
                    <h3 style="margin-top:0;">Registro de esta quincena</h3>
                    <p class="muted" style="margin-top:0;">Se va armando al marcar cuotas o cambiar el parcial.</p>
                    <ul id="registro-descuentos" style="margin:0;padding-left:18px;"></ul>
                    <p style="margin:10px 0 0;"><strong>Total a descontar: <span id="registro-total">$0.00</span></strong></p>
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
    document.querySelectorAll('.cuota-check').forEach((el) => {
        el.checked = valor;
        sincronizarCuota(el);
    });
}
function marcarEmpleado(empleadoId, valor) {
    document.querySelectorAll('.cuota-check[data-empleado="'+empleadoId+'"]').forEach((el) => {
        el.checked = valor;
        sincronizarCuota(el);
    });
}
function sincronizarCuota(check) {
    const monto = check.closest('.cuota-linea')?.querySelector('.cuota-monto');
    if (monto) {
        monto.disabled = !check.checked;
        if (check.checked && (!monto.value || Number(monto.value) <= 0)) {
            monto.value = monto.max;
        }
    }
    actualizarRegistro();
}
function actualizarRegistro() {
    const items = [];
    let total = 0;
    const porEmpleado = {};
    document.querySelectorAll('.cuota-check:checked').forEach((el) => {
        const montoEl = el.closest('.cuota-linea')?.querySelector('.cuota-monto');
        const max = Number(el.dataset.max || 0);
        let monto = Number(montoEl?.value || max);
        if (monto > max) monto = max;
        if (monto <= 0) return;
        total += monto;
        const emp = el.dataset.empleado;
        porEmpleado[emp] = (porEmpleado[emp] || 0) + monto;
        const esParcial = Math.abs(monto - max) > 0.009;
        items.push(
            (el.dataset.nombre || 'Empleado')
            + ' · cuota #' + (el.dataset.cuota || '')
            + ' · ' + (el.dataset.motivo || '')
            + ' · $' + monto.toFixed(2)
            + (esParcial ? ' (parcial, saldo $' + max.toFixed(2) + ')' : '')
        );
    });
    const ul = document.getElementById('registro-descuentos');
    if (ul) {
        ul.innerHTML = items.length
            ? items.map((t) => '<li>' + t + '</li>').join('')
            : '<li class="muted">Aún no hay cuotas marcadas.</li>';
    }
    const tot = document.getElementById('registro-total');
    if (tot) tot.textContent = '$' + total.toFixed(2);
    document.querySelectorAll('.total-empleado').forEach((cel) => {
        const n = porEmpleado[cel.dataset.empleado] || 0;
        cel.textContent = '$' + n.toFixed(2);
    });
}
document.addEventListener('DOMContentLoaded', actualizarRegistro);
</script>
@endpush
