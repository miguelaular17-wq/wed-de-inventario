@extends('layouts.app')

@section('title', 'Catálogo al mayor')

@push('head')
@include('partials.ventas-mayor-responsive')
@endpush

@section('content')
@php
    $presNombre = fn ($p) => $p->presentacion === 'caja' ? 'caja' : ($p->presentacion === 'paquete' ? 'paquete' : 'unidad');
    $disponibleTexto = fn ($p) => \App\Models\Mayor\MayorCatalogo::cantidadTexto($p->disponible_presentaciones, $p->presentacion);
    $descPct = (float) ($descuentoDivisaPct ?? 0);
    $descFactor = (float) ($descuentoFactor ?? max(0, 1 - ($descPct / 100)));
    $descEtiqueta = $descuentoEtiqueta ?? (rtrim(rtrim(number_format($descPct, 2, '.', ''), '0'), '.').'%');
    $filtros = $filtros ?? [];
    $q = (string) ($filtros['q'] ?? '');
    $categoria = (string) ($filtros['categoria'] ?? '');
    $sede = (string) ($filtros['sede'] ?? 'todas');
    $soloExistencia = (bool) ($filtros['solo_existencia'] ?? false);
    $perPage = (int) ($filtros['limite'] ?? 24);
    $subcategoriasElegidas = $subcategoriasElegidas ?? [];
    $sedesExcluidas = $sedesExcluidas ?? [];
    $pdfQuery = request()->query();
    unset($pdfQuery['page']);
