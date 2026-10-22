{{-- $filas: resumen diario; $total: cantidad de movimientos de la sección; $limite: tope de detalle --}}
<p class="nota-resumen">
    Esta sección tiene {{ number_format($total, 0, ',', '.') }} movimientos (más de {{ $limite }}): se muestra el resumen por día.
</p>
<table class="data-table">
    <thead>
        <tr>
            <th>FECHA</th>
            <th class="text-right">MOVIMIENTOS</th>
            <th class="text-right">INGRESOS BS.</th>
            <th class="text-right">EGRESOS BS.</th>
            <th class="text-right">NETO BS.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($filas as $dia)
        <tr>
            <td class="text-center">{{ \Carbon\Carbon::parse($dia['fecha'])->format('d/m/Y') }}</td>
            <td class="text-right">{{ number_format($dia['cantidad'], 0, ',', '.') }}</td>
            <td class="text-right" style="color: #059669;">{{ $dia['ingresos'] > 0 ? '+Bs. '.number_format($dia['ingresos'], 2) : '—' }}</td>
            <td class="text-right" style="color: #dc2626;">{{ $dia['egresos'] > 0 ? '-Bs. '.number_format($dia['egresos'], 2) : '—' }}</td>
            <td class="text-right">Bs. {{ number_format($dia['ingresos'] - $dia['egresos'], 2) }}</td>
        </tr>
        @endforeach
        @php
            $totIngresos = (float) $filas->sum('ingresos');
            $totEgresos = (float) $filas->sum('egresos');
        @endphp
        <tr class="total-row">
            <td class="text-right">Total:</td>
            <td class="text-right">{{ number_format($total, 0, ',', '.') }}</td>
            <td class="text-right">+Bs. {{ number_format($totIngresos, 2) }}</td>
            <td class="text-right">-Bs. {{ number_format($totEgresos, 2) }}</td>
            <td class="text-right">Bs. {{ number_format($totIngresos - $totEgresos, 2) }}</td>
        </tr>
    </tbody>
</table>
