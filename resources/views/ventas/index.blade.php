@extends('layouts.app')

@section('title', 'Ventas')

@section('content')
<div class="page-header">
    <h1>Ventas — {{ $sede }}</h1>
    <p class="lead">Mostrando {{ $rows->count() }} de {{ $calculatedCount }} productos calculados.</p>
</div>

<div class="stats-row" data-tour="ventas-stats">
    <div class="stat-chip"><strong>{{ $rows->count() }}</strong> filas visibles</div>
    <div class="stat-chip"><strong>{{ $calculatedCount }}</strong> total calculado</div>
</div>

<form id="filters-form" method="GET" class="filter-bar" data-auto-filter data-auto-filter-delay="350" data-tour="ventas-filters">
    <div class="field field-wide">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Producto o código…" autocomplete="off">
    </div>
    <div class="field">
        <label for="categoria-select">Categoría</label>
        <select name="categoria" id="categoria-select">
            <option value="Ninguno">Todas</option>
            @foreach ($categorias as $cat)
                <option value="{{ $cat }}" @selected($filters['categoria'] === $cat)>{{ $cat }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="subcategoria-select">Subcategoría</label>
        <select name="subcategoria" id="subcategoria-select">
            <option value="Ninguno">Todas</option>
            @foreach ($subcategorias as $sub)
                <option value="{{ $sub }}" @selected($filters['subcategoria'] === $sub)>{{ $sub }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="accion-select">Acción</label>
        <select name="accion" id="accion-select">
            @foreach ($accionesCombo as $acc)
                <option value="{{ $acc }}" @selected($filters['accion'] === $acc)>{{ $acc === 'Ninguno' ? 'Todas' : $acc }}</option>
            @endforeach
        </select>
    </div>
    <div class="field req-filter" @if(!$reqFiltersVisible) style="display:none" @endif>
        <label for="req_opc">Sede (OPC)</label>
        <select name="req_opc" id="req_opc">
            <option value="Todos">Todos</option>
            @foreach ($sedesOpc as $s)
                <option value="{{ $s }}" @selected($filters['req_opc'] === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <div class="field req-filter" @if(!$reqFiltersVisible) style="display:none" @endif>
        <label for="req_color">Estado requisición</label>
        <select name="req_color" id="req_color">
            @foreach (config('inventario.req_colores') as $c)
                <option value="{{ $c }}" @selected($filters['req_color'] === $c)>{{ $c }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="tiempo_pronostico">Pronóstico (días)</label>
        <input type="number" id="tiempo_pronostico" name="tiempo_pronostico" min="1" max="365" value="{{ $tiempoPronostico }}">
    </div>
</form>

<section class="table-section-full" data-tour="ventas-table">
    <div class="table-wrap table-wrap-full">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Exist.</th>
                    <th>Categoría</th>
                    <th>Subcat.</th>
                    <th>Venta 15d</th>
                    @foreach ($sedesStock as $sedeCol)
                        <th>{{ config('inventario.display.'.$sedeCol, $sedeCol) }}</th>
                    @endforeach
                    <th>Sugerido</th>
                    <th>OPC</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $tag = $row['req_tag'] ?? '';
                        $rowClass = match($tag) {
                            'req_ok' => 'row-req-ok',
                            'req_parcial' => 'row-req-parcial',
                            'req_insuf' => 'row-req-insuf',
                            default => '',
                        };
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td>{{ $row['cod_centro'] }}</td>
                        <td>{{ $row['producto'] }}</td>
                        <td>{{ $row['existencia'] }}</td>
                        <td>{{ $row['categoria'] }}</td>
                        <td>{{ $row['subcategoria'] }}</td>
                        <td>{{ $row['venta'] }}</td>
                        @foreach ($sedesStock as $sedeCol)
                            <td>{{ $row['stocks'][$sedeCol] ?? 0 }}</td>
                        @endforeach
                        <td>{{ $row['sugerido'] ?: '—' }}</td>
                        <td>{{ $row['opc'] ?: '—' }}</td>
                        <td>
                            @php $acc = $row['accion']; @endphp
                            @if ($acc === 'HACER REQUISICION')
                                <span class="tag req">{{ $acc }}</span>
                            @elseif ($acc === 'TIENE EXISTENCIA')
                                <span class="tag ok">{{ $acc }}</span>
                            @elseif ($acc === 'NO TIENE EXISTENCIA')
                                <span class="tag warn">{{ $acc }}</span>
                            @else
                                <span class="tag no">{{ $acc }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 10 + count($sedesStock) }}">Sin datos. Importe el Excel multisede desde el panel admin.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    const accion = document.getElementById('accion-select');
    const reqBlocks = document.querySelectorAll('.req-filter');
    const cat = document.getElementById('categoria-select');
    const sub = document.getElementById('subcategoria-select');

    if (accion) {
        accion.addEventListener('change', () => {
            const show = accion.value === 'HACER REQUISICION';
            reqBlocks.forEach(el => el.style.display = show ? '' : 'none');
        });
    }

    if (cat && sub) {
        function updateSubDisabled() {
            sub.disabled = (cat.value === 'Ninguno');
        }
        updateSubDisabled();
        cat.addEventListener('change', updateSubDisabled);
    }

    let since = @json($stockUpdatedAt);
    const tableBody = document.querySelector('section.table-section-full tbody');
    const filterForm = document.getElementById('filters-form');

    async function syncVentas() {
        if (!since || !tableBody) {
            return;
        }

        const url = new URL(@json(route('ventas.sync')).replace(/&amp;/g, '&'), window.location.origin);
        url.searchParams.set('since', since);
        new FormData(filterForm).forEach((value, key) => url.searchParams.set(key, value));

        try {
            const r = await fetch(url.toString());
            if (!r.ok) {
                return;
            }

            const j = await r.json();
            if (!j.changed) {
                return;
            }

            since = j.updated_at;
            tableBody.innerHTML = j.rows.map(row => {
                const stocks = @json($sedesStock).map(sede => `<td>${row.stocks[sede] ?? 0}</td>`).join('');
                const tag = row.accion === 'HACER REQUISICION'
                    ? '<span class="tag req">HACER REQUISICION</span>'
                    : row.accion === 'TIENE EXISTENCIA'
                        ? '<span class="tag ok">TIENE EXISTENCIA</span>'
                        : row.accion === 'NO TIENE EXISTENCIA'
                            ? '<span class="tag warn">NO TIENE EXISTENCIA</span>'
                            : `<span class="tag no">${row.accion}</span>`;

                return `<tr>
                    <td>${row.cod_centro}</td>
                    <td>${row.producto}</td>
                    <td>${row.existencia}</td>
                    <td>${row.categoria}</td>
                    <td>${row.subcategoria}</td>
                    <td>${row.venta}</td>
                    ${stocks}
                    <td>${row.sugerido ?? '—'}</td>
                    <td>${row.opc ?? '—'}</td>
                    <td>${tag}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            // ignore transient sync issues
        }
    }

    setInterval(syncVentas, 15000);
})();
</script>
@endpush
