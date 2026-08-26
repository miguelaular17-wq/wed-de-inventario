@extends('layouts.app')

@section('title', 'Rentabilidad gerencial')

@php $fmt = fn ($n, $d = 2) => number_format((float) $n, $d); @endphp

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Rentabilidad</h1>
            <p class="gerencial-pregunta">¿Cuánto estamos ganando realmente?</p>
            <p class="muted" style="margin:4px 0 0;">{{ $periodo['etiqueta'] }} · ventas − costo (FAC − DEV)</p>
        </div>
    </div>

    @include('gerencial._tabs')
    @include('gerencial._filtros', ['modo' => 'rentabilidad', 'action' => route('gerencial.rentabilidad')])

    <div class="nomina-kpis">
        <div class="nomina-kpi">
            <span>Ventas</span>
            <strong>${{ $fmt($kpis['ventas']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Costo</span>
            <strong>${{ $fmt($kpis['costo']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Utilidad bruta</span>
            <strong>${{ $fmt($kpis['utilidad']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>% margen</span>
            <strong>{{ $fmt($kpis['margen_pct'], 1) }}%</strong>
        </div>
    </div>

    <div class="gerencial-grid-2">
        <div class="nomina-card">
            <h3>Margen por sede</h3>
            <div class="gerencial-chart"><canvas id="chart-margen-sede"></canvas></div>
            <table class="data-table">
                <thead><tr><th>Sede</th><th>Ventas</th><th>Costo</th><th>Utilidad</th><th>% margen</th></tr></thead>
                <tbody>
                    @forelse($por_sede as $fila)
                        <tr>
                            <td><strong>{{ $fila->nombre }}</strong></td>
                            <td>${{ $fmt($fila->ventas) }}</td>
                            <td>${{ $fmt($fila->costo) }}</td>
                            <td>${{ $fmt($fila->utilidad) }}</td>
                            <td>{{ $fmt($fila->margen_pct, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Sin ventas en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card">
            <h3>Margen por categoría</h3>
            <table class="data-table">
                <thead><tr><th>Categoría</th><th>Ventas</th><th>Utilidad</th><th>% margen</th></tr></thead>
                <tbody>
                    @forelse($por_categoria as $fila)
                        <tr>
                            <td>{{ $fila->nombre }}</td>
                            <td>${{ $fmt($fila->ventas) }}</td>
                            <td>${{ $fmt($fila->utilidad) }}</td>
                            <td>{{ $fmt($fila->margen_pct, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Sin categorías con ventas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="gerencial-grid-2" style="margin-top:16px;">
        <div class="nomina-card">
            <h3>Margen por vendedor</h3>
            <table class="data-table">
                <thead><tr><th>Vendedor</th><th>Ventas</th><th>Utilidad</th><th>% margen</th></tr></thead>
                <tbody>
                    @forelse($por_vendedor as $fila)
                        <tr>
                            <td>{{ $fila->nombre }}</td>
                            <td>${{ $fmt($fila->ventas) }}</td>
                            <td>${{ $fmt($fila->utilidad) }}</td>
                            <td>{{ $fmt($fila->margen_pct, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Sin vendedores en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card">
            <h3>Margen por producto</h3>
            <table class="data-table">
                <thead><tr><th>Producto</th><th>Ventas</th><th>Utilidad</th><th>% margen</th></tr></thead>
                <tbody>
                    @forelse($por_producto as $fila)
                        <tr>
                            <td>{{ $fila->nombre }}</td>
                            <td>${{ $fmt($fila->ventas) }}</td>
                            <td>${{ $fmt($fila->utilidad) }}</td>
                            <td>{{ $fmt($fila->margen_pct, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Sin productos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="gerencial-grid-2" style="margin-top:16px;">
        <div class="nomina-card">
            <h3>Venden mucho, dejan poco</h3>
            <p class="muted">Mayor volumen de ventas con menor % de margen.</p>
            <table class="data-table">
                <thead><tr><th>Producto</th><th>Ventas</th><th>% margen</th></tr></thead>
                <tbody>
                    @forelse($poco_margen as $fila)
                        <tr>
                            <td>{{ $fila->nombre }}</td>
                            <td>${{ $fmt($fila->ventas) }}</td>
                            <td>{{ $fmt($fila->margen_pct, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin productos para comparar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card">
            <h3>Mejor rentabilidad</h3>
            <p class="muted">Mayor % de margen entre los productos con ventas.</p>
            <table class="data-table">
                <thead><tr><th>Producto</th><th>Ventas</th><th>% margen</th></tr></thead>
                <tbody>
                    @forelse($mejor_margen as $fila)
                        <tr>
                            <td>{{ $fila->nombre }}</td>
                            <td>${{ $fmt($fila->ventas) }}</td>
                            <td>{{ $fmt($fila->margen_pct, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin productos para comparar.</td></tr>
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
const canvas = document.getElementById('chart-margen-sede');
if (canvas && porSede.length) {
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: porSede.map(r => r.nombre),
            datasets: [{
                label: '% margen',
                data: porSede.map(r => Number(r.margen_pct)),
                backgroundColor: '#1e3a8a',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}
</script>
@endpush
