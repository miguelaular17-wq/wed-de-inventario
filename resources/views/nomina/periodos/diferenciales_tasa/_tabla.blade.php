@php
    $fila = fn ($l) => is_array($l) ? (object) $l : $l;
    $totUsd = 0.0; $totPagado = 0.0; $totNuevo = 0.0; $totDif = 0.0; $totAPagar = 0.0;
@endphp
@include('nomina.partials.empleado-tabla-buscador', ['target' => 'tabla-diferencial-tasa'])
<div class="table-wrap" style="margin-top:8px;">
    <table class="data-table" id="tabla-diferencial-tasa">
        <thead>
            <tr>
                <th>Empleado</th>
                <th>Empresa</th>
                <th>Situación</th>
                <th style="text-align:right;">Nómina USD</th>
                <th style="text-align:right;">Pagado Bs<div class="muted" style="font-weight:400;font-size:.7rem;">tasa {{ number_format($tasaPagada, 4, ',', '.') }}</div></th>
                <th style="text-align:right;">Correspondía Bs<div class="muted" style="font-weight:400;font-size:.7rem;">tasa {{ number_format($tasaNueva, 4, ',', '.') }}</div></th>
                <th style="text-align:right;">Diferencia Bs</th>
                <th style="text-align:right;">A pagar Bs</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filas as $l)
                @php
                    $l = $fila($l);
                    $empleado = $l->empleado;
                    $aPagar = $l->no_pagado ? (float) $l->diferencia_bs : 0.0;
                    $totUsd += (float) $l->total_usd;
                    $totPagado += (float) $l->bs_pagado;
                    $totNuevo += (float) $l->bs_nuevo;
                    $totDif += (float) $l->diferencia_bs;
                    $totAPagar += $aPagar;
                    $buscar = mb_strtolower(trim(implode(' ', array_filter([
                        $empleado?->nombre(), $empleado?->cedula(), $empleado?->nombreSede(),
                    ]))));
                @endphp
                <tr data-empleado-buscar="{{ $buscar }}">
                    <td>
                        {{ $empleado?->nombre() ?? '—' }}
                        <div class="muted" style="font-size:.72rem;">{{ $empleado?->cedula() ?: 'Sin cédula' }} · {{ $empleado?->nombreSede() }}</div>
                    </td>
                    <td>{{ $empleado?->empresa?->codigo ?? 'Sin empresa' }}</td>
                    <td>
                        @if($l->no_pagado)
                            <span style="color:#b91c1c;font-weight:700;">No se le canceló la diferencia</span>
                        @else
                            <span class="muted">Ya se le pagó la diferencia</span>
                        @endif
                    </td>
                    <td style="text-align:right;">${{ number_format((float) $l->total_usd, 2) }}</td>
                    <td style="text-align:right;">Bs {{ number_format((float) $l->bs_pagado, 2) }}</td>
                    <td style="text-align:right;">Bs {{ number_format((float) $l->bs_nuevo, 2) }}</td>
                    <td style="text-align:right;">Bs {{ number_format((float) $l->diferencia_bs, 2) }}</td>
                    <td style="text-align:right;"><strong>Bs {{ number_format($aPagar, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No hay montos a pagar en esta quincena.</td></tr>
            @endforelse
        </tbody>
        @if(count($filas) > 0)
            <tfoot>
                <tr>
                    <th colspan="3">Totales ({{ count($filas) }} empleados)</th>
                    <th style="text-align:right;">${{ number_format($totUsd, 2) }}</th>
                    <th style="text-align:right;">Bs {{ number_format($totPagado, 2) }}</th>
                    <th style="text-align:right;">Bs {{ number_format($totNuevo, 2) }}</th>
                    <th style="text-align:right;">Bs {{ number_format($totDif, 2) }}</th>
                    <th style="text-align:right;">Bs {{ number_format($totAPagar, 2) }}</th>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
