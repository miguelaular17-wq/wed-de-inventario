@extends('layouts.app')

@section('title', 'Existencias - Inventario Global')

@push('head')
<style>
/* ===== EDURAR EXISTENCIAS ===== */
.edurar-wrap {
    max-width: 1600px;
    margin: 0 auto;
    padding: 20px 16px;
    font-family: 'Outfit', sans-serif;
}

/* Filters bar */
.edurar-filters {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
    box-shadow: 0 1px 6px rgba(0,0,0,.06);
}

.edurar-filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 160px;
    flex: 1;
}

.edurar-filter-group label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.edurar-filter-group input,
.edurar-filter-group select {
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.875rem;
    color: #1e293b;
    background: #f8fafc;
    transition: border .2s, box-shadow .2s;
    outline: none;
}

.edurar-filter-group input:focus,
.edurar-filter-group select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    background: #fff;
}

.edurar-filter-group.search-group { flex: 2; min-width: 220px; }

.filter-btn {
    padding: 9px 20px;
    background: #3b82f6;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    align-self: flex-end;
    transition: background .2s;
}
.filter-btn:hover { background: #2563eb; }
.filter-btn.clear {
    background: #f1f5f9;
    color: #475569;
}
.filter-btn.clear:hover { background: #e2e8f0; }

/* Stats bar */
.edurar-stats {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.edurar-stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 18px;
    font-size: 0.85rem;
    color: #475569;
    font-weight: 500;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.edurar-stat-card strong { color: #1e293b; font-size: 1.05rem; }

/* Table */
.edurar-table-wrap {
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 1px 8px rgba(0,0,0,.08);
    border: 1px solid #e2e8f0;
}

.edurar-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
    background: #fff;
}

.edurar-table thead th {
    background: #1e293b;
    color: #94a3b8;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    font-size: 0.72rem;
    padding: 12px 10px;
    text-align: center;
    border-bottom: 2px solid #334155;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 5;
}

.edurar-table thead th:first-child,
.edurar-table thead th:nth-child(2) {
    text-align: left;
}

.edurar-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background .15s;
}

.edurar-table tbody tr:hover > td {
    background: #f8fafc;
}

.edurar-table tbody td {
    padding: 10px 10px;
    color: #334155;
    text-align: center;
    vertical-align: middle;
}

.edurar-table tbody td:first-child { text-align: left; }
.edurar-table tbody td:nth-child(2) { text-align: left; }

.td-code {
    font-family: monospace;
    font-size: 0.78rem;
    color: #64748b;
}

.td-name {
    font-weight: 600;
    color: #1e293b;
    max-width: 260px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.td-cat {
    font-size: 0.75rem;
    color: #64748b;
}

.badge-stock {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.78rem;
}

.badge-stock.high  { background: #dcfce7; color: #166534; }
.badge-stock.mid   { background: #fef9c3; color: #713f12; }
.badge-stock.low   { background: #fee2e2; color: #991b1b; }
.badge-stock.zero  { background: #f1f5f9; color: #94a3b8; }

.td-price {
    font-weight: 600;
    color: #0f766e;
    white-space: nowrap;
}
.td-price-mayor { color: #2563eb; }

/* Toggle ventas button */
.btn-ventas-toggle {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
}
.btn-ventas-toggle:hover {
    background: #e0f2fe;
    color: #0284c7;
    border-color: #7dd3fc;
}
.btn-ventas-toggle.active {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #93c5fd;
}
.btn-ventas-toggle svg { width: 13px; height: 13px; }

/* Ventas expansion row */
.ventas-expansion {
    display: none;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-top: 2px solid #bae6fd;
}
.ventas-expansion.open { display: table-row; }

.ventas-expansion td {
    padding: 14px 20px !important;
}

.ventas-grid {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: stretch;
}

.venta-sede-card {
    background: #fff;
    border: 1px solid #e0f2fe;
    border-radius: 10px;
    padding: 10px 16px;
    min-width: 110px;
    text-align: center;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
}

.venta-sede-card .sede-name {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: .05em;
    margin-bottom: 4px;
}

.venta-sede-card .venta-15d-val {
    font-size: 1.25rem;
    font-weight: 800;
    color: #0284c7;
    line-height: 1;
}

.venta-sede-card .venta-label {
    font-size: 0.68rem;
    color: #94a3b8;
    margin-top: 2px;
}

.venta-sede-card.zero .venta-15d-val { color: #cbd5e1; }

/* No data row */
.no-data-row td {
    text-align: center;
    padding: 48px !important;
    color: #94a3b8;
    font-size: 0.9rem;
}

/* Pagination fix */
.pagination { margin-top: 24px; }
</style>
@endpush

@section('content')
<div class="edurar-wrap">

    {{-- Page header --}}
    <div style="margin-bottom: 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="font-size:1.5rem; font-weight:800; color:#1e293b; margin:0;">Existencias de Inventario</h1>
            <p style="font-size:0.85rem; color:#64748b; margin:4px 0 0;">Vista global de stock por sede</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('edurar.existencias') }}" id="edurar-form">
        <div class="edurar-filters">
            {{-- Search --}}
            <div class="edurar-filter-group search-group">
                <label>🔍 Buscar</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Nombre o código del producto...">
            </div>

            {{-- Category --}}
            <div class="edurar-filter-group">
                <label>Categoría</label>
                <select name="categoria" id="sel-categoria">
                    <option value="Ninguno">— Todas —</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat }}" {{ $filters['categoria'] === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Subcategory (dynamic) --}}
            <div class="edurar-filter-group" id="wrap-subcategoria" style="{{ $filters['categoria'] === 'Ninguno' ? 'display:none' : '' }}">
                <label>Subcategoría</label>
                <select name="subcategoria" id="sel-subcategoria">
                    <option value="Ninguno">— Todas —</option>
                    @foreach($subcategorias as $sub)
                        <option value="{{ $sub }}" {{ $filters['subcategoria'] === $sub ? 'selected' : '' }}>{{ $sub }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Provider --}}
            <div class="edurar-filter-group">
                <label>Proveedor</label>
                <select name="proveedor">
                    <option value="Ninguno">— Todos —</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov }}" {{ $filters['proveedor'] === $prov ? 'selected' : '' }}>{{ $prov }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="filter-btn">Filtrar</button>
            @if($filters['q'] !== '' || $filters['categoria'] !== 'Ninguno' || $filters['proveedor'] !== 'Ninguno')
                <a href="{{ route('edurar.existencias') }}" class="filter-btn clear">Limpiar</a>
            @endif
        </div>
    </form>

    {{-- Stats --}}
    <div class="edurar-stats">
        <div class="edurar-stat-card">
            Total mostrado: <strong>{{ $rows->total() }}</strong> productos
        </div>
        <div class="edurar-stat-card">
            Página <strong>{{ $rows->currentPage() }}</strong> de <strong>{{ $rows->lastPage() }}</strong>
        </div>
        @if($filters['categoria'] !== 'Ninguno')
            <div class="edurar-stat-card">
                Categoría: <strong>{{ $filters['categoria'] }}</strong>
            </div>
        @endif
    </div>

    {{-- Table --}}
    <div class="edurar-table-wrap">
        <table class="edurar-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Exist. Global</th>
                    @foreach($sedes as $s)
                        <th>{{ config('inventario.display.' . $s, $s) }}</th>
                    @endforeach
                    <th>P. Unidad</th>
                    <th>P. Mayor</th>
                    <th>Ventas 15d</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        $g = $row['global_stock'];
                        $badgeClass = $g === 0 ? 'zero' : ($g >= 10 ? 'high' : ($g >= 4 ? 'mid' : 'low'));
                    @endphp
                    <tr class="product-row" data-id="{{ $row['id'] }}">
                        <td class="td-code">{{ $row['codigo'] }}</td>
                        <td>
                            <div class="td-name" title="{{ $row['nombre'] }}">{{ $row['nombre'] }}</div>
                            @if($row['proveedor'])
                                <div class="td-cat">{{ $row['proveedor'] }}</div>
                            @endif
                        </td>
                        <td class="td-cat">
                            <div>{{ $row['categoria'] }}</div>
                            @if($row['subcategoria'])
                                <div style="color:#94a3b8; font-size:0.72rem;">{{ $row['subcategoria'] }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge-stock {{ $badgeClass }}">{{ $g }}</span>
                        </td>
                        @foreach($sedes as $s)
                            @php
                                $sv = $row['stocks'][$s] ?? 0;
                                $sc = $sv === 0 ? 'zero' : ($sv >= 10 ? 'high' : ($sv >= 4 ? 'mid' : 'low'));
                            @endphp
                            <td><span class="badge-stock {{ $sc }}">{{ $sv }}</span></td>
                        @endforeach
                        <td class="td-price">${{ number_format($row['precio_unidad'], 2) }}</td>
                        <td class="td-price td-price-mayor">${{ number_format($row['precio_mayor'], 2) }}</td>
                        <td>
                            <button class="btn-ventas-toggle" onclick="toggleVentas({{ $row['id'] }}, this)" type="button">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/>
                                </svg>
                                Ver
                            </button>
                        </td>
                    </tr>
                    {{-- Ventas 15d expansion row --}}
                    <tr class="ventas-expansion" id="ventas-{{ $row['id'] }}">
                        <td colspan="{{ 4 + count($sedes) + 3 }}">
                            <div style="font-size:0.8rem; font-weight:700; color:#0284c7; margin-bottom:10px; text-transform:uppercase; letter-spacing:.05em;">
                                📊 Ventas de los últimos 15 días por sede — {{ $row['nombre'] }}
                            </div>
                            <div class="ventas-grid">
                                @foreach($sedes as $s)
                                    @php $v = $row['ventas_15d'][$s] ?? 0; @endphp
                                    <div class="venta-sede-card {{ $v == 0 ? 'zero' : '' }}">
                                        <div class="sede-name">{{ config('inventario.display.' . $s, $s) }}</div>
                                        <div class="venta-15d-val">{{ number_format($v, 1) }}</div>
                                        <div class="venta-label">uds. en 15d</div>
                                    </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="no-data-row">
                        <td colspan="{{ 4 + count($sedes) + 3 }}">
                            <div style="font-size:2rem; margin-bottom:8px;">📦</div>
                            No se encontraron productos con los filtros aplicados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    {{ $rows->withQueryString()->links() }}

</div>

<script>
function toggleVentas(id, btn) {
    const row = document.getElementById('ventas-' + id);
    const open = row.classList.contains('open');
    row.classList.toggle('open', !open);
    btn.classList.toggle('active', !open);
    btn.innerHTML = open
        ? `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:13px;height:13px"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/></svg> Ver`
        : `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:13px;height:13px"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg> Ocultar`;
}

// Dynamic subcategories
const selCat = document.getElementById('sel-categoria');
const wrapSub = document.getElementById('wrap-subcategoria');
const selSub = document.getElementById('sel-subcategoria');
const currentSubcat = "{{ $filters['subcategoria'] }}";

selCat.addEventListener('change', function () {
    const cat = this.value;
    if (cat === 'Ninguno') {
        wrapSub.style.display = 'none';
        selSub.innerHTML = '<option value="Ninguno">— Todas —</option>';
        return;
    }
    fetch('{{ route("edurar.subcategorias") }}?categoria=' + encodeURIComponent(cat))
        .then(r => r.json())
        .then(subs => {
            wrapSub.style.display = subs.length > 0 ? '' : 'none';
            selSub.innerHTML = '<option value="Ninguno">— Todas —</option>';
            subs.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s;
                opt.textContent = s;
                selSub.appendChild(opt);
            });
        });
});
</script>
@endsection
