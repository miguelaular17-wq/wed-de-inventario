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
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
            @foreach ($tipos as $t)
                <option value="{{ $t }}" @selected($filters['tipo'] === $t)>{{ $t }}</option>
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
                    <th>Fecha</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Origen → Destino</th>
                    <th>Tipo</th>
                    <th>Cant.</th>
                    <th>Usuario</th>
                    <th>Nota</th>
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
                        <td>
                            <span class="tag {{ strtolower($row['tipo']) === 'requisicion' ? 'req' : 'no' }}">{{ $row['tipo'] }}</span>
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
                    <tr><td colspan="8">Sin movimientos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('head')
<style>
    .movements-table .cell-nowrap { white-space: nowrap; font-size: .82rem; color: var(--muted); }
    .movements-table .cell-code { font-family: ui-monospace, monospace; font-size: .82rem; white-space: nowrap; }
    .movements-table .cell-product { max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .movements-table .cell-qty { text-align: center; }
    .movements-table .cell-user { white-space: nowrap; font-weight: 500; }
    .movements-table .cell-route { white-space: nowrap; }
    .route-pill {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 6px;
        background: #f1f5f9;
        font-size: .78rem;
        font-weight: 500;
    }
    .route-pill-dest { background: #eef4fc; color: var(--blue); }
    .route-arrow { color: var(--muted); margin: 0 4px; font-size: .85rem; }
    .manual-note { margin-top: 4px; color: var(--muted); font-size: .82rem; line-height: 1.3; }
    .manual-note.manual-exported { color: var(--green); }
    .cell-note { max-width: 240px; }
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
        ['q', 'sede', 'tipo', 'desde', 'hasta'].forEach(name => {
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
        tr.appendChild(createCell(`<span class="tag ${row.tipo.toLowerCase() === 'requisicion' ? 'req' : 'no'}">${row.tipo}</span>`));
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

    setInterval(fetchUpdates, 15000);
})();
</script>
@endpush
