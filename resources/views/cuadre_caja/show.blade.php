@extends('layouts.app')

@section('title', 'Cuadre de caja · '.($cuadre->usuario_nombre ?: $cuadre->usuario))

@php
    $dinero = fn ($n) => $n === null ? '—' : (round((float) $n, 2) < 0 ? '-' : '').'$'.number_format(abs((float) $n), 2, ',', '.');
    $monto = fn ($n, $usd) => $n === null ? '' : ($usd ? '$' : 'Bs ').number_format((float) $n, 2, ',', '.');
    $valor = fn ($n) => $n === null ? '' : number_format((float) $n, 2, '.', '');
    $colorDif = fn ($d) => $d === null ? 'inherit' : ($d < -0.004 ? '#b91c1c' : ($d > 0.004 ? '#15803d' : '#334155'));
    $volverUrl = $volver && str_starts_with($volver, url('/')) ? $volver : route('cuadre_caja.index', ['desde' => $cuadre->fecha_cierre->toDateString(), 'hasta' => $cuadre->fecha_cierre->toDateString()]);
    $editable = $puedeConciliar;
@endphp

@section('content')
<div class="page-header">
    <a href="{{ $volverUrl }}" class="btn secondary" style="margin-bottom:10px;">← Cuadre de caja</a>
    <h1>{{ $cuadre->usuario_nombre ?: $cuadre->usuario }}</h1>
    <p class="lead">
        {{ $etiquetas[$cuadre->sede] ?? $cuadre->sede }} · {{ $cuadre->descripcion_caja ?: $cuadre->codigo_caja }}
        · {{ $cuadre->fecha_cierre->format('d/m/Y') }}
        · tasa {{ number_format($cuadre->factor_cambio, 2, ',', '.') }}
        · {{ $cuadre->id_cierre ? 'cierre N.º '.$cuadre->id_cierre.' en Suite' : 'sin cierre en Suite todavía' }}
    </p>
    @if($cuadre->conciliado())
        <p class="muted" style="font-size:.82rem;margin:4px 0 0;">
            Conciliado por {{ $cuadre->conciliador?->name ?? '—' }} el {{ $cuadre->conciliado_at->format('d/m/Y H:i') }}.
        </p>
    @endif
</div>

<div class="nomina-kpis" style="margin-bottom:14px;">
    <div class="nomina-kpi"><span>Ingresos en Suite</span><strong>{{ $dinero($ingresos_usd) }}</strong></div>
    <div class="nomina-kpi"><span>Contado por Tesorería</span><strong id="cc-total-tes">{{ $dinero($tesoreria_usd) }}</strong></div>
    <div class="nomina-kpi"><span>Diferencia</span><strong id="cc-total-dif" style="color:{{ $colorDif($diferencia_usd) }};">{{ $dinero($diferencia_usd) }}</strong></div>
</div>

