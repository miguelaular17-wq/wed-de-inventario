@extends('layouts.app')

@section('title', 'Catálogo al mayor')

@section('content')
@php
    $presNombre = fn ($p) => $p->presentacion === 'caja' ? 'caja' : ($p->presentacion === 'paquete' ? 'paquete' : 'unidad');
    $disponibleTexto = fn ($p) => \App\Models\Mayor\MayorCatalogo::cantidadTexto($p->disponible_presentaciones, $p->presentacion);
@endphp
<style>
    main:has(.cm-page) { max-width: none; width: 100%; padding: 20px 28px 40px; }
    .cm-cab { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .cm-filtros { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; margin-bottom: 16px; }
    .cm-filtros label { display: flex; flex-direction: column; gap: 4px; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #64748b; }
    .cm-filtros input, .cm-filtros select { padding: 9px 11px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: .9rem; min-width: 220px; }
    .cm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
    .cm-card { background: #fff; border: 1px solid #0f172a; border-radius: 14px; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
    .cm-foto { background: #f8fafc; border-radius: 10px; height: 180px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
    .cm-foto .cat-foto-link { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
    .cm-foto img { max-width: 100%; max-height: 180px; object-fit: contain; }
    .cm-badge { position: absolute; left: 8px; bottom: 8px; background: #dcfce7; color: #166534; font-weight: 800; font-size: .74rem; padding: 3px 9px; border-radius: 999px; }
    .cm-pres { position: absolute; right: 8px; top: 8px; background: #6d28d9; color: #fff; font-weight: 800; font-size: .74rem; padding: 3px 9px; border-radius: 999px; }
    .cm-meta { display: flex; justify-content: space-between; gap: 6px; font-size: .7rem; color: #64748b; font-weight: 700; text-transform: uppercase; }
    .cm-meta span:last-child { font-family: ui-monospace, monospace; text-transform: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 50%; }
    .cm-nombre { font-weight: 800; color: #0f172a; font-size: .9rem; line-height: 1.3; min-height: 2.6em; }
    .cm-precio { background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 10px; padding: 8px 10px; text-align: center; }
    .cm-precio strong { display: block; font-size: 1.35rem; color: #6d28d9; line-height: 1.1; }
    .cm-precio span { font-size: .75rem; color: #475569; }
    .cm-facturar { display: block; text-align: center; padding: 8px; border-radius: 8px; background: #2563eb; color: #fff; font-weight: 700; font-size: .82rem; text-decoration: none; }
</style>
<div class="cm-page">
    <div class="cm-cab">
        <div>
            <h1 style="margin:0;">Catálogo al mayor</h1>
            <p class="muted" style="margin:4px 0 0;">{{ $productos->count() }} productos con stock en JRZ · precio y presentación al mayor</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('ventas_mayor.stock') }}" class="btn secondary">← Stock</a>
            <a href="{{ route('ventas_mayor.catalogo.pdf', array_filter(['q' => $q, 'categoria' => $categoria])) }}" target="_blank" rel="noopener" class="btn primary">📄 Catálogo en PDF</a>
        </div>
    </div>

    <form method="GET" class="cm-filtros">
        <label>Buscar <input type="search" name="q" value="{{ $q }}" placeholder="Nombre o código…"></label>
        <label>Categoría
            <select name="categoria" onchange="this.form.submit()">
                <option value="">Todas</option>
                @foreach($categorias as $c)<option value="{{ $c }}" @selected($categoria === $c)>{{ $c }}</option>@endforeach
            </select>
        </label>
        <button type="submit" class="btn primary">Buscar</button>
        @if($q !== '' || $categoria !== '')<a href="{{ route('ventas_mayor.catalogo') }}" class="btn secondary">Limpiar</a>@endif
    </form>

    @if($productos->isEmpty())
        <p class="muted" style="text-align:center; padding:40px;">No hay productos con stock en JRZ y precio al mayor con ese criterio.</p>
    @else
        <div class="cm-grid">
            @foreach($productos as $p)
                <div class="cm-card">
                    <div class="cm-foto">
                        @include('partials.foto-producto', ['codigo' => $p->codigo, 'alt' => $p->nombre, 'clase' => ''])
                        <span class="cm-badge">{{ $disponibleTexto($p) }}</span>
                        @if($p->presentacion !== 'unidad')<span class="cm-pres">{{ $p->presentacion_texto }}</span>@endif
                    </div>
                    <div class="cm-meta"><span>{{ $p->categoria }}</span><span>{{ \App\Support\Sku::principal($p->codigo) }}</span></div>
                    <div class="cm-nombre">{{ $p->nombre }}</div>
                    <div class="cm-precio">
                        <strong>${{ number_format($p->precio_presentacion, 2) }}</strong>
                        <span>por {{ $presNombre($p) }}@if($p->unidades_por > 1) · ${{ number_format($p->precio_presentacion / $p->unidades_por, 2) }} c/u @endif</span>
                    </div>
                    <a href="{{ route('ventas_mayor.create', ['productos' => $p->id]) }}" class="cm-facturar">+ Facturar</a>
                </div>
            @endforeach
        </div>
        @if($productos->count() >= $limite)
            <p class="muted" style="font-size:.8rem; margin-top:10px;">Se muestran los de más stock en JRZ. Busca o filtra por categoría para ver otros.</p>
        @endif
    @endif
</div>
@endsection
