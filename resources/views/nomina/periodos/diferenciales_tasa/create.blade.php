@extends('layouts.app')

@section('title', 'Nuevo diferencial por tasa')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.periodos.diferenciales_tasa.index', $periodo) }}" class="muted" style="font-size:.82rem;">← Diferenciales por tasa {{ $periodo->etiqueta }}</a>
            <h1 style="margin:4px 0 0;">Nuevo diferencial por tasa</h1>
            <p class="muted" style="margin:4px 0 0;">
                Se toma lo que cobró cada empleado en USD en la quincena:
                <strong>diferencia = nómina USD × (tasa nueva − tasa con que se pagó)</strong>.
                Solo se paga en el TXT a quien no se le canceló la diferencia.
            </p>
        </div>
    </div>

    @php
        $seleccion = $valores['seleccion'] ?? null;
        $saltoTasa = (float) $valores['tasa_nueva'] - (float) $valores['tasa_pagada'];
    @endphp
    <form method="POST" action="{{ route('nomina.periodos.diferenciales_tasa.store', $periodo) }}" id="form-diferencial-tasa">
        @csrf

        <div class="nomina-card" style="margin-top:16px;">
            <h3>Datos del pago</h3>
            <div class="nomina-form-grid">
                <div class="field field-wide">
                    <label>Motivo</label>
                    <input type="text" name="motivo" value="{{ old('motivo', $valores['motivo']) }}" minlength="3" maxlength="255" required placeholder="Ej.: se pagó con la tasa del 15/11 y subió">
                </div>
                <div class="field">
                    <label>Fecha de pago (TXT)</label>
                    <input type="date" name="fecha_pago" value="{{ old('fecha_pago', $valores['fecha_pago']) }}" required>
                </div>
                <div class="field">
                    <label>Tasa con que se pagó</label>
                    <input type="number" step="0.0001" min="0" name="tasa_pagada" value="{{ old('tasa_pagada', number_format((float) $valores['tasa_pagada'], 4, '.', '')) }}" required>
                    <span class="muted" style="font-size:.72rem;">Tasa de la quincena: {{ number_format($tasaPeriodo, 4, ',', '.') }}</span>
                </div>
                <div class="field">
                    <label>Tasa nueva</label>
                    <input type="number" step="0.0001" min="0" name="tasa_nueva" value="{{ old('tasa_nueva', number_format((float) $valores['tasa_nueva'], 4, '.', '')) }}" required>
                    <span class="muted" style="font-size:.72rem;">Por defecto, la tasa BCV de hoy.</span>
                </div>
            </div>
        </div>

        @if($vistaPrevia !== null)
            <div class="nomina-card" style="margin-top:16px;border-color:#93c5fd;">
                <h3>Vista previa</h3>
                <div class="nomina-kpis">
                    <div class="nomina-kpi"><span>Personas</span><strong>{{ $vistaPrevia->count() }}</strong></div>
                    <div class="nomina-kpi"><span>Ya se les pagó la diferencia</span><strong>{{ $vistaPrevia->where('no_pagado', false)->count() }}</strong></div>
                    <div class="nomina-kpi"><span>Nómina USD</span><strong>${{ number_format((float) $vistaPrevia->sum('total_usd'), 2) }}</strong></div>
                    <div class="nomina-kpi"><span>A pagar Bs</span><strong>Bs {{ number_format((float) $vistaPrevia->where('no_pagado', true)->sum('diferencia_bs'), 2) }}</strong></div>
                </div>
                @include('nomina.periodos.diferenciales_tasa._tabla', [
                    'filas' => $vistaPrevia,
                    'tasaPagada' => (float) $valores['tasa_pagada'],
                    'tasaNueva' => (float) $valores['tasa_nueva'],
                ])
            </div>
        @endif

        <div class="nomina-card" style="margin-top:16px;">
            <h3>Personas</h3>
            <p class="muted">Marca a quienes entran en el diferencial. Por defecto la diferencia no se le ha cancelado y se le paga en el TXT; elige "Ya se le pagó la diferencia" para dejarla registrada sin volver a pagarla.</p>
            @include('nomina.partials.empleado-tabla-buscador', ['target' => 'tabla-tasa-empleados'])
            <div class="table-wrap" style="margin-top:8px;">
                <table class="data-table" id="tabla-tasa-empleados">
                    <thead>
                        <tr>
                            <th style="width:32px;"><input type="checkbox" id="tasa-marcar-todos" title="Marcar los visibles" @checked($seleccion === null)></th>
                            <th>Empleado</th>
                            <th>Empresa</th>
                            <th style="text-align:right;">Nómina USD</th>
                            <th style="text-align:right;">Diferencia Bs</th>
                            <th>¿Se le pagó la diferencia?</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($candidatos as $c)
                            @php
                                $id = $c->empleado_id;
                                $marcado = $seleccion === null || array_key_exists($id, $seleccion);
                                $noPagado = $seleccion === null || ($seleccion[$id] ?? true);
                                $buscar = mb_strtolower(trim(implode(' ', array_filter([
                                    $c->empleado->nombre(),
                                    $c->empleado->cedula(),
                                    $c->empleado->nombreSede(),
                                    $c->empleado->nombreCargo(),
                                ]))));
                            @endphp
                            <tr data-empleado-buscar="{{ $buscar }}">
                                <td><input type="checkbox" name="empleados[]" value="{{ $id }}" data-tasa-check @checked($marcado)></td>
                                <td>
                                    {{ $c->empleado->nombre() }}
                                    <div class="muted" style="font-size:.72rem;">{{ $c->empleado->cedula() ?: 'Sin cédula' }} · {{ $c->empleado->nombreSede() }}</div>
                                </td>
                                <td>{{ $c->empleado->empresa?->codigo ?? 'Sin empresa' }}</td>
                                <td style="text-align:right;"><strong>${{ number_format($c->total_usd, 2) }}</strong></td>
                                <td style="text-align:right;">Bs {{ number_format(max(0, $c->total_usd * $saltoTasa), 2) }}</td>
                                <td>
                                    <select name="pagado[{{ $id }}]" style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px;">
                                        <option value="no" @selected($noPagado)>No se le canceló la diferencia</option>
                                        <option value="si" @selected(! $noPagado)>Ya se le pagó la diferencia</option>
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">No hay montos a pagar en esta quincena.</td></tr>
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
    const todos = document.getElementById('tasa-marcar-todos');
    if (todos) {
        todos.addEventListener('change', function () {
            document.querySelectorAll('#tabla-tasa-empleados tr[data-empleado-buscar]').forEach(function (fila) {
                if (fila.style.display === 'none') return;
                const check = fila.querySelector('[data-tasa-check]');
                if (check) check.checked = todos.checked;
            });
        });
    }

    const form = document.getElementById('form-diferencial-tasa');
    if (form) {
        form.addEventListener('submit', function (event) {
            if (!form.querySelector('[data-tasa-check]:checked')) {
                event.preventDefault();
                alert('Marca al menos una persona.');
            }
        });
    }
});
</script>
@endpush
