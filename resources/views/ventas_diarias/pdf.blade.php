<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ventas diarias {{ $reporte->sede }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 12px; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 70px; }
        .header-logo img { height: 52px; width: 52px; }
        .header-titles { display: table-cell; vertical-align: middle; }
        h1 { font-size: 15px; color: #1e3a8a; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 3px; }
        h2 { font-size: 12px; margin: 12px 0 6px; color: #334155; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #dbeafe; color: #1e3a8a; font-size: 8px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #bfdbfe; text-align: left; }
        td { padding: 5px 6px; border: 1px solid #e2e8f0; }
        .num { text-align: right; white-space: nowrap; }
        .usd { color: #047857; font-weight: bold; text-align: right; }
        .tot td { font-weight: bold; background: #f1f5f9; }
        .neg { color: #dc2626; }
        .pos { color: #16a34a; }
    </style>
</head>
<body>
@php
    $pct = function (?float $v) {
        if ($v === null) return '—';
        return ($v >= 0 ? '+' : '').number_format($v * 100, 1).'%';
    };
@endphp
    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="header-titles">
            <h1>Reporte cierre de venta diaria: {{ config('inventario.display.'.$reporte->sede, $reporte->sede) }}</h1>
            <div class="sub">
                {{ $reporte->fecha->format('d/m/Y') }}
                · Tasa {{ number_format((float) $reporte->tasa, 2) }}
                · Meta venta ${{ number_format($metaCtx['meta_venta'], 2) }}
                · Meta productos {{ number_format($metaCtx['meta_productos'], 2) }}
                · {{ $metaCtx['es_domingo'] ? 'Domingo' : 'Lunes a sábado' }}
            </div>
        </div>
    </div>

    <h2>Desglose</h2>
    <table>
        <tbody>
            <tr><td>Divisas en efectivo</td><td class="usd">${{ number_format((float) $reporte->divisas_efectivo, 2) }}</td><td></td></tr>
            <tr><td>Efectivo Bs</td><td class="num">{{ number_format((float) $reporte->efectivo_bs, 2) }}</td><td class="usd">${{ number_format($totales['efectivo_bs_usd'], 2) }}</td></tr>
            <tr><td>Punto de venta</td><td class="num">{{ number_format((float) $reporte->punto_venta_bs, 2) }}</td><td class="usd">${{ number_format($totales['punto_venta_usd'], 2) }}</td></tr>
            <tr><td>Transf/P.M.</td><td class="num">{{ number_format((float) $reporte->transf_pm_bs, 2) }}</td><td class="usd">${{ number_format($totales['transf_pm_usd'], 2) }}</td></tr>
            <tr><td>Zelle y binance</td><td class="usd">${{ number_format((float) $reporte->zelle_binance, 2) }}</td><td></td></tr>
            <tr><td>Cashea financiado</td><td class="usd">${{ number_format((float) $reporte->cashea, 2) }}</td><td></td></tr>
            <tr><td>Abono apartado / Deuda</td><td class="usd">${{ number_format((float) $reporte->abonos, 2) }}</td><td></td></tr>
            <tr><td>iPhone</td><td class="usd">${{ number_format((float) $reporte->iphone, 2) }}</td><td></td></tr>
            <tr><td>Gift card</td><td class="usd">${{ number_format((float) $reporte->gift_card, 2) }}</td><td></td></tr>
            <tr class="tot"><td>Total cobros del día</td><td class="usd">${{ number_format($totales['total_cobros'], 2) }}</td><td></td></tr>
            <tr><td>Total créditos del día</td><td class="usd">${{ number_format($totales['total_creditos'], 2) }}</td><td></td></tr>
            <tr class="tot"><td>Total de ventas del día</td><td class="usd">${{ number_format($totales['total_ventas'], 2) }}</td><td class="{{ ($totales['pct_vs_meta_venta'] ?? 0) >= 0 ? 'pos' : 'neg' }}">{{ $pct($totales['pct_vs_meta_venta']) }}</td></tr>
            <tr><td>Total de productos vendidos</td><td class="num">{{ number_format((float) $reporte->productos_vendidos, 0) }}</td><td class="{{ ($totales['pct_vs_meta_prod'] ?? 0) >= 0 ? 'pos' : 'neg' }}">{{ $pct($totales['pct_vs_meta_prod']) }}</td></tr>
            <tr><td>Z fiscal (Bs)</td><td class="num">{{ number_format((float) $reporte->z_fiscal_bs, 2) }}</td><td></td></tr>
            <tr><td>Facturación fiscal</td><td class="usd">${{ number_format($totales['facturacion_fiscal_usd'], 2) }}</td><td>{{ $pct($totales['pct_fiscal']) }}</td></tr>
            <tr><td>Deliverys pendientes</td><td class="usd">${{ number_format((float) $reporte->deliverys_pendientes, 2) }}</td><td></td></tr>
        </tbody>
    </table>

    @if($reporte->observaciones)
        <p style="margin-top:8px;"><strong>Observaciones:</strong> {{ $reporte->observaciones }}</p>
    @endif

    <h2>Cajas</h2>
    <table>
        <thead>
            <tr>
                <th>Caja</th>
                <th>Efectivo $</th>
                <th>Efectivo Bs</th>
                <th>Punto</th>
                <th>Trans/PM</th>
                <th>Zelle</th>
                <th>Cashea</th>
                <th>Crédito</th>
                <th>Abonos</th>
                <th>iPhone</th>
                <th>Gift card</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reporte->cajas as $c)
                <tr>
                    <td>{{ $c->nombre }}</td>
                    <td class="num">{{ number_format((float) $c->efectivo_usd, 2) }}</td>
                    <td class="num">{{ number_format((float) $c->efectivo_bs, 2) }}</td>
                    <td class="num">{{ number_format((float) $c->punto_venta, 2) }}</td>
                    <td class="num">{{ number_format((float) $c->transf_pm, 2) }}</td>
                    <td class="num">{{ number_format((float) $c->zelle_binance, 2) }}</td>
                    <td class="num">{{ number_format((float) $c->cashea, 2) }}</td>
                    <td class="num">{{ number_format((float) $c->fact_credito, 2) }}</td>
                    <td class="num">{{ number_format((float) $c->abonos, 2) }}</td>
                    <td class="num">{{ number_format((float) $c->iphone, 2) }}</td>
                    <td class="num">{{ number_format((float) $c->gift_card, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="11">Sin cajas registradas.</td></tr>
            @endforelse
            @if($reporte->cajas->isNotEmpty())
                <tr class="tot">
                    <td>Totales</td>
                    <td class="num">{{ number_format($totalesCajas['efectivo_usd'], 2) }}</td>
                    <td class="num">{{ number_format($totalesCajas['efectivo_bs'], 2) }}</td>
                    <td class="num">{{ number_format($totalesCajas['punto_venta'], 2) }}</td>
                    <td class="num">{{ number_format($totalesCajas['transf_pm'], 2) }}</td>
                    <td class="num">{{ number_format($totalesCajas['zelle_binance'], 2) }}</td>
                    <td class="num">{{ number_format($totalesCajas['cashea'], 2) }}</td>
                    <td class="num">{{ number_format($totalesCajas['fact_credito'], 2) }}</td>
                    <td class="num">{{ number_format($totalesCajas['abonos'], 2) }}</td>
                    <td class="num">{{ number_format($totalesCajas['iphone'], 2) }}</td>
                    <td class="num">{{ number_format($totalesCajas['gift_card'], 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
