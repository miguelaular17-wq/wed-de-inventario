@extends('layouts.app')

@section('title', !empty($modoJrz) ? 'Catálogo JRZ — Stock y ventas' : 'Catálogo de Productos')

@section('content')
@php
    use App\Support\VentaDescuento;
    $descPct = VentaDescuento::porcentaje();
    $netoFactor = VentaDescuento::factorNeto();
    $descEtiqueta = VentaDescuento::etiqueta();
    $modoJrz = !empty($modoJrz);
    $verPrecios = ! $modoJrz && (auth()->user()?->canSeeCatalogoPrecios() ?? true);
    $diasVentas = (int) ($diasVentas ?? 30);
    $diasOpciones = $diasOpciones ?? [7, 15, 30, 60, 90];
    $ventasPorSede = $ventasPorSede ?? [];
    $categoria = (string) ($categoria ?? '');
    $subcategoria = (string) ($subcategoria ?? '');
    $categoriasTree = $categoriasTree ?? [];
    $formAction = $modoJrz ? route('vendedor.jrz') : route('vendedor.dashboard');
    $filtrosLimpiar = $modoJrz
        ? array_filter(['dias' => $diasVentas])
        : [];
@endphp
<div class="cat-page">
<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="margin:0;">{{ $modoJrz ? 'Catálogo vendedor JRZ' : 'Catálogo de Productos' }}</h1>
        <p class="lead" style="margin:4px 0 0;">
            @if($modoJrz)
                {{ $rows->total() }} productos · Stock y <strong>ventas</strong> de todas las sedes (últimos días elegidos)
            @else
                {{ $rows->total() }} productos · Pulsa <strong>Cashea</strong> para ver niveles de pago
            @endif
        </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        @if(! $modoJrz && auth()->user()?->canAccess('vendedor.jrz'))
            <a href="{{ route('vendedor.jrz', ['dias' => 30]) }}" class="btn secondary" style="padding:10px 14px; border-radius:8px; text-decoration:none;">Vista JRZ + ventas</a>
        @endif
        @if(! $modoJrz && $verPrecios)
            <a href="{{ route('etiquetas.index') }}" class="btn secondary" style="padding:10px 14px; border-radius:8px; text-decoration:none; font-weight:600;">🏷️ Sacar etiquetas</a>
        @endif
        @if($modoJrz)
            <button type="button" class="btn secondary" style="padding:10px 14px; border-radius:8px; font-weight:600;"
                    onclick="document.getElementById('rep-proveedor').style.display='flex'">📊 Reporte por proveedor</button>
        @endif
        <a href="{{ route('catalogo.index') }}" class="btn btn-primary" style="background-color: var(--blue); color: white; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-images"></i> Ver Catálogo Gráfico / PDF
        </a>
    </div>
</div>

@if($modoJrz)
<div id="rep-proveedor" style="display:none; position:fixed; inset:0; z-index:90; background:rgba(15,23,42,.55); align-items:center; justify-content:center; padding:16px;"
     onclick="if (event.target === this) this.style.display='none'">
    <form method="GET" action="{{ route('vendedor.jrz.reporte_proveedor') }}"
          style="background:#fff; border-radius:12px; padding:20px 22px; width:100%; max-width:440px; box-shadow:0 20px 40px rgba(15,23,42,.25);"
          onsubmit="setTimeout(() => document.getElementById('rep-proveedor').style.display='none', 300)">
        <h3 style="margin:0 0 4px;">Reporte por proveedor</h3>
        <p class="muted" style="margin:0 0 14px; font-size:.85rem;">Excel con lo vendido de cada producto en cada sede, el total vendido y la existencia total.</p>
        <label style="display:block; font-weight:600; font-size:.85rem; margin-bottom:4px;">Proveedor *</label>
        <input type="text" name="proveedor" list="rep-proveedores" required placeholder="Escribe o elige el proveedor"
               style="width:100%; padding:9px 10px; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:12px;">
        <datalist id="rep-proveedores">
            @foreach($proveedores as $prov)
                <option value="{{ $prov }}">
            @endforeach
        </datalist>
        <label style="display:block; font-weight:600; font-size:.85rem; margin-bottom:4px;">Días de ventas *</label>
        <div style="display:flex; gap:8px; margin-bottom:16px;">
            <select onchange="if (this.value) this.form.dias.value = this.value" style="flex:1; padding:9px 10px; border:1px solid #cbd5e1; border-radius:8px;">
                <option value="">Elegir…</option>
                @foreach($diasOpciones as $d)
                    <option value="{{ $d }}" @selected($d === $diasVentas)>Últimos {{ $d }} días</option>
                @endforeach
            </select>
            <input type="number" name="dias" min="1" max="730" required value="{{ $diasVentas }}" title="O escribe la cantidad de días"
                   style="width:110px; padding:9px 10px; border:1px solid #cbd5e1; border-radius:8px;">
        </div>
        <div style="display:flex; gap:8px; justify-content:flex-end;">
            <button type="button" class="btn secondary" onclick="document.getElementById('rep-proveedor').style.display='none'">Cancelar</button>
            <button type="submit" class="btn primary">Descargar Excel</button>
        </div>
    </form>
