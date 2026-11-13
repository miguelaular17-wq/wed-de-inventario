@extends('layouts.app')

@section('title', 'Ventas al mayor · Stock')

@section('content')
@php
    $jrz = \App\Services\Mayor\VentasMayorService::SEDE_FACTURA;
    $nombreSede = fn ($s) => config('inventario.display.'.$s, $s);
    $filtros = ['alguna' => 'Con existencia', 'jrz' => 'Con stock en JRZ', 'todos' => 'Todos'];
@endphp
<div class="cat-page">
<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="margin:0;">Ventas al mayor · Stock</h1>
        <p class="lead" style="margin:4px 0 0;">
            {{ number_format($resumen['productos']) }} productos con stock en JRZ · {{ number_format($resumen['unidades']) }} unidades
            · ${{ number_format($resumen['valor_mayor'], 2) }} al mayor · Se ve el stock de todas las sedes, pero <strong>solo se factura del de JRZ</strong>
        </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <a href="{{ route('ventas_mayor.facturas') }}" class="btn secondary" style="padding:10px 14px; border-radius:8px; text-decoration:none;">Facturas</a>
        <a href="{{ route('ventas_mayor.create') }}" class="btn secondary" style="padding:10px 14px; border-radius:8px; text-decoration:none; font-weight:600;">+ Nueva factura</a>
        <a href="{{ route('ventas_mayor.catalogo') }}" class="btn btn-primary" style="background-color: var(--blue); color: white; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-images"></i> Ver Catálogo Gráfico / PDF
        </a>
    </div>
</div>

