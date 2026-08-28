@extends('layouts.app')

@section('title', 'Consolidados de ajustes de inventarios')

@php $fmt = fn ($n, $d = 2) => number_format((float) $n, $d); @endphp

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Consolidados de ajustes de inventarios</h1>
            <p class="gerencial-pregunta">¿Por qué está cambiando el inventario y quién genera esos movimientos?</p>
            <p class="muted" style="margin:4px 0 0;">{{ $periodo['etiqueta'] }}</p>
        </div>
    </div>

    @include('gerencial._tabs')
    @include('gerencial._filtros', ['modo' => 'ajustes', 'action' => route('gerencial.ajustes')])

    <div class="nomina-kpis">
        <div class="nomina-kpi">
            <span>Total movimientos</span>
            <strong>{{ number_format($kpis['movimientos']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Unidades ajustadas</span>
            <strong>{{ $fmt($kpis['unidades'], 0) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Valor ajustado</span>
            <strong>${{ $fmt($kpis['valor']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Entradas</span>
            <strong>+{{ $fmt($kpis['entradas_und'], 0) }}</strong>
            <div class="muted" style="font-size:.75rem;">{{ number_format($kpis['positivos']) }} ajustes +</div>
        </div>
        <div class="nomina-kpi warn">
            <span>Salidas</span>
            <strong>−{{ $fmt($kpis['salidas_und'], 0) }}</strong>
            <div class="muted" style="font-size:.75rem;">{{ number_format($kpis['negativos']) }} ajustes −</div>
        </div>
    </div>

    @if($alertas)
        <div class="gerencial-alertas">
            <h3>Alertas</h3>
            <ul>
                @foreach($alertas as $alerta)
                    <li>{{ $alerta }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="gerencial-grid-2">
        <div class="nomina-card">
            <h3>Entradas vs salidas por tipo</h3>
            <div class="gerencial-chart"><canvas id="chart-ajustes-tipo"></canvas></div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Movs</th>
                        <th>Entradas</th>
                        <th>Salidas</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($por_tipo as $fila)
                        <tr>
                            <td><strong>{{ $fila->tipo }}</strong></td>
                            <td>{{ number_format($fila->movimientos) }}</td>
                            <td>+{{ $fmt($fila->entradas, 0) }}</td>
                            <td>−{{ $fmt($fila->salidas, 0) }}</td>
                            <td>${{ $fmt($fila->valor) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Sin ajustes en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card">
            <h3>Por sede</h3>
            <p class="muted" style="margin-top:0;">Torta = % de documentos de ajuste de cada sede.</p>
            <div class="gerencial-chart"><canvas id="chart-ajustes-sede"></canvas></div>
            @php $totalMovSede = max(1, (int) $por_sede->sum('movimientos')); @endphp
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sede</th>
                        <th>Movimientos</th>
                        <th>%</th>
                        <th>Entradas</th>
                        <th>Salidas</th>
                        <th>Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($por_sede as $fila)
                        @php $pctSede = ((int) $fila->movimientos / $totalMovSede) * 100; @endphp
                        <tr>
                            <td><strong>{{ $fila->sede }}</strong></td>
                            <td>{{ number_format($fila->movimientos) }}</td>
                            <td>{{ $fmt($pctSede, 1) }}%</td>
                            <td>+{{ $fmt($fila->entradas, 0) }}</td>
                            <td>−{{ $fmt($fila->salidas, 0) }}</td>
                            <td>{{ ($fila->diferencia >= 0 ? '+' : '').$fmt($fila->diferencia, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Sin movimientos por sede.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="gerencial-grid-2" style="margin-top:16px;">
        <div class="nomina-card">
            <h3>Motivos más repetidos</h3>
            <table class="data-table">
                <thead><tr><th>Motivo</th><th>Veces</th><th>Unds</th><th>USD</th></tr></thead>
                <tbody>
                    @forelse($por_motivo as $fila)
                        <tr>
                            <td>{{ $fila->motivo }}</td>
                            <td>{{ number_format($fila->veces) }}</td>
                            <td>{{ $fmt($fila->unidades, 0) }}</td>
                            <td>${{ $fmt($fila->valor) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Sin motivos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card">
            <h3>Auditoría — usuarios que realizan ajustes</h3>
            <p class="muted">No implica irregularidad: sirve para detectar patrones.</p>
            <table class="data-table">
                <thead><tr><th>Usuario</th><th>Movimientos</th><th>Valor</th></tr></thead>
                <tbody>
                    @forelse($usuarios as $fila)
                        <tr>
                            <td>
                                <strong>{{ $fila->nombre ?: $fila->usuario }}</strong>
                                @if(!empty($fila->codigo) && $fila->codigo !== 'Sin usuario' && ($fila->nombre ?? '') !== '')
                                    <div class="muted" style="font-size:.75rem;">{{ $fila->codigo }}</div>
                                @endif
                            </td>
                            <td>{{ number_format($fila->movimientos) }}</td>
                            <td>${{ $fmt($fila->valor) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin usuario registrado en los movimientos.</td></tr>
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
const colores = ['#1e3a8a','#dc2626','#d97706','#059669','#7c3aed','#64748b','#0ea5e9','#be185d'];
const porTipo = @json($por_tipo->values());
const canvas = document.getElementById('chart-ajustes-tipo');
if (canvas && porTipo.length) {
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: porTipo.map(r => r.tipo),
            datasets: [
                { label: 'Entradas', data: porTipo.map(r => Number(r.entradas)), backgroundColor: '#059669' },
                { label: 'Salidas', data: porTipo.map(r => Number(r.salidas)), backgroundColor: '#dc2626' },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: { x: { stacked: false }, y: { beginAtZero: true } }
        }
    });
}
const porSede = @json($por_sede->values());
const canvasSede = document.getElementById('chart-ajustes-sede');
const totalMov = porSede.reduce((acc, r) => acc + Number(r.movimientos || 0), 0);
if (canvasSede && totalMov > 0) {
    new Chart(canvasSede, {
        type: 'doughnut',
        data: {
            labels: porSede.map(r => r.sede),
            datasets: [{
                data: porSede.map(r => Number(r.movimientos)),
                backgroundColor: colores,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const n = Number(ctx.parsed) || 0;
                            const pct = ((n / totalMov) * 100).toFixed(1);
                            return `${ctx.label}: ${pct}% (${n.toLocaleString()} docs)`;
                        }
                    }
                }
            }
        }
    });
}
</script>
@endpush
