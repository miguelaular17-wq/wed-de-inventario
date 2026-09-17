@extends('layouts.app')

@section('title', 'Faltante de caja')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Faltante de caja</h1>
            <p class="muted" style="margin:4px 0 0;">
                Quincena {{ $quincena['etiqueta'] }}. Cada registro se suma a la cuenta de la persona; tú eliges cuánto descontar en comisión.
            </p>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Cajeras / supervisores</span><strong>{{ $kpis['cajeras'] }}</strong></div>
        <div class="nomina-kpi"><span>En cuentas</span><strong>${{ number_format($kpis['por_decidir'] ?? 0, 2) }}</strong></div>
        <div class="nomina-kpi"><span>A descontar</span><strong>${{ number_format($kpis['pendiente'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Del día</span><strong>${{ number_format($kpis['del_dia'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Personas hoy</span><strong>{{ $kpis['personas_hoy'] }}</strong></div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Fecha del día</label>
            <input type="date" name="fecha" value="{{ $fecha }}">
        </div>
        <div class="field field-wide">
            <label>Buscar cajera / supervisor</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nombre o cédula">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;gap:8px;">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
    <div style="margin-top:10px;">
        @include('nomina.partials.excel-quincena', ['excelRoute' => route('nomina.faltante_caja.excel'), 'fecha' => $fecha])
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Cuentas por cajera / supervisor</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cédula</th>
                    <th>Sede</th>
                    <th>Cuenta</th>
                    <th>A descontar</th>
                    <th>Registrar (suma a cuenta)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cajeras as $empleado)
                    @php
                        $cuenta = $cuentasPorEmpleado[$empleado->id] ?? ['cuenta' => 0, 'a_descontar' => 0];
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('nomina.empleados.show', $empleado) }}">
                                <strong>{{ $empleado->nombre() }}</strong>
                            </a>
                            <div class="muted" style="font-size:.75rem;">
                                {{ $empleado->nombreCargo() }}
                                @if($empleado->generaComision())
                                    · con comisión
                                @else
                                    · sin comisión (si descuentas, va a nómina)
                                @endif
                            </div>
                        </td>
                        <td>{{ $empleado->cedula() ?: '—' }}</td>
                        <td>{{ $empleado->nombreSede() }}</td>
                        <td><strong>${{ number_format($cuenta['cuenta'], 2) }}</strong></td>
                        <td>${{ number_format($cuenta['a_descontar'], 2) }}</td>
                        <td>
                            <form method="POST" action="{{ route('nomina.faltante_caja.store') }}" class="nomina-inline-form" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                @csrf
                                <input type="hidden" name="empleado_id" value="{{ $empleado->id }}">
                                <input type="hidden" name="fecha" value="{{ $fecha }}">
                                <input type="hidden" name="q" value="{{ $q }}">
                                <input type="number" step="0.01" min="0.01" name="monto" placeholder="Monto" required style="width:95px;">
                                <input name="motivo" placeholder="Detalle" style="min-width:110px;flex:1;">
                                <button class="btn primary" type="submit">Sumar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">
                            @if($q !== '')
                                Nadie coincide con “{{ $q }}”.
                            @else
                                No hay empleados activos con cargo de cajero/cajera o supervisor.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <h3>Historial del {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</h3>
        <p class="muted" style="margin:0 0 10px;font-size:.85rem;">Totales por persona. Haz clic en el total para ver el detalle de cada registro.</p>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cédula</th>
                    <th>Sede</th>
                    <th>Total</th>
                    <th>En cuenta</th>
                    <th>A descontar</th>
                    <th>No descontar</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($historialDia as $grupo)
                    @php $uid = 'faltante-det-'.$grupo['empleado_id']; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('nomina.empleados.show', $grupo['empleado_id']) }}">
                                <strong>{{ $grupo['empleado']?->nombre() ?? '—' }}</strong>
                            </a>
                        </td>
                        <td>{{ $grupo['empleado']?->cedula() ?: '—' }}</td>
                        <td>{{ $grupo['empleado']?->nombreSede() ?: '—' }}</td>
                        <td>
                            <button
                                type="button"
                                class="btn secondary faltante-toggle-detalle"
                                data-target="{{ $uid }}"
                                style="padding:4px 10px;font-weight:700;"
                                title="Ver por qué / detalle"
                            >${{ number_format($grupo['total'], 2) }}</button>
                        </td>
                        <td>${{ number_format($grupo['en_cuenta'], 2) }}</td>
                        <td>${{ number_format($grupo['a_descontar'], 2) }}</td>
                        <td>${{ number_format($grupo['no_descontar'], 2) }}</td>
                        <td>
                            <button type="button" class="btn secondary faltante-toggle-detalle" data-target="{{ $uid }}">Ver detalle</button>
                        </td>
                    </tr>
                    <tr id="{{ $uid }}" class="faltante-detalle-row" style="display:none;background:#f8fafc;">
                        <td colspan="8" style="padding:12px 16px;">
                            <div style="font-size:.82rem;font-weight:600;margin-bottom:8px;color:#475569;">Detalle / por qué</div>
                            <table class="data-table" style="margin:0;">
                                <thead>
                                    <tr>
                                        <th>Monto</th>
                                        <th>Decisión</th>
                                        <th>Estado</th>
                                        <th>Usuario</th>
                                        <th>Detalle</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($grupo['lineas'] as $item)
                                        <tr>
                                            <td>${{ number_format($item->monto, 2) }}</td>
                                            <td>{{ $item->etiquetaDecision() }}</td>
                                            <td>{{ $item->estado }}</td>
                                            <td>{{ $item->creador?->name ?: '—' }}</td>
                                            <td>{{ $item->motivo ?: '—' }}</td>
                                            <td>
                                                @if($item->estado === 'PENDIENTE' && in_array($item->decision, ['PENDIENTE', null], true))
                                                    <form method="POST" action="{{ route('nomina.faltante_caja.cancelar', $item) }}" onsubmit="return confirm('¿Cancelar este registro de la cuenta?')">
                                                        @csrf
                                                        <input type="hidden" name="q" value="{{ $q }}">
                                                        <button class="btn secondary" type="submit">Cancelar</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Sin historial en esta fecha.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @php
        $conCuenta = $cajeras->filter(function ($e) use ($cuentasPorEmpleado) {
            return (($cuentasPorEmpleado[$e->id]['cuenta'] ?? 0) > 0);
        });
    @endphp
    <div class="nomina-card" style="margin-top:16px;">
        <h3 style="margin-top:0;">Decidir descuento</h3>
        <p class="muted" style="margin:0 0 12px;font-size:.88rem;">
            Elige la persona, cuánto sacar de su cuenta y si se descuenta de nómina o comisión (como préstamos).
        </p>
        @if($conCuenta->isEmpty())
            <p class="muted" style="margin:0;">Nadie tiene saldo en cuenta por ahora. Registra faltantes arriba.</p>
        @else
            <form method="POST" action="{{ route('nomina.faltante_caja.cuenta') }}" class="filter-bar" style="flex-wrap:wrap;align-items:flex-end;" id="form-decidir-faltante">
                @csrf
                <input type="hidden" name="fecha" value="{{ $fecha }}">
                <input type="hidden" name="q" value="{{ $q }}">
                <div class="field field-wide">
                    <label>Empleado</label>
                    <select name="empleado_id" id="faltante-empleado" required>
                        <option value="">Seleccionar…</option>
                        @foreach($conCuenta as $empleado)
                            @php $saldo = $cuentasPorEmpleado[$empleado->id]['cuenta'] ?? 0; @endphp
                            <option
                                value="{{ $empleado->id }}"
                                data-saldo="{{ $saldo }}"
                                data-comision="{{ $empleado->generaComision() ? '1' : '0' }}"
                            >
                                {{ $empleado->nombre() }} — cuenta ${{ number_format($saldo, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Monto a decidir</label>
                    <input type="number" step="0.01" min="0.01" name="monto" id="faltante-monto" placeholder="0.00" required style="width:120px;">
                </div>
                <div class="field">
                    <label>Fecha del descuento</label>
                    <input type="date" name="fecha_descuento" value="{{ $fecha }}" required>
                </div>
                <div class="field">
                    <label>Acción</label>
                    <select name="accion" id="faltante-accion" required>
                        <option value="NOMINA">Descontar de nómina (esta quincena)</option>
                        <option value="COMISION" id="faltante-opcion-comision">Descontar de comisión (esta quincena)</option>
                        <option value="NO_DESCONTAR">No descontar</option>
                    </select>
                </div>
                <div class="field field-wide">
                    <label>Nota (opcional)</label>
                    <input name="motivo" placeholder="Detalle de la decisión">
                </div>
                <div class="field" style="display:flex;align-items:flex-end;">
                    <button class="btn primary" type="submit">Confirmar</button>
                </div>
            </form>
            <p class="muted" style="margin:8px 0 0;font-size:.8rem;" id="faltante-saldo-hint"></p>
        @endif
    </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const sel = document.getElementById('faltante-empleado');
    const monto = document.getElementById('faltante-monto');
    const hint = document.getElementById('faltante-saldo-hint');
    const accion = document.getElementById('faltante-accion');
    const opcionComision = document.getElementById('faltante-opcion-comision');
    if (sel && monto) {
        const sync = () => {
            const opt = sel.options[sel.selectedIndex];
            const saldo = parseFloat(opt?.dataset?.saldo || '0') || 0;
            const conComision = (opt?.dataset?.comision || '0') === '1';
            monto.max = saldo > 0 ? String(saldo) : '';
            if (opcionComision) {
                opcionComision.disabled = !conComision;
                opcionComision.hidden = !conComision;
                if (!conComision && accion && accion.value === 'COMISION') {
                    accion.value = 'NOMINA';
                }
            }
            if (hint) {
                hint.textContent = sel.value
                    ? ('Saldo disponible en cuenta: $' + saldo.toFixed(2) + '. Puedes descontar parcial'
                        + (conComision ? ' de nómina o comisión.' : ' de nómina.'))
                    : '';
            }
        };
        sel.addEventListener('change', sync);
        sync();
    }

    document.querySelectorAll('.faltante-toggle-detalle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const row = document.getElementById(btn.getAttribute('data-target'));
            if (!row) return;
            row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
        });
    });
})();
</script>
@endpush
