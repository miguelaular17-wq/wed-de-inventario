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
        grid-template-columns: minmax(180px, 1.6fr) repeat(4, minmax(130px, 1fr)) 88px auto;
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
    .subcat-multi { position: relative; }
    .subcat-toggle {
        text-align: left; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        padding-right: 28px;
        background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='3'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;
    }
    .subcat-panel {
        position: absolute; top: 100%; left: 0; z-index: 40; margin-top: 4px;
        width: max(100%, 280px); background: #fff; border: 1px solid #cbd5e1; border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, .18); padding: 8px;
    }
    .subcat-buscar { width: 100%; padding: 7px 9px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: .85rem; box-sizing: border-box; }
    .subcat-acciones { display: flex; justify-content: space-between; gap: 6px; margin: 6px 0; }
    .subcat-acciones button { background: none; border: none; color: #1d4ed8; font-size: .78rem; font-weight: 600; cursor: pointer; padding: 2px 0; }
    .subcat-lista { max-height: 260px; overflow-y: auto; }
    .subcat-opcion { display: flex; align-items: center; gap: 6px; padding: 5px 4px; font-size: .85rem; cursor: pointer; border-radius: 6px; }
    .subcat-opcion:hover { background: #f1f5f9; }
    .subcat-vacio { color: #64748b; font-size: .82rem; padding: 6px 4px; }
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
        grid-template-columns: repeat(4, minmax(130px, 1fr)) 88px auto;
    }
    .filters-rangos { display: grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap: 10px 24px; margin-top: 12px; }
    .rango-top { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 4px; }
    .rango-top label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; color: #64748b; }
    .rango-valor { font-size: 0.82rem; font-weight: 700; color: #1e3a8a; white-space: nowrap; }
    .rango-barra { position: relative; height: 26px; }
    .rango-barra::before { content: ''; position: absolute; left: 0; right: 0; top: 11px; height: 4px; border-radius: 999px; background: #e2e8f0; }
    .rango-relleno { position: absolute; top: 11px; height: 4px; border-radius: 999px; background: #2563eb; }
    .rango-barra input[type=range] { position: absolute; inset: 0; width: 100%; margin: 0; background: none; pointer-events: none; -webkit-appearance: none; appearance: none; height: 26px; }
    .rango-barra input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; pointer-events: auto; width: 18px; height: 18px; border-radius: 50%; background: #fff; border: 3px solid #2563eb; cursor: pointer; box-shadow: 0 1px 3px rgba(15,23,42,.25); }
    .rango-barra input[type=range]::-moz-range-thumb { pointer-events: auto; width: 14px; height: 14px; border-radius: 50%; background: #fff; border: 3px solid #2563eb; cursor: pointer; }
    .rango-barra input[type=range]::-webkit-slider-runnable-track { background: none; }
    .rango-barra input[type=range]::-moz-range-track { background: none; }
    @media (max-width: 700px) { .filters-rangos { grid-template-columns: 1fr; } }
    .product-card {
        background: #fff;
        border: 1px solid #000;
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
        border-color: #000;
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
    .foto-nav {
        position: absolute; top: 50%; transform: translateY(-50%); z-index: 2;
        width: 28px; height: 28px; border-radius: 50%; border: 1px solid #cbd5e1;
        background: rgba(255,255,255,.92); color: #1e3a8a; font-size: 1.2rem; font-weight: 700; line-height: 1;
        cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0;
    }
    .foto-prev { left: 6px; }
    .foto-next { right: 6px; }
    .foto-contador {
        position: absolute; top: 8px; left: 8px; z-index: 2; background: rgba(15,23,42,.7); color: #fff;
        font-size: .7rem; font-weight: 700; padding: 2px 7px; border-radius: 999px;
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
    .stock-badge.con-desglose { cursor: help; z-index: 3; }
    .stock-desglose {
        display: none;
        position: absolute;
        left: 0;
        bottom: calc(100% + 6px);
        min-width: 150px;
        background: #0f172a;
        color: #fff;
        border-radius: 8px;
        padding: 8px 10px;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.3);
        font-size: 0.74rem;
        font-weight: 600;
        line-height: 1.5;
        white-space: nowrap;
        z-index: 20;
    }
    .stock-badge.con-desglose:hover .stock-desglose,
    .stock-badge.con-desglose:focus .stock-desglose { display: block; }
    .stock-desglose-titulo { display: block; color: #94a3b8; font-size: 0.66rem; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 3px; }
    .stock-desglose-fila { display: flex; justify-content: space-between; gap: 14px; }
    .stock-desglose-fila strong { color: #86efac; }
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
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            @if(!$modoCliente && auth()->check() && auth()->user()->canAccess('catalogo.exportar_precios'))
                <a href="{{ route('catalogo.precios.export') }}" class="btn-custom btn-primary-custom">
                    Excel precios (−25%)
                </a>
            @endif
            <div class="catalogo-badge">{{ $productos->total() }} productos</div>
        </div>
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
                <div class="filter-item subcat-multi">
                    <label>Subcategoría</label>
                    <button type="button" class="filter-input subcat-toggle" id="subcatToggle" aria-haspopup="true" aria-expanded="false">Todas</button>
                    <div class="subcat-panel" id="subcatPanel" hidden>
                        <input type="search" id="subcatBuscar" class="subcat-buscar" placeholder="Buscar subcategoría…" autocomplete="off">
                        <div class="subcat-acciones">
                            <button type="button" id="subcatMarcarVisibles">Marcar visibles</button>
                            <button type="button" id="subcatLimpiar">Todas (quitar filtro)</button>
                        </div>
                        <div class="subcat-lista" id="subcatLista"></div>
                    </div>
                </div>
                <div class="filter-item">
                    <label>Sede</label>
                    <select name="sede" id="sedeSelect" class="filter-input">
                        <option value="todas">Global (Todas)</option>
                        @foreach($sedes as $s)
                            <option value="{{ $s }}" {{ request('sede') == $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item subcat-multi" id="excluirSedeWrap">
                    <label>Excluir sedes</label>
                    <button type="button" class="filter-input subcat-toggle" id="excluirSedeToggle" aria-haspopup="true" aria-expanded="false">Ninguna</button>
                    <div class="subcat-panel" id="excluirSedePanel" hidden>
                        <div class="subcat-acciones">
                            <span style="font-size:.75rem; color:#64748b;">Solo aplica con sede Global</span>
                            <button type="button" id="excluirSedeLimpiar">Ninguna</button>
                        </div>
                        <div class="subcat-lista">
                            @foreach($sedes as $s)
                                <label class="subcat-opcion">
                                    <input type="checkbox" name="excluir_sede[]" value="{{ $s }}" {{ in_array(strtoupper($s), $sedesExcluidas ?? [], true) ? 'checked' : '' }}>
                                    {{ $s }}
                                </label>
                            @endforeach
                        </div>
                    </div>
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

            <div class="filters-rangos">
                @foreach($rangos ?? [] as $clave => $r)
                    <div class="rango" data-pasos='@json($r['pasos'])' data-prefijo="{{ $r['prefijo'] }}">
                        <div class="rango-top">
                            <label>{{ $r['titulo'] }}</label>
                            <span class="rango-valor"></span>
                        </div>
                        <div class="rango-barra">
                            <div class="rango-relleno"></div>
                            <input type="range" class="rango-min" min="0" max="{{ count($r['pasos']) }}" step="1" aria-label="{{ $r['titulo'] }} desde">
                            <input type="range" class="rango-max" min="0" max="{{ count($r['pasos']) }}" step="1" aria-label="{{ $r['titulo'] }} hasta">
                        </div>
                        <input type="hidden" class="rango-input-min" name="{{ $clave }}_min" value="{{ request($clave.'_min') }}">
                        <input type="hidden" class="rango-input-max" name="{{ $clave }}_max" value="{{ request($clave.'_max') }}">
                    </div>
                @endforeach
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
                        $extrasFoto = $fotosExtra[$primary_code] ?? [];
                    @endphp
                    @if($extrasFoto !== [])
                        <button type="button" class="foto-nav foto-prev" onclick="event.stopPropagation(); cambiarFoto(this, -1)" aria-label="Foto anterior">‹</button>
                        <button type="button" class="foto-nav foto-next" onclick="event.stopPropagation(); cambiarFoto(this, 1)" aria-label="Foto siguiente">›</button>
                        <span class="foto-contador">1/{{ count($extrasFoto) + 1 }}</span>
                    @endif
                    <img data-fotos='@json(array_column($extrasFoto, 'url'))' data-indice="0" src="{{ $jpg_url }}" 
                         alt="{{ $prod->descripcion }}" 
                         class="product-img"
                         loading="lazy"
                         onerror="if(this.dataset.triedPng !== 'true') { this.dataset.triedPng = 'true'; this.src='{{ $png_url }}'; } else { this.src='{{ $no_image_url }}'; this.onerror=null; }">
                    @if($prod->existencia > 0)
                        <span class="stock-badge stock-green {{ !empty($verDesgloseStock) ? 'con-desglose' : '' }}" tabindex="0" onclick="event.stopPropagation()">
                            {{ number_format($prod->existencia, 0) }} unds
                            @if(!empty($verDesgloseStock) && $prod->relationLoaded('stock') && $prod->stock->isNotEmpty())
                                <span class="stock-desglose" role="tooltip">
                                    <span class="stock-desglose-titulo">¿Dónde están?</span>
                                    @foreach($prod->stock as $st)
                                        @php $codSede = strtoupper(trim((string) $st->sede)); @endphp
                                        <span class="stock-desglose-fila">
                                            <span>{{ $nombresSede[$codSede] ?? mb_convert_case($codSede, MB_CASE_TITLE, 'UTF-8') }}{{ $codSede === strtoupper((string) config('inventario.sede_central', 'JRZ')) ? ' (almacén)' : '' }}</span>
                                            <strong>{{ number_format($st->existencia, 0) }}</strong>
                                        </span>
                                    @endforeach
                                </span>
                            @endif
                        </span>
                    @else
                        <span class="stock-badge stock-red">Agotado</span>
                    @endif
                    @if(! empty($puedeCambiarImagenes))
                        <button type="button" title="Cambiar o agregar fotos"
                                onclick='event.stopPropagation(); pedirUrlImagen(@json($primary_code), this.closest(".product-card").dataset.producto, @json($extrasFoto), @json($jpg_url))'
                                style="position: absolute; top: 6px; right: 6px; z-index: 3; background: rgba(255,255,255,0.94); border: 1px solid #cbd5e1; border-radius: 6px; padding: 2px 6px; font-size: 0.8rem; cursor: pointer; color: #1e3a8a;">
                            📷
                        </button>
                    @endif
                </div>
                    <div class="product-info">
                        <div class="product-meta">
                            <div class="product-category">{{ $prod->categoria ?? 'Sin Categoría' }}</div>
                            <div class="product-code">{{ \App\Support\Sku::principal($prod->codigo) }}</div>
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
    const CASHEA_MIN_USD = 10;
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

    if (precioUnit < CASHEA_MIN_USD) {
        alert('Cashea aplica solo a productos de $10.00 o más.');
        return;
    }

    document.getElementById('cashea-nombre').textContent = nombre + ' — ' + String(codigo || '').split(' / ')[0].trim();
    document.getElementById('cashea-punit').textContent = precioUnit > 0 ? fmt(precioUnit) : '—';
    document.getElementById('cashea-pmayor').textContent = precioMayor > 0 ? fmt(precioMayor) : '—';
    document.getElementById('cashea-descuento').textContent = precioUnit > 0
        ? fmt(precioUnit * {{ $netoFactor }})
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
    const allSubcategorias = @json(collect($subcategorias)->values());
    // Varias subcategorías: casillas subcategoria[]; ninguna marcada = todas.
    let selectedSubcats = new Set(@json($subcategoriasElegidas));

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
        const options = cat === 'todas' ? allSubcategorias : (catSubcatMap[cat] || []);
        // Una subcategoría que no es de la categoría elegida deja de filtrar.
        selectedSubcats = new Set([...selectedSubcats].filter(s => options.includes(s)));

        subcatLista.innerHTML = '';
        options.forEach(sub => {
            const label = document.createElement('label');
            label.className = 'subcat-opcion';
            label.dataset.nombre = sub.toLowerCase();
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
        if (!options.length) subcatLista.innerHTML = '<div class="subcat-vacio">Esta categoría no tiene subcategorías.</div>';
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
        if (!subcatPanel.hidden && e.target.closest('.subcat-multi') !== subcatPanel.parentElement) {
            subcatPanel.hidden = true;
            subcatToggle.setAttribute('aria-expanded', 'false');
        }
    });
    subcatBuscar.addEventListener('input', () => {
        const q = subcatBuscar.value.trim().toLowerCase();
        subcatLista.querySelectorAll('.subcat-opcion').forEach(el => el.style.display = el.dataset.nombre.includes(q) ? '' : 'none');
    });
    subcatBuscar.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
    document.getElementById('subcatMarcarVisibles').addEventListener('click', () => {
        subcatLista.querySelectorAll('.subcat-opcion').forEach(el => {
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

    categoriaSelect.addEventListener('change', function() {
        selectedSubcats.clear();
        updateSubcategorias();
    });

    updateSubcategorias();

    (function () {
        const sedeSelect = document.getElementById('sedeSelect');
        const toggle = document.getElementById('excluirSedeToggle');
        const panel = document.getElementById('excluirSedePanel');
        const checks = [...panel.querySelectorAll('input[type=checkbox]')];

        function refrescar() {
            const esGlobal = sedeSelect.value === 'todas';
            const marcadas = checks.filter(c => c.checked).map(c => c.value);
            // Con una sede específica la exclusión no aplica: se deshabilita para no enviarla.
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

    // --- FOTOS DEL PRODUCTO: cambiar la principal o agregar otras (archivo del equipo/teléfono o enlace) ---
    // Ventana propia (no prompt()): sigue abierta al cambiar de pestaña y el nombre
    // solo se copia al pulsar el botón, para no pisar el enlace que el usuario copió.
    const MAX_FOTOS_EXTRA = {{ \App\Models\ProductoImagen::MAXIMO_EXTRA }};

    function cambiarFoto(btn, paso) {
        const wrap = btn.closest('.product-img-wrapper');
        const img = wrap.querySelector('.product-img');
        if (!img.dataset.principal) img.dataset.principal = img.src;
        const lista = [img.dataset.principal, ...JSON.parse(img.dataset.fotos || '[]')];
        const i = ((parseInt(img.dataset.indice, 10) || 0) + paso + lista.length) % lista.length;
        img.dataset.indice = i;
        img.onerror = null;
        img.src = lista[i];
        const contador = wrap.querySelector('.foto-contador');
        if (contador) contador.textContent = (i + 1) + '/' + lista.length;
    }

    function modalImagenUrl() {
        let wrap = document.getElementById('img-url-modal');
        if (wrap) return wrap;
        const etiqueta = 'font-size:.75rem;color:#64748b;text-transform:uppercase;font-weight:700;';
        wrap = document.createElement('div');
        wrap.id = 'img-url-modal';
        wrap.style.cssText = 'display:none;position:fixed;inset:0;z-index:2000;background:rgba(15,23,42,.55);align-items:center;justify-content:center;padding:16px;overflow-y:auto;';
        wrap.innerHTML = ''
            + '<div style="background:#fff;border-radius:12px;width:min(520px,100%);padding:18px 20px;box-shadow:0 20px 40px rgba(0,0,0,.25);font-family:inherit;">'
            + '  <h3 style="margin:0 0 10px;font-size:1.05rem;color:#1e3a8a;">Fotos del producto</h3>'
            + '  <div style="' + etiqueta + '">Producto</div>'
            + '  <div style="display:flex;gap:8px;align-items:center;margin:4px 0 14px;">'
            + '    <input type="text" readonly data-campo="nombre" style="flex:1;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;font-size:.9rem;">'
            + '    <button type="button" data-accion="copiar" style="padding:8px 12px;border:1px solid #1e3a8a;border-radius:8px;background:#fff;color:#1e3a8a;font-weight:700;cursor:pointer;white-space:nowrap;">Copiar nombre</button>'
            + '  </div>'
            + '  <div style="' + etiqueta + '">Fotos actuales</div>'
            + '  <div data-campo="fotos" style="display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 14px;"></div>'
            + '  <div style="' + etiqueta + '">¿Qué quieres hacer?</div>'
            + '  <div style="display:flex;gap:14px;flex-wrap:wrap;margin:6px 0 14px;font-size:.9rem;">'
            + '    <label style="display:flex;gap:6px;align-items:center;cursor:pointer;"><input type="radio" name="img-modo" value="principal" checked> Cambiar la foto principal</label>'
            + '    <label style="display:flex;gap:6px;align-items:center;cursor:pointer;" data-campo="op-extra"><input type="radio" name="img-modo" value="extra"> Añadir otra foto</label>'
            + '  </div>'
            + '  <div style="' + etiqueta + '">Desde tu equipo o teléfono</div>'
            + '  <input type="file" accept="image/*" data-campo="archivo" style="width:100%;margin:4px 0 12px;font-size:.88rem;">'
            + '  <div style="' + etiqueta + '">O pega el enlace de la imagen</div>'
            + '  <input type="url" data-campo="url" placeholder="https://…" style="width:100%;box-sizing:border-box;margin:4px 0 16px;padding:9px 10px;border:1.5px solid #93c5fd;border-radius:8px;font-size:.9rem;">'
            + '  <div data-campo="estado" style="font-size:.85rem;color:#1e3a8a;font-weight:600;min-height:1.2em;margin-bottom:8px;"></div>'
            + '  <div style="display:flex;justify-content:flex-end;gap:8px;">'
            + '    <button type="button" data-accion="cancelar" style="padding:9px 14px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font-weight:700;cursor:pointer;">Cerrar</button>'
            + '    <button type="button" data-accion="guardar" style="padding:9px 14px;border:0;border-radius:8px;background:#1e3a8a;color:#fff;font-weight:700;cursor:pointer;">Guardar foto</button>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(wrap);

        const campoNombre = wrap.querySelector('[data-campo="nombre"]');
        const campoUrl = wrap.querySelector('[data-campo="url"]');
        const campoArchivo = wrap.querySelector('[data-campo="archivo"]');
        const btnCopiar = wrap.querySelector('[data-accion="copiar"]');
        const btnGuardar = wrap.querySelector('[data-accion="guardar"]');
        const estado = wrap.querySelector('[data-campo="estado"]');
        const cerrar = () => { wrap.style.display = 'none'; if (wrap.dataset.cambio === '1') window.location.reload(); };

        wrap.addEventListener('click', e => { if (e.target === wrap) cerrar(); });
        wrap.querySelector('[data-accion="cancelar"]').addEventListener('click', cerrar);
        btnCopiar.addEventListener('click', async () => {
            let ok = false;
            if (navigator.clipboard && window.isSecureContext) {
                try { await navigator.clipboard.writeText(campoNombre.value); ok = true; } catch (e) { ok = false; }
            }
            if (!ok) {
                campoNombre.select();
                ok = document.execCommand && document.execCommand('copy');
            }
            btnCopiar.textContent = ok ? '¡Copiado!' : 'Selecciónalo y copia';
            setTimeout(() => { btnCopiar.textContent = 'Copiar nombre'; }, 1800);
        });
        btnGuardar.addEventListener('click', async () => {
            const url = campoUrl.value.trim();
            const archivo = campoArchivo.files[0];
            if (!archivo && !url) { estado.textContent = 'Elige una foto de tu equipo o pega un enlace.'; return; }
            const datos = new FormData();
            datos.append('codigo', wrap.dataset.codigo);
            datos.append('modo', wrap.querySelector('input[name="img-modo"]:checked').value);
            if (archivo) datos.append('archivo', archivo); else datos.append('imagen_url', url);
            btnGuardar.disabled = true;
            estado.textContent = 'Subiendo foto…';
            try {
                const r = await fetch(@json(route('catalogo.upload_image')), {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                    body: datos,
                });
                const data = await r.json().catch(() => ({}));
                if (r.ok && data.success) {
                    estado.textContent = '✅ Foto guardada.';
                    wrap.dataset.cambio = '1';
                    setTimeout(cerrar, 600);
                } else {
                    estado.textContent = '⚠️ ' + (data.error || data.message || 'No se pudo guardar la foto.');
                }
            } catch (e) {
                estado.textContent = '⚠️ No se pudo conectar con el servidor.';
            }
            btnGuardar.disabled = false;
        });
        campoUrl.addEventListener('keydown', e => {
            if (e.key === 'Enter') btnGuardar.click();
            if (e.key === 'Escape') cerrar();
        });

        return wrap;
    }

    function pintarFotosModal(wrap, principal, extras) {
        const caja = wrap.querySelector('[data-campo="fotos"]');
        const miniatura = (src, texto, id) => `
            <div style="position:relative;width:78px;text-align:center;font-size:.68rem;color:#64748b;">
                <img src="${src}" style="width:78px;height:78px;object-fit:contain;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;" onerror="this.style.opacity=.3">
                <div>${texto}</div>
                ${id ? `<button type="button" data-borrar="${id}" title="Borrar esta foto" style="position:absolute;top:-6px;right:-6px;width:22px;height:22px;border-radius:50%;border:0;background:#ef4444;color:#fff;font-weight:700;cursor:pointer;line-height:1;">×</button>` : ''}
            </div>`;
        caja.innerHTML = miniatura(principal, 'Principal', null) + extras.map((f, n) => miniatura(f.url, 'Foto ' + (n + 2), f.id)).join('');
        const opExtra = wrap.querySelector('[data-campo="op-extra"]');
        const lleno = extras.length >= MAX_FOTOS_EXTRA;
        opExtra.querySelector('input').disabled = lleno;
        opExtra.title = lleno ? 'Ya tiene ' + MAX_FOTOS_EXTRA + ' fotos adicionales: borra una para agregar otra.' : '';
        caja.querySelectorAll('[data-borrar]').forEach(b => b.addEventListener('click', async () => {
            if (!confirm('¿Borrar esta foto?')) return;
            const r = await fetch(@json(url('/catalogo/imagenes')) + '/' + b.dataset.borrar, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
            });
            if (r.ok) {
                wrap.dataset.cambio = '1';
                pintarFotosModal(wrap, principal, extras.filter(f => String(f.id) !== b.dataset.borrar));
            } else {
                alert('No se pudo borrar la foto.');
            }
        }));
    }

    function pedirUrlImagen(codigo, nombre, extras = [], principal = '') {
        const wrap = modalImagenUrl();
        wrap.dataset.codigo = codigo;
        wrap.dataset.cambio = '';
        wrap.querySelector('[data-campo="nombre"]').value = String(nombre || '').replace(/^[\/\s.]+/, '').trim();
        wrap.querySelector('[data-campo="url"]').value = '';
        wrap.querySelector('[data-campo="archivo"]').value = '';
        wrap.querySelector('[data-campo="estado"]').textContent = '';
        wrap.querySelector('input[name="img-modo"][value="principal"]').checked = true;
        pintarFotosModal(wrap, principal, extras || []);
        wrap.style.display = 'flex';
    }

    document.getElementById('form-filtros').addEventListener('submit', function(e) {
        if(e.submitter && e.submitter.name === 'pdf_scope') {
            return;
        }
        let btn = document.getElementById('btn-buscar');
        btn.innerHTML = 'Buscando...';
        btn.disabled = true;
    });

    // Barras de stock y precio: dos manijas (desde / hasta). El tope derecho es «sin límite».
    document.querySelectorAll('.rango').forEach(function (rango) {
        const pasos = JSON.parse(rango.dataset.pasos);
        const prefijo = rango.dataset.prefijo || '';
        const tope = pasos.length;
        const deslizMin = rango.querySelector('.rango-min');
        const deslizMax = rango.querySelector('.rango-max');
        const campoMin = rango.querySelector('.rango-input-min');
        const campoMax = rango.querySelector('.rango-input-max');
        const relleno = rango.querySelector('.rango-relleno');
        const etiqueta = rango.querySelector('.rango-valor');
        const fmt = v => prefijo + Number(v).toLocaleString('es-VE');
        const indice = valor => {
            if (valor === '' || valor === null) return null;
            const n = parseFloat(valor);
            let i = pasos.findIndex(p => p >= n);
            return i === -1 ? tope - 1 : i;
        };

        deslizMin.value = indice(campoMin.value) ?? 0;
        deslizMax.value = indice(campoMax.value) ?? tope;

        function pintar() {
            let a = parseInt(deslizMin.value, 10), b = parseInt(deslizMax.value, 10);
            if (a > b) { [a, b] = [b, a]; }
            relleno.style.left = (a / tope * 100) + '%';
            relleno.style.width = ((b - a) / tope * 100) + '%';
            const desde = a === 0 ? null : pasos[a];
            const hasta = b >= tope ? null : pasos[b];
            etiqueta.textContent = desde === null && hasta === null ? 'Todos'
                : desde === null ? 'Hasta ' + fmt(hasta)
                : hasta === null ? 'Desde ' + fmt(desde)
                : fmt(desde) + ' – ' + fmt(hasta);
            campoMin.value = desde === null ? '' : desde;
            campoMax.value = hasta === null ? '' : hasta;
            campoMin.disabled = campoMin.value === '';
            campoMax.disabled = campoMax.value === '';
            deslizMin.style.zIndex = parseInt(deslizMin.value, 10) >= tope - 1 ? 3 : 1;
        }
        [deslizMin, deslizMax].forEach(d => {
            d.addEventListener('input', pintar);
            d.addEventListener('change', () => { pintar(); document.getElementById('form-filtros').requestSubmit(); });
        });
        pintar();
    });
</script>
@endsection
