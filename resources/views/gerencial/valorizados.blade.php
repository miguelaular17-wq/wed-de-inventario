@extends('layouts.app')

@section('title', 'Valorizados de inventarios')

@php $fmt = fn ($n, $d = 2) => number_format((float) $n, $d); @endphp

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Valorizados de inventarios</h1>
            @include('gerencial._tabs')
        </div>
    </div>

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

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Análisis ABC / Pareto de rotación</h3>
        <div class="gerencial-grid-2">
            <div>
                @foreach(['A' => 'abc-a', 'B' => 'abc-b', 'C' => 'abc-c'] as $clase => $css)
                    @php $r = $abc_resumen_rotacion[$clase]; @endphp
                    <div class="gerencial-abc-bar">
                        <span>{{ $clase }}</span>
                        <div class="gerencial-abc-track"><i class="gerencial-{{ $css }}" style="width: {{ min(100, $r['pct_valor']) }}%;"></i></div>
                        <span>{{ $fmt($r['pct_valor'], 1) }}% · {{ number_format($r['productos']) }} prod.</span>
                    </div>
                @endforeach
                <div class="gerencial-chart gerencial-chart-tall" style="margin-top:16px;"><canvas id="chart-abc-pareto"></canvas></div>
            </div>
            <div>
                <h4 style="margin:0 0 8px;">ABC rotación × ABC margen</h4>
                <p class="muted" style="margin:0 0 8px;">Clic en un recuadro para ver esos productos.</p>
                <table class="data-table gerencial-matriz">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Margen A</th>
                            <th>Margen B</th>
                            <th>Margen C</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(['A' => 'Rotación A', 'B' => 'Rotación B', 'C' => 'Rotación C'] as $rot => $label)
                            <tr>
                                <th>{{ $label }}</th>
                                @foreach(['A', 'B', 'C'] as $mar)
                                    @php
                                        $cell = $abc_matriz[$rot][$mar];
                                        $cls = '';
                                        if ($rot === 'A' && $mar === 'A') $cls = 'is-core';
                                        if ($rot === 'A' && $mar === 'C') $cls = 'is-buy';
                                        if ($rot === 'C' && $mar === 'C') $cls = 'is-liq';
                                    @endphp
                                    <td class="{{ $cls }}">
                                        <button type="button" class="gerencial-matriz-btn" data-rot="{{ $rot }}" data-mar="{{ $mar }}" @disabled($cell['productos'] < 1)>
                                            <strong>{{ number_format($cell['productos']) }}</strong>
                                            <div class="muted" style="font-size:.72rem;">{{ $fmt($cell['unidades'], 0) }} und</div>
                                        </button>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="table-wrap" style="margin-top:12px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Unds</th>
                        <th>%</th>
                        <th>% acum.</th>
                        <th>Margen $</th>
                        <th>ABC rot.</th>
                        <th>ABC mar.</th>
                        <th>Inventario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($abc_pareto as $fila)
                        <tr>
                            <td>{{ $fila->nombre ?: $fila->codigo }}</td>
                            <td>{{ $fmt($fila->unidades, 0) }}</td>
                            <td>{{ $fmt($fila->pct, 1) }}%</td>
                            <td>{{ $fmt($fila->pct_acum, 1) }}%</td>
                            <td>${{ $fmt($fila->utilidad) }}</td>
                            <td><span class="gerencial-abc-badge gerencial-abc-{{ strtolower($fila->abc_rotacion) }}">{{ $fila->abc_rotacion }}</span></td>
                            <td><span class="gerencial-abc-badge gerencial-abc-{{ strtolower($fila->abc_margen) }}">{{ $fila->abc_margen }}</span></td>
                            <td>{{ $fila->clase_inv }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">Sin ventas de unidades en el período. Cambia el período o la categoría.</td></tr>
                    @endforelse
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

<div id="modal-abc-celda" class="modal-overlay" hidden>
    <div class="panel modal-box modal-wide" role="dialog" aria-modal="true" aria-labelledby="modal-abc-titulo">
        <div class="panel-header-flex">
            <div>
                <h3 id="modal-abc-titulo" style="margin:0;">Productos</h3>
                <p class="muted" id="modal-abc-meta" style="margin:4px 0 0;"></p>
            </div>
            <button type="button" class="btn" id="modal-abc-cerrar">Cerrar</button>
        </div>
        <div class="table-wrap" style="max-height:60vh;overflow:auto;margin-top:12px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th><button type="button" class="gerencial-sort" data-sort="unidades">Unds</button></th>
                        <th><button type="button" class="gerencial-sort is-active" data-sort="utilidad">Margen $</button></th>
                        <th>Inventario</th>
                    </tr>
                </thead>
                <tbody id="modal-abc-body"></tbody>
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
const canvasAbc = document.getElementById('chart-abc-pareto');
const pareto = @json($abc_pareto->values());
if (canvasAbc && pareto.length) {
    const labels = pareto.map(r => (r.nombre || r.codigo || '').slice(0, 28));
    new Chart(canvasAbc, {
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Unidades',
                    data: pareto.map(r => Number(r.unidades)),
                    backgroundColor: '#1e3a8a',
                    yAxisID: 'y',
                },
                {
                    type: 'line',
                    label: '% acumulado',
                    data: pareto.map(r => Number(r.pct_acum)),
                    borderColor: '#dc2626',
                    backgroundColor: '#dc2626',
                    yAxisID: 'y1',
                    tension: 0.2,
                },
                {
                    type: 'line',
                    label: '80%',
                    data: labels.map(() => 80),
                    borderColor: '#94a3b8',
                    borderDash: [6, 4],
                    pointRadius: 0,
                    yAxisID: 'y1',
                },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Unidades' } },
                y1: {
                    min: 0,
                    max: 100,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: '% acum.' },
                }
            }
        }
    });
}

