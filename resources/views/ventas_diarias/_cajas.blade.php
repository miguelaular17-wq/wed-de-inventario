@php
    $servicio = app(\App\Services\VentasDiariasService::class);
@endphp
<table class="{{ $tablaClass ?? 'vd-table' }}">
    <thead>
        <tr>
            <th>Caja</th>
            @foreach($columnasCaja as $etiqueta)
                <th>{{ $etiqueta }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($reporte->cajas as $caja)
            @php $fila = $servicio->leerCaja($caja); @endphp
            <tr>
                <td><strong>{{ $caja->nombre }}</strong></td>
                @foreach($columnasCaja as $campo => $etiqueta)
                    <td class="num">{{ number_format((float) ($fila[$campo] ?? 0), 2) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columnasCaja) + 1 }}" class="muted">Sin cajas registradas.</td></tr>
        @endforelse
        @if($reporte->cajas->isNotEmpty())
            <tr class="vd-totales tot">
                <td>Totales</td>
                @foreach($columnasCaja as $campo => $etiqueta)
                    <td class="num">{{ number_format((float) ($totalesCajas[$campo] ?? 0), 2) }}</td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>
