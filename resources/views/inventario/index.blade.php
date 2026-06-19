@extends('layouts.app')

@section('title', 'Inventario — Requisición personalizada')

@section('content')
<div class="page-header">
    <h1>Requisición personalizada</h1>
    <p class="lead">Sede {{ $sede }} · Productos con stock en otras sucursales. Registra aquí; el stock se aplica al exportar el CSV.</p>
</div>

<div class="stats-row">
    <div class="stat-chip"><strong>{{ $rows->count() }}</strong> visibles</div>
    <div class="stat-chip"><strong>{{ $totalManual ?? 0 }}</strong> pendientes de exportar</div>
</div>

<form method="GET" class="filter-bar" data-auto-filter data-auto-filter-delay="350" data-tour="inventario-filters">
    <div class="field field-wide">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Código o nombre de producto…" autocomplete="off">
    </div>
    <div class="field">
        <label for="categoria">Categoría</label>
        <select id="categoria" name="categoria">
            <option value="Ninguno">Todas</option>
            @foreach ($categorias ?? [] as $cat)
                <option value="{{ $cat }}" @selected(($filters['categoria'] ?? 'Ninguno') === $cat)>{{ $cat }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="subcategoria">Subcategoría</label>
        <select id="subcategoria" name="subcategoria">
            <option value="Ninguno">Todas</option>
            @foreach ($subcategorias ?? [] as $sub)
                <option value="{{ $sub }}" @selected(($filters['subcategoria'] ?? 'Ninguno') === $sub)>{{ $sub }}</option>
            @endforeach
        </select>
    </div>
</form>

@if($rows->isEmpty())
    <div class="panel empty-state" data-tour="inventario-grid">
        <p>No hay productos con stock en otras sedes para estos filtros.</p>
    </div>
@else
    <div class="product-grid" data-tour="inventario-grid">
        @foreach ($rows as $row)
            <article class="product-card {{ ($row['req_manual'] ?? false) ? 'has-manual' : '' }}"
                data-codigo="{{ $row['cod_centro'] }}"
                data-producto="{{ e($row['producto']) }}"
                data-origen-manual="{{ $row['origen_manual'] ?? '' }}"
                data-cantidad-manual="{{ $row['cantidad_manual'] ?? 0 }}">
                <div class="code">{{ $row['cod_centro'] }}</div>
                <div class="name">{{ $row['producto'] }}</div>
                <div class="stock-pills">
                    <span class="stock-pill">Local <strong>{{ $row['existencia'] }}</strong></span>
                    @foreach ($sedesStock as $sedeCol)
                        @if(($row['stocks'][$sedeCol] ?? 0) > 0)
                            <span class="stock-pill">{{ config('inventario.display.'.$sedeCol, $sedeCol) }} <strong>{{ $row['stocks'][$sedeCol] }}</strong></span>
                        @endif
                    @endforeach
                </div>
                @if($row['req_manual'] ?? false)
                    <span class="tag {{ ($row['manual_pendiente'] ?? false) ? 'manual' : 'ok' }}">{{ $row['accion_manual'] }}</span>
                @else
                    <span class="muted">Clic para requisitar</span>
                @endif
            </article>
        @endforeach
    </div>
@endif

<div id="modal-manual" class="modal-overlay" style="display:none;">
    <div class="panel modal-box">
        <h2 style="margin:0 0 8px;font-size:1.15rem;">Requisición manual</h2>
        <p id="modal-producto" class="muted" style="margin:0 0 16px;"></p>
        <form method="POST" action="{{ route('inventario.manual.store') }}">
            @csrf
            <input type="hidden" name="codigo" id="manual-codigo">
            <input type="hidden" name="producto" id="manual-producto">
            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
            <input type="hidden" name="categoria" value="{{ $filters['categoria'] ?? 'Ninguno' }}">
            <input type="hidden" name="subcategoria" value="{{ $filters['subcategoria'] ?? 'Ninguno' }}">
            <div class="field" style="margin-bottom:12px;">
                <label for="manual-origen">Sede origen</label>
                <select name="sede_origen" id="manual-origen" required>
                    @foreach ($sedesOrigen as $orig)
                        <option value="{{ $orig }}">{{ config('inventario.display.'.$orig, $orig) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-bottom:12px;">
                <label for="manual-cantidad">Cantidad</label>
                <input type="number" name="cantidad" id="manual-cantidad" min="1" value="1" required>
            </div>
            <div id="manual-metricas" class="metric-box muted">Calculando…</div>
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button type="submit" class="btn">Confirmar</button>
                <button type="button" class="btn secondary" id="manual-cancel" type="button">Cancelar</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('modal-manual');
    const metricasUrl = @json(route('inventario.manual.metricas'));
    let currentCod = null;

    function updateMetricas() {
        if (!currentCod) return;
        const origen = document.getElementById('manual-origen').value;
        const cant = document.getElementById('manual-cantidad').value || 1;
        const box = document.getElementById('manual-metricas');
        box.textContent = 'Calculando…';
        fetch(metricasUrl + '?codigo=' + encodeURIComponent(currentCod) + '&sede_origen=' + encodeURIComponent(origen) + '&cantidad=' + encodeURIComponent(cant))
            .then(r => r.json())
            .then(j => {
                const m = j.metricas || {};
                let html = 'Stock origen: <strong>' + m.stock + '</strong> · Demanda: <strong>' + m.demanda + '</strong> · Excedente: <strong>' + m.excedente + '</strong>';
                if (j.faltante) {
                    html += '<div class="warn">' + j.mensaje + ' Faltante: ' + j.faltante + '</div>';
                } else if (j.mensaje) {
                    html += '<div class="ok">' + j.mensaje + '</div>';
                }
                box.innerHTML = html;
            }).catch(() => { box.textContent = 'No se pudieron cargar métricas.'; });
    }

    document.querySelectorAll('.product-card').forEach(function (card) {
        card.addEventListener('click', function () {
            currentCod = card.dataset.codigo;
            document.getElementById('manual-codigo').value = card.dataset.codigo;
            document.getElementById('manual-producto').value = card.dataset.producto;
            document.getElementById('modal-producto').textContent = card.dataset.producto + ' · ' + card.dataset.codigo;
            if (card.dataset.origenManual) {
                document.getElementById('manual-origen').value = card.dataset.origenManual;
                document.getElementById('manual-cantidad').value = card.dataset.cantidadManual || 1;
            }
            modal.style.display = 'flex';
            updateMetricas();
        });
    });

    document.getElementById('manual-origen').addEventListener('change', updateMetricas);
    document.getElementById('manual-cantidad').addEventListener('input', updateMetricas);
    document.getElementById('manual-cancel').addEventListener('click', () => { modal.style.display = 'none'; });
    modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

    let since = @json($stockUpdatedAt);
    if (!since) return;

    async function syncInventario() {
        try {
            const url = new URL(@json(route('inventario.sync')).replace(/&amp;/g, '&'), window.location.origin);
            url.searchParams.set('since', since);
            url.searchParams.set('q', document.getElementById('q').value);
            url.searchParams.set('categoria', document.getElementById('categoria').value);
            url.searchParams.set('subcategoria', document.getElementById('subcategoria').value);

            const r = await fetch(url.toString());
            if (!r.ok) return;
            const j = await r.json();
            if (!j.changed) return;

            since = j.updated_at;
            document.querySelector('.stats-row .stat-chip strong').textContent = j.row_count;
            document.querySelector('.stats-row .stat-chip:last-child strong').textContent = j.total_manual;

            const grid = document.querySelector('.product-grid');
            if (!grid) return;

            if (j.rows.length === 0) {
                grid.innerHTML = '<div class="panel empty-state" data-tour="inventario-grid"><p>No hay productos con stock en otras sedes para estos filtros.</p></div>';
                return;
            }

            grid.innerHTML = j.rows.map(row => {
                const stockPills = @json($sedesStock).map(sede => {
                    const value = row.stocks[sede] ?? 0;
                    return value > 0 ? `<span class="stock-pill">${sede} <strong>${value}</strong></span>` : '';
                }).join('');

                const manualBadge = row.req_manual
                    ? `<span class="tag ${row.manual_pendiente ? 'manual' : 'ok'}">${row.accion_manual}</span>`
                    : '<span class="muted">Clic para requisitar</span>';

                return `<article class="product-card ${row.req_manual ? 'has-manual' : ''}"
                    data-codigo="${row.cod_centro}"
                    data-producto="${row.producto}"
                    data-origen-manual="${row.origen_manual}"
                    data-cantidad-manual="${row.cantidad_manual}">
                    <div class="code">${row.cod_centro}</div>
                    <div class="name">${row.producto}</div>
                    <div class="stock-pills">${stockPills}</div>
                    ${manualBadge}
                </article>`;
            }).join('');

            document.querySelectorAll('.product-card').forEach(function (card) {
                card.addEventListener('click', function () {
                    currentCod = card.dataset.codigo;
                    document.getElementById('manual-codigo').value = card.dataset.codigo;
                    document.getElementById('manual-producto').value = card.dataset.producto;
                    document.getElementById('modal-producto').textContent = card.dataset.producto + ' · ' + card.dataset.codigo;
                    if (card.dataset.origenManual) {
                        document.getElementById('manual-origen').value = card.dataset.origenManual;
                        document.getElementById('manual-cantidad').value = card.dataset.cantidadManual || 1;
                    }
                    modal.style.display = 'flex';
                    updateMetricas();
                });
            });
        } catch (e) {
            // ignore transient sync issues
        }
    }

    setInterval(syncInventario, 15000);
})();
</script>
@endpush