</div>
@endif

{{-- Barra de búsqueda (+ filtros JRZ) --}}
<form method="GET" action="{{ $formAction }}" class="cat-search" id="vendedor-filtros">
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
    @if($modoJrz)
        <label class="cat-dias-label" for="categoria">
            Categoría
            <select name="categoria" id="categoria" onchange="onCategoriaChange()">
                <option value="">Todas</option>
                @foreach(array_keys($categoriasTree) as $cat)
                    <option value="{{ $cat }}" @selected($categoria === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
        </label>
        <label class="cat-dias-label" for="subcategoria">
            Subcategoría
            <select name="subcategoria" id="subcategoria" onchange="this.form.submit()" @disabled($categoria === '')>
                <option value="">Todas</option>
            </select>
        </label>
        <label class="cat-dias-label" for="dias">
            Ventas por sede
            <select name="dias" id="dias" onchange="this.form.submit()">
                @foreach($diasOpciones as $d)
                    <option value="{{ $d }}" @selected($diasVentas === (int) $d)>{{ $d }} días</option>
                @endforeach
            </select>
        </label>
    @endif
    @if($q !== '' || $categoria !== '' || $subcategoria !== '')
        <a href="{{ $modoJrz ? route('vendedor.jrz', $filtrosLimpiar) : route('vendedor.dashboard') }}">✕ Limpiar</a>
    @endif
</form>

{{-- Tabla --}}
<section class="cat-panel">
    <div class="cat-table-wrap">
        <table class="data-table cat-table" id="vendedor-tabla">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="width:{{ $verPrecios ? '42%' : '58%' }};">Existencias</th>
                    @if($verPrecios)
                        <th style="width:340px;">Precios</th>
                    @endif
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
                            <div class="cat-product-fila">
                            @include('partials.foto-producto', ['codigo' => $row['cod_centro'], 'alt' => $row['producto']])
                            <div class="cat-product">
                                <code class="cat-code">{{ \App\Support\Sku::principal($row['cod_centro']) }}</code>
                                <div class="cat-name">{{ $row['producto'] }}</div>
                                @if(!empty($row['categoria']))
                                    <span class="cat-tag">{{ $row['categoria'] }}</span>
                                @endif
                                @if($modoJrz && !empty($row['subcategoria']))
                                    <span class="cat-tag" style="background:#eef2ff;color:#4338ca;">{{ $row['subcategoria'] }}</span>
                                @endif
                            </div>
                            </div>
                        </td>
                        <td>
                            <div class="cat-stock">
                                <div class="cat-stock-global">
                                    <strong>{{ number_format($row['existencia_global']) }}</strong>
                                    <span>global</span>
                                </div>
                                @if($modoJrz)
                                    @php
                                        $totalVendidas = 0.0;
                                        foreach ($sedes as $sedeSuma) {
                                            $totalVendidas += (float) ($row['vendidas_por_sede'][strtoupper(trim((string) $sedeSuma))] ?? 0);
                                        }
                                    @endphp
                                    <div class="cat-stock-ventas-total" title="Suma de unidades netas vendidas en todas las sedes (últimos {{ $diasVentas }} días)">
                                        <strong>{{ number_format($totalVendidas, $totalVendidas == floor($totalVendidas) ? 0 : 1) }}</strong>
                                        <span>ventas {{ $diasVentas }}d</span>
                                    </div>
                                @endif
                                <div class="cat-stock-sedes">
                                    @foreach ($sedes as $sedeCol)
                                        @php
                                            $stock = (int) ($row['stocks'][$sedeCol] ?? 0);
                                            $sedeKey = strtoupper(trim((string) $sedeCol));
                                            $esJrz = $sedeKey === 'JRZ';
                                            $vendidas = $modoJrz
                                                ? (float) ($row['vendidas_por_sede'][$sedeKey] ?? 0)
                                                : null;
                                        @endphp
                                        <span class="cat-sede {{ $stock > 0 ? 'has-stock' : 'no-stock' }} {{ $modoJrz && $esJrz ? 'cat-sede-jrz' : '' }} {{ $modoJrz ? 'cat-sede-con-ventas' : '' }}">
                                            <span class="cat-sede-top">
                                                {{ config('inventario.display.'.$sedeCol, $sedeCol) }}
                                                <b>{{ $stock }}</b>
                                            </span>
                                            @if($modoJrz)
                                                <span class="cat-sede-ventas" title="Unidades netas vendidas en {{ $sedeKey }} (últimos {{ $diasVentas }} días)">
                                                    {{ number_format($vendidas, $vendidas == floor($vendidas) ? 0 : 1) }} vend.
                                                    <em>{{ $diasVentas }}d</em>
                                                </span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                        @if($verPrecios)
                        <td>
                            <div class="cat-prices">
                                <div>
                                    <span>Unidad</span>
                                    <strong class="price-unit">{{ $precioUnidad > 0 ? '$'.number_format($precioUnidad, 2) : '—' }}</strong>
                                </div>
                                <div>
                                    <span>Neto -{{ $descEtiqueta }}</span>
                                    @if($precioUnidad > 0)
                                        <strong class="price-desc">${{ number_format($precioUnidad * $netoFactor, 2) }}</strong>
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
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $verPrecios ? 3 : 2 }}" style="text-align:center; padding:40px; color:var(--muted);">
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

