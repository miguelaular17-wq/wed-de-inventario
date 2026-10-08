@php
    $servicio = app(\App\Services\VentasDiariasService::class);
    $num = fn (float $monto) => number_format($monto, 2, ',', '.');
@endphp
<table class="{{ $tablaClass ?? 'vd-table' }}">
    <thead>
        <tr>
            <th>CAJA</th>
            @foreach($columnasCaja as $etiqueta)
                <th>{{ $etiqueta }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($reporte->cajas as $caja)
            @php $fila = $servicio->leerCaja($caja); @endphp
            <tr>
                <td><strong>{{ mb_strtoupper((string) $caja->nombre, 'UTF-8') }}</strong></td>
                @foreach($columnasCaja as $campo => $etiqueta)
                    <td class="num">{{ $num((float) ($fila[$campo] ?? 0)) }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columnasCaja) + 1 }}" class="muted">SIN CAJAS REGISTRADAS.</td></tr>
        @endforelse
        @if($reporte->cajas->isNotEmpty())
            <tr class="vd-totales tot">
                <td>TOTALES</td>
                @foreach($columnasCaja as $campo => $etiqueta)
                    <td class="num">{{ $num((float) ($totalesCajas[$campo] ?? 0)) }}</td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>
