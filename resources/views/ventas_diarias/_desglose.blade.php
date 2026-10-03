@php
    $tasa = (float) $reporte->tasa > 0 ? (float) $reporte->tasa : 1;
    $bsN = fn (float $monto) => 'Bs '.number_format($monto, 2);
    $usd = fn (float $monto) => '$'.number_format($monto, 2);
    $pct = function (?float $v) {
        if ($v === null) {
            return '—';
        }

        return number_format($v * 100, 1).'%';
    };
@endphp
<table class="{{ $tablaClass ?? 'vd-table' }}">
    <thead>
        <tr>
            <th>Forma de pago</th>
            <th class="bs">Bolívares</th>
            <th class="usd">Divisas</th>
            <th>% meta</th>
        </tr>
    </thead>
    <tbody>
        @foreach($totales['lineas'] as $linea)
            <tr class="{{ ! empty($linea['rojo']) ? 'vd-credito' : '' }}">
                <td class="lab">{{ $linea['etiqueta'] }}</td>
                <td class="bs">{{ $linea['bs'] === null ? '' : $bsN((float) $linea['bs']) }}</td>
                <td class="usd">{{ $usd((float) $linea['usd']) }}</td>
                <td></td>
            </tr>
        @endforeach
        <tr class="vd-venta venta">
            <td class="lab">Total de ventas del día</td>
            <td class="bs">{{ $bsN((float) $totales['total_bs']) }}</td>
            <td class="usd">{{ $usd((float) $totales['total_ventas']) }}</td>
            <td>{{ $pct($totales['cumpl_venta'] ?? null) }}</td>
        </tr>
        <tr class="vd-venta venta vd-unidades">
            <td class="lab">Unidades vendidas</td>
            <td class="usd" colspan="2">{{ number_format((float) $reporte->productos_vendidos, 0) }}</td>
            <td>{{ $pct($totales['cumpl_prod'] ?? null) }}</td>
        </tr>
        <tr>
            <td class="lab">Facturación fiscal</td>
            <td class="bs">{{ $bsN((float) $reporte->z_fiscal_bs) }}</td>
            <td class="usd">{{ $usd((float) $totales['facturacion_fiscal_usd']) }}</td>
            <td>{{ $pct($totales['pct_fiscal'] ?? null) }}</td>
        </tr>
        <tr>
            <td class="lab">Disponible fondo Bs</td>
            <td class="bs">{{ $bsN((float) $reporte->fondo_bs) }}</td>
            <td class="usd">{{ $usd((float) $reporte->fondo_bs / $tasa) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="lab">Disponible fondo divisas</td>
            <td class="bs"></td>
            <td class="usd">{{ $usd((float) $reporte->fondo_divisas) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
