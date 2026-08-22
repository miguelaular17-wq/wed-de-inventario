@extends('layouts.app')

@section('title', 'Catálogo de Productos')

@section('content')
<div class="cat-page">
<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="margin:0;">Catálogo de Productos</h1>
        <p class="lead" style="margin:4px 0 0;">
            {{ $rows->total() }} productos · Pulsa <strong>Cashea</strong> para ver niveles de pago
        </p>
    </div>
    <div>
        <a href="{{ route('catalogo.index') }}" class="btn btn-primary" style="background-color: var(--blue); color: white; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-images"></i> Ver Catálogo Gráfico / PDF
        </a>
    </div>
</div>

{{-- Barra de búsqueda --}}
<form method="GET" action="{{ route('vendedor.dashboard') }}" class="cat-search">
    <div class="cat-search-field">
        <span>🔍</span>
        <input
            type="search"
            id="q"
            name="q"
            value="{{ $q }}"
            placeholder="Buscar por nombre o código… (Enter para buscar)"
            autocomplete="off"
        >
    </div>
    @if($q)
        <a href="{{ route('vendedor.dashboard') }}">✕ Limpiar</a>
    @endif
</form>

{{-- Tabla --}}
<section class="cat-panel">
    <div class="cat-table-wrap">
        <table class="data-table cat-table" id="vendedor-tabla">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="width:42%;">Existencias</th>
                    <th style="width:340px;">Precios</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $precioUnidad = (float) ($row['precio_unidad'] ?? 0);
                        $precioMayor = (float) ($row['precio_mayor'] ?? 0);
                    @endphp
                    <tr
                        class="vendedor-row"
                        data-producto="{{ e($row['producto']) }}"
                        data-codigo="{{ e($row['cod_centro']) }}"
                        data-precio-unidad="{{ $precioUnidad }}"
                        data-precio-mayor="{{ $precioMayor }}"
                    >
                        <td>
                            <div class="cat-product">
                                <code class="cat-code">{{ $row['cod_centro'] }}</code>
                                <div class="cat-name">{{ $row['producto'] }}</div>
                                @if(!empty($row['categoria']))
                                    <span class="cat-tag">{{ $row['categoria'] }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="cat-stock">
                                <div class="cat-stock-global">
                                    <strong>{{ number_format($row['existencia_global']) }}</strong>
                                    <span>global</span>
                                </div>
                                <div class="cat-stock-sedes">
                                    @foreach ($sedes as $sedeCol)
                                        @php $stock = (int) ($row['stocks'][$sedeCol] ?? 0); @endphp
                                        <span class="cat-sede {{ $stock > 0 ? 'has-stock' : 'no-stock' }}">
                                            {{ config('inventario.display.'.$sedeCol, $sedeCol) }}
                                            <b>{{ $stock }}</b>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="cat-prices">
                                <div>
                                    <span>Unidad</span>
                                    <strong class="price-unit">{{ $precioUnidad > 0 ? '$'.number_format($precioUnidad, 2) : '—' }}</strong>
                                </div>
                                <div>
                                    <span>Desc. 20%</span>
                                    @if($precioUnidad > 0)
                                        <strong class="price-desc">${{ number_format($precioUnidad * 0.20, 2) }}</strong>
                                        <em>neto ${{ number_format($precioUnidad * 0.80, 2) }}</em>
                                    @else
                                        <strong class="price-empty">—</strong>
                                    @endif
                                </div>
                                <div>
                                    <span>Mayor</span>
                                    <strong class="price-mayor">{{ $precioMayor > 0 ? '$'.number_format($precioMayor, 2) : '—' }}</strong>
                                </div>
                                <button type="button" class="cat-cashea-btn" onclick="event.stopPropagation(); openCasheaFromRow(this)">Cashea</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align:center; padding:40px; color:var(--muted);">
                            No se encontraron productos.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@include('partials.pagination', ['paginator' => $rows])
</div>

{{-- ═══════════════════════════════════════════════
     MODAL — Niveles de Cashea
════════════════════════════════════════════════ --}}
<div id="cashea-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1000; align-items:center; justify-content:center;">
    <div id="cashea-modal" style="
        background:#fff; border-radius:16px; box-shadow:0 25px 60px rgba(0,0,0,.25);
        width:min(640px, 96vw); max-height:90vh; overflow-y:auto;
        padding:0; animation: slideUp .25s ease;
    ">
        {{-- Header --}}
        <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6); border-radius:16px 16px 0 0; padding:24px 28px; color:#fff; position:relative;">
            <button onclick="closeCashea()" style="position:absolute; top:16px; right:18px; background:rgba(255,255,255,.2); border:none; color:#fff; border-radius:8px; width:32px; height:32px; cursor:pointer; font-size:1.1rem; display:flex; align-items:center; justify-content:center;">✕</button>
            <div style="font-size:.8rem; opacity:.8; margin-bottom:4px; letter-spacing:.05em; text-transform:uppercase;">Niveles de Cashea</div>
            <h2 id="cashea-nombre" style="margin:0; font-size:1.2rem; font-weight:700; line-height:1.3;"></h2>
            <div style="margin-top:10px; display:flex; gap:16px; flex-wrap:wrap;">
                <div style="background:rgba(255,255,255,.15); border-radius:8px; padding:8px 14px;">
                    <div style="font-size:.75rem; opacity:.8;">Precio unidad</div>
                    <div id="cashea-punit" style="font-size:1.1rem; font-weight:700;"></div>
                </div>
                <div style="background:rgba(255,255,255,.15); border-radius:8px; padding:8px 14px;">
                    <div style="font-size:.75rem; opacity:.8;">Precio al mayor</div>
                    <div id="cashea-pmayor" style="font-size:1.1rem; font-weight:700;"></div>
                </div>
                <div style="background:rgba(255,255,255,.15); border-radius:8px; padding:8px 14px;">
                    <div style="font-size:.75rem; opacity:.8;">Desc. Especial (20%)</div>
                    <div id="cashea-descuento" style="font-size:1.1rem; font-weight:700; color:#e0f2fe;"></div>
                </div>
            </div>
        </div>

        {{-- Niveles --}}
        <div style="padding:24px 28px;">
            <p style="margin:0 0 16px; color:#64748b; font-size:.9rem;">
                Selecciona un nivel para calcular el pago inicial y las cuotas restantes.
            </p>

            <div id="cashea-niveles" style="display:flex; flex-direction:column; gap:10px;"></div>

            {{-- Resultado --}}
            <div id="cashea-resultado" style="display:none; margin-top:24px; background:#f8faff; border:1.5px solid #c7d2fe; border-radius:12px; padding:20px;">
                <div style="font-size:.85rem; font-weight:600; color:#6366f1; margin-bottom:14px; text-transform:uppercase; letter-spacing:.05em;">
                    Desglose de pago — <span id="res-nivel-label"></span>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div style="text-align:center; background:#fff; border-radius:10px; padding:14px; box-shadow:0 1px 4px rgba(0,0,0,.07);">
                        <div style="font-size:.75rem; color:#64748b; margin-bottom:4px;">💰 Pago inicial</div>
                        <div id="res-inicial" style="font-size:1.35rem; font-weight:800; color:#16a34a;"></div>
                    </div>
                    <div style="text-align:center; background:#fff; border-radius:10px; padding:14px; box-shadow:0 1px 4px rgba(0,0,0,.07);">
                        <div style="font-size:.75rem; color:#64748b; margin-bottom:4px;">📋 Restante</div>
                        <div id="res-restante" style="font-size:1.35rem; font-weight:800; color:#dc2626;"></div>
                    </div>
                    <div style="text-align:center; background:#fff; border-radius:10px; padding:14px; box-shadow:0 1px 4px rgba(0,0,0,.07);">
                        <div style="font-size:.75rem; color:#64748b; margin-bottom:4px;">🗓 Cuota × 3</div>
                        <div id="res-cuota" style="font-size:1.35rem; font-weight:800; color:#7c3aed;"></div>
                    </div>
                </div>
                <div id="res-detalle" style="margin-top:12px; font-size:.85rem; color:#475569; text-align:center;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
