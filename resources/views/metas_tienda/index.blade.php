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
                    Gerencia: la meta del mes se edita por sede. Este mes tiene {{ $diasMes }} días y {{ $domingosMes }} domingos. La diaria reparte lo que queda después de esos domingos, y el domingo es la mitad de esa diaria.
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
                    <input type="text" name="periodo_label" id="mt-periodo" value="{{ old('periodo_label', $periodo) }}" placeholder="META MES SEPTIEMBRE">
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
                                        @php
                                            $ventaMes = (float) old('metas.'.$i.'.venta_meta_mes', $m->venta_meta_mes);
                                            $calcVenta = app(\App\Services\VentasDiariasService::class)->metaVentaDiaria($ventaMes, (int) $diasMes, (int) $domingosMes);
                                        @endphp
                                        <td class="mt-diaria" data-i="{{ $i }}">{{ number_format($calcVenta['diaria'], 4) }}</td>
                                        <td class="mt-domingo" data-i="{{ $i }}">{{ number_format($calcVenta['domingo'], 4) }}</td>
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
                                        @php
                                            $prodMes = (float) old('metas.'.$i.'.productos_meta_mes', $m->productos_meta_mes);
                                            $calcProd = app(\App\Services\VentasDiariasService::class)->metaVentaDiaria($prodMes, (int) $diasMes, (int) $domingosMes);
                                        @endphp
                                        <td class="mt-prod-diaria" data-i="{{ $i }}">{{ number_format($calcProd['diaria'], 4) }}</td>
                                        <td class="mt-prod-domingo" data-i="{{ $i }}">{{ number_format($calcProd['domingo'], 4) }}</td>
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
                                            <input type="number" step="0.01" class="mt-venta-mes" data-i="{{ $i }}" name="metas[{{ $i }}][venta_meta_mes]" value="{{ old('metas.'.$i.'.venta_meta_mes', $m->venta_meta_mes) }}">
                                        @else
                                            {{ $fmt($m->venta_meta_mes) }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($editable)
                                            <input type="number" step="0.01" class="mt-prod-mes" data-i="{{ $i }}" name="metas[{{ $i }}][productos_meta_mes]" value="{{ old('metas.'.$i.'.productos_meta_mes', $m->productos_meta_mes) }}">
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
@if($editable && $metas->isNotEmpty())
<script>
(function () {
    const meses = {ENERO:1,FEBRERO:2,MARZO:3,ABRIL:4,MAYO:5,JUNIO:6,JULIO:7,AGOSTO:8,SEPTIEMBRE:9,OCTUBRE:10,NOVIEMBRE:11,DICIEMBRE:12};
    let dias = {{ (int) $diasMes }};
    let domingos = {{ (int) $domingosMes }};
    const fmt = (n) => (Number(n) || 0).toLocaleString('en-US', {minimumFractionDigits: 4, maximumFractionDigits: 4});
    function calendarioDe(label) {
        const up = (label || '').toUpperCase();
        const anio = (up.match(/20\d{2}/) || [])[0];
        for (const [nombre, num] of Object.entries(meses)) {
            if (!up.includes(nombre)) continue;
            const y = anio ? Number(anio) : new Date().getFullYear();
            const total = new Date(y, num, 0).getDate();
            let sundays = 0;
            for (let d = 1; d <= total; d++) {
                if (new Date(y, num - 1, d).getDay() === 0) sundays++;
            }
            return {dias: total, domingos: sundays};
        }
        return {dias, domingos};
    }
    function reparto(meta) {
        const habiles = Math.max(1, dias - domingos);
        const dia = dias > 0 ? (Number(meta) || 0) / dias : 0;
        const resto = (Number(meta) || 0) - domingos * (dia / 2);
        const diaria = Math.round((resto / habiles) * 10000) / 10000;
        return {diaria, domingo: Math.round((diaria / 2) * 10000) / 10000};
    }
    function pintarFila(input, diariaSel, domingoSel) {
        const i = input.dataset.i;
        const calc = reparto(parseFloat(input.value || '0') || 0);
        const celda = document.querySelector(diariaSel + '[data-i="' + i + '"]');
        const domingo = document.querySelector(domingoSel + '[data-i="' + i + '"]');
        if (celda) celda.textContent = fmt(calc.diaria);
        if (domingo) domingo.textContent = fmt(calc.domingo);
    }
    function pintar() {
        document.querySelectorAll('.mt-venta-mes').forEach((input) => pintarFila(input, '.mt-diaria', '.mt-domingo'));
        document.querySelectorAll('.mt-prod-mes').forEach((input) => pintarFila(input, '.mt-prod-diaria', '.mt-prod-domingo'));
    }
    document.getElementById('mt-periodo')?.addEventListener('input', (e) => {
        const cal = calendarioDe(e.target.value);
        dias = cal.dias;
        domingos = cal.domingos;
        pintar();
    });
    document.querySelectorAll('.mt-venta-mes, .mt-prod-mes').forEach((input) => input.addEventListener('input', pintar));
})();
</script>
@endif
@endsection