@if(session('success'))
    <div style="margin:0 0 12px; padding:10px 14px; border-radius:8px; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0;">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div style="margin:0 0 12px; padding:10px 14px; border-radius:8px; background:#fef2f2; color:#991b1b; border:1px solid #fecaca;">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<form method="GET" action="{{ route('ventas_mayor.stock') }}" class="cat-search">
    <div class="cat-search-field">
        <span>🔍</span>
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por nombre o código… (Enter para buscar)" autocomplete="off" autofocus>
    </div>
    <label class="cat-dias-label">
        Mostrar
        <select name="ver" onchange="this.form.submit()">
            @foreach($filtros as $clave => $texto)
                <option value="{{ $clave }}" @selected($ver === $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    </label>
    @if($q !== '')
        <a href="{{ route('ventas_mayor.stock', ['ver' => $ver]) }}">✕ Limpiar</a>
    @endif
</form>

<section class="cat-panel">
    <div class="cat-table-wrap">
        <table class="data-table cat-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="width:52%;">Existencias</th>
                    <th style="width:300px;">Precios</th>
                </tr>
            </thead>
            <tbody>
                @forelse($productos as $p)
                    <tr>
                        <td>
                            <div class="cat-product-fila">
                                @include('partials.foto-producto', ['codigo' => $p->codigo, 'alt' => $p->nombre])
                                <div class="cat-product">
                                    <code class="cat-code">{{ \App\Support\Sku::principal($p->codigo) }}</code>
                                    <div class="cat-name">{{ $p->nombre }}</div>
                                    @if($p->categoria)<span class="cat-tag">{{ $p->categoria }}</span>@endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="cat-stock">
                                <div class="cat-stock-global">
                                    <strong>{{ number_format($p->stock_total) }}</strong>
                                    <span>global</span>
                                </div>
                                <div class="cat-stock-sedes">
                                    @foreach($sedes as $s)
                                        @php $stock = (int) ($p->stocks[$s] ?? 0); @endphp
                                        <span class="cat-sede {{ $stock > 0 ? 'has-stock' : 'no-stock' }} {{ $s === $jrz ? 'cat-sede-jrz' : '' }}"
                                              @if($s === $jrz) title="Se factura de aquí (ya sin lo facturado al mayor)" @endif>
                                            <span class="cat-sede-top">
                                                {{ $nombreSede($s) }}@if($s === $jrz) · factura @endif
                                                <b>{{ number_format($stock) }}</b>
                                            </span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="cat-prices">
                                <div>
                                    <span>Se vende por</span>
                                    <strong class="vm-pres {{ $p->presentacion !== 'unidad' ? 'vm-pres-caja' : '' }}">{{ $p->presentacion_texto }}</strong>
                                    @if($p->unidades_por > 1)<em>{{ number_format($p->disponible_presentaciones) }} en JRZ</em>@endif
                                </div>
                                <div>
                                    <span>Precio mayor{{ $p->precio_editado ? ' ✎' : '' }}</span>
                                    <strong class="price-mayor">{{ $p->precio_presentacion > 0 ? '$'.number_format($p->precio_presentacion, 2) : '—' }}</strong>
                                    @if($p->unidades_por > 1 && $p->precio_presentacion > 0)<em>${{ number_format($p->precio_presentacion / $p->unidades_por, 2) }} c/u</em>@endif
                                </div>
                                <div>
                                    <span>Costo{{ $p->costo_editado ? ' ✎' : '' }}</span>
                                    <strong style="color:#475569;">{{ $p->costo_presentacion > 0 ? '$'.number_format($p->costo_presentacion, 2) : '—' }}</strong>
                                    @if($p->ganancia_pct !== null)
                                        <em class="{{ $p->ganancia_pct < 0 ? 'vm-perdida' : 'vm-ganancia' }}">{{ $p->ganancia_pct >= 0 ? '+' : '' }}{{ number_format($p->ganancia_pct, 1) }}% ganancia</em>
                                    @endif
                                </div>
                                @php
                                    $ficha = [
                                        'url' => route('ventas_mayor.catalogo.guardar', $p->id),
                                        'nombre' => $p->nombre,
                                        'codigo' => $p->codigo,
                                        'categoria' => $p->categoria ?? null,
                                        'subcategoria' => $p->subcategoria ?? null,
                                        'proveedor' => $p->proveedor ?? null,
                                        'imagen' => \App\Support\FotoProducto::base($p->codigo),
                                        'precio_unidad' => $p->precio_unidad,
                                        'ultima_compra' => isset($p->ultimo_costo_compra) && (float) $p->ultimo_costo_compra > 0
                                            ? ['cantidad' => (float) ($p->ultima_cantidad_compra ?? 0), 'costo' => (float) $p->ultimo_costo_compra] : null,
                                        'stocks' => collect($p->stocks)->mapWithKeys(fn ($n, $s) => [$nombreSede($s).($s === $jrz ? ' · factura' : '') => $n])->all(),
                                        'stock_total' => $p->stock_total,
                                        'presentacion' => $p->presentacion,
                                        'unidades' => $p->unidades_por,
                                        'precio' => $p->precio_editado ? $p->precio_presentacion : null,
                                        'precio_mayor' => $p->precio_mayor,
                                        'costo' => $p->costo_editado ? $p->costo_unidad : null,
                                        'costo_suite' => $p->costo_suite,
                                    ];
                                @endphp
                                <div class="vm-acciones">
                                    <button type="button" class="vm-editar" onclick='vmFicha(@json($ficha))'>{{ $puedeEditarCatalogo ? '✏️ Ficha / editar' : '📋 Ver ficha' }}</button>
                                    @if($p->disponible_presentaciones > 0)
                                        <a href="{{ route('ventas_mayor.create', ['productos' => $p->id]) }}" class="vm-facturar">+ Facturar</a>
                                    @else
                                        <span class="vm-facturar vm-facturar-no">Sin stock en JRZ</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align:center; padding:40px; color:var(--muted);">No hay productos con ese criterio.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@if($productos->count() >= $limite)
    <p class="muted" style="font-size:.8rem; margin-top:8px;">Se muestran los primeros {{ $productos->count() }} (los de más stock en JRZ primero). Busca por nombre o código para ver otros.</p>
@endif
</div>

<div id="vm-modal" class="vm-modal" onclick="if (event.target === this) vmCerrar()">
    <form method="POST" id="vm-modal-form" class="vm-modal-box">
        @csrf
        <button type="button" class="vm-cerrar" onclick="vmCerrar()" aria-label="Cerrar">&times;</button>
        <div class="vm-ficha-top">
            <img id="vm-f-img" alt="" style="display:none;">
            <div style="min-width:0;">
                <h3 id="vm-f-nombre" style="margin:0 0 4px; font-size:1.05rem;"></h3>
                <div><code class="cat-code" id="vm-f-codigo"></code></div>
                <div id="vm-f-tags" style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;"></div>
                <div id="vm-f-proveedor" class="muted" style="font-size:.82rem; margin-top:6px;"></div>
            </div>
        </div>
        <div class="vm-ficha-datos" id="vm-f-datos"></div>
        <div class="vm-ficha-sub">Existencias <span id="vm-f-total" class="muted" style="font-weight:600;"></span></div>
        <div class="vm-ficha-stock" id="vm-f-stock"></div>

        <div class="vm-ficha-sub" style="margin-top:16px;">Cómo se vende al mayor</div>
        @unless($puedeEditarCatalogo)
            <p class="muted" style="margin:0 0 10px; font-size:.8rem;">🔒 Solo la regente puede cambiar la presentación, el precio y el costo.</p>
        @endunless
        <div class="vm-campos">
            <label>Se vende por
                <select name="presentacion" id="vm-presentacion">
                    @foreach(\App\Models\Mayor\MayorCatalogo::PRESENTACIONES as $clave => $nombre)
                        <option value="{{ $clave }}">{{ $nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label id="vm-unidades-wrap"><span>Unidades por <span id="vm-unidades-de">caja</span></span>
                <input type="number" name="unidades" id="vm-unidades" min="1" step="1">
            </label>
            <label><span>Precio al mayor <span id="vm-precio-de">por unidad</span> $</span>
                <input type="number" name="precio_usd" id="vm-precio" min="0.01" step="0.01">
                <small class="muted" id="vm-precio-ayuda"></small>
            </label>
            <label>Costo por unidad $
                <input type="number" name="costo_usd" id="vm-costo" min="0" step="0.0001">
                <small class="muted" id="vm-costo-ayuda"></small>
            </label>
        </div>
        <div id="vm-resultado" class="vm-resultado"></div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
            <button type="button" class="btn secondary" onclick="vmCerrar()">{{ $puedeEditarCatalogo ? 'Cancelar' : 'Cerrar' }}</button>
            @if($puedeEditarCatalogo)
                <button type="submit" class="btn primary">Guardar</button>
            @endif
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const $ = id => document.getElementById(id);
    const usd = n => '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let actual = null;

    function calcular() {
        if (!actual) return;
        const pres = $('vm-presentacion').value;
        const esUnidad = pres === 'unidad';
        const nombre = pres === 'caja' ? 'caja' : 'paquete';
        $('vm-unidades-wrap').style.display = esUnidad ? 'none' : '';
        $('vm-unidades').required = !esUnidad;
        $('vm-unidades-de').textContent = nombre;
        $('vm-precio-de').textContent = esUnidad ? 'por unidad' : 'por ' + nombre;
        const unidades = esUnidad ? 1 : Math.max(1, parseInt($('vm-unidades').value, 10) || 1);
        const precioBase = Math.round(actual.precio_mayor * unidades * 100) / 100;
        $('vm-precio').placeholder = precioBase.toFixed(2);
        $('vm-precio-ayuda').textContent = 'Vacío = precio mayor de Suite' + (unidades > 1 ? ` × ${unidades} (${usd(precioBase)})` : ` (${usd(precioBase)})`);
        $('vm-costo').placeholder = Number(actual.costo_suite || 0).toFixed(2);
        $('vm-costo-ayuda').textContent = 'Vacío = costo de Suite (' + usd(actual.costo_suite) + ')';

        const precio = parseFloat($('vm-precio').value) || precioBase;
        const costoUnidad = $('vm-costo').value !== '' ? (parseFloat($('vm-costo').value) || 0) : Number(actual.costo_suite || 0);
        const costo = Math.round(costoUnidad * unidades * 100) / 100;
        const ganancia = Math.round((precio - costo) * 100) / 100;
        const pct = costo > 0 ? (ganancia / costo * 100) : null;
        $('vm-resultado').innerHTML = `Precio ${usd(precio)} · Costo ${usd(costo)} · `
            + `<strong style="color:${ganancia < 0 ? '#b91c1c' : '#15803d'};">Ganancia ${usd(ganancia)}${pct !== null ? ` (${pct >= 0 ? '+' : ''}${pct.toFixed(1)}%)` : ''}</strong>`
            + (unidades > 1 ? ` · ${usd(precio / unidades)} c/u` : '');
    }

    const puedeEditar = @json($puedeEditarCatalogo);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const num = n => Number(n || 0).toLocaleString('en-US');

    function pintarFicha(p) {
        const img = $('vm-f-img');
        img.style.display = 'none';
        if (p.imagen) {
            img.dataset.intento = 'jpg';
            img.onload = () => { img.style.display = ''; };
            img.onerror = () => {
                if (img.dataset.intento === 'jpg') { img.dataset.intento = 'png'; img.src = p.imagen + '.png'; }
                else { img.style.display = 'none'; }
            };
            img.src = p.imagen + '.jpg';
        }
        $('vm-f-nombre').textContent = p.nombre;
        $('vm-f-codigo').textContent = p.codigo || '';
        $('vm-f-tags').innerHTML = [p.categoria, p.subcategoria].filter(Boolean).map(t => `<span class="cat-tag">${esc(t)}</span>`).join('');
        $('vm-f-proveedor').textContent = p.proveedor ? 'Proveedor: ' + p.proveedor : '';
        const datos = [
            ['Precio detal', p.precio_unidad > 0 ? usd(p.precio_unidad) : '—'],
            ['Precio mayor Suite', p.precio_mayor > 0 ? usd(p.precio_mayor) : '—'],
            ['Costo Suite', p.costo_suite > 0 ? usd(p.costo_suite) : '—'],
            ['Última compra', p.ultima_compra ? `${num(p.ultima_compra.cantidad)} a ${usd(p.ultima_compra.costo)}` : '—'],
        ];
        $('vm-f-datos').innerHTML = datos.map(([t, v]) => `<div><span>${t}</span><strong>${esc(v)}</strong></div>`).join('');
        $('vm-f-total').textContent = '· ' + num(p.stock_total) + ' en total';
        $('vm-f-stock').innerHTML = Object.entries(p.stocks || {}).map(([sede, n]) =>
            `<span class="cat-sede ${n > 0 ? 'has-stock' : 'no-stock'} ${sede.includes('factura') ? 'cat-sede-jrz' : ''}"><span class="cat-sede-top">${esc(sede)} <b>${num(n)}</b></span></span>`).join('');
    }

    window.vmFicha = function (p) {
        actual = p;
        pintarFicha(p);
        $('vm-modal-form').action = p.url;
        $('vm-presentacion').value = p.presentacion;
        $('vm-unidades').value = p.unidades > 1 ? p.unidades : '';
        $('vm-precio').value = p.precio ?? '';
        $('vm-costo').value = p.costo ?? '';
        ['vm-presentacion', 'vm-unidades', 'vm-precio', 'vm-costo'].forEach(id => { $(id).disabled = !puedeEditar; });
        ['vm-precio-ayuda', 'vm-costo-ayuda'].forEach(id => { $(id).style.display = puedeEditar ? '' : 'none'; });
        calcular();
        $('vm-modal').classList.add('abierto');
        if (puedeEditar) $('vm-presentacion').focus();
    };
    window.vmCerrar = () => $('vm-modal').classList.remove('abierto');
    ['vm-presentacion', 'vm-unidades', 'vm-precio', 'vm-costo'].forEach(id => $(id).addEventListener('input', calcular));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') vmCerrar(); });
})();
</script>
@endpush

@push('head')
<style>
@include('partials.catalogo-tabla-estilos')
.vm-facturar {
    grid-column: 1 / -1;
    margin-top: 4px;
    display: block;
    text-align: center;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 0.82rem;
    font-weight: 700;
    text-decoration: none;
    background: #2563eb;
    color: #fff;
    border: 1px solid #2563eb;
}
.vm-facturar:hover { background: #1d4ed8; }
.vm-facturar-no { background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }
.vm-facturar-no:hover { background: #f1f5f9; }
.vm-acciones { grid-column: 1 / -1; display: grid; grid-template-columns: auto 1fr; gap: 8px; flex-direction: row !important; }
.vm-acciones .vm-facturar { margin-top: 0; grid-column: auto; }
.vm-editar { border: 1px solid #ddd6fe; background: #f5f3ff; color: #6d28d9; border-radius: 8px; padding: 8px 12px; font-size: 0.82rem; font-weight: 700; cursor: pointer; font-family: inherit; }
.vm-editar:hover { background: #ede9fe; }
.vm-pres { font-size: 0.95rem !important; color: #334155; }
.vm-pres-caja { color: #6d28d9; }
.vm-ganancia { color: #15803d !important; font-weight: 700; }
.vm-perdida { color: #b91c1c !important; font-weight: 700; }
.vm-modal { display: none; position: fixed; inset: 0; z-index: 1100; background: rgba(15,23,42,.55); align-items: center; justify-content: center; padding: 16px; }
.vm-modal.abierto { display: flex; }
.vm-modal { align-items: flex-start; overflow-y: auto; }
.vm-modal-box { position: relative; background: #fff; border-radius: 12px; padding: 20px 22px; width: 100%; max-width: 640px; margin: 3vh auto; box-shadow: 0 20px 40px rgba(15,23,42,.25); }
.vm-cerrar { position: absolute; top: 10px; right: 12px; border: 0; background: none; font-size: 1.5rem; line-height: 1; color: #94a3b8; cursor: pointer; }
.vm-ficha-top { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 14px; padding-right: 24px; }
.vm-ficha-top img { width: 96px; height: 96px; object-fit: contain; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; flex-shrink: 0; }
.vm-ficha-datos { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 14px; }
.vm-ficha-datos div { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
.vm-ficha-datos span { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; color: #94a3b8; }
.vm-ficha-datos strong { font-size: 0.92rem; color: #0f172a; }
.vm-ficha-sub { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; margin-bottom: 8px; }
.vm-ficha-stock { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.vm-campos input:disabled, .vm-campos select:disabled { background: #f1f5f9; color: #334155; }
@media (max-width: 600px) { .vm-ficha-datos, .vm-ficha-stock { grid-template-columns: repeat(2, 1fr); } }
.vm-campos { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.vm-campos label { display: flex; flex-direction: column; gap: 4px; font-size: 0.82rem; font-weight: 600; color: #334155; }
.vm-campos input, .vm-campos select { padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; font-family: inherit; }
.vm-campos small { font-weight: 400; font-size: 0.72rem; }
.vm-resultado { margin-top: 14px; padding: 10px 12px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.86rem; }
</style>
@endpush