@endphp
<style>
    main:has(.cm-page) { max-width: none; width: 100%; padding: 20px 28px 40px; }
    .cm-cab { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .cm-filtros {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 14px; margin-bottom: 16px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }
    .cm-filters-grid {
        display: grid;
        grid-template-columns: minmax(160px, 1.5fr) repeat(4, minmax(120px, 1fr)) 88px auto;
        gap: 10px; align-items: end;
    }
    .cm-filter-item { display: flex; flex-direction: column; min-width: 0; }
    .cm-filter-item label { font-size: .72rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
    .cm-filter-input {
        width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #cbd5e1;
        font-size: .9rem; background: #fff; color: #0f172a; box-sizing: border-box;
    }
    .cm-filter-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
    .cm-check {
        display: flex; align-items: center; gap: 6px; font-size: .82rem; color: #334155; white-space: nowrap;
        padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;
    }
    .cm-subcat { position: relative; }
    .cm-subcat-toggle {
        text-align: left; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        padding-right: 28px;
        background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='3'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;
    }
    .cm-subcat-panel {
        position: absolute; top: 100%; left: 0; z-index: 35; margin-top: 4px;
        width: max(100%, 280px); background: #fff; border: 1px solid #cbd5e1; border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, .18); padding: 8px;
    }
    .cm-subcat-buscar { width: 100%; padding: 7px 9px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: .85rem; box-sizing: border-box; }
    .cm-subcat-acciones { display: flex; justify-content: space-between; gap: 6px; margin: 6px 0; }
    .cm-subcat-acciones button { background: none; border: none; color: #1d4ed8; font-size: .78rem; font-weight: 600; cursor: pointer; padding: 2px 0; }
    .cm-subcat-lista { max-height: 260px; overflow-y: auto; }
    .cm-subcat-opcion { display: flex; align-items: center; gap: 6px; padding: 5px 4px; font-size: .85rem; cursor: pointer; border-radius: 6px; }
    .cm-subcat-opcion:hover { background: #f1f5f9; }
    .cm-filters-rangos { display: grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap: 10px 24px; margin-top: 12px; }
    .cm-rango-top { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 4px; }
    .cm-rango-top label { font-size: .72rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: #64748b; }
    .cm-rango-valor { font-size: .82rem; font-weight: 700; color: #1e3a8a; white-space: nowrap; }
    .cm-rango-inputs { display: flex; align-items: center; gap: 6px; margin: 0 0 6px; }
    .cm-rango-inputs span { font-size: .72rem; color: #64748b; font-weight: 600; }
    .cm-rango-inputs input[type=number] {
        width: 88px; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 7px;
        font-size: .85rem; font-family: inherit; color: #0f172a;
    }
    .cm-rango-barra { position: relative; height: 26px; }
    .cm-rango-barra::before { content: ''; position: absolute; left: 0; right: 0; top: 11px; height: 4px; border-radius: 999px; background: #e2e8f0; }
    .cm-rango-relleno { position: absolute; top: 11px; height: 4px; border-radius: 999px; background: #2563eb; }
    .cm-rango-barra input[type=range] { position: absolute; inset: 0; width: 100%; margin: 0; background: none; pointer-events: none; -webkit-appearance: none; appearance: none; height: 26px; }
    .cm-rango-barra input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; pointer-events: auto; width: 18px; height: 18px; border-radius: 50%; background: #fff; border: 3px solid #2563eb; cursor: pointer; box-shadow: 0 1px 3px rgba(15,23,42,.25); }
    .cm-rango-barra input[type=range]::-moz-range-thumb { pointer-events: auto; width: 14px; height: 14px; border-radius: 50%; background: #fff; border: 3px solid #2563eb; cursor: pointer; }
    .cm-rango-barra input[type=range]::-webkit-slider-runnable-track { background: none; }
    .cm-rango-barra input[type=range]::-moz-range-track { background: none; }
    @media (max-width: 1100px) {
        .cm-filters-grid { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
        .cm-filter-actions { grid-column: 1 / -1; justify-content: flex-start; }
    }
    @media (max-width: 700px) { .cm-filters-rangos { grid-template-columns: 1fr; } }
    .cm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; padding-bottom: 96px; }
    .cm-card { background: #fff; border: 1px solid #0f172a; border-radius: 14px; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
    .cm-card.is-en-pedido { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, .18); }
    .cm-foto { background: #f8fafc; border-radius: 10px; height: 180px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
    .cm-foto .cat-foto-link { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
    .cm-foto img { max-width: 100%; max-height: 180px; object-fit: contain; }
    .cm-badge { position: absolute; left: 8px; bottom: 8px; background: #dcfce7; color: #166534; font-weight: 800; font-size: .74rem; padding: 3px 9px; border-radius: 999px; }
    .cm-pres { position: absolute; right: 8px; top: 8px; background: #6d28d9; color: #fff; font-weight: 800; font-size: .74rem; padding: 3px 9px; border-radius: 999px; }
    .cm-en-lista { position: absolute; right: 8px; bottom: 8px; background: #2563eb; color: #fff; font-weight: 800; font-size: .72rem; padding: 3px 8px; border-radius: 999px; display: none; }
    .cm-card.is-en-pedido .cm-en-lista { display: inline-block; }
    .cm-meta { display: flex; justify-content: space-between; gap: 6px; font-size: .7rem; color: #64748b; font-weight: 700; text-transform: uppercase; }
    .cm-meta span:last-child { font-family: ui-monospace, monospace; text-transform: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 50%; }
    .cm-nombre { font-weight: 800; color: #0f172a; font-size: .9rem; line-height: 1.3; min-height: 2.6em; }
    .cm-price-box { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 10px; padding: 6px; }
    .cm-price-box > div { text-align: center; padding: 4px 2px; min-width: 0; }
    .cm-price-label { display: block; font-size: .58rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; color: #64748b; margin-bottom: 2px; }
    .cm-price-value { display: block; font-size: .95rem; font-weight: 800; color: #6d28d9; line-height: 1.15; }
    .cm-price-value.is-deal { color: #16a34a; }
    .cm-price-value.is-lote { color: #0f172a; font-size: 1rem; }
    .cm-price-hint { display: block; font-size: .62rem; color: #64748b; margin-top: 1px; }
    .cm-facturar { display: block; width: 100%; text-align: center; padding: 8px; border-radius: 8px; background: #2563eb; color: #fff; font-weight: 700; font-size: .82rem; border: 0; cursor: pointer; }
    .cm-facturar:hover { background: #1d4ed8; }
    .cm-facturar.is-added { background: #0f766e; }
    .cm-pedido-bar {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 40;
        background: #0f172a; color: #fff; padding: 12px 18px;
        display: none; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: nowrap;
        box-shadow: 0 -8px 24px rgba(15, 23, 42, .28);
        box-sizing: border-box;
    }
    .cm-pedido-bar.is-on { display: flex; }
    .cm-pedido-info { flex: 1 1 auto; min-width: 0; }
    .cm-pedido-bar strong { display: block; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cm-pedido-bar .muted { color: #94a3b8; font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cm-pedido-acciones { display: flex; gap: 8px; flex-wrap: nowrap; flex: 0 0 auto; }
    .cm-pedido-acciones .btn { border: 0; cursor: pointer; white-space: nowrap; }
    .cm-pedido-acciones .btn.ghost { background: #334155; color: #fff; }
    .cm-pedido-acciones .btn.go { background: #2563eb; color: #fff; font-weight: 700; }
    /* No meterse detrás del menú lateral */
    @media (min-width: 981px) {
        body.has-sidebar .cm-pedido-bar { left: 248px; }
        body.has-sidebar.sidebar-collapsed .cm-pedido-bar { left: 72px; }
        body.has-sidebar .cm-pedido-panel { right: 16px; left: auto; max-width: calc(100vw - 248px - 32px); }
        body.has-sidebar.sidebar-collapsed .cm-pedido-panel { max-width: calc(100vw - 72px - 32px); }
        body.has-sidebar .cm-toast { left: calc(248px + (100vw - 248px) / 2); }
        body.has-sidebar.sidebar-collapsed .cm-toast { left: calc(72px + (100vw - 72px) / 2); }
    }
    .cm-pedido-panel {
        position: fixed; right: 16px; bottom: 78px; z-index: 41; width: min(420px, calc(100vw - 24px));
        background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .22); display: none; overflow: hidden;
    }
    .cm-pedido-panel.is-on { display: block; }
    .cm-pedido-panel header { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .cm-pedido-panel header h3 { margin: 0; font-size: .95rem; }
    .cm-pedido-lista { max-height: min(48vh, 360px); overflow: auto; }
    .cm-pedido-item { display: grid; grid-template-columns: 1fr auto; gap: 8px; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; align-items: center; }
    .cm-pedido-item .nombre { font-weight: 700; font-size: .84rem; color: #0f172a; }
    .cm-pedido-item .meta { font-size: .72rem; color: #64748b; margin-top: 2px; }
    .cm-pedido-item .controles { display: flex; align-items: center; gap: 6px; }
    .cm-pedido-item input { width: 64px; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: right; }
    .cm-pedido-item .quitar { background: #fee2e2; color: #b91c1c; border: 0; border-radius: 6px; padding: 6px 8px; cursor: pointer; font-weight: 700; font-size: .72rem; }
    .cm-pedido-vacio { padding: 24px 14px; text-align: center; color: #94a3b8; font-size: .88rem; }
    .cm-toast {
        position: fixed; left: 50%; bottom: 88px; transform: translateX(-50%) translateY(12px);
        background: #0f766e; color: #fff; padding: 8px 14px; border-radius: 999px; font-size: .82rem; font-weight: 700;
        opacity: 0; pointer-events: none; transition: .2s ease; z-index: 50; white-space: nowrap;
    }
    .cm-toast.is-on { opacity: 1; transform: translateX(-50%) translateY(0); }
    @media (max-width: 640px) {
        .cm-pedido-panel { left: 12px; right: 12px; width: auto; bottom: 86px; }
        .cm-pedido-bar { padding: 10px 12px; flex-wrap: wrap; }
        .cm-pedido-acciones { width: 100%; justify-content: stretch; }
        .cm-pedido-acciones .btn { flex: 1; }
    }
</style>
<div class="cm-page vm-mobile-root">
    <div class="cm-cab vm-page-head">
        <div>
            <h1 style="margin:0;">Catálogo al mayor</h1>
            <p class="muted" style="margin:4px 0 0;">{{ $productos->count() }} productos · toca <strong>+ Facturar</strong> para ir sumando al pedido (sale de JRZ)</p>
        </div>
        <div class="vm-page-actions">
            <a href="{{ route('ventas_mayor.stock') }}" class="btn secondary">← Stock</a>
            <a href="{{ route('ventas_mayor.catalogo.pdf', $pdfQuery) }}" target="_blank" rel="noopener" class="btn primary" id="cm-pdf-link">PDF</a>
        </div>
    </div>

    <form method="GET" class="cm-filtros" id="form-filtros-mayor" action="{{ route('ventas_mayor.catalogo') }}">
        <div class="cm-filters-grid">
            <div class="cm-filter-item">
                <label>Buscar</label>
                <input type="search" name="q" class="cm-filter-input" placeholder="Nombre o código…" value="{{ $q }}">
            </div>
            <div class="cm-filter-item">
                <label>Categoría</label>
                <select name="categoria" id="categoria" class="cm-filter-input">
                    <option value="todas">Todas</option>
                    @foreach($categorias as $c)
                        <option value="{{ $c }}" @selected($categoria === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cm-filter-item cm-subcat">
                <label>Subcategoría</label>
                <button type="button" class="cm-filter-input cm-subcat-toggle" id="subcatToggle" aria-haspopup="true" aria-expanded="false">Todas</button>
                <div class="cm-subcat-panel" id="subcatPanel" hidden>
                    <input type="search" id="subcatBuscar" class="cm-subcat-buscar" placeholder="Buscar subcategoría…" autocomplete="off">
                    <div class="cm-subcat-acciones">
                        <button type="button" id="subcatMarcarVisibles">Marcar visibles</button>
                        <button type="button" id="subcatLimpiar">Todas (quitar filtro)</button>
                    </div>
                    <div class="cm-subcat-lista" id="subcatLista"></div>
                </div>
            </div>
            <div class="cm-filter-item">
                <label>Sede</label>
                <select name="sede" id="sedeSelect" class="cm-filter-input">
                    <option value="todas" @selected($sede === 'todas' || $sede === '')>Global (Todas)</option>
                    @foreach($sedes as $s)
                        <option value="{{ $s }}" @selected(strtoupper($sede) === strtoupper($s))>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cm-filter-item cm-subcat" id="excluirSedeWrap">
                <label>Excluir sedes</label>
                <button type="button" class="cm-filter-input cm-subcat-toggle" id="excluirSedeToggle" aria-haspopup="true" aria-expanded="false">Ninguna</button>
                <div class="cm-subcat-panel" id="excluirSedePanel" hidden>
                    <div class="cm-subcat-acciones">
                        <span style="font-size:.75rem; color:#64748b;">Solo aplica con sede Global</span>
                        <button type="button" id="excluirSedeLimpiar">Ninguna</button>
                    </div>
                    <div class="cm-subcat-lista">
                        @foreach($sedes as $s)
                            <label class="cm-subcat-opcion">
                                <input type="checkbox" name="excluir_sede[]" value="{{ $s }}" @checked(in_array(strtoupper($s), $sedesExcluidas, true))>
                                {{ $s }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="cm-filter-item">
                <label>Mostrar</label>
                <select name="per_page" class="cm-filter-input">
                    @foreach([24, 48, 72, 96] as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cm-filter-actions">
                <label class="cm-check">
                    <input type="checkbox" name="solo_existencia" value="1" @checked($soloExistencia)>
                    Con stock
                </label>
                <button type="submit" class="btn primary" id="btn-buscar-mayor">Buscar</button>
                <a href="{{ route('ventas_mayor.catalogo') }}" class="btn secondary">Limpiar</a>
                <a href="{{ route('ventas_mayor.catalogo.pdf', $pdfQuery) }}" target="_blank" rel="noopener" class="btn secondary">PDF</a>
            </div>
        </div>

        <div class="cm-filters-rangos">
            @foreach($rangos ?? [] as $clave => $r)
                <div class="cm-rango" data-pasos='@json($r['pasos'])' data-prefijo="{{ $r['prefijo'] }}">
                    <div class="cm-rango-top">
                        <label>{{ $r['titulo'] }}</label>
                        <span class="cm-rango-valor"></span>
                    </div>
                    <div class="cm-rango-inputs">
                        <span>{{ $r['prefijo'] ?: '' }}</span>
                        <input type="number" class="cm-rango-num-min" min="0" step="{{ $clave === 'precio' ? '0.01' : '1' }}" inputmode="decimal" placeholder="Desde" value="{{ request($clave.'_min') }}">
                        <span>hasta</span>
                        <span>{{ $r['prefijo'] ?: '' }}</span>
                        <input type="number" class="cm-rango-num-max" min="0" step="{{ $clave === 'precio' ? '0.01' : '1' }}" inputmode="decimal" placeholder="Hasta" value="{{ request($clave.'_max') }}">
                    </div>
                    <div class="cm-rango-barra">
                        <div class="cm-rango-relleno"></div>
                        <input type="range" class="cm-rango-min" min="0" max="{{ count($r['pasos']) }}" step="1">
                        <input type="range" class="cm-rango-max" min="0" max="{{ count($r['pasos']) }}" step="1">
                    </div>
                    <input type="hidden" class="cm-rango-input-min" name="{{ $clave }}_min" value="{{ request($clave.'_min') }}">
                    <input type="hidden" class="cm-rango-input-max" name="{{ $clave }}_max" value="{{ request($clave.'_max') }}">
                </div>
            @endforeach
        </div>
    </form>

    @if($productos->isEmpty())
        <p class="muted" style="text-align:center; padding:40px;">No hay productos con precio al mayor con ese criterio. Prueba cambiar los filtros.</p>
    @else
        <div class="cm-grid">
            @foreach($productos as $p)
                @php
                    $precioUnidad = (float) $p->precio_presentacion;
                    $precioDivisa = round($precioUnidad * $descFactor, 2);
                    $lote = (int) ($p->lote_inicial ?? 0);
                    $cantDefault = $lote > 0 ? $lote : 1;
                @endphp
                <div class="cm-card" data-producto-id="{{ $p->id }}">
                    <div class="cm-foto">
                        @include('partials.foto-producto', ['codigo' => $p->codigo, 'alt' => $p->nombre, 'clase' => ''])
                        <span class="cm-badge">{{ $disponibleTexto($p) }}</span>
                        @if($p->presentacion !== 'unidad')<span class="cm-pres">{{ $p->presentacion_texto }}</span>@endif
                        <span class="cm-en-lista" data-badge-cant></span>
                    </div>
                    <div class="cm-meta"><span>{{ $p->categoria }}</span><span>{{ \App\Support\Sku::principal($p->codigo) }}</span></div>
                    <div class="cm-nombre">{{ $p->nombre }}</div>
                    <div class="cm-price-box">
                        <div>
                            <span class="cm-price-label">Unidad</span>
                            <span class="cm-price-value">${{ number_format($precioUnidad, 2) }}</span>
                            <span class="cm-price-hint">por {{ $presNombre($p) }}</span>
                        </div>
                        <div>
                            <span class="cm-price-label">−{{ $descEtiqueta }}</span>
                            <span class="cm-price-value is-deal">${{ number_format($precioDivisa, 2) }}</span>
                            <span class="cm-price-hint">desc. divisas</span>
                        </div>
                        <div>
                            <span class="cm-price-label">Lote inicial</span>
                            <span class="cm-price-value is-lote">{{ $lote > 0 ? $lote : '—' }}</span>
                            <span class="cm-price-hint">{{ $lote > 0 ? 'mín. compra' : 'sin mínimo' }}</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="cm-facturar"
                        data-add-pedido
                        data-id="{{ $p->id }}"
                        data-nombre="{{ $p->nombre }}"
                        data-codigo="{{ \App\Support\Sku::principal($p->codigo) }}"
                        data-precio="{{ number_format($precioUnidad, 2, '.', '') }}"
                        data-lote="{{ $cantDefault }}"
                        data-disp="{{ (int) $p->disponible_presentaciones }}"
                        data-pres="{{ $presNombre($p) }}"
                    >+ Facturar</button>
                </div>
            @endforeach
        </div>
        @if($productos->count() >= $limite)
            <p class="muted" style="font-size:.8rem; margin-top:10px;">Se muestran {{ $limite }} productos (los de más stock en JRZ). Aumenta «Mostrar» o afina los filtros.</p>
        @endif
    @endif
</div>

<script>
(function () {
    const catSubcatMap = @json($catSubcatMap ?? []);
    const allSubcategorias = @json(collect($subcategorias ?? [])->values());
    let selectedSubcats = new Set(@json($subcategoriasElegidas ?? []));
    const form = document.getElementById('form-filtros-mayor');
    const categoriaSelect = document.getElementById('categoria');
    const subcatToggle = document.getElementById('subcatToggle');
    const subcatPanel = document.getElementById('subcatPanel');
    const subcatLista = document.getElementById('subcatLista');
    const subcatBuscar = document.getElementById('subcatBuscar');

    function textoSubcats() {
        const n = selectedSubcats.size;
        subcatToggle.textContent = n === 0 ? 'Todas' : (n === 1 ? [...selectedSubcats][0] : n + ' subcategorías');
        subcatToggle.title = n ? [...selectedSubcats].join(', ') : '';
    }
    function updateSubcategorias() {
        const cat = categoriaSelect.value;
        const options = (!cat || cat === 'todas') ? allSubcategorias : (catSubcatMap[cat] || []);
        selectedSubcats = new Set([...selectedSubcats].filter(s => options.includes(s)));
        subcatLista.innerHTML = '';
        options.forEach(sub => {
            const label = document.createElement('label');
            label.className = 'cm-subcat-opcion';
            label.dataset.nombre = String(sub).toLowerCase();
            const chk = document.createElement('input');
            chk.type = 'checkbox';
            chk.name = 'subcategoria[]';
            chk.value = sub;
            chk.checked = selectedSubcats.has(sub);
            chk.addEventListener('change', () => {
                chk.checked ? selectedSubcats.add(sub) : selectedSubcats.delete(sub);
                textoSubcats();
            });
            label.append(chk, document.createTextNode(' ' + sub));
            subcatLista.appendChild(label);
        });
        if (!options.length) subcatLista.innerHTML = '<div style="color:#64748b;font-size:.82rem;padding:6px 4px;">Esta categoría no tiene subcategorías.</div>';
        subcatBuscar.value = '';
        textoSubcats();
    }
    subcatToggle.addEventListener('click', () => {
        const abrir = subcatPanel.hidden;
        subcatPanel.hidden = !abrir;
        subcatToggle.setAttribute('aria-expanded', String(abrir));
        if (abrir) subcatBuscar.focus();
    });
    document.addEventListener('click', (e) => {
        if (!subcatPanel.hidden && e.target.closest('.cm-subcat') !== subcatPanel.parentElement) {
            subcatPanel.hidden = true;
            subcatToggle.setAttribute('aria-expanded', 'false');
        }
    });
    subcatBuscar.addEventListener('input', () => {
        const q = subcatBuscar.value.trim().toLowerCase();
        subcatLista.querySelectorAll('.cm-subcat-opcion').forEach(el => el.style.display = el.dataset.nombre.includes(q) ? '' : 'none');
    });
    subcatBuscar.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
    document.getElementById('subcatMarcarVisibles').addEventListener('click', () => {
        subcatLista.querySelectorAll('.cm-subcat-opcion').forEach(el => {
            if (el.style.display === 'none') return;
            const chk = el.querySelector('input');
            chk.checked = true;
            selectedSubcats.add(chk.value);
        });
        textoSubcats();
    });
    document.getElementById('subcatLimpiar').addEventListener('click', () => {
        selectedSubcats.clear();
        subcatLista.querySelectorAll('input[type=checkbox]').forEach(chk => chk.checked = false);
        textoSubcats();
    });
    categoriaSelect.addEventListener('change', () => { selectedSubcats.clear(); updateSubcategorias(); });
    updateSubcategorias();

    (function () {
        const sedeSelect = document.getElementById('sedeSelect');
        const toggle = document.getElementById('excluirSedeToggle');
        const panel = document.getElementById('excluirSedePanel');
        const checks = [...panel.querySelectorAll('input[type=checkbox]')];
        function refrescar() {
            const esGlobal = sedeSelect.value === 'todas';
            const marcadas = checks.filter(c => c.checked).map(c => c.value);
            checks.forEach(c => c.disabled = !esGlobal);
            toggle.disabled = !esGlobal;
            toggle.style.opacity = esGlobal ? '' : '.55';
            toggle.textContent = !esGlobal || marcadas.length === 0
                ? 'Ninguna'
                : (marcadas.length <= 2 ? 'Sin ' + marcadas.join(', ') : marcadas.length + ' sedes excluidas');
            toggle.title = marcadas.join(', ');
            if (!esGlobal) panel.hidden = true;
        }
        toggle.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            toggle.setAttribute('aria-expanded', String(!panel.hidden));
        });
        document.addEventListener('click', (e) => {
            if (!panel.hidden && !e.target.closest('#excluirSedeWrap')) {
                panel.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
        document.getElementById('excluirSedeLimpiar').addEventListener('click', () => {
            checks.forEach(c => c.checked = false);
            refrescar();
        });
        checks.forEach(c => c.addEventListener('change', refrescar));
        sedeSelect.addEventListener('change', refrescar);
        refrescar();
    })();

    document.querySelectorAll('.cm-rango').forEach(function (rango) {
        const pasos = JSON.parse(rango.dataset.pasos);
        const prefijo = rango.dataset.prefijo || '';
        const tope = pasos.length;
        const deslizMin = rango.querySelector('.cm-rango-min');
        const deslizMax = rango.querySelector('.cm-rango-max');
        const campoMin = rango.querySelector('.cm-rango-input-min');
        const campoMax = rango.querySelector('.cm-rango-input-max');
        const numMin = rango.querySelector('.cm-rango-num-min');
        const numMax = rango.querySelector('.cm-rango-num-max');
        const relleno = rango.querySelector('.cm-rango-relleno');
        const etiqueta = rango.querySelector('.cm-rango-valor');
        let origen = 'slider';
        const fmt = v => prefijo + Number(v).toLocaleString('es-VE');
        const leerNum = el => {
            const v = String(el.value ?? '').trim().replace(',', '.');
            if (v === '') return null;
            const n = parseFloat(v);
            return Number.isFinite(n) && n >= 0 ? n : null;
        };
        const indice = valor => {
            if (valor === '' || valor === null) return null;
            const n = parseFloat(valor);
            let i = pasos.findIndex(p => p >= n);
            return i === -1 ? tope - 1 : i;
        };
        // Si ya hay un valor escrito (p. ej. 6), no lo redondear a los escalones del slider (5→10).
        if (campoMin.value !== '') numMin.value = campoMin.value;
        if (campoMax.value !== '') numMax.value = campoMax.value;
        if (campoMin.value !== '' || campoMax.value !== '') {
            origen = 'numero';
        }
        deslizMin.value = indice(campoMin.value) ?? 0;
        deslizMax.value = indice(campoMax.value) ?? tope;
        function pintar() {
            let desde = null, hasta = null;
            if (origen === 'numero') {
                desde = leerNum(numMin);
                hasta = leerNum(numMax);
                if (desde !== null && hasta !== null && desde > hasta) [desde, hasta] = [hasta, desde];
                // Solo mueve la barra como aproximación visual; el número exacto se conserva.
                deslizMin.value = desde === null ? 0 : (indice(desde) ?? 0);
                deslizMax.value = hasta === null ? tope : (indice(hasta) ?? tope);
            } else {
                let a = parseInt(deslizMin.value, 10), b = parseInt(deslizMax.value, 10);
                if (a > b) [a, b] = [b, a];
                desde = a === 0 ? null : pasos[a];
                hasta = b >= tope ? null : pasos[b];
                numMin.value = desde === null ? '' : desde;
                numMax.value = hasta === null ? '' : hasta;
            }
            let a = parseInt(deslizMin.value, 10), b = parseInt(deslizMax.value, 10);
            if (a > b) [a, b] = [b, a];
            relleno.style.left = (a / tope * 100) + '%';
            relleno.style.width = ((b - a) / tope * 100) + '%';
            etiqueta.textContent = desde === null && hasta === null ? 'Todos'
                : desde === null ? 'Hasta ' + fmt(hasta)
                : hasta === null ? 'Desde ' + fmt(desde)
                : fmt(desde) + ' – ' + fmt(hasta);
            campoMin.value = desde === null ? '' : desde;
            campoMax.value = hasta === null ? '' : hasta;
            campoMin.disabled = campoMin.value === '';
            campoMax.disabled = campoMax.value === '';
        }
        function aplicarYBuscar() { pintar(); form.requestSubmit(); }
        [deslizMin, deslizMax].forEach(d => {
            d.addEventListener('input', () => { origen = 'slider'; pintar(); });
            d.addEventListener('change', () => { origen = 'slider'; aplicarYBuscar(); });
        });
        [numMin, numMax].forEach(inp => {
            inp.addEventListener('input', () => { origen = 'numero'; pintar(); });
            inp.addEventListener('change', () => { origen = 'numero'; aplicarYBuscar(); });
            inp.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    origen = 'numero';
                    aplicarYBuscar();
                }
            });
        });
        pintar();
    });

    form.addEventListener('submit', function () {
        const btn = document.getElementById('btn-buscar-mayor');
        if (btn) { btn.textContent = 'Buscando…'; btn.disabled = true; }
    });
})();
</script>

<div class="cm-pedido-panel" id="cm-pedido-panel" aria-hidden="true">
    <header>
        <h3>Pedido al mayor</h3>
        <button type="button" class="btn secondary" style="padding:4px 10px; font-size:.78rem;" id="cm-cerrar-panel">Cerrar</button>
    </header>
    <div class="cm-pedido-lista" id="cm-pedido-lista"></div>
</div>

<div class="cm-pedido-bar" id="cm-pedido-bar" aria-hidden="true">
    <div class="cm-pedido-info">
        <strong id="cm-pedido-resumen">0 productos</strong>
        <div class="muted" id="cm-pedido-subtotal">Sigue en el catálogo y ve sumando</div>
    </div>
    <div class="cm-pedido-acciones">
        <button type="button" class="btn ghost" id="cm-ver-pedido">Ver lista</button>
        <button type="button" class="btn ghost" id="cm-vaciar-pedido">Vaciar</button>
        <button type="button" class="btn go" id="cm-ir-factura">Terminar → Factura</button>
    </div>
</div>
<div class="cm-toast" id="cm-toast" role="status"></div>

<script>
(function () {
    const KEY = 'ventas_mayor_pedido_catalogo';
    const createUrl = @json(route('ventas_mayor.create'));
    const bar = document.getElementById('cm-pedido-bar');
    const panel = document.getElementById('cm-pedido-panel');
    const lista = document.getElementById('cm-pedido-lista');
    const toast = document.getElementById('cm-toast');
    let pedido = [];
    let toastTimer = null;

    function leer() {
        try {
            const raw = sessionStorage.getItem(KEY);
            const data = raw ? JSON.parse(raw) : [];
            pedido = Array.isArray(data) ? data.filter(x => x && x.id) : [];
        } catch (e) {
            pedido = [];
        }
    }
    function guardar() {
        sessionStorage.setItem(KEY, JSON.stringify(pedido));
        pintar();
    }
    function usd(n) {
        return '$' + (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
    }
    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function mostrarToast(msg) {
        toast.textContent = msg;
        toast.classList.add('is-on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-on'), 1400);
    }
    function cantEnPedido(id) {
        const it = pedido.find(x => Number(x.id) === Number(id));
        return it ? Number(it.cant) || 0 : 0;
    }
    function pintarTarjetas() {
        document.querySelectorAll('.cm-card[data-producto-id]').forEach(card => {
            const id = Number(card.dataset.productoId);
            const cant = cantEnPedido(id);
            card.classList.toggle('is-en-pedido', cant > 0);
            const badge = card.querySelector('[data-badge-cant]');
            if (badge) badge.textContent = cant > 0 ? (cant + ' en pedido') : '';
            const btn = card.querySelector('[data-add-pedido]');
            if (btn) {
                btn.classList.toggle('is-added', cant > 0);
                btn.textContent = cant > 0 ? '+ Facturar otra' : '+ Facturar';
            }
        });
    }
    function pintarLista() {
        if (!pedido.length) {
            lista.innerHTML = '<div class="cm-pedido-vacio">Aún no hay productos. Sigue en el catálogo y pulsa + Facturar.</div>';
            return;
        }
        lista.innerHTML = pedido.map((it, i) => `
            <div class="cm-pedido-item" data-i="${i}">
                <div>
                    <div class="nombre">${esc(it.nombre)}</div>
                    <div class="meta">${esc(it.codigo)} · ${usd(it.precio)} / ${esc(it.pres || 'unidad')}${it.disp ? ' · disp. ' + it.disp : ''}</div>
                </div>
                <div class="controles">
                    <input type="number" min="1" max="${Math.max(1, Number(it.disp) || 99999)}" step="1" value="${Number(it.cant) || 1}" data-cant="${i}">
                    <button type="button" class="quitar" data-quitar="${i}">Quitar</button>
                </div>
            </div>
        `).join('');
        lista.querySelectorAll('[data-cant]').forEach(inp => {
            inp.addEventListener('change', () => {
                const i = Number(inp.dataset.cant);
                let v = parseInt(inp.value, 10) || 1;
                const max = Number(pedido[i]?.disp) || 0;
                if (max > 0) v = Math.min(v, max);
                if (v < 1) v = 1;
                pedido[i].cant = v;
                inp.value = v;
                guardar();
            });
        });
        lista.querySelectorAll('[data-quitar]').forEach(btn => {
            btn.addEventListener('click', () => {
                pedido.splice(Number(btn.dataset.quitar), 1);
                guardar();
            });
        });
    }
    function pintar() {
        const n = pedido.length;
        const und = pedido.reduce((s, it) => s + (Number(it.cant) || 0), 0);
        const total = pedido.reduce((s, it) => s + (Number(it.cant) || 0) * (Number(it.precio) || 0), 0);
        bar.classList.toggle('is-on', n > 0);
        bar.setAttribute('aria-hidden', n > 0 ? 'false' : 'true');
        if (n === 0) {
            panel.classList.remove('is-on');
            panel.setAttribute('aria-hidden', 'true');
        }
        document.getElementById('cm-pedido-resumen').textContent = n === 1
            ? '1 producto · ' + und + ' und.'
            : n + ' productos · ' + und + ' und.';
        document.getElementById('cm-pedido-subtotal').textContent = n
            ? 'Subtotal aprox. ' + usd(total)
            : 'Sigue sumando productos';
        pintarTarjetas();
        pintarLista();
    }
    function agregar(p) {
        const id = Number(p.id);
        const lote = Math.max(1, Number(p.lote) || 1);
        const disp = Math.max(0, Number(p.disp) || 0);
        const existente = pedido.find(x => Number(x.id) === id);
        if (existente) {
            let next = (Number(existente.cant) || 0) + lote;
            if (disp > 0) next = Math.min(next, disp);
            existente.cant = next;
            existente.disp = disp || existente.disp;
            existente.precio = Number(p.precio) || existente.precio;
        } else {
            pedido.push({
                id,
                nombre: p.nombre,
                codigo: p.codigo,
                precio: Number(p.precio) || 0,
                cant: disp > 0 ? Math.min(lote, disp) : lote,
                disp,
                pres: p.pres || 'unidad',
            });
        }
        guardar();
        mostrarToast('Sumado al pedido');
    }

    document.querySelectorAll('[data-add-pedido]').forEach(btn => {
        btn.addEventListener('click', () => agregar({
            id: btn.dataset.id,
            nombre: btn.dataset.nombre,
            codigo: btn.dataset.codigo,
            precio: btn.dataset.precio,
            lote: btn.dataset.lote,
            disp: btn.dataset.disp,
            pres: btn.dataset.pres,
        }));
    });

    document.getElementById('cm-ver-pedido').addEventListener('click', () => {
        const on = !panel.classList.contains('is-on');
        panel.classList.toggle('is-on', on);
        panel.setAttribute('aria-hidden', on ? 'false' : 'true');
        if (on) pintarLista();
    });
    document.getElementById('cm-cerrar-panel').addEventListener('click', () => {
        panel.classList.remove('is-on');
        panel.setAttribute('aria-hidden', 'true');
    });
    document.getElementById('cm-vaciar-pedido').addEventListener('click', () => {
        if (!pedido.length) return;
        if (!confirm('¿Vaciar el pedido del catálogo?')) return;
        pedido = [];
        guardar();
    });
    document.getElementById('cm-ir-factura').addEventListener('click', () => {
        if (!pedido.length) return;
        const productos = pedido.map(it => it.id).join(',');
        const cants = pedido.map(it => Math.max(1, Number(it.cant) || 1)).join(',');
        sessionStorage.removeItem(KEY);
        window.location.href = createUrl + '?productos=' + encodeURIComponent(productos) + '&cants=' + encodeURIComponent(cants);
    });

    leer();
    pintar();
})();
</script>
@endsection