<form method="POST" action="{{ route('cuadre_caja.conciliar', $cuadre) }}" class="panel" id="cc-form">
    @csrf
    <input type="hidden" name="volver" value="{{ $volver }}">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
        <h3 style="margin:0;">Formas de pago</h3>
        @if($editable)
            <button type="button" class="btn secondary" id="cc-copiar" style="padding:5px 10px;font-size:.8rem;"
                    title="Pone en cada casilla vacía lo que declaró la cajera en Suite">Copiar lo de Suite</button>
        @endif
    </div>
    <div class="table-wrap">
        <table class="data-table" id="cc-tabla">
            <thead>
                <tr>
                    <th>Forma de pago</th>
                    <th>Cuenta</th>
                    <th style="text-align:right;">Suite</th>
                    <th style="text-align:right;">Suite USD</th>
                    <th style="text-align:right;">Tesorería</th>
                    <th style="text-align:right;">Tesorería USD</th>
                    <th style="text-align:right;">Diferencia USD</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lineas as $l)
                    <tr class="cc-linea" data-usd="{{ $l['en_dolares'] ? 1 : 0 }}" data-suite="{{ $l['suite'] }}" data-suite-usd="{{ $l['suite_usd'] }}">
                        <td><strong>{{ $l['forma_pago'] }}</strong></td>
                        <td>{{ $l['cuenta'] ?: '—' }}</td>
                        <td style="text-align:right;">{{ $monto($l['suite'], $l['en_dolares']) ?: '—' }}</td>
                        <td style="text-align:right;">{{ $dinero($l['suite_usd']) }}</td>
                        <td style="text-align:right;">
                            @if($editable)
                                <input type="number" step="0.01" min="0" name="montos[{{ $l['clave'] }}]" value="{{ $valor($l['tesoreria']) }}"
                                       class="cc-monto" style="width:130px;text-align:right;" placeholder="{{ $l['en_dolares'] ? 'USD' : 'Bs' }}">
                            @else
                                {{ $monto($l['tesoreria'], $l['en_dolares']) ?: '—' }}
                            @endif
                        </td>
                        <td style="text-align:right;" class="cc-tes-usd">{{ $dinero($l['tesoreria_usd']) }}</td>
                        <td style="text-align:right;font-weight:700;color:{{ $colorDif($l['diferencia_usd']) }};" class="cc-dif">{{ $dinero($l['diferencia_usd']) }}</td>
                    </tr>
                @endforeach
                @if($editable)
                    @foreach($formasExtra as $f)
                        <tr class="cc-linea cc-extra" data-clave="{{ $f['clave'] }}" data-usd="{{ $f['en_dolares'] ? 1 : 0 }}" data-suite="0" data-suite-usd="0" style="display:none;">
                            <td><strong>{{ $f['forma_pago'] ?: $f['codigo_metodo'] }}</strong> <span class="muted" style="font-size:.72rem;">(no declarada en Suite)</span></td>
                            <td>{{ $f['cuenta'] ?: '—' }}</td>
                            <td style="text-align:right;">—</td>
                            <td style="text-align:right;">{{ $dinero(0) }}</td>
                            <td style="text-align:right;">
                                <input type="number" step="0.01" min="0" name="montos[{{ $f['clave'] }}]" class="cc-monto" disabled
                                       style="width:130px;text-align:right;" placeholder="{{ $f['en_dolares'] ? 'USD' : 'Bs' }}">
                            </td>
                            <td style="text-align:right;" class="cc-tes-usd">—</td>
                            <td style="text-align:right;font-weight:700;" class="cc-dif">—</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">Total</th>
                    <th style="text-align:right;">{{ $dinero($ingresos_usd) }}</th>
                    <th></th>
                    <th style="text-align:right;" id="cc-foot-tes">{{ $dinero($tesoreria_usd) }}</th>
                    <th style="text-align:right;" id="cc-foot-dif">{{ $dinero($diferencia_usd) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>

    @if($editable && $formasExtra)
        <div style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap;">
            <select id="cc-agregar-sel" style="min-width:260px;">
                <option value="">Agregar una forma de pago que la cajera no declaró…</option>
                @foreach($formasExtra as $f)
                    <option value="{{ $f['clave'] }}">{{ $f['forma_pago'] ?: $f['codigo_metodo'] }}{{ $f['cuenta'] ? ' · '.$f['cuenta'] : '' }}</option>
                @endforeach
            </select>
            <button type="button" class="btn secondary" id="cc-agregar" style="padding:6px 12px;">Agregar</button>
        </div>
    @endif

    <div class="field" style="margin-top:14px;">
        <label for="cc-obs">Observación de Tesorería</label>
        @if($editable)
            <textarea id="cc-obs" name="observacion" rows="2" maxlength="2000" placeholder="Ej.: faltante por recargo de factura">{{ old('observacion', $cuadre->observacion_tesoreria) }}</textarea>
        @else
            <div>{{ $cuadre->observacion_tesoreria ?: '—' }}</div>
        @endif
    </div>

    @if($editable)
        <p class="muted" style="font-size:.78rem;margin:6px 0 10px;">
            Anota cada monto en la moneda de la forma de pago (los Bs se pasan a USD con la tasa del día).
            Al guardar, una forma de pago vacía cuenta como $0 recibido.
        </p>
        <button type="submit" class="btn primary">{{ $cuadre->conciliado() ? 'Guardar cambios' : 'Guardar cuadre' }}</button>
    @endif
</form>

@if($editable)
<script>
(function () {
    const factor = {{ $cuadre->factor_cambio > 0 ? $cuadre->factor_cambio : 0 }};
    const fmt = (n) => {
        const r = Math.round(n * 100) / 100;
        return (r < 0 ? '-' : '') + '$' + Math.abs(r).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    };
    const color = (d) => d < -0.004 ? '#b91c1c' : (d > 0.004 ? '#15803d' : '#334155');

    function recalcular() {
        let totalTes = 0, totalSuite = 0;
        document.querySelectorAll('#cc-tabla .cc-linea').forEach(function (fila) {
            if (fila.style.display === 'none') return;
            const input = fila.querySelector('.cc-monto');
            const suiteUsd = parseFloat(fila.dataset.suiteUsd || '0');
            const valor = input && input.value !== '' ? parseFloat(input.value) : null;
            const usd = valor === null ? 0 : (fila.dataset.usd === '1' ? valor : (factor ? valor / factor : 0));
            const dif = usd - suiteUsd;
            totalTes += usd;
            totalSuite += suiteUsd;
            fila.querySelector('.cc-tes-usd').textContent = valor === null ? '—' : fmt(usd);
            const celda = fila.querySelector('.cc-dif');
            celda.textContent = valor === null ? '—' : fmt(dif);
            celda.style.color = valor === null ? 'inherit' : color(dif);
        });
        const dif = totalTes - totalSuite;
        ['cc-total-tes', 'cc-foot-tes'].forEach(id => { const el = document.getElementById(id); if (el) el.textContent = fmt(totalTes); });
        ['cc-total-dif', 'cc-foot-dif'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.textContent = fmt(dif); el.style.color = color(dif); }
        });
    }

    document.querySelectorAll('#cc-tabla .cc-monto').forEach(i => i.addEventListener('input', recalcular));

    document.getElementById('cc-copiar')?.addEventListener('click', function () {
        document.querySelectorAll('#cc-tabla .cc-linea').forEach(function (fila) {
            const input = fila.querySelector('.cc-monto');
            if (input && !input.disabled && input.value === '') {
                input.value = parseFloat(fila.dataset.suite || '0').toFixed(2);
            }
        });
        recalcular();
    });

    document.getElementById('cc-agregar')?.addEventListener('click', function () {
        const sel = document.getElementById('cc-agregar-sel');
        const fila = [...document.querySelectorAll('#cc-tabla .cc-extra')].find(f => f.dataset.clave === sel.value);
        if (!fila) return;
        fila.style.display = '';
        const input = fila.querySelector('.cc-monto');
        input.disabled = false;
        input.focus();
        sel.querySelector('option[value="' + CSS.escape(sel.value) + '"]')?.remove();
        sel.value = '';
        recalcular();
    });
})();
</script>
@endif
@endsection
