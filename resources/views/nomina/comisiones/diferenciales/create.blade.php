@extends('layouts.app')

@section('title', 'Nuevo diferencial de comisión')

@section('content')
@php
    $porcentajes = \App\Services\Nomina\CommissionDifferentialService::PORCENTAJES;
    $seleccion = $valores['seleccion'] ?? [];
    $hayPrevia = $vistaPrevia !== null;
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.comisiones.diferenciales.index', $periodo) }}" class="muted" style="font-size:.82rem;">← Diferenciales {{ $periodo->etiqueta }}</a>
            <h1 style="margin:4px 0 0;">Nuevo diferencial de comisión</h1>
            <p class="muted" style="margin:4px 0 0;">
                Marca a las personas, indica si ya se les pagó la quincena y ajusta los porcentajes.
                Se recalcula sobre las mismas ventas de la quincena: <strong>diferencia = comisión ajustada − lo ya pagado</strong>.
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('nomina.comisiones.diferenciales.store', $periodo) }}" id="form-diferencial">
        @csrf

        <div class="nomina-card" style="margin-top:16px;">
            <h3>Datos del pago</h3>
            <div class="nomina-form-grid">
                <div class="field field-wide">
                    <label>Motivo</label>
                    <input type="text" name="motivo" value="{{ $valores['motivo'] }}" minlength="3" maxlength="255" required placeholder="Ej.: comisión no pagada / ajuste de porcentaje telefonía">
                </div>
                <div class="field">
                    <label>Fecha de pago (TXT)</label>
                    <input type="date" name="fecha_pago" value="{{ $valores['fecha_pago'] }}" required>
                </div>
                <div class="field">
                    <label>Tasa BCV</label>
                    <input type="number" step="0.0001" min="0" name="tasa_bcv" value="{{ number_format((float) $valores['tasa_bcv'], 4, '.', '') }}" required>
                </div>
            </div>
        </div>

        <div class="nomina-card" style="margin-top:16px;">
            <h3>Ajuste de porcentajes</h3>
            <p class="muted">Vienen de Configuración. Cámbialos aquí solo para este diferencial; la configuración global no se modifica. Los campos en naranja difieren de la configuración actual. Debajo de cada campo se ve el % con el que se pagó la quincena; en rojo si es distinto.</p>
            <div class="nomina-form-grid">
                @foreach($porcentajes as $clave => [$etiqueta, $default])
                    @php
                        $valor = (float) ($valores['config'][$clave] ?? $configActual[$clave]);
                        $cambiado = abs($valor - (float) $configActual[$clave]) > 0.00001;
                        $usadosQuincena = $pctQuincena[$clave] ?? [];
                        $distintoQuincena = $usadosQuincena !== [] && collect($usadosQuincena)->contains(fn ($u) => abs($u - $valor) > 0.00001);
                        $fmtPct = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
                    @endphp
                    <div class="field">
                        <label>{{ $etiqueta }}</label>
                        <input
                            type="number"
                            step="0.0001"
                            min="0"
                            max="100"
                            name="config[{{ $clave }}]"
                            value="{{ number_format($valor, 4, '.', '') }}"
                            data-config-actual="{{ number_format((float) $configActual[$clave], 4, '.', '') }}"
                            @if($cambiado) style="border-color:#f59e0b;background:#fffbeb;" @endif
                        >
                        @if($usadosQuincena !== [])
                            <span style="font-size:.72rem;{{ $distintoQuincena ? 'color:#b91c1c;font-weight:700;' : 'color:#64748b;' }}">
                                En la quincena: {{ collect($usadosQuincena)->map($fmtPct)->implode(' / ') }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        @if($hayPrevia)
            @php
                $totalDiferencia = (float) $vistaPrevia->filter(fn ($f) => $f['diferencia'] > 0)->sum('diferencia');
                $tasa = (float) $valores['tasa_bcv'];
            @endphp
            <div class="nomina-card" style="margin-top:16px;border-color:#93c5fd;">
                <h3>Vista previa</h3>
                <div class="nomina-kpis">
                    <div class="nomina-kpi"><span>Personas</span><strong>{{ $vistaPrevia->count() }}</strong></div>
                    <div class="nomina-kpi"><span>No se les pagó</span><strong>{{ $vistaPrevia->where('no_pagado', true)->count() }}</strong></div>
                    <div class="nomina-kpi"><span>Diferencia USD</span><strong>${{ number_format($totalDiferencia, 2) }}</strong></div>
                    <div class="nomina-kpi"><span>Diferencia Bs</span><strong>Bs {{ number_format($totalDiferencia * $tasa, 2) }}</strong></div>
                </div>
                @include('nomina.comisiones.diferenciales._tabla', ['filas' => $vistaPrevia, 'tasa' => $tasa])
            </div>
        @endif

        <div class="nomina-card" style="margin-top:16px;">
            <h3>Personas</h3>
            <p class="muted">Marca a quienes entran en el diferencial. Por defecto se asume que ya cobró la quincena y se paga solo la diferencia; elige "No se le pagó" para pagarle la comisión ajustada completa.</p>
            @include('nomina.partials.empleado-tabla-buscador', ['target' => 'tabla-diferencial-empleados'])
            <div class="table-wrap" style="margin-top:8px;">
                <table class="data-table" id="tabla-diferencial-empleados">
                    <thead>
                        <tr>
                            <th style="width:32px;"><input type="checkbox" id="diferencial-marcar-todos" title="Marcar los visibles"></th>
                            <th>Empleado</th>
                            <th>Modo</th>
                            <th>Comisión quincena</th>
                            <th>A pagar quincena</th>
                            <th>¿Se le pagó?</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($liquidaciones as $liq)
                            @php
                                $id = (int) $liq->empleado_id;
                                $marcado = array_key_exists($id, $seleccion);
                                $noPagado = $marcado ? $seleccion[$id] : false;
                                $buscar = mb_strtolower(trim(implode(' ', array_filter([
                                    $liq->empleado->nombre(),
                                    $liq->empleado->cedula(),
                                    $liq->empleado->nombreSede(),
                                    $liq->empleado->nombreCargo(),
                                ]))));
                            @endphp
                            <tr data-empleado-buscar="{{ $buscar }}">
                                <td><input type="checkbox" name="empleados[]" value="{{ $id }}" data-diferencial-check @checked($marcado)></td>
                                <td>
                                    {{ $liq->empleado->nombre() }}
                                    <div class="muted" style="font-size:.72rem;">{{ $liq->empleado->cedula() ?: 'Sin cédula' }} · {{ $liq->empleado->nombreSede() }}</div>
                                </td>
                                <td class="muted" style="font-size:.8rem;">{{ str_replace('_', ' ', $liq->modo) }}</td>
                                <td>${{ number_format((float) $liq->comision_total, 2) }}</td>
                                <td><strong>${{ number_format((float) $liq->total_pagar, 2) }}</strong></td>
                                <td>
                                    <select name="pagado[{{ $id }}]" style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px;">
                                        <option value="si" @selected(! $noPagado)>Ya se le pagó ${{ number_format(max(0, (float) $liq->total_pagar), 2) }}</option>
                                        <option value="no" @selected($noPagado)>No se le pagó</option>
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">No hay comisiones calculadas en esta quincena.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;">
            <button class="btn" type="submit" name="accion" value="previsualizar">Calcular vista previa</button>
            <button class="btn primary" type="submit" name="accion" value="guardar">Guardar diferencial</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const todos = document.getElementById('diferencial-marcar-todos');
    if (todos) {
        todos.addEventListener('change', function () {
            document.querySelectorAll('#tabla-diferencial-empleados tr[data-empleado-buscar]').forEach(function (fila) {
                if (fila.style.display === 'none') return;
                const check = fila.querySelector('[data-diferencial-check]');
                if (check) check.checked = todos.checked;
            });
        });
    }

    document.querySelectorAll('input[data-config-actual]').forEach(function (input) {
        input.addEventListener('input', function () {
            const cambiado = Math.abs(parseFloat(input.value || '0') - parseFloat(input.dataset.configActual)) > 0.00001;
            input.style.borderColor = cambiado ? '#f59e0b' : '';
            input.style.background = cambiado ? '#fffbeb' : '';
        });
    });

    const form = document.getElementById('form-diferencial');
    if (form) {
        form.addEventListener('submit', function (event) {
            if (!form.querySelector('[data-diferencial-check]:checked')) {
                event.preventDefault();
                alert('Marca al menos una persona.');
            }
        });
    }
});
</script>
@endpush
