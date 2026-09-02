@extends('layouts.app')

@section('title', !empty($modoCliente) ? 'Catálogo — Palacio de los Detalles' : 'Catálogo de Productos')

@section('content')
@php
    use App\Support\VentaDescuento;
    $descPct = VentaDescuento::porcentaje();
    $descFactor = VentaDescuento::factorDescuento();
    $netoFactor = VentaDescuento::factorNeto();
    $descEtiqueta = VentaDescuento::etiqueta();
@endphp
@php
    $modoCliente = $modoCliente ?? false;
    $casheaLevels = $casheaLevels ?? [1 => 60, 2 => 50, 3 => 40, 4 => 40, 5 => 40, 6 => 40];
    $formAction = $modoCliente
        ? route('catalogo.cliente', $clienteToken)
        : route('catalogo.index');
@endphp
<style>
    main:has(.catalogo-page) {
        max-width: none;
        width: 100%;
        padding: 16px 24px 40px;
    }
    .catalogo-page { width: 100%; }

    .catalogo-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .catalogo-top h1 {
        margin: 0;
        font-size: 1.45rem;
        font-weight: 750;
        color: #0f172a;
    }
    .catalogo-top-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .catalogo-back {
        color: #475569;
        text-decoration: none;
        font-size: 0.88rem;
        font-weight: 600;
        background: #fff;
        border: 1px solid #e2e8f0;
        padding: 6px 12px;
        border-radius: 8px;
    }
    .catalogo-back:hover { background: #f8fafc; }
    .catalogo-badge {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .filters-bar {
        position: sticky;
        top: 64px;
        z-index: 40;
        background: #fff;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        margin-bottom: 18px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }
    .filters-grid {
        display: grid;
        grid-template-columns: minmax(180px, 1.6fr) repeat(3, minmax(130px, 1fr)) 88px auto;
        gap: 10px;
        align-items: end;
    }
    .filter-item {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .filter-item label {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 4px;
    }
    .filter-input {
        width: 100%;
        padding: 9px 11px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-size: 0.9rem;
        background: #fff;
        color: var(--text, #333);
        box-sizing: border-box;
    }
    .filter-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .btn-custom {
        padding: 9px 14px;
        border-radius: 8px;
        font-weight: 650;
        cursor: pointer;
        border: none;
        font-size: 0.86rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        font-family: inherit;
    }
    .btn-primary-custom { background: var(--blue, #1e3a8a); color: #fff; }
    .btn-danger-custom { background: #fff; color: #be123c; border: 1px solid #fecdd3; }
    .btn-danger-custom:hover { background: #fff1f2; }
    .btn-light-custom { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        color: #334155;
        white-space: nowrap;
        padding: 8px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
    }

    .filters-grid.is-cliente {
        grid-template-columns: repeat(3, minmax(130px, 1fr)) 88px auto;
    }
    .product-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: transform 0.15s, box-shadow 0.15s;
        cursor: pointer;
    }
    .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }
    .product-img-wrapper {
        width: 100%;
        padding-top: 78%;
        position: relative;
        background: #f8fafc;
    }
    .product-img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 8px;
        box-sizing: border-box;
    }
    .product-info {
        padding: 10px 12px 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        flex: 1;
    }
    .product-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }
    .product-category {
        font-size: 0.68rem;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.03em;
    }
    .product-code {
        font-size: 0.72rem;
        color: #64748b;
        font-family: ui-monospace, Menlo, Consolas, monospace;
    }
    .product-title {
        font-size: 0.86rem;
        font-weight: 700;
        color: #0f172a;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 2.4em;
        line-height: 1.25;
        margin: 0;
    }
    .price-box {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 6px;
        margin-top: auto;
    }
    .price-box > div {
        background: #f8fafc;
        border: 1px solid #eef2f7;
        border-radius: 8px;
        padding: 6px 4px;
        text-align: center;
    }
    .price-label {
        font-size: 0.62rem;
        color: #94a3b8;
        font-weight: 700;
        text-transform: uppercase;
        display: block;
        margin-bottom: 2px;
    }
    .price-value {
        font-weight: 800;
        font-size: 0.82rem;
        color: #0f172a;
    }
    .price-value.is-deal { color: #16a34a; }
    .stock-badge {
        position: absolute;
        left: 8px;
        bottom: 8px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
    }
    .stock-green { background: #dcfce7; color: #166534; }
    .stock-red { background: #fee2e2; color: #991b1b; }

    .catalogo-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(2, 1fr);
    }
    @media (min-width: 768px) {
        .catalogo-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (min-width: 1100px) {
        .catalogo-grid { grid-template-columns: repeat(4, 1fr); }
    }
    @media (min-width: 1400px) {
        .catalogo-grid { grid-template-columns: repeat(5, 1fr); }
    }
    @media (min-width: 1700px) {
        .catalogo-grid { grid-template-columns: repeat(6, 1fr); }
    }

    .empty-state {
        text-align: center;
        padding: 50px 20px;
        background: white;
        border-radius: 12px;
        border: 1px dashed #cbd5e1;
        color: #64748b;
    }
    .pdf-actions {
        background: #fff1f2;
        border: 1px solid #fecdd3;
        border-radius: 10px;
        padding: 12px;
        margin-top: 12px;
        display: none;
    }
    .pagination {
        display: flex;
        padding-left: 0;
        list-style: none;
        gap: 5px;
        margin: 0;
        align-items: center;
    }
    .page-item .page-link {
        padding: 8px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: white;
        color: var(--blue, #1e3a8a);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
    }
    .page-item.active .page-link {
        background: var(--blue, #1e3a8a);
        color: white;
        border-color: var(--blue, #1e3a8a);
    }
    .page-item.disabled .page-link {
        color: #94a3b8;
        pointer-events: none;
        background: #f8fafc;
    }
    .page-link:hover { background: #f1f5f9; }

    .btn-cashea {
        width: calc(100% - 24px);
        margin: 0 12px 12px;
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
    .btn-cashea:hover { background: #ede9fe; }
    #cashea-overlay {
        z-index: 50000 !important;
    }
    .nivel-btn {
        display:flex; align-items:center; justify-content:space-between;
        border:1.5px solid #e2e8f0; border-radius:10px; padding:14px 18px;
        cursor:pointer; background:#fff; transition:all .2s; text-align:left; width:100%;
        font-family: inherit;
    }
    .nivel-btn:hover { border-color:#6366f1; background:#f5f3ff; transform:translateX(3px); }
    .nivel-btn.activo { border-color:#6366f1; background:#ede9fe; }
    @keyframes slideUp {
        from { opacity:0; transform:translateY(24px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .cliente-hint {
        font-size: 0.82rem;
        color: #64748b;
        margin: 0;
    }
    .cliente-link-box {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 10px;
        margin-bottom: 12px;
        font-size: 0.82rem;
    }
    .cliente-link-box input {
        flex: 1;
        min-width: 220px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 7px 10px;
        font-size: 0.8rem;
    }
    @media (max-width: 1100px) {
        .filters-grid,
        .filters-grid.is-cliente {
            grid-template-columns: 1fr 1fr;
        }
        .filter-actions { grid-column: 1 / -1; justify-content: flex-start; }
    }
    .filters-bar-wrap { display: block; }
    .filters-bar-wrap > summary {
        display: none;
        list-style: none;
        cursor: pointer;
        font-weight: 700;
        font-size: 0.92rem;
        padding: 4px 2px 8px;
        color: #0f172a;
    }
    .filters-bar-wrap > summary::-webkit-details-marker { display: none; }
    .product-category, .product-code {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }
    .product-code { max-width: 48%; text-align: right; }

    @media (max-width: 720px) {
        main:has(.catalogo-page) {
            padding: 10px 12px 28px;
        }
        .catalogo-top {
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .catalogo-top h1 {
            font-size: 1.12rem;
            line-height: 1.25;
        }
        .cliente-hint { font-size: 0.78rem; }
        .catalogo-badge {
            font-size: 0.74rem;
            padding: 4px 10px;
        }
        .filters-bar {
            top: 8px;
            padding: 10px 12px;
            margin-bottom: 12px;
            border-radius: 12px;
        }
        .filters-bar-wrap > summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .filters-bar-wrap > summary::after {
            content: 'Mostrar';
            font-size: 0.75rem;
            font-weight: 650;
            color: #1d4ed8;
        }
        .filters-bar-wrap[open] > summary::after { content: 'Ocultar'; }
        .filters-grid,
        .filters-grid.is-cliente {
            grid-template-columns: 1fr;
        }
        .filter-item.is-per-page { display: none; }
        .filter-input {
            font-size: 1rem;
            padding: 11px 12px;
        }
        .filter-actions {
            width: 100%;
        }
        .filter-actions .btn-custom,
        .filter-actions .checkbox-item {
            flex: 1;
            justify-content: center;
        }
        .catalogo-grid { gap: 10px; }
        .product-info { padding: 8px 8px 10px; gap: 5px; }
        .product-title {
            font-size: 0.78rem;
            min-height: 2.3em;
        }
        .product-meta { flex-direction: column; align-items: flex-start; gap: 2px; }
        .product-code { max-width: 100%; text-align: left; font-size: 0.66rem; }
        .price-box { gap: 4px; }
        .price-box > div { padding: 5px 2px; min-width: 0; }
        .price-label { font-size: 0.55rem; }
        .price-value {
            font-size: 0.72rem;
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .btn-cashea {
            width: calc(100% - 16px);
            margin: 0 8px 8px;
            padding: 9px 8px;
            font-size: 0.78rem;
        }
        .stock-badge { font-size: 0.66rem; padding: 2px 7px; }
        .product-card:hover { transform: none; }
    }
    @media (max-width: 380px) {
        .catalogo-grid { grid-template-columns: 1fr; }
        .price-value { font-size: 0.86rem; }
    }
</style>

<div class="catalogo-page">
    <div class="catalogo-top">
        <div class="catalogo-top-left">
            @if(!$modoCliente)
                <a href="{{ route('inventario.index') }}" class="catalogo-back">← Volver</a>
            @else
                <img src="{{ asset('logo.png') }}" alt="Palacio de los Detalles" width="36" height="36" style="border-radius:50%;">
            @endif
            <div>
                <h1>{{ $modoCliente ? 'Catálogo Palacio de los Detalles' : 'Catálogo de Productos' }}</h1>
                @if($modoCliente)
                    <p class="cliente-hint">Toca un producto para ver los niveles de Cashea</p>
                @endif
            </div>
        </div>
        <div class="catalogo-badge">{{ $productos->total() }} productos</div>
    </div>

    @if(!$modoCliente && auth()->user()?->role === 'admin' && !empty($enlaceCliente))
        <div class="cliente-link-box">
            <strong>Enlace para clientes:</strong>
            <input id="enlace-cliente" type="text" readonly value="{{ $enlaceCliente }}">
            <button type="button" class="btn-custom btn-light-custom" onclick="navigator.clipboard.writeText(document.getElementById('enlace-cliente').value); this.textContent='Copiado';">Copiar</button>
            <a href="{{ $enlaceCliente }}" class="btn-custom btn-primary-custom" target="_blank" rel="noopener">Abrir</a>
        </div>
    @endif

    <!-- Filtros -->
    <details class="filters-bar filters-bar-wrap" open>
        <summary>Filtros</summary>
        <form action="{{ $formAction }}" method="GET" id="form-filtros">
            <div class="filters-grid {{ $modoCliente ? 'is-cliente' : '' }}">
                @unless($modoCliente)
                <div class="filter-item">
                    <label>Buscar</label>
                    <input type="text" name="search" class="filter-input" placeholder="Nombre o código..." value="{{ request('search') }}">
                </div>
                @endunless
                <div class="filter-item">
                    <label>Categoría</label>
                    <select name="categoria" id="categoria" class="filter-input">
                        <option value="todas">Todas</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat }}" {{ request('categoria') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item">
                    <label>Subcategoría</label>
                    <select name="subcategoria" id="subcategoria" class="filter-input">
                        <option value="todas">Todas</option>
                        @foreach($subcategorias as $sub)
                            <option value="{{ $sub }}" {{ request('subcategoria') == $sub ? 'selected' : '' }}>{{ $sub }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item">
                    <label>Sede</label>
                    <select name="sede" class="filter-input">
                        <option value="todas">Global (Todas)</option>
                        @foreach($sedes as $s)
                            <option value="{{ $s }}" {{ request('sede') == $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item is-per-page">
                    <label>Mostrar</label>
                    <select name="per_page" class="filter-input">
                        <option value="24" {{ request('per_page') == 24 ? 'selected' : '' }}>24</option>
                        <option value="48" {{ request('per_page') == 48 ? 'selected' : '' }}>48</option>
                        <option value="72" {{ request('per_page') == 72 ? 'selected' : '' }}>72</option>
                        <option value="96" {{ request('per_page') == 96 ? 'selected' : '' }}>96</option>
                    </select>
                </div>
                <div class="filter-actions">
                    @if($modoCliente)
                        <span class="checkbox-item" style="cursor: default;">Solo con stock</span>
                    @else
                        <label class="checkbox-item">
                            <input type="checkbox" id="solo_existencia" name="solo_existencia" value="1" {{ request('solo_existencia') ? 'checked' : '' }}>
                            Con stock
                        </label>
                    @endif
                    <button type="submit" class="btn-custom btn-primary-custom" id="btn-buscar">Buscar</button>
                    <a href="{{ $formAction }}" class="btn-custom btn-light-custom">Limpiar</a>
                    @unless($modoCliente)
                    <button type="button" class="btn-custom btn-danger-custom" onclick="document.getElementById('pdf-panel').style.display = 'block';">PDF</button>
                    @endunless
                </div>
            </div>
            
            @unless($modoCliente)
            <!-- Panel PDF (Oculto por defecto en lugar de modal Bootstrap) -->
            <div class="pdf-actions" id="pdf-panel">
                <h4 style="margin-top: 0; color: #be123c;">Descargar Catálogo (PDF)</h4>
                <p style="font-size: 0.9rem; color: #881337; margin-bottom: 15px;">Selecciona cómo deseas descargar el catálogo (se aplicarán los filtros actuales):</p>
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <button type="button" onclick="descargarPDF('page')" class="btn-custom btn-danger-custom">
                        Descargar Página Actual ({{ count($productos) }} prod.)
                    </button>
                    <button type="button" onclick="descargarPDF('all')" class="btn-custom btn-danger-custom" style="background-color: #991b1b; color: #fff; border: none;">
                        Descargar TODO ({{ $productos->total() }} prod.)
                    </button>
                    <button type="button" class="btn-custom btn-light-custom" onclick="document.getElementById('pdf-panel').style.display = 'none';">
                        Cancelar
                    </button>
                </div>
                <p style="font-size: 0.8rem; color: #be123c; margin-top: 10px; margin-bottom: 0;">* Nota: Descargar TODO puede tardar varios minutos dependiendo de la cantidad de productos.</p>
            </div>
            
            <!-- Panel de Carga -->
            <div class="pdf-actions" id="pdf-loader" style="background-color: #f8fafc; border-color: #cbd5e1; text-align: center;">
                <div style="margin-bottom: 15px;">
                    <svg class="spinner" style="width:40px; height:40px; color:#1e3a8a; animation: spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <style>@keyframes spin { 100% { transform: rotate(360deg); } }</style>
                </div>
                <h4 style="margin-top: 0; color: #1e3a8a;">Generando y Subiendo a Supabase...</h4>
                <p style="font-size: 0.9rem; color: #475569; margin-bottom: 0;">Por favor espera, este proceso puede tomar unos segundos.</p>
            </div>

            <!-- Panel de Éxito -->
            <div class="pdf-actions" id="pdf-success" style="background-color: #dcfce7; border-color: #86efac;">
                <h4 style="margin-top: 0; color: #166534;">✅ Catálogo generado correctamente</h4>
                
                <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; margin-bottom: 15px; font-size: 0.85rem; word-break: break-all;">
                    <a href="#" id="pdf-success-link" target="_blank" style="color: #2563eb; text-decoration: underline;">https://enlace...</a>
                </div>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" id="btn-share-open" class="btn-custom" style="background: #166534; color: white;">
                        Abrir Catálogo
                    </button>
                    <button type="button" id="btn-share-copy" class="btn-custom btn-light-custom">
                        Copiar Enlace
                    </button>
                    <button type="button" id="btn-share-wa" class="btn-custom" style="background: #25D366; color: white;">
                        WhatsApp
                    </button>
                    <button type="button" id="btn-share-mail" class="btn-custom" style="background: #ea4335; color: white;">
                        Correo
                    </button>
                    <button type="button" class="btn-custom btn-light-custom" onclick="document.getElementById('pdf-success').style.display = 'none';">
                        Cerrar
                    </button>
                </div>
            </div>
            @endunless
        </form>
    </details>

    <!-- Catálogo Grid -->
    @if($productos->isEmpty())
        <div class="empty-state">
            <h3 style="margin-bottom: 5px;">No se encontraron productos</h3>
            <p style="margin: 0;">Intenta cambiar los filtros o los términos de búsqueda.</p>
        </div>
    @else
        <div class="catalogo-grid">
            @foreach($productos as $prod)
                <div class="product-card"
                     role="button"
                     tabindex="0"
                     data-producto="{{ e($prod->descripcion) }}"
                     data-codigo="{{ e($prod->codigo) }}"
                     data-precio-unidad="{{ (float) $prod->precio_unidad }}"
                     data-precio-mayor="{{ (float) $prod->precio_mayor }}"
                     onclick="openCasheaFromCard(this)">
                    <div class="product-img-wrapper">
                    @php
                        $codigos = explode('/', $prod->codigo);
                        if(count($codigos) === 1) {
                            $codigos = explode(' ', $prod->codigo);
                        }
                        $primary_code = trim($codigos[0]);
                        
                        $base_url = "https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/object/public/imagenes_producto/imagenes/";
                        $jpg_url = $base_url . rawurlencode($primary_code) . ".jpg";
                        $png_url = $base_url . rawurlencode($primary_code) . ".png";
                        $no_image_url = "https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/object/public/imagenes_producto/imagenes/no-image.jpg";
                    @endphp
                    <img src="{{ $jpg_url }}" 
                         alt="{{ $prod->descripcion }}" 
                         class="product-img"
                         loading="lazy"
                         onerror="if(this.dataset.triedPng !== 'true') { this.dataset.triedPng = 'true'; this.src='{{ $png_url }}'; } else { this.src='{{ $no_image_url }}'; this.onerror=null; }">
                    @if($prod->existencia > 0)
                        <span class="stock-badge stock-green">{{ number_format($prod->existencia, 0) }} unds</span>
                    @else
                        <span class="stock-badge stock-red">Agotado</span>
                    @endif
                    @if(!$modoCliente && auth()->check() && auth()->user()->role === 'vendedor')
                        <button type="button" 
                                onclick="event.stopPropagation(); pedirUrlImagen('{{ $primary_code }}')"
                                style="position: absolute; top: 6px; right: 6px; background: rgba(255,255,255,0.94); border: 1px solid #cbd5e1; border-radius: 6px; padding: 2px 6px; font-size: 0.72rem; cursor: pointer; color: #1e3a8a;">
                            🔗
                        </button>
                    @endif
                </div>
                    <div class="product-info">
                        <div class="product-meta">
                            <div class="product-category">{{ $prod->categoria ?? 'Sin Categoría' }}</div>
                            <div class="product-code">{{ $prod->codigo }}</div>
                        </div>
                        <h3 class="product-title" title="{{ $prod->descripcion }}">{{ ltrim((string) $prod->descripcion, "/ \t") }}</h3>
                        <div class="price-box">
                            <div>
                                <span class="price-label">Unidad</span>
                                <span class="price-value">${{ number_format($prod->precio_unidad, 2) }}</span>
                            </div>
                            <div>
                                <span class="price-label">Mayor</span>
                                <span class="price-value">${{ number_format($prod->precio_mayor, 2) }}</span>
                            </div>
                            <div>
                                <span class="price-label">-{{ $descEtiqueta }}</span>
                                <span class="price-value is-deal">${{ number_format($prod->precio_unidad * $netoFactor, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-cashea" onclick="event.stopPropagation(); openCasheaFromCard(this.closest('.product-card'))">Ver Cashea</button>
                </div>
            @endforeach
        </div>
        
        <div style="margin-top: 30px; display: flex; justify-content: center;">
            {{ $productos->links('pagination::bootstrap-4') }}
        </div>
    @endif
</div>

<div id="cashea-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:50000; align-items:center; justify-content:center;">
    <div id="cashea-modal" style="background:#fff; border-radius:16px; box-shadow:0 25px 60px rgba(0,0,0,.25); width:min(640px, 96vw); max-height:90vh; overflow-y:auto; padding:0; animation: slideUp .25s ease;">
        <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6); border-radius:16px 16px 0 0; padding:24px 28px; color:#fff; position:relative;">
            <button type="button" onclick="closeCashea()" style="position:absolute; top:16px; right:18px; background:rgba(255,255,255,.2); border:none; color:#fff; border-radius:8px; width:32px; height:32px; cursor:pointer; font-size:1.1rem;">✕</button>
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
        <div style="padding:24px 28px;">
            <p style="margin:0 0 16px; color:#64748b; font-size:.9rem;">Selecciona un nivel para calcular el pago inicial y las cuotas restantes.</p>
            <div id="cashea-niveles" style="display:flex; flex-direction:column; gap:10px;"></div>
            <div id="cashea-resultado" style="display:none; margin-top:24px; background:#f8faff; border:1.5px solid #c7d2fe; border-radius:12px; padding:20px;">
                <div style="font-size:.85rem; font-weight:600; color:#6366f1; margin-bottom:14px; text-transform:uppercase; letter-spacing:.05em;">
                    Desglose de pago — <span id="res-nivel-label"></span>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div style="text-align:center; background:#fff; border-radius:10px; padding:14px; box-shadow:0 1px 4px rgba(0,0,0,.07);">
                        <div style="font-size:.75rem; color:#64748b; margin-bottom:4px;">Pago inicial</div>
                        <div id="res-inicial" style="font-size:1.35rem; font-weight:800; color:#16a34a;"></div>
                    </div>
                    <div style="text-align:center; background:#fff; border-radius:10px; padding:14px; box-shadow:0 1px 4px rgba(0,0,0,.07);">
                        <div style="font-size:.75rem; color:#64748b; margin-bottom:4px;">Restante</div>
                        <div id="res-restante" style="font-size:1.35rem; font-weight:800; color:#dc2626;"></div>
                    </div>
                    <div style="text-align:center; background:#fff; border-radius:10px; padding:14px; box-shadow:0 1px 4px rgba(0,0,0,.07);">
                        <div style="font-size:.75rem; color:#64748b; margin-bottom:4px;">Cuota × 3</div>
                        <div id="res-cuota" style="font-size:1.35rem; font-weight:800; color:#7c3aed;"></div>
                    </div>
                </div>
                <div id="res-detalle" style="margin-top:12px; font-size:.85rem; color:#475569; text-align:center;"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const wrap = document.querySelector('.filters-bar-wrap');
    if (!wrap) return;
    if (window.matchMedia('(max-width: 720px)').matches) {
        wrap.removeAttribute('open');
    }
})();
window.openCasheaFromCard = function (card) {
    if (!card) return;
    const NIVELES = [
        { id: 1, label: 'Nivel 1', factor: {{ ($casheaLevels[1] ?? 60) / 100 }}, desc: '{{ $casheaLevels[1] ?? 60 }}% de inicial' },
        { id: 2, label: 'Nivel 2', factor: {{ ($casheaLevels[2] ?? 50) / 100 }}, desc: '{{ $casheaLevels[2] ?? 50 }}% de inicial' },
        { id: 3, label: 'Nivel 3', factor: {{ ($casheaLevels[3] ?? 40) / 100 }}, desc: '{{ $casheaLevels[3] ?? 40 }}% de inicial' },
        { id: 4, label: 'Nivel 4', factor: {{ ($casheaLevels[4] ?? 40) / 100 }}, desc: '{{ $casheaLevels[4] ?? 40 }}% de inicial' },
        { id: 5, label: 'Nivel 5', factor: {{ ($casheaLevels[5] ?? 40) / 100 }}, desc: '{{ $casheaLevels[5] ?? 40 }}% de inicial' },
        { id: 6, label: 'Nivel 6', factor: {{ ($casheaLevels[6] ?? 40) / 100 }}, desc: '{{ $casheaLevels[6] ?? 40 }}% de inicial' },
    ];
    const fmt = v => '$' + parseFloat(v).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const nombre = card.getAttribute('data-producto') || '';
    const codigo = card.getAttribute('data-codigo') || '';
    const precioUnit = parseFloat(card.getAttribute('data-precio-unidad')) || 0;
    const precioMayor = parseFloat(card.getAttribute('data-precio-mayor')) || 0;
    const overlay = document.getElementById('cashea-overlay');

    document.getElementById('cashea-nombre').textContent = nombre + ' — ' + codigo;
    document.getElementById('cashea-punit').textContent = precioUnit > 0 ? fmt(precioUnit) : '—';
    document.getElementById('cashea-pmayor').textContent = precioMayor > 0 ? fmt(precioMayor) : '—';
    document.getElementById('cashea-descuento').textContent = precioUnit > 0
        ? fmt(precioUnit * {{ $descFactor }}) + ' (Neto: ' + fmt(precioUnit * {{ $netoFactor }}) + ')'
        : '—';
    document.getElementById('cashea-resultado').style.display = 'none';

    const container = document.getElementById('cashea-niveles');
    container.innerHTML = '';
    NIVELES.forEach(function (nivel) {
        const inicial = precioUnit * nivel.factor;
        const restante = precioUnit - inicial;
        const cuota = restante / 3;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'nivel-btn';
        btn.innerHTML = '<div style="display:flex;align-items:center;gap:12px;"><div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;flex-shrink:0;">' + nivel.id + '</div><div><div style="font-weight:700;color:#1e293b;">' + nivel.label + '</div><div style="font-size:.8rem;color:#64748b;">' + nivel.desc + '</div></div></div><div style="text-align:right;"><div style="font-size:.75rem;color:#64748b;">Inicial</div><div style="font-weight:800;font-size:1.05rem;color:#16a34a;">' + fmt(inicial) + '</div></div>';
        btn.addEventListener('click', function () {
            document.querySelectorAll('.nivel-btn').forEach(function (b) { b.classList.remove('activo'); });
            btn.classList.add('activo');
            document.getElementById('res-nivel-label').textContent = nivel.label;
            document.getElementById('res-inicial').textContent = fmt(inicial);
            document.getElementById('res-restante').textContent = fmt(restante);
            document.getElementById('res-cuota').textContent = fmt(cuota);
            document.getElementById('res-detalle').textContent = 'Precio total: ' + fmt(precioUnit) + ' · Inicial: ' + fmt(inicial) + ' · 3 cuotas de ' + fmt(cuota);
            document.getElementById('cashea-resultado').style.display = 'block';
        });
        container.appendChild(btn);
    });

    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
};
window.closeCashea = function () {
    document.getElementById('cashea-overlay').style.display = 'none';
    document.body.style.overflow = '';
};
document.getElementById('cashea-overlay').addEventListener('click', function (e) {
    if (e.target === this) closeCashea();
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeCashea();
});
</script>

<script>
    const catSubcatMap = @json($catSubcatMap);
    const allSubcategorias = @json($subcategorias);
    let selectedSubcat = '{{ request('subcategoria', 'todas') }}';

    const categoriaSelect = document.getElementById('categoria');
    const subcategoriaSelect = document.getElementById('subcategoria');

    function updateSubcategorias() {
        const cat = categoriaSelect.value;
        let options = [];

        if (cat === 'todas') {
            options = allSubcategorias;
        } else {
            options = catSubcatMap[cat] || [];
        }

        subcategoriaSelect.innerHTML = '<option value="todas">Todas</option>';
        
        options.forEach(sub => {
            const opt = document.createElement('option');
            opt.value = sub;
            opt.textContent = sub;
            if (sub === selectedSubcat) {
                opt.selected = true;
            }
            subcategoriaSelect.appendChild(opt);
        });
    }

    categoriaSelect.addEventListener('change', function() {
        // Reset subcategory selection to "todas" when category changes manually
        subcategoriaSelect.value = 'todas';
        selectedSubcat = 'todas'; 
        updateSubcategorias();
    });

    // Initialize subcategories on page load
    updateSubcategorias();
    
    // --- NUEVA LÓGICA DE PDF ASÍNCRONA ---
    @unless($modoCliente)
    async function descargarPDF(scope) {
        let btnPanel = document.getElementById('pdf-panel');
        let loaderPanel = document.getElementById('pdf-loader');
        let successPanel = document.getElementById('pdf-success');
        
        btnPanel.style.display = 'none';
        successPanel.style.display = 'none';
        loaderPanel.style.display = 'block';

        let form = document.getElementById('form-filtros');
        let url = new URL("{{ route('catalogo.pdf') }}", window.location.origin);
        
        let formData = new FormData(form);
        for (let pair of formData.entries()) {
            if (pair[1] && pair[1] !== 'todas') {
                url.searchParams.append(pair[0], pair[1]);
            }
        }
        
        let currentUrlParams = new URLSearchParams(window.location.search);
        if (currentUrlParams.has('page') && scope === 'page') {
            url.searchParams.append('page', currentUrlParams.get('page'));
        }
        
        url.searchParams.append('pdf_scope', scope);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            loaderPanel.style.display = 'none';
            
            if (response.ok && data.success) {
                // Configurar Modal de Éxito
                document.getElementById('pdf-success-link').href = data.url;
                document.getElementById('pdf-success-link').textContent = data.url;
                
                // Configurar botones de compartir
                document.getElementById('btn-share-open').onclick = () => window.open(data.url, '_blank');
                document.getElementById('btn-share-copy').onclick = () => {
                    navigator.clipboard.writeText(data.url);
                    alert('¡Enlace copiado al portapapeles!');
                };
                
                let encodedUrl = encodeURIComponent(data.url);
                document.getElementById('btn-share-wa').onclick = () => window.open(`https://api.whatsapp.com/send?text=${encodedUrl}`, '_blank');
                document.getElementById('btn-share-mail').onclick = () => window.location.href = `mailto:?subject=Catálogo de Productos&body=${encodedUrl}`;
                
                // Mostrar panel de éxito
                successPanel.style.display = 'block';
            } else {
                alert('Error al generar catálogo: ' + (data.error || 'Error desconocido'));
                btnPanel.style.display = 'block';
            }
        } catch (error) {
            console.error('Fetch error:', error);
            loaderPanel.style.display = 'none';
            btnPanel.style.display = 'block';
            alert('Ocurrió un error de conexión al generar el catálogo.');
        }
    }
    @endunless

    // --- LÓGICA PARA ACTUALIZAR IMAGEN VÍA URL ---
    async function pedirUrlImagen(codigo) {
        const nuevaUrl = prompt('Pega aquí el enlace de la nueva imagen (debe terminar en .jpg o .png):');
        if (!nuevaUrl || nuevaUrl.trim() === '') return;
        
        try {
            // Mostrar estado de carga usando un toast o alert temporal
            window.showStatusMessage ? window.showStatusMessage('Descargando y subiendo imagen...', false) : console.log('Subiendo...');
            
            const response = await fetch("{{ route('catalogo.upload_image') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    codigo: codigo,
                    imagen_url: nuevaUrl
                })
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                alert('¡Imagen actualizada exitosamente!');
                window.location.reload(); // Recargar para ver la nueva imagen
            } else {
                alert('Error al actualizar: ' + (data.error || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Fetch error:', error);
            alert('Ocurrió un error al intentar conectarse con el servidor.');
        }
    }

    document.getElementById('form-filtros').addEventListener('submit', function(e) {
        if(e.submitter && e.submitter.name === 'pdf_scope') {
            return;
        }
        let btn = document.getElementById('btn-buscar');
        btn.innerHTML = 'Buscando...';
        btn.disabled = true;
    });
</script>
@endsection
