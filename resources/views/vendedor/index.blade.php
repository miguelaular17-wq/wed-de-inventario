@extends('layouts.app')

@section('title', 'Catálogo de Productos')

@section('content')
<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="margin:0;">Catálogo de Productos</h1>
        <p class="lead" style="margin:4px 0 0;">
            {{ $rows->total() }} productos · Doble clic en una fila para ver niveles de pago
        </p>
    </div>
</div>

{{-- Barra de búsqueda --}}
<form method="GET" action="{{ route('vendedor.dashboard') }}" style="margin-bottom:20px; display:flex; gap:10px; align-items:center;">
    <div style="flex:1; max-width:480px; position:relative;">
        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--muted); pointer-events:none;">🔍</span>
        <input
            type="search"
            id="q"
            name="q"
            value="{{ $q }}"
            placeholder="Buscar por nombre o código… (Enter para buscar)"
            autocomplete="off"
            style="width:100%; padding:10px 12px 10px 36px; border-radius:8px; border:1.5px solid var(--border); font-size:0.95rem; background:var(--surface); color:var(--text); transition:border-color .2s;"
            onfocus="this.style.borderColor='var(--blue)'"
            onblur="this.style.borderColor='var(--border)'"
        >
    </div>
    @if($q)
        <a href="{{ route('vendedor.dashboard') }}" style="font-size:.85rem; color:var(--muted); text-decoration:none;">✕ Limpiar</a>
    @endif
</form>

{{-- Tabla --}}
<section class="table-section-full">
    <div class="table-wrap table-wrap-full">
        <table class="data-table" id="vendedor-tabla">
            <thead>
                <tr>
                    <th style="width:120px;">Código</th>
                    <th>Producto</th>
                    <th style="width:160px;">Categoría</th>
                    <th class="col-number" style="width:110px; color:var(--blue); font-weight:700;">Exist. Global</th>
                    @foreach ($sedes as $sedeCol)
                        <th class="col-number" style="width:100px;">{{ config('inventario.display.'.$sedeCol, $sedeCol) }}</th>
                    @endforeach
                    <th class="col-number" style="width:120px; color:#16a34a; font-weight:700;">P. Unidad</th>
                    <th class="col-number" style="width:120px; color:#7c3aed; font-weight:700;">P. Mayor</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr
                        class="vendedor-row"
                        style="cursor:pointer; transition:background .15s;"
                        data-producto="{{ e($row['producto']) }}"
                        data-codigo="{{ e($row['cod_centro']) }}"
                        data-precio-unidad="{{ $row['precio_unidad'] ?? 0 }}"
                        data-precio-mayor="{{ $row['precio_mayor'] ?? 0 }}"
                        title="Doble clic para ver niveles de pago"
                    >
                        <td class="col-code">{{ $row['cod_centro'] }}</td>
                        <td style="font-weight:500;">{{ $row['producto'] }}</td>
                        <td style="color:var(--muted); font-size:.88rem;">{{ $row['categoria'] }}</td>
                        <td class="col-number" style="font-weight:700; color:var(--blue); font-size:1.05rem;">
                            {{ $row['existencia_global'] }}
                        </td>
                        @foreach ($sedes as $sedeCol)
                            @php $stock = $row['stocks'][$sedeCol] ?? 0; @endphp
                            <td class="col-number" style="{{ $stock > 0 ? 'font-weight:600; color:#0f172a;' : 'color:#94a3b8;' }}">
                                {{ $stock }}
                            </td>
                        @endforeach
                        <td class="col-number" style="font-weight:700; color:#16a34a;">
                            @if(($row['precio_unidad'] ?? 0) > 0)
                                ${{ number_format($row['precio_unidad'], 2) }}
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                        <td class="col-number" style="font-weight:700; color:#7c3aed;">
                            @if(($row['precio_mayor'] ?? 0) > 0)
                                ${{ number_format($row['precio_mayor'], 2) }}
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 4 + count($sedes) + 2 }}" style="text-align:center; padding:32px; color:var(--muted);">
                            No se encontraron productos.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@include('partials.pagination', ['paginator' => $rows])

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
.vendedor-row:hover { background: #f8faff !important; }
.nivel-btn {
    display:flex; align-items:center; justify-content:space-between;
    border:1.5px solid #e2e8f0; border-radius:10px; padding:14px 18px;
    cursor:pointer; background:#fff; transition:all .2s; text-align:left; width:100%;
}
.nivel-btn:hover { border-color:#6366f1; background:#f5f3ff; transform:translateX(3px); }
.nivel-btn.activo { border-color:#6366f1; background:#ede9fe; }
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
