@php
    $totales = ['comision_original' => 0, 'comision_ajustada' => 0, 'retencion_ajustada' => 0, 'descuentos' => 0, 'total_ajustado' => 0, 'pagado' => 0, 'diferencia' => 0];
@endphp
<div class="table-wrap" style="margin-top:8px;">
    <table class="data-table">
        <thead>
            <tr>
                <th>Empleado</th>
                <th>Situación</th>
                <th>Comisión quincena</th>
                <th>Comisión ajustada</th>
                <th>Bonos</th>
                <th>Retención</th>
                <th>Desc. / préstamos</th>
                <th>Total ajustado</th>
                <th>Ya pagado</th>
                <th>Diferencia</th>
                <th>Diferencia Bs</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filas as $f)
                @php
                    $empleado = $f['empleado'];
                    $diferencia = (float) $f['diferencia'];
                    foreach (array_keys($totales) as $campo) {
                        $totales[$campo] += (float) $f[$campo];
                    }
                @endphp
                <tr>
                    <td>
                        {{ $empleado?->nombre() ?? '—' }}
                        <div class="muted" style="font-size:.72rem;">{{ $empleado?->cedula() ?: 'Sin cédula' }}{{ $empleado?->empresa ? ' · '.$empleado->empresa->codigo : ' · Sin empresa' }}</div>
                    </td>
                    <td>
                        @if($f['no_pagado'])
                            <span style="color:#b91c1c;font-weight:700;font-size:.8rem;">No se le pagó</span>
                        @else
                            <span class="muted" style="font-size:.8rem;">Ya se le pagó</span>
                        @endif
                    </td>
                    <td>${{ number_format((float) $f['comision_original'], 2) }}</td>
                    <td>
                        ${{ number_format((float) $f['comision_ajustada'], 2) }}
                        @if(abs((float) $f['comision_ajustada'] - (float) $f['comision_original']) >= 0.01)
                            <div style="font-size:.72rem;color:#b45309;">{{ (float) $f['comision_ajustada'] >= (float) $f['comision_original'] ? '+' : '−' }}${{ number_format(abs((float) $f['comision_ajustada'] - (float) $f['comision_original']), 2) }}</div>
                        @endif
                    </td>
                    <td>${{ number_format((float) $f['abonos'], 2) }}</td>
                    <td>${{ number_format((float) $f['retencion_ajustada'], 2) }}</td>
                    <td>${{ number_format((float) $f['descuentos'], 2) }}</td>
                    <td>${{ number_format((float) $f['total_ajustado'], 2) }}</td>
                    <td>${{ number_format((float) $f['pagado'], 2) }}</td>
                    <td>
                        <strong style="color:{{ $diferencia > 0 ? '#166534' : ($diferencia < 0 ? '#b91c1c' : 'inherit') }};">${{ number_format($diferencia, 2) }}</strong>
                        @if($diferencia < 0)
                            <div class="muted" style="font-size:.72rem;">Pagado de más, no va al TXT</div>
                        @endif
                    </td>
                    <td>Bs {{ number_format(max(0, $diferencia) * $tasa, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="muted">Sin personas seleccionadas.</td></tr>
            @endforelse
        </tbody>
        @if(count($filas) > 0)
            <tfoot>
                <tr>
                    <th colspan="2">Totales</th>
                    <th>${{ number_format($totales['comision_original'], 2) }}</th>
                    <th>${{ number_format($totales['comision_ajustada'], 2) }}</th>
                    <th></th>
                    <th>${{ number_format($totales['retencion_ajustada'], 2) }}</th>
                    <th>${{ number_format($totales['descuentos'], 2) }}</th>
                    <th>${{ number_format($totales['total_ajustado'], 2) }}</th>
                    <th>${{ number_format($totales['pagado'], 2) }}</th>
                    <th>${{ number_format($totales['diferencia'], 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