@keyframes slideUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
}
.vendedor-row { cursor: pointer; }
.vendedor-row:hover { background: #f8faff !important; }
.nivel-btn {
    display:flex; align-items:center; justify-content:space-between;
    border:1.5px solid #e2e8f0; border-radius:10px; padding:14px 18px;
    cursor:pointer; background:#fff; transition:all .2s; text-align:left; width:100%;
}
.nivel-btn:hover { border-color:#6366f1; background:#f5f3ff; transform:translateX(3px); }
.nivel-btn.activo { border-color:#6366f1; background:#ede9fe; }

main:has(.cat-page) {
    max-width: none;
    width: 100%;
    padding: 20px 28px 40px;
}
.cat-page {
    width: 100%;
}
.cat-search {
    display: flex;
    gap: 12px;
    align-items: center;
    margin: 0 0 18px;
}
.cat-search-field {
    position: relative;
    flex: 1;
}
.cat-search-field span {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #64748b;
}
.cat-search-field input {
    width: 100%;
    padding: 12px 14px 12px 42px;
    border-radius: 10px;
    border: 1.5px solid var(--border);
    font-size: 1rem;
    background: #fff;
    color: var(--text);
}
.cat-search-field input:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
}
.cat-search a {
    font-size: 0.88rem;
    color: var(--muted);
    text-decoration: none;
    white-space: nowrap;
}

.cat-panel {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow);
}
.cat-table-wrap { overflow: visible; border: none; }
.cat-table { width: 100%; }
.cat-table th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8fafc;
    font-size: 0.82rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 14px 20px;
}
.cat-table td {
    vertical-align: middle;
    padding: 16px 20px;
}

