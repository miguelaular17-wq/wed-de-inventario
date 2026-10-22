@extends('layouts.app')

@section('title', 'Etiquetas de precio')

@section('content')
<div class="etq-page">
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="margin:0;">🏷️ Etiquetas de precio</h1>
            <p class="lead" style="margin:4px 0 0;">Escanea o busca los productos, arma la lista y genera las fichas para imprimir.</p>
        </div>
        <a href="{{ route('vendedor.dashboard') }}" class="btn secondary" style="padding:10px 14px; border-radius:8px; text-decoration:none;">← Volver al catálogo</a>
    </div>

    @if(session('error'))
        <div class="etq-alert">{{ session('error') }}</div>
    @endif

    <div class="etq-grid">
        {{-- Buscar / escanear --}}
        <section class="etq-panel">
            <h2 class="etq-title">1. Escanear o buscar</h2>
            <div class="etq-search">
                <span>🔍</span>
                <input type="search" id="etq-q" placeholder="Escanea el código de barras o escribe nombre / código…" autocomplete="off" autofocus>
            </div>
            <p class="etq-hint">Con el lector: apunta y dispara, el producto se agrega solo. Escribiendo: elige de la lista.</p>
            <div id="etq-flash" class="etq-flash" style="display:none;"></div>
            <div id="etq-resultados" class="etq-resultados">
                <div class="etq-empty">Los resultados aparecerán aquí.</div>
            </div>
        </section>

        {{-- Lista --}}
        <section class="etq-panel">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
                <h2 class="etq-title" style="margin:0;">2. Lista a imprimir <span id="etq-count" class="etq-badge">0</span></h2>
                <button type="button" class="btn secondary etq-btn-sm" onclick="etqVaciar()">Vaciar lista</button>
            </div>

            <div class="etq-lista-wrap">
                <table class="data-table etq-lista">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th style="text-align:right;">1 pza</th>
                            <th style="text-align:right;">Mayor</th>
                            <th style="width:80px; text-align:center;">Copias</th>
                            <th style="width:36px;"></th>
                        </tr>
                    </thead>
                    <tbody id="etq-lista-body"></tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('etiquetas.pdf') }}" target="_blank" id="etq-form" class="etq-opciones">
                @csrf
                <div id="etq-form-items"></div>
                <div class="etq-tipos">
                    <span class="etq-tipos-label">Tipo de etiqueta</span>
                    <div class="etq-tipos-opciones">
                        @foreach($tipos as $clave => $label)
                            <label class="etq-tipo">
                                <input type="radio" name="tipo" value="{{ $clave }}" @checked($loop->first)>
                                <span>{{ $label }}<small>{{ $tiposDescripcion[$clave] ?? '' }}</small></span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <label id="etq-cant-mayor-wrap">
                    Precio mayor desde
                    <span style="display:flex; align-items:center; gap:6px;">
                        <input type="number" name="cantidad_mayor" id="etq-cant-mayor" value="3" min="2" max="999" style="width:80px;">
                        <span style="font-weight:500; text-transform:none;">piezas</span>
                    </span>
                </label>
                <div class="etq-total">
                    <strong id="etq-total-etiquetas">0</strong> etiquetas · <span id="etq-total-hojas">0</span> hoja(s) carta
                    <div id="etq-tipo-nota" class="etq-hint" style="margin:4px 0 0;"></div>
                </div>
                <button type="submit" class="btn primary etq-generar" id="etq-generar" disabled>🖨️ Generar PDF para imprimir</button>
            </form>

            <h3 class="etq-subtitle">Vista previa</h3>
            <div id="etq-preview" class="etq-preview-wrap">
                <div class="etq-empty">Agrega un producto para ver cómo queda la ficha.</div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('head')
