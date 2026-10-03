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
.vd-desglose-head { text-align:center; }
.vd-desglose-head h3 { margin-bottom:2px; }
.vd-desglose-head p { margin:0 0 10px; color:#64748b; font-size:.85rem; }
.vd-table { width:100%; border-collapse:collapse; font-size:.85rem; }
.vd-table th, .vd-table td { padding:6px 8px; border-bottom:1px solid #f1f5f9; }
.vd-table .lab { font-weight:600; white-space:nowrap; }
.vd-table .bs, .vd-table .usd, .vd-table .num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
.vd-table .bs { color:#334155; font-weight:600; }
.vd-table .usd { color:#059669; font-weight:700; }
.vd-credito .lab { color:#dc2626; }
.vd-venta td { background:#d1fae5; border-top:2px solid #059669; border-bottom:2px solid #059669; }
.vd-venta .lab { color:#064e3b; font-size:1rem; }
.vd-venta .usd { font-size:1.12rem; }
.vd-unidades .usd { text-align:center; color:#059669; font-weight:700; font-size:1.12rem; }
.vd-cajas-wrap { overflow-x:auto; }
.vd-actions { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; }
.vd-totales td { font-weight:700; background:#f8fafc; }
</style>
@endpush

@section('content')
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
            <div class="vd-desglose-head">
                <h3>DESGLOSE VENTAS DEL DIA</h3>
                <p>{{ $reporte->fecha->format('d/m/Y') }} · Tasa {{ number_format((float) $reporte->tasa, 2) }}</p>
            </div>
            @include('ventas_diarias._desglose')
            @if($reporte->observaciones)
                <p style="margin:12px 0 0;"><strong>Observaciones:</strong><br>{{ $reporte->observaciones }}</p>
            @endif
        </div>

        <div class="vd-card">
            <h3>CAJAS</h3>
            <div class="vd-cajas-wrap">
                @include('ventas_diarias._cajas')
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
