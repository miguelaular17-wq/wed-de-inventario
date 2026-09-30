@extends('layouts.app')

@section('title', 'Ventas diarias '.$reporte->sede)

@push('head')
<style>
.vd-wrap { max-width: 1200px; }
.vd-title { font-size: 1.25rem; font-weight: 700; margin: 0 0 4px; }
.vd-sub { color:#64748b; margin:0 0 14px; }
.vd-grid { display: grid; grid-template-columns: minmax(280px, 1fr) minmax(320px, 1.4fr); gap: 16px; }
@media (max-width: 960px) { .vd-grid { grid-template-columns: 1fr; } }
.vd-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; }
.vd-card h3 { margin:0 0 10px; font-size:.95rem; color:#334155; }
.vd-table { width:100%; border-collapse:collapse; font-size:.85rem; }
.vd-table th, .vd-table td { padding:6px 8px; border-bottom:1px solid #f1f5f9; }
.vd-table .lab { font-weight:600; }
.vd-usd { color:#059669; font-weight:700; }
.vd-pct.neg { color:#dc2626; }
.vd-pct.pos { color:#16a34a; }
.vd-cajas-wrap { overflow-x:auto; }
.vd-check { color:#16a34a; }
.vd-actions { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; }
.vd-totales td { font-weight:700; background:#f8fafc; }
</style>
@endpush

@section('content')
@php
    $pct = function (?float $v) {
        if ($v === null) return '—';
        $cls = $v >= 0 ? 'pos' : 'neg';
        return '<span class="vd-pct '.$cls.'">'.($v >= 0 ? '+' : '').number_format($v * 100, 1).'%</span>';
    };
@endphp
<div class="vd-wrap">
    <div class="vd-title">
        Reporte cierre de venta diaria: {{ config('inventario.display.'.$reporte->sede, $reporte->sede) }}
    </div>
    <p class="vd-sub">
        {{ $reporte->fecha->format('d/m/Y') }}
        · Tasa {{ number_format((float) $reporte->tasa, 2) }}
        · Meta venta ${{ number_format($metaCtx['meta_venta'], 2) }}
        · Meta productos {{ number_format($metaCtx['meta_productos'], 2) }}
        ({{ $metaCtx['es_domingo'] ? 'Domingo' : 'Lunes a sábado' }})
    </p>

    @if(session('success'))
        <div class="alert success" style="margin-bottom:12px;">{{ session('success') }}</div>
    @endif

    <div class="vd-grid">
        <div class="vd-card">
            <h3>DESGLOSE</h3>
            <table class="vd-table">
                <tr><td class="lab"><span class="vd-check">✅</span> Divisas en efectivo</td><td class="vd-usd">${{ number_format((float)$reporte->divisas_efectivo, 2) }}</td><td></td></tr>
                <tr><td class="lab"><span class="vd-check">✅</span> Efectivo Bs</td><td>{{ number_format((float)$reporte->efectivo_bs, 2) }}</td><td class="vd-usd">${{ number_format($totales['efectivo_bs_usd'], 2) }}</td></tr>
                <tr><td class="lab"><span class="vd-check">✅</span> Punto de venta</td><td>{{ number_format((float)$reporte->punto_venta_bs, 2) }}</td><td class="vd-usd">${{ number_format($totales['punto_venta_usd'], 2) }}</td></tr>
                <tr><td class="lab"><span class="vd-check">✅</span> Transf/P.M.</td><td>{{ number_format((float)$reporte->transf_pm_bs, 2) }}</td><td class="vd-usd">${{ number_format($totales['transf_pm_usd'], 2) }}</td></tr>
                <tr><td class="lab"><span class="vd-check">✅</span> Zelle y binance</td><td class="vd-usd">${{ number_format((float)$reporte->zelle_binance, 2) }}</td><td></td></tr>
                <tr><td class="lab"><span class="vd-check">✅</span> Cashea financiado</td><td class="vd-usd">${{ number_format((float)$reporte->cashea, 2) }}</td><td></td></tr>
                <tr><td class="lab"><span class="vd-check">✅</span> Abono apartado / Deuda</td><td class="vd-usd">${{ number_format((float)$reporte->abonos, 2) }}</td><td></td></tr>
                <tr><td class="lab"><span class="vd-check">✅</span> iPhone</td><td class="vd-usd">${{ number_format((float)$reporte->iphone, 2) }}</td><td></td></tr>
                <tr><td class="lab"><span class="vd-check">✅</span> Gift card</td><td class="vd-usd">${{ number_format((float)$reporte->gift_card, 2) }}</td><td></td></tr>
                <tr><td class="lab">Total cobros del dia</td><td class="vd-usd">${{ number_format($totales['total_cobros'], 2) }}</td><td></td></tr>
                <tr><td class="lab">Total creditos del dia</td><td class="vd-usd">${{ number_format($totales['total_creditos'], 2) }}</td><td></td></tr>
                <tr><td class="lab">Total de ventas del dia</td><td class="vd-usd">${{ number_format($totales['total_ventas'], 2) }}</td><td>{!! $pct($totales['pct_vs_meta_venta']) !!}</td></tr>
                <tr><td class="lab">Total de productos vendidos</td><td>{{ number_format((float)$reporte->productos_vendidos, 0) }}</td><td>{!! $pct($totales['pct_vs_meta_prod']) !!}</td></tr>
                <tr><td class="lab">Z FISCAL (Bs)</td><td>{{ number_format((float)$reporte->z_fiscal_bs, 2) }}</td><td></td></tr>
                <tr><td class="lab">Facturacion Fiscal</td><td class="vd-usd">${{ number_format($totales['facturacion_fiscal_usd'], 2) }}</td><td>{!! $pct($totales['pct_fiscal']) !!}</td></tr>
                <tr><td class="lab">Deliverys pendientes</td><td class="vd-usd">${{ number_format((float) $reporte->deliverys_pendientes, 2) }}</td><td></td></tr>
            </table>
            @if($reporte->observaciones)
                <p style="margin:12px 0 0;"><strong>Observaciones:</strong><br>{{ $reporte->observaciones }}</p>
            @endif
        </div>

        <div class="vd-card">
            <h3>CAJAS</h3>
            <div class="vd-cajas-wrap">
                <table class="vd-table">
                    <thead>
                        <tr>
                            <th>CAJA</th>
                            <th>EFECTIVO $</th>
                            <th>EFECTIVO BS</th>
                            <th>PUNTO</th>
                            <th>TRANS/PM</th>
                            <th>ZELLE</th>
                            <th>CASHEA</th>
                            <th>CREDITO</th>
                            <th>ABONOS</th>
                            <th>IPHONE</th>
                            <th>GIFT CARD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reporte->cajas as $c)
                            <tr>
                                <td><strong>{{ $c->nombre }}</strong></td>
                                <td>{{ number_format((float)$c->efectivo_usd, 2) }}</td>
                                <td>{{ number_format((float)$c->efectivo_bs, 2) }}</td>
                                <td>{{ number_format((float)$c->punto_venta, 2) }}</td>
                                <td>{{ number_format((float)$c->transf_pm, 2) }}</td>
                                <td>{{ number_format((float)$c->zelle_binance, 2) }}</td>
                                <td>{{ number_format((float)$c->cashea, 2) }}</td>
                                <td>{{ number_format((float)$c->fact_credito, 2) }}</td>
                                <td>{{ number_format((float)$c->abonos, 2) }}</td>
                                <td>{{ number_format((float)$c->iphone, 2) }}</td>
                                <td>{{ number_format((float)$c->gift_card, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="muted">Sin cajas registradas.</td></tr>
                        @endforelse
                        @if($reporte->cajas->isNotEmpty())
                            <tr class="vd-totales">
                                <td>Totales</td>
                                <td>{{ number_format($totalesCajas['efectivo_usd'], 2) }}</td>
                                <td>{{ number_format($totalesCajas['efectivo_bs'], 2) }}</td>
                                <td>{{ number_format($totalesCajas['punto_venta'], 2) }}</td>
                                <td>{{ number_format($totalesCajas['transf_pm'], 2) }}</td>
                                <td>{{ number_format($totalesCajas['zelle_binance'], 2) }}</td>
                                <td>{{ number_format($totalesCajas['cashea'], 2) }}</td>
                                <td>{{ number_format($totalesCajas['fact_credito'], 2) }}</td>
                                <td>{{ number_format($totalesCajas['abonos'], 2) }}</td>
                                <td>{{ number_format($totalesCajas['iphone'], 2) }}</td>
                                <td>{{ number_format($totalesCajas['gift_card'], 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="vd-actions">
        <a class="btn primary" href="{{ route('ventas_diarias.pdf', $reporte) }}">Descargar PDF</a>
        @if($puedeEditar)
            <a class="btn" href="{{ route('ventas_diarias.edit', $reporte) }}">Editar</a>
        @endif
        <a class="btn" href="{{ route('ventas_diarias.index', array_filter(['sede' => $verTodas ? $reporte->sede : null])) }}">Volver</a>
    </div>
</div>
@endsection
