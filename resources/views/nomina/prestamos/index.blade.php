@extends('layouts.app')

@section('title', 'Préstamos')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Préstamos</h1>
            <p class="muted" style="margin:4px 0 0;">
                Quincena {{ $quincena['etiqueta'] }}. Marca quién debe y cuánto se descuenta ahora. Si ganan comisión, eliges de dónde sale; si no, va a esta nómina. Queda en la ficha y se usa al calcular.
            </p>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Deudores</span><strong>{{ $kpis['deudores'] }}</strong></div>
        <div class="nomina-kpi"><span>Saldo vivo</span><strong>${{ number_format($kpis['saldo'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Esta quincena</span><strong>${{ number_format($kpis['programado'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>A nómina</span><strong>${{ number_format($kpis['nomina'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>A comisión</span><strong>${{ number_format($kpis['comision'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Pendiente global</span><strong>${{ number_format($kpisGlobales['total_pendiente'], 2) }}</strong></div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field field-wide">
            <label>Buscar</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nombre o cédula" autofocus>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Buscar</button>
        </div>
    </form>

    <form method="POST" action="{{ route('nomina.prestamos.programar') }}" id="form-prestamos-quincena" style="margin-top:16px;">
        @csrf
        <input type="hidden" name="q" value="{{ $q }}">

        <div class="nomina-card">
            <h3>Quién debe</h3>
            @if($deudores->isEmpty())
                <p class="muted" style="margin-bottom:0;">Nadie tiene cuotas pendientes{{ $q !== '' ? ' que coincidan con “'.$q.'”' : '' }}.</p>
            @else
                <p class="muted">Si no marcas, la cuota no entra en esta quincena. Un parcial deja el resto en el préstamo. La barra nómina/comisión solo aparece cuando esa persona genera comisión.</p>
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
                            @foreach($deudores as $fila)
                                @php $empleado = $fila['empleado']; @endphp
                                <tr data-empleado-id="{{ $empleado->id }}">
                                    <td>
                                        <button type="button" class="btn secondary" style="padding:4px 8px;font-size:.75rem;" onclick="marcarEmpleado({{ $empleado->id }}, true)">Todas</button>
                                    </td>
                                    <td>
                                        <a href="{{ route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'prestamos']) }}">
                                            <strong>{{ $empleado->nombre() }}</strong>
                                        </a>
                                        @if($empleado->cedula())
                                            <div class="muted" style="font-size:.75rem;">C.I. {{ $empleado->cedula() }}</div>
                                        @endif
                                        <div class="muted" style="font-size:.75rem;">Saldo ${{ number_format($fila['saldo'], 2) }}</div>
                                    </td>
                                    <td class="cuotas-cell">
                                        @foreach($fila['cuotas'] as $cuota)
                                            @include('nomina.partials.cuota-descuento-linea', [
                                                'cuota' => $cuota,
                                                'empleado' => $empleado,
                                                'plan' => $planes->get($cuota->id),
                                            ])
                                        @endforeach
                                    </td>
                                    <td>
                                        <strong class="total-empleado" data-empleado="{{ $empleado->id }}">$0.00</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="nomina-card" style="margin-top:12px;background:#f8fafc;">
                    <h3 style="margin-top:0;">Esta quincena</h3>
                    <ul id="registro-descuentos" style="margin:0;padding-left:18px;"></ul>
                    <p style="margin:10px 0 0;"><strong>Total: <span id="registro-total">$0.00</span></strong></p>
                </div>
                <div style="margin-top:16px;">
                    <button class="btn primary" type="submit">Guardar para esta quincena</button>
                </div>
            @endif
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
    const linea = check.closest('.cuota-linea');
    const monto = linea?.querySelector('.cuota-monto');
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
        const linea = el.closest('.cuota-linea');
        const montoEl = linea?.querySelector('.cuota-monto');
        const destEl = linea?.querySelector('.cuota-destino');
        const max = Number(el.dataset.max || 0);
        let monto = Number(montoEl?.value || max);
        if (monto > max) monto = max;
        if (monto <= 0) return;
        total += monto;
        const emp = el.dataset.empleado;
        porEmpleado[emp] = (porEmpleado[emp] || 0) + monto;
        const dest = destEl
            ? (destEl.value === 'COMISION' ? ' · comisión' : ' · nómina')
            : '';
        items.push(
            (el.dataset.nombre || 'Empleado')
            + ' · cuota #' + (el.dataset.cuota || '')
            + ' · ' + (el.dataset.motivo || '')
            + ' · $' + monto.toFixed(2)
            + dest
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