@unless($modoJrz)
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
                    <div style="font-size:.75rem; opacity:.8;">Desc. Especial ({{ $descEtiqueta }})</div>
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
@endunless
@endsection

@push('head')
<style>
@keyframes slideUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
}
.vendedor-row { cursor: {{ $modoJrz ? 'default' : 'pointer' }}; }
.vendedor-row:hover { background: #f8faff !important; }
.nivel-btn {
    display:flex; align-items:center; justify-content:space-between;
    border:1.5px solid #e2e8f0; border-radius:10px; padding:14px 18px;
    cursor:pointer; background:#fff; transition:all .2s; text-align:left; width:100%;
}
.nivel-btn:hover { border-color:#6366f1; background:#f5f3ff; transform:translateX(3px); }
.nivel-btn.activo { border-color:#6366f1; background:#ede9fe; }

@include('partials.catalogo-tabla-estilos')
</style>
@endpush

@push('scripts')
@if($modoJrz)
<script>
(function () {
    const tree = @json($categoriasTree);
    const selectedSub = @json($subcategoria);
    const selCat = document.getElementById('categoria');
    const selSub = document.getElementById('subcategoria');

    function fillSubcategorias(cat, keepSelected) {
        if (!selSub) return;
        const list = cat && tree[cat] ? tree[cat] : [];
        selSub.innerHTML = '<option value="">Todas</option>';
        list.forEach(function (sub) {
            const opt = document.createElement('option');
            opt.value = sub;
            opt.textContent = sub;
            if (keepSelected && sub === selectedSub) {
                opt.selected = true;
            }
            selSub.appendChild(opt);
        });
        selSub.disabled = !cat;
    }

    window.onCategoriaChange = function () {
        fillSubcategorias(selCat.value, false);
        document.getElementById('vendedor-filtros').submit();
    };

    fillSubcategorias(selCat ? selCat.value : '', true);
})();
</script>
@else
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

    const CASHEA_MIN_USD = 10;

    function openCashea(row) {
        const nombre      = row.dataset.producto;
        const codigo      = row.dataset.codigo;
        const precioUnit  = parseFloat(row.dataset.precioUnidad) || 0;
        const precioMayor = parseFloat(row.dataset.precioMayor)  || 0;

        if (precioUnit < CASHEA_MIN_USD) {
            alert('Cashea aplica solo a productos de $10.00 o más.');
            return;
        }

        document.getElementById('cashea-nombre').textContent  = `${nombre} — ${String(codigo || '').split(' / ')[0].trim()}`;
        document.getElementById('cashea-punit').textContent   = precioUnit  > 0 ? fmt(precioUnit)  : '—';
        document.getElementById('cashea-pmayor').textContent  = precioMayor > 0 ? fmt(precioMayor) : '—';

        const netoEspecial = precioUnit * {{ $netoFactor }};
        document.getElementById('cashea-descuento').textContent = precioUnit > 0 ? fmt(netoEspecial) : '—';

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
        document.querySelectorAll('.nivel-btn').forEach(b => b.classList.remove('activo'));
        const active = document.getElementById(`nivel-btn-${nivel.id}`);
        if (active) active.classList.add('activo');

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

    document.querySelectorAll('.vendedor-row').forEach(row => {
        row.addEventListener('dblclick', () => openCashea(row));
    });

    document.getElementById('cashea-overlay').addEventListener('click', function (e) {
        if (e.target === this) closeCashea();
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCashea(); });
})();
</script>
@endif
@endpush
