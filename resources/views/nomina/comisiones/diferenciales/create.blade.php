@extends('layouts.app')

@section('title', 'Ajuste / recálculo de comisión')

@push('head')
<style>
.dif-fuente { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 8px; }
@media (max-width: 720px) { .dif-fuente { grid-template-columns: 1fr; } }
.dif-fuente-opt {
    display: flex; gap: 10px; align-items: flex-start;
    margin: 0; padding: 12px 14px;
    border: 1px solid #cbd5e1; border-radius: 10px; background: #fff;
    cursor: pointer; text-transform: none; letter-spacing: 0;
    font-size: .88rem; font-weight: 700; color: #0f172a;
}
.dif-fuente-opt:has(input:checked) {
    border-color: #1a4480; background: #eff6ff; box-shadow: inset 0 0 0 1px #1a4480;
}
.dif-fuente-opt input[type="radio"] {
    width: 1.05rem !important; height: 1.05rem; min-width: 1.05rem; max-width: 1.05rem;
    margin: 2px 0 0; padding: 0; flex: 0 0 auto; accent-color: #1a4480;
}
.dif-fuente-opt .dif-fuente-txt { flex: 1; min-width: 0; line-height: 1.35; }
.dif-fuente-opt .dif-fuente-txt small {
    display: block; margin-top: 4px; font-weight: 500; font-size: .78rem; color: #64748b;
}
</style>
@endpush

@section('content')
@php
    $porcentajes = \App\Services\Nomina\CommissionDifferentialService::PORCENTAJES;
    $seleccion = $valores['seleccion'] ?? [];
    $hayPrevia = $vistaPrevia !== null;
    $fuente = $valores['fuente'] ?? 'ventas';
    $defaultPagado = $valores['default_pagado'] ?? 'si';
    $periodoPagado = in_array($periodo->estado, ['PAGADO', 'CERRADO'], true);
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.comisiones.diferenciales.index', $periodo) }}" class="muted" style="font-size:.82rem;">← Ajustes {{ $periodo->etiqueta }}</a>
            <h1 style="margin:4px 0 0;">Ajuste / recálculo de comisión</h1>
            <p class="muted" style="margin:4px 0 0;">
                Elige personas y marca si ya cobraron. Quincena en <strong>{{ $periodo->estado }}</strong>.
            </p>
        </div>
        <a class="btn secondary" href="{{ route('nomina.comisiones.show', $periodo) }}">Volver a comisiones</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:16px;">
        <div class="nomina-card" style="margin:0;border-color:#93c5fd;background:#eff6ff;">
            <h3 style="margin:0 0 6px;color:#1e3a8a;">No se le pagó</h3>
            <p class="muted" style="margin:0;font-size:.86rem;line-height:1.45;">
                Se recalcula la liquidación y el TXT del diferencial paga el <strong>total nuevo</strong>
                (complemento si la quincena ya está PAGADO, o primera vez).
            </p>
        </div>
        <div class="nomina-card" style="margin:0;border-color:#fcd34d;background:#fffbeb;">
            <h3 style="margin:0 0 6px;color:#92400e;">Ya se le pagó</h3>
            <p class="muted" style="margin:0;font-size:.86rem;line-height:1.45;">
                Se recalcula y el TXT paga solo la <strong>diferencia</strong>
                (nuevo − lo que ya cobró). Si no hay aumento, la diferencia queda en 0.
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
                    <input type="text" name="motivo" value="{{ $valores['motivo'] }}" minlength="3" maxlength="255" required placeholder="Ej.: ajuste ventas / no se le pagó / corrección porcentaje">
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

            <div style="margin-top:16px;">
                <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;margin-bottom:2px;">Cómo recalcular</div>
                <div class="dif-fuente">
                    <label class="dif-fuente-opt">
                        <input type="radio" name="fuente" value="ventas" @checked($fuente !== 'porcentajes') required>
                        <span class="dif-fuente-txt">
                            Desde ventas y configuración actuales
                            <small>Vuelve a leer ventas, actualiza la liquidación de los elegidos y arma el pago.</small>
                        </span>
                    </label>
                    <label class="dif-fuente-opt">
                        <input type="radio" name="fuente" value="porcentajes" @checked($fuente === 'porcentajes')">
                        <span class="dif-fuente-txt">
                            Solo cambiar porcentajes
                            <small>Usa las bases ya guardadas de la quincena; no vuelve a leer ventas.</small>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div class="nomina-card" style="margin-top:16px;" id="bloque-porcentajes" @if($fuente !== 'porcentajes') hidden @endif>
            <h3>Ajuste de porcentajes</h3>
            <p class="muted">Solo aplica si elegiste “Solo cambiar porcentajes”. La configuración global no se modifica.</p>
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
                    <div class="nomina-kpi"><span>Ya se les pagó</span><strong>{{ $vistaPrevia->where('no_pagado', false)->count() }}</strong></div>
                    <div class="nomina-kpi"><span>A pagar USD</span><strong>${{ number_format($totalDiferencia, 2) }}</strong></div>
                    <div class="nomina-kpi"><span>A pagar Bs</span><strong>Bs {{ number_format($totalDiferencia * $tasa, 2) }}</strong></div>
                </div>
                @include('nomina.comisiones.diferenciales._tabla', ['filas' => $vistaPrevia, 'tasa' => $tasa])
            </div>
        @endif

        <div class="nomina-card" style="margin-top:16px;">
            <h3>Personas</h3>
            <p class="muted">
                Marca a quienes entran.
                @if($periodoPagado)
                    Por defecto se asume <strong>Ya se le pagó</strong> (solo diferencia). Cambia a “No se le pagó” si esa persona quedó fuera del TXT.
                @else
                    Por defecto se asume <strong>No se le pagó</strong>. Si alguien ya cobró por fuera, márcalo como pagado para pagar solo la diferencia.
                @endif
            </p>
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
                                $noPagado = $marcado ? $seleccion[$id] : ($defaultPagado === 'no');
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

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;align-items:center;">
            <button class="btn" type="submit" name="accion" value="previsualizar">Calcular vista previa</button>
            <button class="btn primary" type="submit" name="accion" value="guardar">Guardar diferencial + TXT</button>
            @if(! $periodoPagado)
                <button class="btn secondary" type="submit" name="accion" value="solo_recalcular" title="Solo para quienes no se les pagó, sin generar pago extra">
                    Solo recalcular (sin diferencial)
                </button>
            @endif
        </div>
        <p class="muted" style="margin:10px 0 0;font-size:.8rem;">
            “Solo recalcular” actualiza liquidaciones de quienes están en “No se le pagó” y los deja listos para el TXT principal.
            Si la quincena ya está PAGADO, usa siempre el diferencial para el complemento o la diferencia.
        </p>
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

    const bloquePct = document.getElementById('bloque-porcentajes');
    function sincronizarFuente() {
        const elegida = document.querySelector('input[type="radio"][name="fuente"]:checked');
        if (!elegida) {
            const ventas = document.querySelector('input[type="radio"][name="fuente"][value="ventas"]');
            if (ventas) ventas.checked = true;
        }
        const valor = document.querySelector('input[type="radio"][name="fuente"]:checked')?.value || 'ventas';
        if (bloquePct) bloquePct.hidden = valor !== 'porcentajes';
    }
    document.querySelectorAll('input[type="radio"][name="fuente"]').forEach(function (radio) {
        radio.addEventListener('change', sincronizarFuente);
    });
    sincronizarFuente();

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
