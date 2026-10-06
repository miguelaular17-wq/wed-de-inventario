@extends('layouts.app')

@section('title', 'Stock por sede')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Stock por sede</h1>
            <p class="muted" style="margin:4px 0 0;">Existencia actual y SKU con stock en cada sede. No incluye ventas.</p>
            @include('gerencial._tabs')
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi">
            <span>Unidades en stock</span>
            <strong>{{ number_format($totales['unidades']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>SKUs con existencia</span>
            <strong>{{ number_format($totales['skus']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Sedes con stock</span>
            <strong>{{ number_format($totales['sedes']) }}</strong>
        </div>
    </div>

    <h3 style="margin:20px 0 8px;">Por sede</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sede</th>
                    <th>Total SKU</th>
                    <th>Total unidades</th>
                    <th>Valorizado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($por_sede as $fila)
                    <tr>
                        <td><strong>{{ $fila['sede'] }}</strong></td>
                        <td>{{ number_format($fila['skus']) }}</td>
                        <td>{{ number_format($fila['unidades']) }}</td>
                        <td>${{ number_format($fila['valorizado'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Total</strong></td>
                    <td><strong>{{ number_format($totales['skus']) }}</strong></td>
                    <td><strong>{{ number_format($totales['unidades']) }}</strong></td>
                    <td><strong>${{ number_format($totales['valorizado'], 2) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="muted" style="margin-top:8px;">Valorizado es existencia por costo. Los traslados, que vienen con signo, están en el indicador Traslados.</p>

    <div class="gerencial-grid-2" style="margin-top:16px;">
        <div class="nomina-card">
            <h3>Unidades por sede</h3>
            <p class="muted">Total {{ number_format($totales['unidades']) }}</p>
            <div class="gerencial-chart"><canvas id="chart-stock-unidades"></canvas></div>
        </div>
        <div class="nomina-card">
            <h3>Reparto del stock</h3>
            <p class="muted">Participación de cada sede</p>
            <div class="gerencial-chart"><canvas id="chart-stock-reparto"></canvas></div>
        </div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>SKUs con existencia por sede</h3>
        <p class="muted">Productos distintos con stock mayor a cero</p>
        <div class="gerencial-chart"><canvas id="chart-stock-skus"></canvas></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const porSede = @json($por_sede);
const labels = porSede.map(r => r.nombre);
const colores = ['#1e3a8a','#0ea5e9','#059669','#d97706','#7c3aed','#dc2626','#64748b','#0891b2'];
const unidades = document.getElementById('chart-stock-unidades');
if (unidades) {
    new Chart(unidades, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Unidades',
                data: porSede.map(r => Number(r.unidades)),
                backgroundColor: '#1e3a8a',
                borderRadius: 6,
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
const reparto = document.getElementById('chart-stock-reparto');
if (reparto) {
    new Chart(reparto, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: porSede.map(r => Number(r.unidades)),
                backgroundColor: colores,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right' } }
        }
    });
}
const skus = document.getElementById('chart-stock-skus');
if (skus) {
    new Chart(skus, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'SKUs',
                data: porSede.map(r => Number(r.skus)),
                backgroundColor: '#0ea5e9',
                borderRadius: 6,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });
}
</script>
@endpush
