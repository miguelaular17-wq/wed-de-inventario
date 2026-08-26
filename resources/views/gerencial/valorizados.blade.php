@extends('layouts.app')

@section('title', 'Valorizados de inventarios')

@php $fmt = fn ($n, $d = 2) => number_format((float) $n, $d); @endphp

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Valorizados de inventarios</h1>
            <p class="gerencial-pregunta">¿Dónde está nuestro dinero y qué tan sano está el inventario?</p>
            <p class="muted" style="margin:4px 0 0;">Stock a hoy × costo. El período se usa para conectar con el resto de dashboards.</p>
        </div>
    </div>

    @include('gerencial._tabs')
    @include('gerencial._filtros', ['modo' => 'valorizados', 'action' => route('gerencial.valorizados')])

    <div class="nomina-kpis">
        <div class="nomina-kpi">
            <span>Valor total inventario</span>
            <strong>${{ $fmt($kpis['valor']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Costo total</span>
            <strong>${{ $fmt($kpis['costo']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Unidades</span>
            <strong>{{ $fmt($kpis['unidades'], 0) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Productos</span>
            <strong>{{ number_format($kpis['productos']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Sedes con inventario</span>
            <strong>{{ number_format($kpis['sedes']) }}</strong>
        </div>
        <div class="nomina-kpi warn">
            <span>Sin rotación</span>
            <strong>${{ $fmt($kpis['sin_rotacion']) }}</strong>
        </div>
        <div class="nomina-kpi warn">
            <span>Inventario &gt;90 días</span>
            <strong>${{ $fmt($kpis['gt90']) }}</strong>
        </div>
        <div class="nomina-kpi warn">
            <span>Inventario &gt;6 meses</span>
            <strong>${{ $fmt($kpis['gt6m']) }}</strong>
        </div>
    </div>

    <div class="gerencial-grid-2">
        <div class="nomina-card">
            <h3>Distribución del inventario</h3>
            <p class="muted">Total ${{ $fmt($kpis['valor']) }}</p>
            <div class="gerencial-chart"><canvas id="chart-inv-sede"></canvas></div>
        </div>
        <div class="nomina-card">
            <h3>Clasificación de inventario</h3>
            <table class="data-table">
                <thead><tr><th>Estado</th><th>Productos</th><th>Valor</th></tr></thead>
                <tbody>
                    @foreach($clasificacion as $estado => $fila)
                        <tr>
                            <td>
                                <span class="gerencial-clase gerencial-clase-{{ $fila['color'] }}">
                                    @if($fila['color']==='verde')🟢
                                    @elseif($fila['color']==='amarillo')🟡
                                    @elseif($fila['color']==='naranja')🟠
                                    @else 🔴
                                    @endif
                                    {{ $estado }}
                                </span>
                            </td>
                            <td>{{ number_format($fila['productos']) }}</td>
                            <td>${{ $fmt($fila['valor']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="gerencial-grid-2" style="margin-top:16px;">
        <div class="nomina-card">
            <h3>Por sede</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sede</th>
                        <th>Unidades</th>
                        <th>Costo</th>
                        <th>Valor</th>
                        <th>% Inventario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($por_sede as $fila)
                        <tr>
                            <td><strong>{{ $fila->sede }}</strong></td>
                            <td>{{ $fmt($fila->unidades, 0) }}</td>
                            <td>${{ $fmt($fila->costo) }}</td>
                            <td>${{ $fmt($fila->valor) }}</td>
                            <td>{{ $fmt($fila->pct, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Sin inventario valorizado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card">
            <h3>Por categoría</h3>
            <table class="data-table">
                <thead><tr><th>Categoría</th><th>Unidades</th><th>Valor</th></tr></thead>
                <tbody>
                    @forelse($por_categoria as $fila)
                        <tr>
                            <td>{{ $fila->nombre }}</td>
                            <td>{{ $fmt($fila->unidades, 0) }}</td>
                            <td>${{ $fmt($fila->valor) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin categorías para valorizar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Por marca / proveedor</h3>
        <table class="data-table">
            <thead><tr><th>Marca</th><th>Unidades</th><th>Valor</th></tr></thead>
            <tbody>
                @forelse($por_marca as $fila)
                    <tr>
                        <td>{{ $fila->nombre }}</td>
                        <td>{{ $fmt($fila->unidades, 0) }}</td>
                        <td>${{ $fmt($fila->valor) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Sin marca o proveedor para agrupar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Rotación — capital inmovilizado</h3>
        <p class="muted">Top productos con más meses de inventario. Ventas 30/60 días desde hoy.</p>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Inventario</th>
                        <th>Ventas 30d</th>
                        <th>Ventas 60d</th>
                        <th>Días sin venta</th>
                        <th>Meses de inventario</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rotacion as $item)
                        <tr>
                            <td>{{ $item['nombre'] ?: $item['codigo'] }}</td>
                            <td>{{ $fmt($item['unidades'], 0) }} · ${{ $fmt($item['valor']) }}</td>
                            <td>{{ $fmt($item['ventas_30d'], 0) }}</td>
                            <td>{{ $fmt($item['ventas_60d'], 0) }}</td>
                            <td>{{ $item['dias_sin_venta'] >= 999 ? 'Sin venta' : $item['dias_sin_venta'] }}</td>
                            <td>{{ $item['meses_inventario'] >= 999 ? '∞' : $fmt($item['meses_inventario'], 1) }}</td>
                            <td>{{ $item['clase'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">Sin productos para analizar rotación.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const porSede = @json($por_sede->values());
const canvas = document.getElementById('chart-inv-sede');
if (canvas && porSede.length) {
    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: porSede.map(r => r.sede),
            datasets: [{
                data: porSede.map(r => Number(r.valor)),
                backgroundColor: ['#1e3a8a','#0ea5e9','#059669','#d97706','#7c3aed','#dc2626','#64748b'],
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right' } }
        }
    });
}
</script>
@endpush
