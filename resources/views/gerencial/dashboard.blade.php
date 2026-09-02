@extends('layouts.app')

@section('title', 'Dashboard gerencial')

@php
    $fmt = fn ($n, $d = 2) => number_format((float) $n, $d);
    $deltaTxt = function ($delta) {
        if ($delta === null) return '—';
        $signo = $delta > 0 ? '+' : '';
        return $signo.number_format($delta, 1).'%';
    };
@endphp

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Dashboard gerencial</h1>
            @include('gerencial._tabs')
        </div>
    </div>

    @include('gerencial._filtros', ['modo' => 'completo', 'action' => route('gerencial.dashboard')])

    <h3 style="margin:20px 0 8px;">Compañía</h3>
    <div class="nomina-kpis">
        <div class="nomina-kpi">
            <span>Ventas</span>
            <strong>${{ $fmt($total['ventas_usd']) }}</strong>
            <div class="muted" style="font-size:.75rem;">Bs {{ $fmt($total['ventas_bs'], 2) }} · {{ $deltaTxt($total['delta_ventas_usd']) }} vs anterior</div>
        </div>
        <div class="nomina-kpi">
            <span>Facturas</span>
            <strong>{{ number_format($total['facturas']) }}</strong>
        </div>
        <div class="nomina-kpi warn">
            <span>Devoluciones</span>
            <strong>{{ number_format($total['devoluciones']) }}</strong>
            <div class="muted" style="font-size:.75rem;">${{ $fmt($total['devoluciones_usd']) }}</div>
            @if(auth()->user()->canAccess('gerencial.devoluciones'))
                <a href="{{ route('gerencial.devoluciones', request()->except('page')) }}" class="gerencial-kpi-link">Ver módulo</a>
            @endif
        </div>
        <div class="nomina-kpi">
            <span>Unidades vendidas</span>
            <strong>{{ $fmt($total['unidades'], 0) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Margen</span>
            <strong>${{ $fmt($total['margen_usd']) }}</strong>
            @if(auth()->user()->canAccess('gerencial.rentabilidad'))
                <a href="{{ route('gerencial.rentabilidad', request()->except(['page', 'ranking'])) }}" class="gerencial-kpi-link">Ver rentabilidad</a>
            @endif
        </div>
        <div class="nomina-kpi">
            <span>Inventario (hoy)</span>
            <strong>${{ $fmt($total['inventario_valor']) }}</strong>
            <div class="muted" style="font-size:.75rem;">{{ $fmt($total['inventario_unidades'], 0) }} unds</div>
            @if(auth()->user()->canAccess('gerencial.valorizados'))
                <a href="{{ route('gerencial.valorizados', request()->except(['page', 'ranking'])) }}" class="gerencial-kpi-link">Ver valorizado</a>
            @endif
        </div>
        <div class="nomina-kpi">
            <span>Ajustes del período</span>
            <strong>{{ $fmt($total['ajustes_unidades'], 0) }}</strong>
            <div class="muted" style="font-size:.75rem;">${{ $fmt($total['ajustes_valor']) }}</div>
            @if(auth()->user()->canAccess('gerencial.ajustes'))
                <a href="{{ route('gerencial.ajustes', request()->except(['page', 'ranking'])) }}" class="gerencial-kpi-link">Ver consolidado</a>
            @endif
        </div>
    </div>

    <h3 style="margin:20px 0 8px;">Por sede</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sede</th>
                    <th>Ventas</th>
                    <th>DEV</th>
                    <th>Venta neta</th>
                    <th>FAC</th>
                    <th>Nº DEV</th>
                    <th>Productos</th>
                    <th>Utilidad</th>
                    <th>% de utilidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($porSede as $fila)
                    <tr>
                        <td><strong>{{ $fila['sede'] }}</strong></td>
                        <td>${{ $fmt($fila['ventas_brutas'] ?? ($fila['venta_neta'] + $fila['devoluciones_usd'])) }}</td>
                        <td>${{ $fmt($fila['devoluciones_usd']) }}</td>
                        <td>${{ $fmt($fila['venta_neta'] ?? $fila['ventas_usd']) }}</td>
                        <td>{{ number_format($fila['facturas']) }}</td>
                        <td>{{ number_format($fila['devoluciones']) }}</td>
                        <td>{{ number_format($fila['productos']) }}</td>
                        <td>${{ $fmt($fila['utilidad'] ?? $fila['margen_usd']) }}</td>
                        <td>{{ $fmt($fila['margen_pct'] ?? 0, 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php
        $ranking = $filtros['ranking'] ?? 'usd';
        $rankingUrl = function (string $modo) use ($filtros) {
            return route('gerencial.dashboard', array_filter([
                'preset' => $filtros['preset'] ?? null,
                'desde' => ($filtros['preset'] ?? '') === 'personalizado' ? ($filtros['desde'] ?? null) : null,
                'hasta' => ($filtros['preset'] ?? '') === 'personalizado' ? ($filtros['hasta'] ?? null) : null,
                'sede' => ($filtros['sede'] ?? 'todas') !== 'todas' ? $filtros['sede'] : null,
                'categoria' => $filtros['categoria'] ?: null,
                'vendedor' => $filtros['vendedor'] ?: null,
                'producto' => $filtros['producto'] ?: null,
                'ranking' => $modo,
            ], fn ($v) => $v !== null && $v !== ''));
        };
        $rankingMeta = match ($ranking) {
            'unidades' => ['header' => 'Unds', 'celda' => fn ($item) => $fmt($item['unidades'] ?? 0, 0)],
            'clientes' => ['header' => 'Clientes', 'celda' => fn ($item) => number_format($item['clientes'] ?? 0)],
            'utilidad' => ['header' => 'Utilidad', 'celda' => fn ($item) => '$'.$fmt($item['utilidad'] ?? 0)],
            default => ['header' => 'USD', 'celda' => fn ($item) => '$'.$fmt($item['ventas_usd'] ?? 0)],
        };
    @endphp
    <div class="panel-header-flex" style="margin-top:20px; align-items:center;">
        <h3 style="margin:0;">Rankings</h3>
        <div class="segmented">
            <a href="{{ $rankingUrl('usd') }}" class="{{ $ranking === 'usd' ? 'active' : '' }}">Por USD</a>
            <a href="{{ $rankingUrl('unidades') }}" class="{{ $ranking === 'unidades' ? 'active' : '' }}">Por unidades</a>
            <a href="{{ $rankingUrl('clientes') }}" class="{{ $ranking === 'clientes' ? 'active' : '' }}">Por clientes</a>
            <a href="{{ $rankingUrl('utilidad') }}" class="{{ $ranking === 'utilidad' ? 'active' : '' }}">Por utilidad</a>
        </div>
    </div>
    <div class="nomina-split" style="margin-top:12px;">
        <div class="nomina-card">
            <h3>Top productos</h3>
            <table class="data-table">
                <thead><tr><th>Producto</th><th>{{ $rankingMeta['header'] }}</th></tr></thead>
                <tbody>
                    @forelse($tops['productos'] as $item)
                        <tr>
                            <td>{{ $item['nombre'] }}</td>
                            <td>{{ $rankingMeta['celda']($item) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="muted">Sin datos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card">
            <h3>Top vendedores</h3>
            <table class="data-table">
                <thead><tr><th>Vendedor</th><th>{{ $rankingMeta['header'] }}</th></tr></thead>
                <tbody>
                    @forelse($tops['vendedores'] as $item)
                        <tr>
                            <td>{{ $item['nombre'] }}</td>
                            <td>{{ $rankingMeta['celda']($item) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="muted">Sin datos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card">
            <h3>Top categorías</h3>
            <table class="data-table">
                <thead><tr><th>Categoría</th><th>{{ $rankingMeta['header'] }}</th></tr></thead>
                <tbody>
                    @forelse($tops['categorias'] as $item)
                        <tr>
                            <td>{{ $item['nombre'] }}</td>
                            <td>{{ $rankingMeta['celda']($item) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="muted">Sin datos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($diario)
        <div class="nomina-card" style="margin-top:20px;">
            <h3>Ventas por día</h3>
            <div style="position:relative;height:260px;">
                <canvas id="gerencial-diario"></canvas>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
@if($diario)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const diario = @json($diario);
new Chart(document.getElementById('gerencial-diario'), {
    type: 'line',
    data: {
        labels: diario.map(r => r.fecha),
        datasets: [{
            label: 'Ventas USD',
            data: diario.map(r => r.ventas_usd),
            borderColor: '#1a4480',
            backgroundColor: 'rgba(26,68,128,.12)',
            fill: true,
            tension: .25,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>
@endif
@endpush
