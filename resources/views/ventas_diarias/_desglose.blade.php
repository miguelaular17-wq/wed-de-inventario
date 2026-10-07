@php
    $tasa = (float) $reporte->tasa > 0 ? (float) $reporte->tasa : 1;
    $num = fn (float $monto, int $dec = 2) => number_format($monto, $dec, ',', '.');
    $bsN = fn (float $monto) => 'Bs '.$num($monto);
    $usd = fn (float $monto) => '$'.$num($monto);
    $pct = function (?float $v) use ($num) {
        if ($v === null) {
            return '—';
        }

        return $num($v * 100, 1).'%';
    };
    $may = fn (string $texto) => mb_strtoupper($texto, 'UTF-8');
@endphp
<table class="{{ $tablaClass ?? 'vd-table' }}">
    <thead>
        <tr>
            <th>FORMA DE PAGO</th>
            <th class="bs">BOLÍVARES</th>
            <th class="usd">DIVISAS</th>
            <th>% META</th>
        </tr>
    </thead>
    <tbody>
        @foreach($totales['lineas'] as $linea)
            <tr class="{{ ! empty($linea['rojo']) ? 'vd-credito' : '' }}">
                <td class="lab">{{ $may($linea['etiqueta']) }}</td>
                <td class="bs">{{ $linea['bs'] === null ? '' : $bsN((float) $linea['bs']) }}</td>
                <td class="usd">{{ $usd((float) $linea['usd']) }}</td>
                <td></td>
            </tr>
        @endforeach
        <tr class="vd-venta venta">
            <td class="lab">TOTAL DE VENTAS DEL DÍA</td>
            <td class="bs">{{ $bsN((float) $totales['total_bs']) }}</td>
            <td class="usd">{{ $usd((float) $totales['total_ventas']) }}</td>
            <td>{{ $pct($totales['cumpl_venta'] ?? null) }}</td>
        </tr>
        <tr class="vd-venta venta vd-unidades">
            <td class="lab">UNIDADES VENDIDAS</td>
            <td class="usd" colspan="2">{{ $num((float) $reporte->productos_vendidos, 0) }}</td>
            <td>{{ $pct($totales['cumpl_prod'] ?? null) }}</td>
        </tr>
        <tr>
            <td class="lab">FACTURACIÓN FISCAL</td>
            <td class="bs">{{ $bsN((float) $reporte->z_fiscal_bs) }}</td>
            <td class="usd">{{ $usd((float) $totales['facturacion_fiscal_usd']) }}</td>
            <td>{{ $pct($totales['pct_fiscal'] ?? null) }}</td>
        </tr>
        <tr>
            <td class="lab">DISPONIBLE FONDO BS</td>
            <td class="bs">{{ $bsN((float) $reporte->fondo_bs) }}</td>
            <td class="usd">{{ $usd((float) $reporte->fondo_bs / $tasa) }}</td>
            <td></td>
        </tr>
        <tr>
            <td class="lab">DISPONIBLE FONDO DIVISAS</td>
            <td class="bs"></td>
            <td class="usd">{{ $usd((float) $reporte->fondo_divisas) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
