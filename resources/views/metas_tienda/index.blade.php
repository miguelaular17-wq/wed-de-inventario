@extends('layouts.app')

@section('title', 'Metas de tienda')

@push('head')
<style>
.mt-page { max-width: 1100px; }
.mt-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 16px; align-items: start; }
@media (max-width: 900px) { .mt-grid { grid-template-columns: 1fr; } }
.mt-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; overflow:auto; }
.mt-card h2 { margin:0 0 10px; font-size:1rem; color:#14532d; }
.mt-table { width:100%; border-collapse:collapse; font-size:.85rem; }
.mt-table th {
    background:#166534; color:#fff; padding:8px 10px; text-align:left; font-size:.75rem;
    text-transform:uppercase; letter-spacing:.03em; white-space:nowrap;
}
.mt-table td { padding:6px 8px; border-bottom:1px solid #e2e8f0; }
.mt-table .sede { background:#dbeafe; font-weight:700; color:#1e3a8a; white-space:nowrap; }
.mt-table input {
    width:100%; min-width:88px; padding:5px 7px; border:1px solid #cbd5e1; border-radius:6px; font-weight:600;
}
.mt-table .total-row td { background:#e2e8f0; font-weight:700; }
.mt-stack { display:flex; flex-direction:column; gap:16px; }
.mt-head { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:14px; }
.mt-head h1 { margin:0; font-size:1.4rem; }
.mt-period input { min-width:260px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-weight:600; }
</style>
@endpush

@section('content')
@php
    $fmt = fn ($n) => number_format((float) $n, 2);
    $totHist = $metas->sum(fn ($m) => (float) $m->venta_historica);
    $totVenta = $metas->sum(fn ($m) => (float) $m->venta_meta_mes);
    $totProd = $metas->sum(fn ($m) => (float) $m->productos_meta_mes);
    $totCli = $metas->sum(fn ($m) => (float) $m->clientes_meta_mes);
@endphp
<div class="mt-page">
    <div class="mt-head">
        <div>
            <h1>Metas de tienda</h1>
            <p class="muted" style="margin:4px 0 0;">
                @if($editable)
                    Gerencia: edita metas diarias, de domingo y metas mensuales por sede.
                @else
                    Meta de tu sede <strong>{{ config('inventario.display.'.$sedeFiltro, $sedeFiltro) }}</strong>.
                @endif
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-bottom:12px;">{{ session('success') }}</div>
    @endif

    @if($metas->isEmpty())
        <div class="mt-card"><p class="muted" style="margin:0;">No hay metas cargadas para esta sede.</p></div>
    @else
        <form method="POST" action="{{ route('metas_tienda.update') }}">
            @csrf
            @method('PUT')

            @if($editable)
                <div class="mt-period" style="margin-bottom:12px;">
                    <label class="muted" style="font-size:.75rem;font-weight:700;text-transform:uppercase;">Periodo / etiqueta meta mes</label><br>
                    <input type="text" name="periodo_label" value="{{ old('periodo_label', $periodo) }}" placeholder="META MES SEPTIEMBRE">
                </div>
            @elseif($periodo)
                <p style="margin:0 0 12px;"><strong>{{ $periodo }}</strong></p>
            @endif

            <div class="mt-grid">
                <div class="mt-stack">
                    <div class="mt-card">
                        <h2>VENTAS</h2>
                        <table class="mt-table">
                            <thead>
                                <tr>
                                    <th>Sede</th>
                                    <th>Meta diaria</th>
                                    <th>Meta domingo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($metas as $i => $m)
                                    <tr>
                                        <td class="sede">
                                            {{ config('inventario.display.'.$m->sede, $m->sede) }}
                                            @if($editable)
                                                <input type="hidden" name="metas[{{ $i }}][id]" value="{{ $m->id }}">
                                            @endif
                                        </td>
                                        <td>
                                            @if($editable)
                                                <input type="number" step="0.0001" name="metas[{{ $i }}][meta_venta_lv_sab]" value="{{ old('metas.'.$i.'.meta_venta_lv_sab', $m->meta_venta_lv_sab) }}">
                                            @else
                                                {{ $fmt($m->meta_venta_lv_sab) }}
                                            @endif
                                        </td>
                                        <td>
                                            @if($editable)
                                                <input type="number" step="0.0001" name="metas[{{ $i }}][meta_venta_domingo]" value="{{ old('metas.'.$i.'.meta_venta_domingo', $m->meta_venta_domingo) }}">
                                            @else
                                                {{ $fmt($m->meta_venta_domingo) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-card">
                        <h2>PRODUCTOS</h2>
                        <table class="mt-table">
                            <thead>
                                <tr>
                                    <th>Sede</th>
                                    <th>Meta diaria</th>
                                    <th>Meta domingo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($metas as $i => $m)
                                    <tr>
                                        <td class="sede">{{ config('inventario.display.'.$m->sede, $m->sede) }}</td>
                                        <td>
                                            @if($editable)
                                                <input type="number" step="0.0001" name="metas[{{ $i }}][meta_prod_lv_sab]" value="{{ old('metas.'.$i.'.meta_prod_lv_sab', $m->meta_prod_lv_sab) }}">
                                            @else
                                                {{ $fmt($m->meta_prod_lv_sab) }}
                                            @endif
                                        </td>
                                        <td>
                                            @if($editable)
                                                <input type="number" step="0.0001" name="metas[{{ $i }}][meta_prod_domingo]" value="{{ old('metas.'.$i.'.meta_prod_domingo', $m->meta_prod_domingo) }}">
                                            @else
                                                {{ $fmt($m->meta_prod_domingo) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-card">
                    <h2>{{ $periodo ?: 'META TIENDAS' }}</h2>
                    <table class="mt-table">
                        <thead>
                            <tr>
                                <th>Sede</th>
                                <th>Venta año ant.</th>
                                <th>Venta meta</th>
                                <th>Productos</th>
                                <th>Clientes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($metas as $i => $m)
                                <tr>
                                    <td class="sede">{{ config('inventario.display.'.$m->sede, $m->sede) }}</td>
                                    <td>
                                        @if($editable)
                                            <input type="number" step="0.01" name="metas[{{ $i }}][venta_historica]" value="{{ old('metas.'.$i.'.venta_historica', $m->venta_historica) }}">
                                        @else
                                            {{ $fmt($m->venta_historica) }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($editable)
                                            <input type="number" step="0.01" name="metas[{{ $i }}][venta_meta_mes]" value="{{ old('metas.'.$i.'.venta_meta_mes', $m->venta_meta_mes) }}">
                                        @else
                                            {{ $fmt($m->venta_meta_mes) }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($editable)
                                            <input type="number" step="0.01" name="metas[{{ $i }}][productos_meta_mes]" value="{{ old('metas.'.$i.'.productos_meta_mes', $m->productos_meta_mes) }}">
                                        @else
                                            {{ $fmt($m->productos_meta_mes) }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($editable)
                                            <input type="number" step="0.01" name="metas[{{ $i }}][clientes_meta_mes]" value="{{ old('metas.'.$i.'.clientes_meta_mes', $m->clientes_meta_mes) }}">
                                        @else
                                            {{ $fmt($m->clientes_meta_mes) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="total-row">
                                <td>TOTAL META</td>
                                <td>{{ $fmt($totHist) }}</td>
                                <td>{{ $fmt($totVenta) }}</td>
                                <td>{{ $fmt($totProd) }}</td>
                                <td>{{ $fmt($totCli) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            @if($editable)
                <div style="margin-top:14px;">
                    <button class="btn primary" type="submit">Guardar metas</button>
                </div>
            @endif
        </form>
    @endif
</div>
@endsection
