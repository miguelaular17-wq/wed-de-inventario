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
            <p class="muted" style="margin:4px 0 0;">
                {{ $periodo['etiqueta'] }}
                · comparado con {{ $periodo['anterior_inicio']->format('d/m/Y') }} al {{ $periodo['anterior_fin']->format('d/m/Y') }}
            </p>
        </div>
    </div>

    <form method="GET" action="{{ route('gerencial.dashboard') }}" class="nomina-card" style="margin-top:16px;">
        <div class="nomina-form-grid">
            <div class="field">
                <label>Período</label>
                <select name="preset" onchange="this.form.hasta.disabled = this.value !== 'personalizado'; this.form.desde.disabled = this.value !== 'personalizado';">
                    <option value="mes" @selected($filtros['preset']==='mes')>Este mes</option>
                    <option value="mes_anterior" @selected($filtros['preset']==='mes_anterior')>Mes anterior</option>
                    <option value="quincena" @selected($filtros['preset']==='quincena')>Quincena actual</option>
                    <option value="personalizado" @selected($filtros['preset']==='personalizado')>Rango</option>
                </select>
            </div>
            <div class="field">
                <label>Desde</label>
                <input type="date" name="desde" value="{{ $filtros['desde'] }}" @disabled($filtros['preset']!=='personalizado')>
            </div>
            <div class="field">
                <label>Hasta</label>
                <input type="date" name="hasta" value="{{ $filtros['hasta'] }}" @disabled($filtros['preset']!=='personalizado')>
            </div>
            <div class="field">
                <label>Sede</label>
                <select name="sede">
                    <option value="todas">Todas las tiendas</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede }}" @selected($filtros['sede']===$sede)>{{ $sede }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Categoría</label>
                <select name="categoria">
                    <option value="">Todas</option>
                    @foreach($catalogos['categorias'] as $cat)
                        <option value="{{ $cat }}" @selected($filtros['categoria']===$cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Vendedor</label>
                <select name="vendedor">
                    <option value="">Todos</option>
                    @foreach($catalogos['vendedores'] as $vend)
                        <option value="{{ $vend }}" @selected($filtros['vendedor']===$vend)>{{ $vend }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Producto</label>
                <input name="producto" value="{{ $filtros['producto'] }}" placeholder="Código o nombre">
            </div>
            <div class="field" style="display:flex;align-items:flex-end;">
                <button class="btn primary" type="submit">Aplicar</button>
            </div>
        </div>
        <input type="hidden" name="ranking" value="{{ $filtros['ranking'] }}">
        @if($usaLineas)
            <p class="muted" style="margin:8px 0 0;">Con producto, categoría o vendedor el total es por líneas, no el de la factura de Profit.</p>
        @else
            <p class="muted" style="margin:8px 0 0;">Las ventas son el total de factura de Profit (FAC − DEV, solo registrados).</p>
        @endif
    </form>

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
        </div>
        <div class="nomina-kpi">
            <span>Unidades vendidas</span>
            <strong>{{ $fmt($total['unidades'], 0) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Margen</span>
            <strong>${{ $fmt($total['margen_usd']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Inventario (hoy)</span>
            <strong>${{ $fmt($total['inventario_valor']) }}</strong>
            <div class="muted" style="font-size:.75rem;">{{ $fmt($total['inventario_unidades'], 0) }} unds</div>
        </div>
        <div class="nomina-kpi">
            <span>Ajustes del período</span>
            <strong>{{ $fmt($total['ajustes_unidades'], 0) }}</strong>
            <div class="muted" style="font-size:.75rem;">${{ $fmt($total['ajustes_valor']) }}</div>
        </div>
    </div>

    <h3 style="margin:20px 0 8px;">Por sede</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sede</th>
                    <th>Ventas USD</th>
                    <th>Vs anterior</th>
                    <th>Ventas Bs</th>
                    <th>FAC</th>
                    <th>DEV</th>
                    <th>Valor DEV</th>
                    <th>Unidades</th>
                    <th>Margen</th>
                    <th>Inventario</th>
                    <th>Ajustes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($porSede as $fila)
                    <tr>
                        <td><strong>{{ $fila['sede'] }}</strong></td>
                        <td>${{ $fmt($fila['ventas_usd']) }}</td>
                        <td>{{ $deltaTxt($fila['delta_ventas_usd']) }}</td>
                        <td>{{ $fmt($fila['ventas_bs'], 0) }}</td>
                        <td>{{ number_format($fila['facturas']) }}</td>
                        <td>{{ number_format($fila['devoluciones']) }}</td>
                        <td>${{ $fmt($fila['devoluciones_usd']) }}</td>
                        <td>{{ $fmt($fila['unidades'], 0) }}</td>
                        <td>${{ $fmt($fila['margen_usd']) }}</td>
                        <td>${{ $fmt($fila['inventario_valor']) }}</td>
                        <td>{{ $fmt($fila['ajustes_unidades'], 0) }}</td>
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
    @endphp
    <div class="panel-header-flex" style="margin-top:20px; align-items:center;">
        <h3 style="margin:0;">Rankings</h3>
        <div class="segmented">
            <a href="{{ $rankingUrl('usd') }}" class="{{ $ranking === 'usd' ? 'active' : '' }}">Por USD</a>
            <a href="{{ $rankingUrl('unidades') }}" class="{{ $ranking === 'unidades' ? 'active' : '' }}">Por unidades</a>
        </div>
    </div>
    <div class="nomina-split" style="margin-top:12px;">
        <div class="nomina-card">
            <h3>Top productos</h3>
            <table class="data-table">
                <thead><tr><th>Producto</th><th>Unds</th><th>USD</th></tr></thead>
                <tbody>
                    @forelse($tops['productos'] as $item)
                        <tr>
                            <td>{{ $item['nombre'] }}</td>
                            <td>{{ $fmt($item['unidades'], 0) }}</td>
                            <td>${{ $fmt($item['ventas_usd']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin datos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card">
            <h3>Top vendedores</h3>
            <table class="data-table">
                <thead><tr><th>Vendedor</th><th>Unds</th><th>USD</th></tr></thead>
                <tbody>
                    @forelse($tops['vendedores'] as $item)
                        <tr>
                            <td>{{ $item['nombre'] }}</td>
                            <td>{{ $fmt($item['unidades'] ?? 0, 0) }}</td>
                            <td>${{ $fmt($item['ventas_usd']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin datos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card">
            <h3>Top categorías</h3>
            <table class="data-table">
                <thead><tr><th>Categoría</th><th>Unds</th><th>USD</th></tr></thead>
                <tbody>
                    @forelse($tops['categorias'] as $item)
                        <tr>
                            <td>{{ $item['nombre'] }}</td>
                            <td>{{ $fmt($item['unidades'] ?? 0, 0) }}</td>
                            <td>${{ $fmt($item['ventas_usd']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin datos en el período.</td></tr>
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