<style>
main:has(.etq-page) { max-width: none; width: 100%; padding: 20px 28px 40px; }
.etq-alert { margin: 12px 0; padding: 12px 16px; border-radius: 10px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.etq-grid { display: grid; grid-template-columns: minmax(320px, 1fr) minmax(420px, 1.25fr); gap: 20px; margin-top: 16px; align-items: start; }
.etq-panel { background: #fff; border: 1px solid var(--border); border-radius: 16px; box-shadow: var(--shadow); padding: 18px 20px; }
.etq-title { font-size: 1rem; margin: 0 0 12px; color: #0f172a; }
.etq-subtitle { font-size: 0.85rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin: 22px 0 10px; }
.etq-badge { display: inline-block; min-width: 24px; padding: 1px 8px; border-radius: 999px; background: #ede9fe; color: #6d28d9; font-size: .8rem; text-align: center; }
.etq-search { position: relative; }
.etq-search span { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #64748b; pointer-events: none; }
.etq-search input { width: 100%; padding: 14px 14px 14px 42px; border-radius: 10px; border: 2px solid #c4b5fd; font-size: 1.05rem; background: #fff; }
.etq-search input:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 4px rgba(124, 58, 237, .15); }
.etq-hint { font-size: .8rem; color: #64748b; margin: 8px 0 12px; }
.etq-flash { padding: 10px 12px; border-radius: 10px; font-size: .88rem; font-weight: 600; margin-bottom: 10px; }
.etq-flash.ok { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.etq-flash.warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.etq-resultados { display: flex; flex-direction: column; gap: 8px; max-height: 62vh; overflow-y: auto; }
.etq-res { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; }
.etq-res:hover { border-color: #c4b5fd; background: #faf5ff; }
.etq-res-name { font-weight: 650; color: #0f172a; font-size: .92rem; line-height: 1.3; }
.etq-res-meta { font-size: .76rem; color: #64748b; margin-top: 2px; }
.etq-res-meta code { color: #2563eb; background: #eff6ff; padding: 1px 6px; border-radius: 5px; }
.etq-btn-sm { padding: 6px 10px !important; font-size: .8rem !important; white-space: nowrap; }
.etq-empty { color: #94a3b8; font-size: .88rem; padding: 18px; text-align: center; border: 1px dashed #e2e8f0; border-radius: 10px; }
.etq-lista-wrap { max-height: 40vh; overflow-y: auto; margin-top: 12px; border: 1px solid #e2e8f0; border-radius: 10px; }
.etq-lista { width: 100%; font-size: .85rem; margin: 0; }
.etq-lista td { vertical-align: middle; }
.etq-lista input[type=number] { width: 64px; padding: 6px; border-radius: 8px; border: 1px solid var(--border); text-align: center; }
.etq-quitar { border: none; background: none; color: #dc2626; font-size: 1rem; cursor: pointer; }
.etq-row-new { animation: etqFlash 1.2s ease; }
@keyframes etqFlash { from { background: #ede9fe; } to { background: transparent; } }
.etq-opciones { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; margin-top: 16px; }
.etq-opciones label { display: flex; flex-direction: column; gap: 4px; font-size: .75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }
.etq-opciones select, .etq-opciones input { padding: 9px 10px; border-radius: 8px; border: 1.5px solid var(--border); font-size: .9rem; }
.etq-total { font-size: .88rem; color: #475569; padding-bottom: 10px; }
.etq-tipos { width: 100%; display: flex; flex-direction: column; gap: 6px; }
.etq-tipos-label { font-size: .75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }
.etq-tipos-opciones { display: flex; gap: 8px; flex-wrap: wrap; }
.etq-opciones label.etq-tipo { flex-direction: row; align-items: center; gap: 8px; cursor: pointer; padding: 9px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px;
    font-size: .85rem; font-weight: 600; color: #334155; text-transform: none; letter-spacing: 0; background: #fff; }
.etq-opciones label.etq-tipo:has(input:checked) { border-color: #7c3aed; background: #f5f3ff; color: #5b21b6; }
.etq-tipo input { margin: 0; padding: 0; }
.etq-tipo small { display: block; font-size: .72rem; font-weight: 500; color: #64748b; }
.etq-specs-row td { background: #faf5ff; padding-top: 4px !important; }
.etq-specs { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 6px 8px; }
.etq-specs label { display: flex; flex-direction: column; gap: 2px; font-size: .68rem; font-weight: 700; color: #7c3aed; text-transform: uppercase; }
.etq-specs input { padding: 5px 7px; border-radius: 6px; border: 1px solid #ddd6fe; font-size: .8rem; font-weight: 500; color: #0f172a; text-transform: none; }

/* Vista previa: Precios de locura */
.etq-lz { width: 280px; height: 200px; border-radius: 14px; padding: 9px; box-sizing: border-box; display: flex; gap: 7px;
    background: linear-gradient(135deg, #ec4899 0%, #9333ea 50%, #3b82f6 100%); font-family: inherit; }
.etq-lz-izq { width: 108px; flex: none; display: flex; flex-direction: column; align-items: center; gap: 4px; color: #fff; }
.etq-lz-izq img { width: 48px; height: 48px; }
.etq-lz-de { font-weight: 800; font-size: 12px; letter-spacing: .03em; white-space: nowrap; }
.etq-lz-loc { width: 100%; text-align: center; background: #3b0764; color: #facc15; font-weight: 900; font-size: 21px; border-radius: 7px; border-bottom: 3px solid #facc15; line-height: 1.3; }
.etq-lz-cinta { width: 92%; text-align: center; background: #facc15; color: #9d174d; font-weight: 800; font-size: 7px; border-radius: 4px; padding: 2px 4px; line-height: 1.3; }
.etq-lz.con-marca .etq-lz-izq { gap: 3px; }
.etq-lz.con-marca .etq-lz-izq > img { width: 34px; height: 34px; }
.etq-lz.con-marca .etq-lz-de { font-size: 11px; }
.etq-lz.con-marca .etq-lz-loc { font-size: 18px; }
.etq-lz-marca { width: 94%; height: 30px; background: #fff; border-radius: 7px; display: flex; align-items: center; justify-content: center; margin-top: 2px; }
.etq-lz-marca img { max-height: 24px; max-width: 88%; object-fit: contain; }
.etq-lz-marca b { color: #1e1b4b; font-size: 14px; font-weight: 900; letter-spacing: .08em; }
.etq-lz-cod { margin-top: auto; font-size: 7.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.etq-lz-der { flex: 1; background: #fff; border-radius: 10px; padding: 8px 10px; display: flex; flex-direction: column; color: #0f172a; min-width: 0; }
.etq-lz-pill { align-self: flex-start; background: #7c3aed; color: #fff; font-weight: 800; font-size: 9px; letter-spacing: .1em; padding: 2px 12px; border-radius: 999px; }
.etq-lz-precio { display: flex; align-items: flex-start; justify-content: center; gap: 4px; margin-top: 2px; }
.etq-lz-precio small { font-size: 9px; font-weight: 800; color: #64748b; margin-top: 6px; }
.etq-lz-precio strong { font-size: 30px; font-weight: 900; line-height: 1.1; }
.etq-lz-mayor { text-align: center; font-size: 9px; font-weight: 800; color: #7c3aed; }
.etq-lz-nombre { border-top: 1px dashed #c4b5fd; margin-top: 4px; padding-top: 5px; font-weight: 800; font-size: 11px; text-transform: uppercase; line-height: 1.2;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.etq-lz-web { margin-top: auto; text-align: center; font-size: 7px; font-weight: 700; color: #7c3aed; }

/* Vista previa: PC */
.etq-pc { width: 270px; height: 315px; border-radius: 14px; padding: 9px; box-sizing: border-box; display: flex; flex-direction: column; gap: 7px;
    background: linear-gradient(135deg, #ec4899 0%, #9333ea 50%, #3b82f6 100%); font-family: inherit; }
.etq-pc-izq { display: flex; flex-direction: column; gap: 0; color: #fff; }
.etq-pc-caja { position: relative; background: #4c1d95; border-radius: 10px; padding: 8px 14px 14px; }
.etq-pc-caja img { position: absolute; right: 10px; top: 7px; width: 50px; height: 50px; }
.etq-pc-caja b { display: block; font-size: 21px; font-weight: 900; line-height: 1.1; text-align: center; }
.etq-pc-caja b + b { color: #facc15; font-size: 23px; margin-top: 2px; }
.etq-pc-pill { margin-top: -8px; background: #db2777; border: 2px solid #fff; border-radius: 12px; padding: 4px 8px; position: relative; text-align: center; }
.etq-pc-pill small { position: absolute; left: 12px; top: 7px; font-size: 9px; font-weight: 800; color: #fde68a; }
.etq-pc-pill strong { font-size: 44px; font-weight: 900; line-height: 1.15; }
.etq-pc-pie { display: none; }
.etq-pc-der { flex: 1; background: #fff; border-radius: 10px; padding: 8px 10px; display: flex; flex-direction: column; color: #0f172a; min-width: 0; }
.etq-pc-marca { font-size: 22px; font-weight: 900; letter-spacing: .06em; line-height: 1.1; }
.etq-pc-cod { font-size: 7.5px; font-weight: 700; color: #64748b; margin: 2px 0 6px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.etq-pc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px 8px; }
.etq-pc-spec { display: flex; align-items: center; gap: 5px; font-size: 9.5px; font-weight: 800; line-height: 1.15; min-width: 0; }
.etq-pc-spec i { flex-shrink: 0; width: 17px; height: 17px; border-radius: 50%; background: #db2777; color: #fff; font-style: normal; font-size: 10px; display: flex; align-items: center; justify-content: center; }
.etq-pc-web { margin-top: auto; text-align: center; font-size: 7.5px; font-weight: 700; color: #7c3aed; }
.etq-generar { padding: 11px 18px !important; font-weight: 700; margin-left: auto; }
.etq-generar:disabled { opacity: .5; cursor: not-allowed; }

/* Vista previa (misma ficha que el PDF) */
.etq-preview-wrap { display: flex; justify-content: center; padding: 16px; background: #f1f5f9; border-radius: 12px; }
.etq-card { width: 280px; height: 200px; border-radius: 14px; padding: 10px 12px; box-sizing: border-box; color: #fff;
    background: linear-gradient(135deg, #ec4899 0%, #9333ea 50%, #3b82f6 100%); display: flex; flex-direction: column; gap: 7px; font-family: inherit; }
.etq-card-top { display: flex; gap: 10px; align-items: stretch; height: 64px; }
.etq-card-logo { width: 64px; height: 64px; border-radius: 50%; background: #fff; padding: 2px; box-sizing: border-box; flex-shrink: 0; }
.etq-card-name { flex: 1; background: #fff; color: #0f172a; border-radius: 10px; padding: 6px 10px; display: flex; flex-direction: column; justify-content: center; overflow: hidden; }
.etq-card-name small { font-size: 8px; letter-spacing: .12em; color: #7c3aed; font-weight: 800; }
.etq-card-name b { font-size: 13px; line-height: 1.15; text-transform: uppercase; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.etq-card-prices { display: flex; gap: 8px; flex: 1; }
.etq-card-box { flex: 1; background: #fff; color: #0f172a; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; }
.etq-card-box .h { background: #1e1b4b; color: #fff; text-align: center; font-weight: 800; font-size: 11px; padding: 3px 0; letter-spacing: .03em; }
.etq-card-box .h em { font-style: normal; color: #fde047; }
.etq-card-box.mayor { border: 3px solid #facc15; }
.etq-card-box.mayor .h { background: #facc15; color: #1e1b4b; }
.etq-card-box.mayor .h em { color: #9d174d; }
.etq-card-box .p { flex: 1; display: flex; align-items: center; justify-content: center; gap: 4px; }
.etq-card-box .p small { font-size: 10px; font-weight: 800; color: #64748b; align-self: flex-start; margin-top: 10px; }
.etq-card-box .p strong { font-size: 32px; font-weight: 900; letter-spacing: -.02em; }
.etq-card-box .ahorro { text-align: center; font-size: 9px; font-weight: 800; color: #be185d; padding-bottom: 3px; }
.etq-card-foot { display: flex; justify-content: space-between; font-size: 9px; opacity: .95; font-weight: 600; }

@media (max-width: 1000px) { .etq-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const STORAGE_KEY = 'etiquetas_lista_v1';
    const BUSCAR_URL = @json(route('etiquetas.buscar'));
    const LOGO = @json(asset('logo.png'));
    const POR_HOJA = @json(collect(\App\Services\EtiquetasPrecioService::TAMANOS)->map(fn ($t) => $t['columnas'] * $t['filas']));
    const MEDIDA = @json($tiposDescripcion);
    const MAX = {{ (int) $maxEtiquetas }};
    const TIPO_KEY = 'etiquetas_tipo_v1';
    const WEB = @json(\App\Services\EtiquetasPrecioService::WEB);
    const SPECS = @json($specsPc);
    const SPEC_LABEL = { marca: 'Marca', modelo: 'Modelo', procesador: 'Procesador', grafica: 'Tarjeta gráfica', pantalla: 'Pantalla', ram: 'RAM', almacenamiento: 'Almacenamiento', sistema: 'Sistema' };
    const SPEC_ICONO = { modelo: '◆', procesador: '⚙', grafica: '▦', pantalla: '▭', ram: '▤', almacenamiento: '◉', sistema: '⊞' };
    const LOGOS = @json(collect(\App\Services\EtiquetasPrecioService::LOGOS_MARCA)->filter(fn ($m) => is_file(public_path("img/marcas/{$m}.png")))->mapWithKeys(fn ($m) => [$m => asset("img/marcas/{$m}.png")]));
    function logoKey(p) {
        const s = p.specs || {};
        const marca = (s.marca || '').trim().toUpperCase();
        const texto = `${marca} ${s.modelo || ''} ${p.nombre}`.toUpperCase();
        let k = null;
        if (texto.includes('VICTUS')) k = 'victus';
        else if (/\bPOCO\b/.test(texto)) k = 'poco';
        else if (marca === 'REDMI') k = 'xiaomi';
        else if (marca === 'APPLE' || (marca === '' && /\b(APPLE|MACBOOK|IMAC)\b/.test(texto))) k = 'apple';
        else if (marca) k = marca.toLowerCase();
        return k && LOGOS[k] ? k : null;
    }
    const FECHA = @json(now()->format('d/m/Y'));

    let lista = [];
    try { lista = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]') || []; } catch (e) { lista = []; }
    const tipoGuardado = localStorage.getItem(TIPO_KEY);
    if (tipoGuardado) {
        const radio = document.querySelector(`input[name="tipo"][value="${tipoGuardado}"]`);
        if (radio) radio.checked = true;
    }
    const tipoActual = () => (document.querySelector('input[name="tipo"]:checked') || {}).value || 'clasica';

    const $q = document.getElementById('etq-q');
    const $res = document.getElementById('etq-resultados');
    const $flash = document.getElementById('etq-flash');
    const $body = document.getElementById('etq-lista-body');
    let debounce = null;
    let ultimoAgregado = null;

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const ceilPrecio = v => { const n = Number(v); return n > 0 ? Math.ceil(Math.round(n * 100) / 100) : 0; };
    const fmt = v => { const n = ceilPrecio(v); return n > 0 ? n.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'; };
    const tieneMayor = p => {
        const u = ceilPrecio(p.precio_unidad);
        const m = ceilPrecio(p.precio_mayor);
        return m > 0 && (u <= 0 || m < u);
    };

    function guardar() { localStorage.setItem(STORAGE_KEY, JSON.stringify(lista)); render(); }

    function flash(msg, tipo) {
        $flash.className = 'etq-flash ' + (tipo || 'ok');
        $flash.textContent = msg;
        $flash.style.display = 'block';
        clearTimeout(flash._t);
        flash._t = setTimeout(() => { $flash.style.display = 'none'; }, 3500);
    }

    function beep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.frequency.value = 880; g.gain.value = 0.08;
            o.connect(g); g.connect(ctx.destination);
            o.start(); o.stop(ctx.currentTime + 0.09);
        } catch (e) {}
    }

    function agregar(p) {
        const existente = lista.find(x => x.id === p.id);
        if (existente) {
            existente.copias = Math.min(100, (existente.copias || 1) + 1);
            flash(`+1 copia: ${p.nombre} (${existente.copias})`);
        } else {
            lista.push({ id: p.id, codigo: p.codigo, nombre: p.nombre, precio_unidad: p.precio_unidad, precio_mayor: p.precio_mayor, specs: p.specs || null, copias: 1 });
            flash(`Agregado: ${p.nombre}`);
        }
        ultimoAgregado = p.id;
        beep();
        guardar();
    }

    async function buscar(q, desdeEnter) {
        if (q.length < 2 && !desdeEnter) return;
        try {
            const r = await fetch(BUSCAR_URL + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
            if (!r.ok) throw new Error(r.status);
            const data = await r.json();
            if (desdeEnter && data.exacto) {
                agregar(data.exacto);
                $q.value = '';
                $res.innerHTML = '<div class="etq-empty">Listo para el siguiente producto.</div>';
                return;
            }
            pintarResultados(data.resultados || [], q);
            if (desdeEnter && (data.resultados || []).length === 0) {
                flash(`No se encontró "${q}".`, 'warn');
                $q.select();
            }
        } catch (e) {
            flash('Error buscando el producto.', 'warn');
        }
    }

    function pintarResultados(items, q) {
        if (items.length === 0) {
            $res.innerHTML = `<div class="etq-empty">Sin resultados para "${esc(q)}".</div>`;
            return;
        }
        $res.innerHTML = items.map(p => `
            <div class="etq-res">
                <div>
                    <div class="etq-res-name">${esc(p.nombre)}</div>
                    <div class="etq-res-meta"><code>${esc(p.codigo)}</code> · 1 pza <b>REF ${fmt(p.precio_unidad)}</b>${tieneMayor(p) ? ` · mayor <b>REF ${fmt(p.precio_mayor)}</b>` : ''}</div>
                </div>
                <button type="button" class="btn primary etq-btn-sm" data-add="${p.id}">➕ Agregar</button>
            </div>`).join('');
        $res.querySelectorAll('[data-add]').forEach(btn => {
            const p = items.find(x => x.id === Number(btn.dataset.add));
            btn.addEventListener('click', () => { agregar(p); $q.focus(); });
        });
    }

    // Productos guardados antes de existir la ficha técnica: se piden al buscador por código.
    async function completarSpecs() {
        const faltan = lista.filter(p => !p.specs);
        for (const p of faltan) {
            try {
                const r = await fetch(BUSCAR_URL + '?q=' + encodeURIComponent(p.codigo.split('/')[0].trim()), { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                const hit = (data.resultados || []).find(x => x.id === p.id);
                p.specs = hit && hit.specs ? hit.specs : Object.fromEntries(SPECS.map(s => [s, '']));
            } catch (e) {
                p.specs = Object.fromEntries(SPECS.map(s => [s, '']));
            }
        }
        if (faltan.length) guardar();
    }

    function filaSpecs(p, i) {
        const s = p.specs || {};
        return `<tr class="etq-specs-row"><td colspan="5"><div class="etq-specs">${SPECS.map(campo => `
            <label>${SPEC_LABEL[campo]}<input type="text" maxlength="40" value="${esc(s[campo] || '')}" data-spec="${i}" data-campo="${campo}"></label>`).join('')}
            <button type="button" class="btn secondary etq-btn-sm" data-releer="${i}" style="align-self:end;" title="Volver a leer la ficha técnica del nombre del producto">↻ Releer del nombre</button>
        </div></td></tr>`;
    }

    function render() {
        const tipo = tipoActual();
        const esPc = tipo === 'pc';
        document.getElementById('etq-count').textContent = lista.length;
        document.getElementById('etq-cant-mayor-wrap').style.display = esPc ? 'none' : '';
        document.getElementById('etq-tipo-nota').textContent = 'Medida: ' + (MEDIDA[tipo] || '');
        if (lista.length === 0) {
            $body.innerHTML = '<tr><td colspan="5"><div class="etq-empty" style="border:none;">La lista está vacía.</div></td></tr>';
        } else {
            $body.innerHTML = lista.map((p, i) => `
                <tr class="${p.id === ultimoAgregado ? 'etq-row-new' : ''}">
                    <td><div style="font-weight:600;">${esc(p.nombre)}</div><div style="font-size:.74rem; color:#64748b;">${esc(p.codigo)}</div></td>
                    <td style="text-align:right; font-weight:600; color:#16a34a;">${fmt(p.precio_unidad)}</td>
                    <td style="text-align:right; font-weight:600; color:#7c3aed;">${tieneMayor(p) ? fmt(p.precio_mayor) : (Number(p.precio_mayor) > 0 ? `<span style="color:#94a3b8; font-weight:500;" title="Mismo precio que 1 pza: la etiqueta lleva un solo precio">igual · ${fmt(p.precio_mayor)}</span>` : '—')}</td>
                    <td style="text-align:center;"><input type="number" min="1" max="100" value="${p.copias}" data-copias="${i}"></td>
                    <td><button type="button" class="etq-quitar" title="Quitar" data-quitar="${i}">✕</button></td>
                </tr>${esPc ? filaSpecs(p, i) : ''}`).join('');
            $body.querySelectorAll('[data-releer]').forEach(btn => btn.addEventListener('click', () => {
                const item = lista[Number(btn.dataset.releer)];
                item.specs = null;
                ultimoAgregado = item.id;
                completarSpecs();
            }));
            $body.querySelectorAll('[data-spec]').forEach(inp => inp.addEventListener('input', () => {
                const item = lista[Number(inp.dataset.spec)];
                item.specs = item.specs || {};
                item.specs[inp.dataset.campo] = inp.value;
                ultimoAgregado = item.id;
                localStorage.setItem(STORAGE_KEY, JSON.stringify(lista));
                pintarFormItems();
                pintarPreview();
            }));
            $body.querySelectorAll('[data-copias]').forEach(inp => inp.addEventListener('change', () => {
                const v = Math.max(1, Math.min(100, parseInt(inp.value, 10) || 1));
                lista[Number(inp.dataset.copias)].copias = v;
                guardar();
            }));
            $body.querySelectorAll('[data-quitar]').forEach(btn => btn.addEventListener('click', () => {
                lista.splice(Number(btn.dataset.quitar), 1);
                guardar();
            }));
        }

        const total = Math.min(MAX, lista.reduce((s, p) => s + (p.copias || 1), 0));
        document.getElementById('etq-total-etiquetas').textContent = total;
        document.getElementById('etq-total-hojas').textContent = Math.ceil(total / (POR_HOJA[tipo] || 10));
        document.getElementById('etq-generar').disabled = lista.length === 0;

        pintarFormItems();
        pintarPreview();
    }

    function pintarFormItems() {
        const esPc = tipoActual() === 'pc';
        document.getElementById('etq-form-items').innerHTML = lista.map((p, i) =>
            `<input type="hidden" name="items[${i}][id]" value="${p.id}"><input type="hidden" name="items[${i}][copias]" value="${p.copias}">`
            + (esPc && p.specs ? SPECS.map(c => `<input type="hidden" name="items[${i}][specs][${c}]" value="${esc(p.specs[c] || '')}">`).join('') : '')
        ).join('');
    }

    function previewLocura(p) {
        const cant = Math.max(2, parseInt(document.getElementById('etq-cant-mayor').value, 10) || 3);
        const marca = ((p.specs || {}).marca || '').trim();
        const lk = logoKey(p);
        const caja = lk
            ? `<div class="etq-lz-marca"><img src="${LOGOS[lk]}" alt="${esc(marca)}"></div>`
            : (marca ? `<div class="etq-lz-marca"><b>${esc(marca.toUpperCase())}</b></div>` : '');
        return `
            <div class="etq-lz ${caja ? 'con-marca' : ''}">
                <div class="etq-lz-izq">
                    <img src="${LOGO}" alt="">
                    <div class="etq-lz-de">PRECIOS DE</div>
                    <div class="etq-lz-loc">LOCURA</div>
                    <div class="etq-lz-cinta">PRECIOS BAJOS TODO EL AÑO</div>
                    ${caja}
                    <div class="etq-lz-cod">Cód. ${esc(p.codigo)}</div>
                </div>
                <div class="etq-lz-der">
                    <span class="etq-lz-pill">PRECIO</span>
                    <div class="etq-lz-precio"><small>REF</small><strong>${fmt(p.precio_unidad)}</strong></div>
                    ${tieneMayor(p) ? `<div class="etq-lz-mayor">${cant}+ PZAS: REF ${fmt(p.precio_mayor)} C/U</div>` : ''}
                    <div class="etq-lz-nombre">${esc(p.nombre)}</div>
                    <div class="etq-lz-web">${esc(WEB)} · ${FECHA}</div>
                </div>
            </div>`;
    }

    function previewPc(p) {
        const s = p.specs || {};
        const marca = (s.marca || '').trim() || (p.nombre.split(' ')[0] || '').toUpperCase();
        const entero = ceilPrecio(p.precio_unidad);
        const lleno = c => (s[c] || '').trim() !== '';
        const izq = ['modelo', 'procesador', 'grafica', 'pantalla'].filter(lleno);
        const der3 = ['ram', 'almacenamiento', 'sistema'].filter(lleno);
        const spec = c => c ? `<div class="etq-pc-spec"><i>${SPEC_ICONO[c]}</i><span>${esc(s[c])}</span></div>` : '<div></div>';
        let specs = '';
        for (let f = 0; f < Math.max(izq.length, der3.length); f++) specs += spec(izq[f]) + spec(der3[f]);
        const lk = logoKey(p);
        return `
            <div class="etq-pc">
                <div class="etq-pc-izq">
                    <div class="etq-pc-caja"><img src="${LOGO}" alt=""><b>PRECIO</b><b>REGULAR</b></div>
                    <div class="etq-pc-pill"><small>REF</small><strong>${entero > 0 ? '$' + entero.toLocaleString('es-VE') : '—'}</strong></div>
                    <div class="etq-pc-pie">Precios en REF<br><span style="font-weight:500;">${FECHA}</span></div>
                </div>
                <div class="etq-pc-der">
                    ${lk ? `<img src="${LOGOS[lk]}" alt="${esc(marca)}" style="height:${['dell', 'apple', 'victus', 'hp', 'xiaomi', 'poco'].includes(lk) ? 30 : 24}px; max-width:120px; object-fit:contain; align-self:center;">` : `<div class="etq-pc-marca" style="text-align:center;">${esc(marca)}</div>`}
                    <div class="etq-pc-cod">COD: ${esc(p.codigo)}</div>
                    <div class="etq-pc-grid">${specs || '<span style="font-size:10px; color:#94a3b8;">Completa la ficha técnica en la lista.</span>'}</div>
                    <div class="etq-pc-web">${esc(WEB)}</div>
                </div>
            </div>`;
    }

    function pintarPreview() {
        const cont = document.getElementById('etq-preview');
        const p = lista.find(x => x.id === ultimoAgregado) || lista[lista.length - 1];
        if (!p) {
            cont.innerHTML = '<div class="etq-empty">Agrega un producto para ver cómo queda la ficha.</div>';
            return;
        }
        const tipo = tipoActual();
        if (tipo === 'locura') { cont.innerHTML = previewLocura(p); return; }
        if (tipo === 'pc') { cont.innerHTML = previewPc(p); return; }
        const cant = Math.max(2, parseInt(document.getElementById('etq-cant-mayor').value, 10) || 3);
        const unidad = Number(p.precio_unidad);
        const mayor = Number(p.precio_mayor);
        const ahorro = tieneMayor(p) && unidad > 0 ? Math.round((1 - mayor / unidad) * 100) : 0;
        const precios = tieneMayor(p)
            ? `<div class="etq-card-box"><div class="h">COMPRA <em>1 PZA</em></div><div class="p"><small>REF</small><strong>${fmt(unidad)}</strong></div></div>
               <div class="etq-card-box mayor"><div class="h">COMPRA <em>${cant}+ PZAS</em></div><div class="p"><small>REF</small><strong>${fmt(mayor)}</strong></div>${ahorro > 0 ? `<div class="ahorro">¡AHORRAS ${ahorro}% C/U!</div>` : ''}</div>`
            : `<div class="etq-card-box"><div class="h">PRECIO</div><div class="p"><small>REF</small><strong>${fmt(unidad)}</strong></div></div>`;
        cont.innerHTML = `
            <div class="etq-card">
                <div class="etq-card-top">
                    <img class="etq-card-logo" src="${LOGO}" alt="">
                    <div class="etq-card-name"><small>PRODUCTO</small><b style="font-size:${p.nombre.length <= 26 ? 15 : (p.nombre.length <= 46 ? 13 : (p.nombre.length <= 75 ? 11 : 9.5))}px;">${esc(p.nombre)}</b></div>
                </div>
                <div class="etq-card-prices">${precios}</div>
                <div class="etq-card-foot"><span>Cód. ${esc(p.codigo)}</span><span>Precios en REF · {{ now()->format('d/m/Y') }}</span></div>
            </div>`;
    }

    window.etqVaciar = function () {
        if (lista.length && confirm('¿Vaciar toda la lista de etiquetas?')) {
            lista = [];
            ultimoAgregado = null;
            guardar();
        }
    };

    $q.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(debounce);
            const q = $q.value.trim();
            if (q) buscar(q, true);
        }
    });
    $q.addEventListener('input', () => {
        clearTimeout(debounce);
        const q = $q.value.trim();
        debounce = setTimeout(() => buscar(q, false), 350);
    });
    document.getElementById('etq-cant-mayor').addEventListener('input', pintarPreview);
    document.querySelectorAll('input[name="tipo"]').forEach(r => r.addEventListener('change', () => {
        localStorage.setItem(TIPO_KEY, tipoActual());
        if (tipoActual() !== 'clasica') completarSpecs();
        render();
    }));

    render();
    if (tipoActual() !== 'clasica') completarSpecs();
})();
</script>
@endpush