const matrizAbc = @json($abc_matriz);
const modalAbc = document.getElementById('modal-abc-celda');
const modalAbcTitulo = document.getElementById('modal-abc-titulo');
const modalAbcMeta = document.getElementById('modal-abc-meta');
const modalAbcBody = document.getElementById('modal-abc-body');
function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}
function dineroAbc(n) {
    return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function abrirModalAbc() {
    if (!modalAbc) return;
    modalAbc.hidden = false;
}
function cerrarModalAbc() {
    if (!modalAbc) return;
    modalAbc.hidden = true;
}
let abcItemsActuales = [];
let abcOrden = 'utilidad';
function pintarFilasAbc() {
    if (!modalAbcBody) return;
    if (!abcItemsActuales.length) {
        modalAbcBody.innerHTML = '<tr><td colspan="4" class="muted">No hay productos en esta casilla.</td></tr>';
        return;
    }
    const items = abcItemsActuales.slice().sort((a, b) => {
        const va = Number(a[abcOrden] || 0);
        const vb = Number(b[abcOrden] || 0);
        return vb - va;
    });
    modalAbcBody.innerHTML = items.map((it) => (
            '<tr>'
            + '<td>' + escHtml(it.nombre || '—') + '</td>'
            + '<td>' + Number(it.unidades || 0).toLocaleString('en-US') + '</td>'
        + '<td>$' + dineroAbc(it.utilidad) + '</td>'
        + '<td>' + escHtml(it.clase_inv || '—') + '</td>'
        + '</tr>'
    )).join('');
}
function abrirCeldaAbc(rot, mar) {
    const cell = matrizAbc?.[rot]?.[mar] || { items: [], productos: 0, unidades: 0 };
    abcItemsActuales = cell.items || [];
    abcOrden = 'utilidad';
    document.querySelectorAll('.gerencial-sort').forEach((btn) => {
        btn.classList.toggle('is-active', btn.dataset.sort === abcOrden);
    });
    if (modalAbcTitulo) modalAbcTitulo.textContent = 'Rotación ' + rot + ' × Margen ' + mar;
    if (modalAbcMeta) {
        modalAbcMeta.textContent = abcItemsActuales.length
            ? abcItemsActuales.length + ' producto' + (abcItemsActuales.length === 1 ? '' : 's') + ' · ' + Number(cell.unidades || 0).toLocaleString('en-US') + ' und · mayor a menor'
            : 'Sin productos en este recuadro';
    }
    pintarFilasAbc();
    abrirModalAbc();
}
document.querySelectorAll('.gerencial-sort').forEach((btn) => {
    btn.addEventListener('click', () => {
        abcOrden = btn.dataset.sort || 'utilidad';
        document.querySelectorAll('.gerencial-sort').forEach((b) => b.classList.toggle('is-active', b === btn));
        pintarFilasAbc();
    });
});
document.querySelectorAll('.gerencial-matriz-btn').forEach((btn) => {
    btn.addEventListener('click', () => abrirCeldaAbc(btn.dataset.rot, btn.dataset.mar));
});
if (modalAbc) {
    modalAbc.addEventListener('click', (e) => { if (e.target === modalAbc) cerrarModalAbc(); });
}
document.getElementById('modal-abc-cerrar')?.addEventListener('click', cerrarModalAbc);
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrarModalAbc(); });
</script>
@endpush
