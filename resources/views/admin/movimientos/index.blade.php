@extends('layouts.app')

@section('title', 'Movimientos — Admin')

@section('content')
<div class="page-header">
    <h1>Movimientos de stock</h1>
    <p class="lead">Historial multisede de requisiciones y traslados · hasta 500 registros más recientes.</p>
</div>

<div class="stats-row">
    <div class="stat-chip"><strong>{{ $rows->count() }}</strong> movimientos visibles</div>
</div>

<form method="GET" class="filter-bar" data-auto-filter data-auto-filter-delay="350">
    <div class="field field-wide">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Código o nombre de producto…" autocomplete="off">
    </div>
    <div class="field">
        <label for="sede">Sede</label>
        <select id="sede" name="sede">
            <option value="">Todas</option>
            @foreach ($sedes as $s)
                <option value="{{ $s }}" @selected($filters['sede'] === $s)>{{ config('inventario.display.'.$s, $s) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" value="{{ $filters['desde'] }}">
    </div>
    <div class="field">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="{{ $filters['hasta'] }}">
    </div>
</form>

<section class="table-section-full">
    <div class="table-wrap table-wrap-full">
        <table class="data-table movements-table">
            <thead>
                <tr>
                    <th style="width:120px">Fecha</th>
                    <th style="width:160px">Código</th>
                    <th>Producto</th>
                    <th style="width:160px">Origen → Destino</th>
                    <th style="width:60px">Cant.</th>
                    <th style="width:130px">Usuario</th>
                    <th style="width:200px">Nota</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="cell-nowrap">{{ $row['created_at'] }}</td>
                        <td class="cell-code">{{ $row['codigo'] }}</td>
                        <td class="cell-product" title="{{ $row['producto'] }}">{{ $row['producto'] }}</td>
                        <td class="cell-route">
                            <span class="route-pill">{{ config('inventario.display.'.$row['origen'], $row['origen']) }}</span>
                            <span class="route-arrow">→</span>
                            <span class="route-pill route-pill-dest">{{ config('inventario.display.'.$row['destino'], $row['destino']) }}</span>
                        </td>
                        <td class="cell-qty"><strong>{{ $row['cantidad'] }}</strong></td>
                        <td class="cell-user">{{ $row['usuario'] }}</td>
                        <td class="cell-note">
                            @if($row['is_manual'] ?? false)
                                <span class="tag {{ ($row['manual_exported'] ?? false) ? 'ok' : 'manual' }}">
                                    {{ ($row['manual_exported'] ?? false) ? 'Exportada' : 'Manual' }}
                                </span>
                                @if(! empty($row['manual_note']))
                                    <div class="manual-note {{ ($row['manual_exported'] ?? false) ? 'manual-exported' : '' }}">{{ $row['manual_note'] }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Sin movimientos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('head')
<style>
    /* Make the movimientos table use full width and fit columns properly */
    .table-wrap-full { overflow-x: auto; }
    .movements-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
    .movements-table th, .movements-table td { padding: 8px 10px; font-size: .85rem; }
    .movements-table .cell-nowrap { white-space: nowrap; font-size: .82rem; color: var(--muted); width: 120px; }
    .movements-table .cell-code { font-family: ui-monospace, monospace; font-size: .8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 150px; }
    .movements-table .cell-product { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .movements-table .cell-qty { text-align: center; width: 55px; }
    .movements-table .cell-user { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; width: 120px; }
    .movements-table .cell-route { white-space: nowrap; width: 155px; }
    .movements-table .cell-note { width: 190px; }
    .route-pill {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 6px;
        background: #f1f5f9;
        font-size: .75rem;
        font-weight: 500;
    }
    .route-pill-dest { background: #eef4fc; color: var(--blue); }
    .route-arrow { color: var(--muted); margin: 0 3px; font-size: .82rem; }
    .manual-note { margin-top: 4px; color: var(--muted); font-size: .80rem; line-height: 1.3; }
    .manual-note.manual-exported { color: var(--green); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const lastUpdatedAt = @json($lastUpdatedAt);
    if (! lastUpdatedAt) {
        return;
    }

    const tableBody = document.querySelector('.movements-table tbody');
    const syncUrlBase = @json(route('admin.movimientos.sync')).replace(/&amp;/g, '&');
    const filtersForm = document.querySelector('.filter-bar');
    let since = lastUpdatedAt;

    function getFilters() {
        const params = new URLSearchParams();
        params.set('since', since);
        ['q', 'sede', 'desde', 'hasta'].forEach(name => {
            const el = document.querySelector(`[name="${name}"]`);
            if (el && el.value) {
                params.set(name, el.value);
            }
        });
        return params.toString();
    }

    async function fetchUpdates() {
        try {
            const url = syncUrlBase + '?' + getFilters();
            const response = await fetch(url);
            if (! response.ok) {
                return;
            }

            const payload = await response.json();
            if (! payload.updated_at) {
                return;
            }

            if (payload.rows.length === 0 && payload.removed.length === 0) {
                since = payload.updated_at;
                return;
            }

            payload.rows.forEach(update => {
                const existing = document.querySelector(`[data-movimiento-id="${update.id}"]`);
                if (existing) {
                    replaceRow(existing, update);
                } else {
                    insertRow(update);
                }
            });

            payload.removed.forEach(id => {
                const existing = document.querySelector(`[data-movimiento-id="manual-${id}"]`);
                if (existing) {
                    existing.remove();
                }
            });

            since = payload.updated_at;
        } catch (error) {
            console.error('Movimiento sync error', error);
        }
    }

    function createCell(content, className = '') {
        const td = document.createElement('td');
        if (className) td.className = className;
        td.innerHTML = content;
        return td;
    }

    function renderNoteCell(row) {
        if (row.is_manual) {
            const tagClass = row.manual_exported ? 'ok' : 'manual';
            const tagLabel = row.manual_exported ? 'Exportada' : 'Manual';
            const note = `<span class="tag ${tagClass}">${tagLabel}</span>`;
            const detailClass = row.manual_exported ? 'manual-note manual-exported' : 'manual-note';
            const detail = row.manual_note ? `<div class="${detailClass}">${row.manual_note}</div>` : '';
            return createCell(note + detail, 'cell-note');
        } else if (row.metadata && row.metadata.motivo) {
            const note = `<span class="tag primary">Sincronización</span>`;
            let dateStr = '';
            if (row.metadata.fecha_venta_local) {
                const parts = row.metadata.fecha_venta_local.split(' ');
                if (parts.length >= 2) {
                    const dp = parts[0].split('-');
                    const tp = parts[1].split(':');
                    if (dp.length >= 3 && tp.length >= 2) {
                        dateStr = `<br><small style="color:var(--muted)">Fecha venta: ${dp[2]}/${dp[1]}/${dp[0]} ${tp[0]}:${tp[1]}</small>`;
                    }
                }
            }
            const detail = `<div class="manual-note">${row.metadata.motivo}${dateStr}</div>`;
            return createCell(note + detail, 'cell-note');
        }
        return createCell('—', 'cell-note');
    }

    function renderRow(row) {
        const tr = document.createElement('tr');
        tr.setAttribute('data-movimiento-id', row.id);
        tr.appendChild(createCell(row.created_at, 'cell-nowrap'));
        tr.appendChild(createCell(row.codigo, 'cell-code'));
        tr.appendChild(createCell(`<span title="${row.producto}">${row.producto}</span>`, 'cell-product'));
        tr.appendChild(createCell(
            `<span class="route-pill">${row.origen}</span> <span class="route-arrow">→</span> <span class="route-pill route-pill-dest">${row.destino}</span>`,
            'cell-route'
        ));
        const classification = row.classification || 'automatica';
        let classLabel = 'Automático';
        let classTag = 'req';
        
        if (classification === 'manual') {
            classLabel = 'Manual';
            classTag = 'manual';
        } else if (classification === 'mayor_demanda') {
            classLabel = 'Mayor Demanda';
            classTag = 'warn';
        } else if (classification === 'migracion') {
            classLabel = 'Migración';
            classTag = 'ok';
        } else if (classification.startsWith('sincronizacion_')) {
            const sede = classification.replace('sincronizacion_', '');
            const displaySede = sede.charAt(0).toUpperCase() + sede.slice(1);
            classLabel = 'Sync ' + displaySede;
            classTag = 'primary';
        } else if (classification === 'sincronizacion') {
            classLabel = 'Sincronización';
            classTag = 'primary';
        }

        tr.appendChild(createCell(`<span class="tag ${classTag}" title="Tipo original: ${row.tipo}">${classLabel}</span>`));
        tr.appendChild(createCell(`<strong>${row.cantidad}</strong>`, 'cell-qty'));
        tr.appendChild(createCell(row.usuario, 'cell-user'));
        tr.appendChild(renderNoteCell(row));
        return tr;
    }

    function replaceRow(existing, row) {
        const tr = renderRow(row);
        existing.replaceWith(tr);
    }

    function insertRow(row) {
        const tr = renderRow(row);
        const firstRow = tableBody.querySelector('tr');
        if (! firstRow) {
            tableBody.appendChild(tr);
            return;
        }

        const rows = Array.from(tableBody.querySelectorAll('tr'));
        const rowTimestamp = Number(new Date(row.created_at.split('/').reverse().join('-') + 'T00:00:00')) || 0;

        let inserted = false;
        for (const existing of rows) {
            const existingDate = existing.querySelector('.cell-nowrap')?.textContent?.trim();
            if (! existingDate) continue;
            const existingTs = Number(new Date(existingDate.split('/').reverse().join('-') + 'T00:00:00')) || 0;
            if (rowTimestamp > existingTs) {
                existing.before(tr);
                inserted = true;
                break;
            }
        }

        if (! inserted) {
            tableBody.appendChild(tr);
        }
    }

    if (filtersForm) {
        filtersForm.addEventListener('submit', () => {
            since = lastUpdatedAt;
        });
    }

    if (window.AppSyncPoll) {
        window.AppSyncPoll.start(fetchUpdates, @json((int) config('inventario.sync_interval_ms', 60000)));
    } else {
        setInterval(fetchUpdates, @json((int) config('inventario.sync_interval_ms', 60000)));
    }
})();
</script>
@endpush
