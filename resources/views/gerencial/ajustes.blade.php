@extends('layouts.app')

@section('title', 'Consolidados de ajustes de inventarios')

@php
    $fmt = fn ($n, $d = 2) => number_format((float) $n, $d);
    $filtrosAjustesJs = [
        'preset' => $filtros['preset'] ?? 'mes',
        'desde' => $filtros['desde'] ?? '',
        'hasta' => $filtros['hasta'] ?? '',
        'sede' => $filtros['sede'] ?? 'todas',
        'tipo' => $tipo ?? '',
    ];
@endphp

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Consolidados de ajustes de inventarios</h1>
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
            <table class="data-table">
                <thead><tr><th>Usuario</th><th>Movimientos</th><th>Valor</th></tr></thead>
                <tbody>
                    @forelse($usuarios as $fila)
                        @php $codigosUsuario = $fila->codigos ?? array_values(array_filter([$fila->usuario_raw ?? $fila->codigo ?? $fila->usuario])); @endphp
                        <tr>
                            <td>
                                <button type="button"
                                    class="link-usuario-ajuste"
                                    data-nombre="{{ $fila->nombre ?: $fila->usuario }}"
                                    data-clave="{{ $fila->clave ?? '' }}"
                                    data-codigos="{{ implode('||', $codigosUsuario) }}">
                                    {{ $fila->nombre ?: $fila->usuario }}
                                </button>
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

<div id="modal-ajustes-usuario" class="modal-overlay" style="display:none;" hidden>
    <div class="panel modal-box modal-wide" role="dialog" aria-modal="true" aria-labelledby="modal-ajustes-titulo">
        <div class="panel-header-flex">
            <div>
                <h3 id="modal-ajustes-titulo" style="margin:0;">Ajustes del usuario</h3>
                <p class="muted" id="modal-ajustes-meta" style="margin:4px 0 0;"></p>
            </div>
            <button type="button" class="btn" id="modal-ajustes-cerrar">Cerrar</button>
        </div>
        <div class="table-wrap" style="max-height:60vh;overflow:auto;margin-top:12px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Sede</th>
                        <th>Tipo</th>
                        <th>Documento</th>
                        <th>Producto</th>
                        <th>Cant.</th>
                        <th>Valor</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody id="modal-ajustes-body"></tbody>
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

const modalAjustes = document.getElementById('modal-ajustes-usuario');
const modalAjustesBody = document.getElementById('modal-ajustes-body');
const modalAjustesTitulo = document.getElementById('modal-ajustes-titulo');
const modalAjustesMeta = document.getElementById('modal-ajustes-meta');
const urlAjustesUsuario = @json(route('gerencial.ajustes.usuario'));
const filtrosAjustes = @json($filtrosAjustesJs);

function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}
function dinero(n) {
    return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function abrirModalAjustes() {
    if (!modalAjustes) return;
    modalAjustes.hidden = false;
    modalAjustes.style.display = 'flex';
}
function cerrarModalAjustes() {
    if (!modalAjustes) return;
    modalAjustes.hidden = true;
    modalAjustes.style.display = 'none';
}
function cargarAjustesUsuario(boton) {
    const nombre = boton.dataset.nombre || 'Usuario';
    const rawCodigos = boton.dataset.codigos || '';
    let codigos = rawCodigos.split('||').map((c) => c.trim()).filter(Boolean);
    if (codigos.length === 0) {
        try { const parsed = JSON.parse(rawCodigos || '[]'); if (Array.isArray(parsed)) codigos = parsed; } catch (e) { codigos = []; }
    }
    if (modalAjustesTitulo) modalAjustesTitulo.textContent = nombre;
    if (modalAjustesMeta) modalAjustesMeta.textContent = 'Cargando ajustes…';
    if (modalAjustesBody) modalAjustesBody.innerHTML = '<tr><td colspan="8" class="muted">Cargando…</td></tr>';
    abrirModalAjustes();

    const params = new URLSearchParams(filtrosAjustes);
    (Array.isArray(codigos) ? codigos : []).forEach((c) => params.append('codigos[]', c));
    if (boton.dataset.clave) params.set('clave', boton.dataset.clave);

    fetch(urlAjustesUsuario + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    })
        .then((r) => { if (!r.ok) throw new Error('No se pudieron cargar los ajustes.'); return r.json(); })
        .then((data) => {
            const items = data.items || [];
            if (modalAjustesMeta) {
                modalAjustesMeta.textContent = items.length
                    ? items.length + ' movimiento' + (items.length === 1 ? '' : 's') + ' en el período'
                    : 'Sin ajustes en el período';
            }
            if (!modalAjustesBody) return;
            if (!items.length) {
                modalAjustesBody.innerHTML = '<tr><td colspan="8" class="muted">Este usuario no tiene ajustes en el filtro actual.</td></tr>';
                return;
            }
            modalAjustesBody.innerHTML = items.map((it) => {
                const cant = Number(it.cantidad || 0);
                const signo = cant > 0 ? '+' : '';
                return '<tr>'
                    + '<td>' + escHtml(it.fecha || '') + '</td>'
                    + '<td>' + escHtml(it.sede || '') + '</td>'
                    + '<td>' + escHtml(it.tipo || '') + '</td>'
                    + '<td>' + escHtml(it.documento || '') + '</td>'
                    + '<td>' + escHtml(it.producto || it.codigo || '—') + '</td>'
                    + '<td>' + signo + cant.toLocaleString('en-US') + '</td>'
                    + '<td>$' + dinero(it.valor) + '</td>'
                    + '<td>' + escHtml(it.motivo || '—') + '</td>'
                    + '</tr>';
            }).join('');
        })
        .catch((err) => {
            if (modalAjustesMeta) modalAjustesMeta.textContent = '';
            if (modalAjustesBody) modalAjustesBody.innerHTML = '<tr><td colspan="8" class="muted">' + (err.message || 'Error') + '</td></tr>';
        });
}
document.querySelectorAll('.link-usuario-ajuste').forEach((btn) => {
    btn.addEventListener('click', () => cargarAjustesUsuario(btn));
});
if (modalAjustes) {
    modalAjustes.addEventListener('click', (e) => { if (e.target === modalAjustes) cerrarModalAjustes(); });
}
document.getElementById('modal-ajustes-cerrar')?.addEventListener('click', cerrarModalAjustes);
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrarModalAjustes(); });
</script>
@endpush
