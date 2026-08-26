@extends('layouts.app')

@section('title', 'Devoluciones en ventas')

@php
    $fmt = fn ($n, $d = 2) => number_format((float) $n, $d);
    $detalleUrl = route('gerencial.devoluciones', array_merge(request()->except('page'), ['ver_detalle' => 1]));
    $ocultarUrl = route('gerencial.devoluciones', request()->except(['page', 'ver_detalle']));
@endphp

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Devoluciones en ventas</h1>
            <p class="gerencial-pregunta">¿Qué estamos devolviendo, dónde, por qué y cuánto dinero estamos perdiendo?</p>
            <p class="muted" style="margin:4px 0 0;">{{ $periodo['etiqueta'] }}</p>
        </div>
        @if(! $verDetalle)
            <a class="btn primary" href="{{ $detalleUrl }}">Ver devoluciones</a>
        @else
            <a class="btn" href="{{ $ocultarUrl }}">Ocultar detalle</a>
        @endif
    </div>

    @include('gerencial._tabs')
    @include('gerencial._filtros', ['modo' => 'devoluciones', 'action' => route('gerencial.devoluciones')])

    <div class="nomina-kpis">
        <div class="nomina-kpi warn">
            <span>Devoluciones</span>
            <strong>{{ number_format($kpis['documentos']) }}</strong>
        </div>
        <div class="nomina-kpi warn">
            <span>Valor vendido devuelto</span>
            <strong>${{ $fmt($kpis['usd']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Unidades devueltas</span>
            <strong>{{ $fmt($kpis['unidades'], 0) }}</strong>
        </div>
        <div class="nomina-kpi warn">
            <span>% sobre ventas</span>
            <strong>{{ $fmt($kpis['pct_ventas'], 1) }}%</strong>
            <div class="muted" style="font-size:.75rem;">Ventas ${{ $fmt($kpis['ventas_usd']) }}</div>
        </div>
        <div class="nomina-kpi">
            <span>Costo asociado</span>
            <strong>${{ $fmt($kpis['costo']) }}</strong>
        </div>
        <div class="nomina-kpi warn">
            <span>Impacto en margen</span>
            <strong>${{ $fmt($kpis['margen']) }}</strong>
            <div class="muted" style="font-size:.75rem;">Valor − costo de lo devuelto</div>
        </div>
    </div>

    <div class="gerencial-grid-2">
        <div class="nomina-card">
            <h3>Análisis por motivo</h3>
            <div class="gerencial-chart"><canvas id="chart-motivos"></canvas></div>
            <table class="data-table">
                <thead><tr><th>Motivo</th><th>Cantidad</th><th>Valor</th><th>%</th></tr></thead>
                <tbody>
                    @forelse($porMotivo as $fila)
                        <tr>
                            <td>{{ $fila->motivo }}</td>
                            <td>{{ number_format($fila->veces) }}</td>
                            <td>${{ $fmt($fila->usd) }}</td>
                            <td>{{ $fmt($fila->pct, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Sin motivos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card">
            <h3>Comparación por sede</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sede</th>
                        <th>Ventas</th>
                        <th>DEV</th>
                        <th>% DEV</th>
                        <th>Valor DEV</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($porSede as $fila)
                        <tr class="{{ $fila->pct >= 2 ? 'gerencial-row-alert' : '' }}">
                            <td><strong>{{ $fila->sede }}</strong></td>
                            <td>${{ $fmt($fila->ventas_usd) }}</td>
                            <td>{{ number_format($fila->devoluciones) }}</td>
                            <td>{{ $fmt($fila->pct, 1) }}%</td>
                            <td>${{ $fmt($fila->valor_dev) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Sin datos de sedes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Productos más devueltos</h3>
        <p class="muted" style="margin-top:0;">El % usa unidades vendidas del mismo período, no solo el conteo de devoluciones.</p>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Unidades vendidas</th>
                        <th>Unidades devueltas</th>
                        <th>% devolución</th>
                        <th>Valor devuelto</th>
                        <th>Motivo principal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($porProducto as $item)
                        <tr>
                            <td>{{ $item->nombre }}</td>
                            <td>{{ $fmt($item->vendidas, 0) }}</td>
                            <td>{{ $fmt($item->devueltas, 0) }}</td>
                            <td>{{ $fmt($item->pct, 1) }}%</td>
                            <td>${{ $fmt($item->valor_dev) }}</td>
                            <td>{{ $item->motivo }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Sin productos devueltos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($verDetalle)
        <div class="nomina-card" style="margin-top:16px;" id="detalle-devoluciones">
            <h3>Detalle de devoluciones</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nº factura</th>
                            <th>Fecha</th>
                            <th>Sede</th>
                            <th>Cliente</th>
                            <th>Vendedor</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio</th>
                            <th>Motivo</th>
                            <th>Procesó</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($detalle as $row)
                            <tr>
                                <td>{{ $row->numero_documento }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->fecha)->format('d/m/Y') }}</td>
                                <td>{{ $row->sede }}</td>
                                <td>{{ $row->cliente ?: '—' }}</td>
                                <td>{{ $row->vendedor ?: '—' }}</td>
                                <td>{{ $row->nombre_producto ?: $row->codigo_producto }}</td>
                                <td>{{ $fmt($row->cantidad, 0) }}</td>
                                <td>${{ $fmt($row->precio_neto ?? $row->precio_venta) }}</td>
                                <td>{{ $row->motivo_devolucion ?: '—' }}</td>
                                <td>{{ $row->usuario ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="muted">Sin líneas de devolución.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px;">{{ $detalle->links() }}</div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const motivos = @json($porMotivo->values());
const canvas = document.getElementById('chart-motivos');
if (canvas && motivos.length) {
    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: motivos.map(r => r.motivo),
            datasets: [{
                data: motivos.map(r => Number(r.usd)),
                backgroundColor: ['#1e3a8a','#dc2626','#d97706','#059669','#7c3aed','#64748b','#0ea5e9','#be185d'],
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