.cat-product { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
.cat-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.8rem;
    color: #2563eb;
    background: #eff6ff;
    padding: 2px 8px;
    border-radius: 6px;
}
.cat-name { font-weight: 650; color: #0f172a; line-height: 1.35; font-size: 0.98rem; }
.cat-tag {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 2px 9px;
}

.cat-stock { display: flex; align-items: center; gap: 16px; }
.cat-stock-global {
    min-width: 78px;
    text-align: center;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
    padding: 10px 12px;
}
.cat-stock-global strong {
    display: block;
    font-size: 1.35rem;
    color: #1d4ed8;
    line-height: 1.1;
}
.cat-stock-global span {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
}
.cat-stock-sedes {
    display: grid;
    grid-template-columns: repeat(3, minmax(110px, 1fr));
    gap: 8px;
    flex: 1;
}
.cat-sede {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
}
.cat-sede b { font-size: 0.95rem; }
.cat-sede.has-stock {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #166534;
}
.cat-sede.no-stock b { color: #94a3b8; font-weight: 500; }

.cat-prices {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px 12px;
    align-items: start;
}
.cat-prices div { display: flex; flex-direction: column; gap: 2px; }
.cat-prices span {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #94a3b8;
}
.cat-prices strong { font-size: 1.05rem; }
.cat-prices em {
    font-style: normal;
    font-size: 0.72rem;
    color: #64748b;
}
.price-unit { color: #16a34a; }
.price-desc { color: #0284c7; }
.price-mayor { color: #7c3aed; }
.price-empty { color: #94a3b8; }
.cat-cashea-btn {
    grid-column: 1 / -1;
    margin-top: 4px;
    border: 1px solid #ddd6fe;
    background: #f5f3ff;
    color: #6d28d9;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}
.cat-cashea-btn:hover { background: #ede9fe; }

@media (max-width: 1100px) {
    .cat-stock-sedes { grid-template-columns: repeat(2, minmax(100px, 1fr)); }
}
@media (max-width: 900px) {
    .cat-page { padding: 0 16px 32px; }
    .cat-table thead { display: none; }
    .cat-table, .cat-table tbody, .cat-table tr, .cat-table td { display: block; width: 100%; }
    .cat-table tr { padding: 16px; border-bottom: 1px solid var(--border); }
    .cat-table td { padding: 8px 0; }
    .cat-stock { flex-direction: column; align-items: stretch; }
    .cat-prices { margin-top: 4px; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const NIVELES = [
        { id: 1, label: 'Nivel 1', factor: {{ ($casheaLevels[1] ?? 60) / 100 }}, desc: '{{ $casheaLevels[1] ?? 60 }}% de inicial' },
        { id: 2, label: 'Nivel 2', factor: {{ ($casheaLevels[2] ?? 50) / 100 }}, desc: '{{ $casheaLevels[2] ?? 50 }}% de inicial' },
        { id: 3, label: 'Nivel 3', factor: {{ ($casheaLevels[3] ?? 40) / 100 }}, desc: '{{ $casheaLevels[3] ?? 40 }}% de inicial' },
        { id: 4, label: 'Nivel 4', factor: {{ ($casheaLevels[4] ?? 40) / 100 }}, desc: '{{ $casheaLevels[4] ?? 40 }}% de inicial' },
        { id: 5, label: 'Nivel 5', factor: {{ ($casheaLevels[5] ?? 40) / 100 }}, desc: '{{ $casheaLevels[5] ?? 40 }}% de inicial' },
        { id: 6, label: 'Nivel 6', factor: {{ ($casheaLevels[6] ?? 40) / 100 }}, desc: '{{ $casheaLevels[6] ?? 40 }}% de inicial' },
    ];

    const fmt = v => '$' + parseFloat(v).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function openCashea(row) {
        const nombre      = row.dataset.producto;
        const codigo      = row.dataset.codigo;
        const precioUnit  = parseFloat(row.dataset.precioUnidad) || 0;
        const precioMayor = parseFloat(row.dataset.precioMayor)  || 0;

        document.getElementById('cashea-nombre').textContent  = `${nombre} — ${codigo}`;
        document.getElementById('cashea-punit').textContent   = precioUnit  > 0 ? fmt(precioUnit)  : '—';
        document.getElementById('cashea-pmayor').textContent  = precioMayor > 0 ? fmt(precioMayor) : '—';
        
        const descEspecial = precioUnit * 0.20;
        const netoEspecial = precioUnit * 0.80;
        document.getElementById('cashea-descuento').textContent = precioUnit > 0 ? `${fmt(descEspecial)} (Neto: ${fmt(netoEspecial)})` : '—';

        document.getElementById('cashea-resultado').style.display = 'none';

        const container = document.getElementById('cashea-niveles');
        container.innerHTML = '';

        NIVELES.forEach(nivel => {
            const inicial   = precioUnit * nivel.factor;
            const restante  = precioUnit - inicial;
            const cuota     = restante / 3;

            const btn = document.createElement('button');
            btn.className = 'nivel-btn';
            btn.id = `nivel-btn-${nivel.id}`;
            btn.innerHTML = `
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff;
                                display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.95rem; flex-shrink:0;">
                        ${nivel.id}
                    </div>
                    <div>
                        <div style="font-weight:700; color:#1e293b;">${nivel.label}</div>
                        <div style="font-size:.8rem; color:#64748b;">${nivel.desc}</div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:.75rem; color:#64748b;">Inicial</div>
                    <div style="font-weight:800; font-size:1.05rem; color:#16a34a;">${fmt(inicial)}</div>
                </div>
            `;
            btn.addEventListener('click', () => selectNivel(nivel, precioUnit, inicial, restante, cuota));
            container.appendChild(btn);
        });

        const overlay = document.getElementById('cashea-overlay');
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function selectNivel(nivel, precio, inicial, restante, cuota) {
        // Mark active button
        document.querySelectorAll('.nivel-btn').forEach(b => b.classList.remove('activo'));
        document.getElementById(`nivel-btn-${nivel.id}`).classList.add('activo');

        document.getElementById('res-nivel-label').textContent = nivel.label;
        document.getElementById('res-inicial').textContent     = fmt(inicial);
        document.getElementById('res-restante').textContent    = fmt(restante);
        document.getElementById('res-cuota').textContent       = fmt(cuota);
        document.getElementById('res-detalle').textContent     =
            `Precio total: ${fmt(precio)} · Inicial: ${fmt(inicial)} (${nivel.factor*100}%) · 3 cuotas de ${fmt(cuota)}`;

        document.getElementById('cashea-resultado').style.display = 'block';
        document.getElementById('cashea-resultado').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    window.closeCashea = function () {
        document.getElementById('cashea-overlay').style.display = 'none';
        document.body.style.overflow = '';
    };

    window.openCasheaFromRow = function (el) {
        const row = el.closest ? el.closest('tr') : el;
        if (row) openCashea(row);
    };

    // Double-click on any row
    document.querySelectorAll('.vendedor-row').forEach(row => {
        row.addEventListener('dblclick', () => openCashea(row));
    });

    // Close on overlay click
    document.getElementById('cashea-overlay').addEventListener('click', function (e) {
        if (e.target === this) closeCashea();
    });

    // Close on Escape
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCashea(); });
})();
</script>
@endpush
