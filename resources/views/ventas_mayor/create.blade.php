@extends('layouts.app')

@section('title', 'Ventas al mayor · Nueva factura')

@section('content')
@php $formas = ['Transferencia', 'Pago móvil', 'Zelle', 'Efectivo $', 'Efectivo Bs', 'Binance', 'Punto de venta']; @endphp
<style>
    .vm-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 16px; align-items: start; }
    @media (max-width: 1100px) { .vm-grid { grid-template-columns: 1fr; } .vm-lado { position: static !important; } }
    .vm-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
    .vm-card h3 { margin: 0 0 12px; font-size: .95rem; color: #1e3a8a; display: flex; align-items: center; gap: 8px; }
    .vm-paso { display: inline-flex; width: 22px; height: 22px; border-radius: 50%; background: #1e3a8a; color: #fff; font-size: .75rem; align-items: center; justify-content: center; }
    .vm-row { display: flex; gap: 10px; flex-wrap: wrap; }
    .vm-row > div { flex: 1; min-width: 150px; }
    .vm-card label { display: block; font-size: .78rem; font-weight: 600; margin-bottom: 3px; color: #334155; }
    .vm-card input, .vm-card select, .vm-card textarea { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 7px; box-sizing: border-box; font-size: .88rem; }
    .vm-buscador { position: relative; }
    .vm-resultados { position: absolute; z-index: 30; left: 0; right: 0; top: 100%; margin-top: 4px; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; max-height: 360px; overflow: auto; display: none; box-shadow: 0 12px 28px rgba(15, 23, 42, .18); }
    .vm-op { display: flex; justify-content: space-between; align-items: center; gap: 10px; width: 100%; text-align: left; padding: 9px 12px; border: 0; border-bottom: 1px solid #f1f5f9; background: #fff; cursor: pointer; font-size: .84rem; }
    .vm-op:hover, .vm-op.activa { background: #eff6ff; }
    .vm-op[disabled] { cursor: not-allowed; background: #fafafa; color: #94a3b8; }
    .vm-agregar { flex-shrink: 0; background: #2563eb; color: #fff; border-radius: 6px; padding: 4px 10px; font-weight: 700; font-size: .78rem; }
    .vm-op[disabled] .vm-agregar { background: #e2e8f0; color: #94a3b8; }
    .vm-chip { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: .72rem; font-weight: 700; }
    .vm-num { text-align: right; font-variant-numeric: tabular-nums; }
    .vm-items td { vertical-align: middle; }
    .vm-items input { padding: 6px 8px; text-align: right; }
    .vm-lado { position: sticky; top: 12px; }
    .vm-total { background: #1e3a8a; color: #fff; border-radius: 12px; padding: 14px 16px; }
    .vm-total .fila { display: flex; justify-content: space-between; font-size: .85rem; padding: 2px 0; }
    .vm-pills { display: flex; gap: 6px; }
    .vm-pills label { flex: 1; margin: 0; }
    .vm-pills input { display: none; }
    .vm-pills span { display: block; text-align: center; padding: 9px; border: 1.5px solid #cbd5e1; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: .85rem; color: #475569; }
    .vm-pills input:checked + span { border-color: #1e3a8a; background: #eff6ff; color: #1e3a8a; }
</style>
<div class="panel">
    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:14px;">
        <div>
            <h1 style="margin:0;">Nueva factura al mayor</h1>
            <p class="muted" style="margin:4px 0 0;">Las cantidades salen del stock de <strong>JRZ</strong>: no se puede facturar más de lo disponible.</p>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('ventas_mayor.stock') }}" class="btn secondary">Stock</a>
            <a href="{{ route('ventas_mayor.facturas') }}" class="btn secondary">Facturas</a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert error" style="margin-bottom:12px;">⚠️ {{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('ventas_mayor.store') }}" enctype="multipart/form-data" id="vm-form">
        @csrf
        <div class="vm-grid">
            <div>
                {{-- 1. CLIENTE --}}
                <div class="vm-card">
                    <h3><span class="vm-paso">1</span> Cliente</h3>
                    <input type="hidden" name="cliente_id" id="vm-cliente-id" value="{{ old('cliente_id', $clienteInicial?->id) }}">
                    <input type="hidden" name="cliente_etiqueta" id="vm-cliente-etiqueta" value="{{ old('cliente_etiqueta', $clienteInicial ? $clienteInicial->nombre.' · '.$clienteInicial->documento : '') }}">
                    <div id="vm-cliente-elegido" style="display:none; padding:10px 12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; justify-content:space-between; align-items:center; gap:10px;">
                        <div>✅ <strong id="vm-cliente-texto"></strong></div>
                        <button type="button" class="btn secondary" style="padding:3px 10px; font-size:.78rem;" onclick="vmQuitarCliente()">Cambiar</button>
                    </div>
                    <div id="vm-cliente-buscar" class="vm-row" style="align-items:flex-end;">
                        <div class="vm-buscador" style="flex:3;">
                            <label>Buscar cliente (nombre o RIF/cédula)</label>
                            <input type="search" id="vm-cliente-q" placeholder="Ej. Comercial Los Andes / J-40123456" autocomplete="off">
                            <div class="vm-resultados" id="vm-clientes"></div>
                        </div>
                        <div style="flex:1; min-width:180px;">
                            <button type="button" class="btn secondary" style="width:100%; padding:8px;" onclick="vmNuevoCliente(true)">+ Cliente nuevo</button>
                        </div>
                    </div>
                    <div id="vm-cliente-nuevo" style="display:none; margin-top:10px; padding:12px; background:#f8fafc; border:1px dashed #94a3b8; border-radius:8px;">
                        <div class="vm-row">
                            <div><label>RIF / cédula *</label><input type="text" name="cliente_documento" id="vm-c-doc" maxlength="40" value="{{ old('cliente_documento') }}" placeholder="J-40123456-7"></div>
                            <div style="flex:2;"><label>Nombre o razón social *</label><input type="text" name="cliente_nombre" id="vm-c-nombre" maxlength="200" value="{{ old('cliente_nombre') }}"></div>
                        </div>
                        <div class="vm-row" style="margin-top:8px;">
                            <div><label>Teléfono</label><input type="text" name="cliente_telefono" maxlength="60" value="{{ old('cliente_telefono') }}"></div>
                            <div><label>Correo</label><input type="email" name="cliente_email" maxlength="120" value="{{ old('cliente_email') }}"></div>
                            <div style="flex:2;"><label>Dirección</label><input type="text" name="cliente_direccion" maxlength="300" value="{{ old('cliente_direccion') }}"></div>
                        </div>
                        <button type="button" class="btn secondary" style="margin-top:8px; padding:3px 10px; font-size:.78rem;" onclick="vmNuevoCliente(false)">Cancelar, buscar uno existente</button>
                    </div>
                </div>

                {{-- 2. PRODUCTOS --}}
                <div class="vm-card">
                    <h3><span class="vm-paso">2</span> Productos y cantidades</h3>
                    <div class="vm-buscador">
                        <label>Buscar producto (nombre o código) · <span class="muted" style="font-weight:400;">Enter agrega el primero disponible</span></label>
                        <input type="search" id="vm-prod-q" placeholder="Ej. maxglow, parlante, 6820220421300…" autocomplete="off">
                        <div class="vm-resultados" id="vm-productos"></div>
                    </div>

                    <div class="table-wrap" style="margin-top:12px;">
                        <table class="data-table vm-items" style="font-size:.84rem;">
                            <thead><tr><th>Producto</th><th class="vm-num">Disp. JRZ</th><th class="vm-num" style="width:100px;">Cantidad</th><th class="vm-num" style="width:120px;">Precio $</th><th class="vm-num" style="width:110px;">Total $</th><th style="width:40px;"></th></tr></thead>
                            <tbody id="vm-items"><tr id="vm-sin-items"><td colspan="6" class="muted" style="text-align:center; padding:22px;">Busca un producto arriba y haz clic en <strong>Agregar</strong>.</td></tr></tbody>
                        </table>
                    </div>
                </div>

                <div class="vm-card">
                    <label>Observaciones</label>
                    <textarea name="observaciones" rows="2" maxlength="1000" placeholder="Opcional">{{ old('observaciones') }}</textarea>
                </div>
            </div>

            {{-- LADO: datos, pago y total --}}
            <div class="vm-lado">
                <div class="vm-card">
                    <h3><span class="vm-paso">3</span> Datos</h3>
                    <div style="margin-bottom:8px;">
                        <label>Vendedora *</label>
                        <select name="vendedor_empleado_id" required>
                            <option value="">-- Elige la vendedora --</option>
                            @foreach($vendedoras as $v)
                                <option value="{{ $v->id }}" @selected((int) old('vendedor_empleado_id') === $v->id)>{{ $v->nombre() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="vm-row">
                        <div><label>Fecha</label><input type="date" name="fecha" id="vm-fecha" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
                        <div><label>Tasa BCV</label><input type="number" step="0.0001" min="0.0001" name="tasa_bcv" id="vm-tasa" value="{{ old('tasa_bcv', $tasa > 0 ? $tasa : '') }}" required></div>
                    </div>
                </div>

                <div class="vm-card">
                    <h3><span class="vm-paso">4</span> Pago</h3>
                    <div class="vm-pills" style="margin-bottom:10px;">
                        <label><input type="radio" name="condicion" value="contado" @checked(old('condicion', 'contado') === 'contado')><span>De contado</span></label>
                        <label><input type="radio" name="condicion" value="credito" @checked(old('condicion') === 'credito')><span>A crédito</span></label>
                    </div>
                    <div id="vm-credito" style="display:none; padding:10px; background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; margin-bottom:10px;">
                        <div class="vm-row">
                            <div><label>Abono de hoy $</label><input type="number" step="0.01" min="0" id="vm-abono" placeholder="0.00 si no abona"></div>
                            <div><label>Vence *</label><input type="date" name="fecha_vencimiento" id="vm-vence" value="{{ old('fecha_vencimiento', now()->addDays(15)->toDateString()) }}"></div>
                        </div>
                        <div style="display:flex; gap:6px; margin-top:6px;">
                            @foreach([7, 15, 30] as $d)
                                <button type="button" class="btn secondary" style="padding:2px 8px; font-size:.72rem;" data-dias="{{ $d }}">{{ $d }} días</button>
                            @endforeach
                        </div>
                    </div>
                    <input type="hidden" name="pago_monto_usd" id="vm-pago-monto" value="{{ old('pago_monto_usd') }}">
                    <div class="vm-row">
                        <div><label>Forma de pago</label>
                            <select name="pago_forma">
                                <option value="">--</option>
                                @foreach($formas as $f)<option value="{{ $f }}" @selected(old('pago_forma') === $f)>{{ $f }}</option>@endforeach
                            </select>
                        </div>
                        <div><label>Referencia</label><input type="text" name="pago_referencia" maxlength="120" value="{{ old('pago_referencia') }}"></div>
                    </div>
                    <div style="margin-top:8px;">
                        <label>Comprobantes (imagen o PDF)</label>
                        <input type="file" name="comprobantes[]" multiple accept="image/*,.pdf">
                    </div>
                </div>

                <div class="vm-total">
                    <div class="fila"><span>Productos</span><span id="vm-n-items">0 · 0 u.</span></div>
                    <div class="fila" style="font-size:1.5rem; font-weight:800; padding:6px 0;"><span>Total</span><span id="vm-total-usd">$0.00</span></div>
                    <div class="fila" style="opacity:.85;"><span>En Bs (tasa BCV)</span><span id="vm-total-bs">Bs 0,00</span></div>
                    <div class="fila" style="border-top:1px solid rgba(255,255,255,.25); margin-top:6px; padding-top:6px;"><span>Paga hoy</span><span id="vm-paga">$0.00</span></div>
                    <div class="fila" style="font-weight:700; color:#fde68a;"><span>Queda a crédito</span><span id="vm-saldo">$0.00</span></div>
                    <button type="submit" class="btn" style="width:100%; margin-top:10px; padding:11px; font-weight:800; background:#22c55e; color:#fff; border:0; border-radius:8px; font-size:.95rem;">Emitir factura</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const $ = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    const usd = n => '$' + n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const bs = n => 'Bs ' + n.toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const debounce = (fn, ms = 250) => { let t; return e => { clearTimeout(t); t = setTimeout(() => fn(e), ms); }; };
    const cerrarAlClicFuera = (input, box) => document.addEventListener('click', e => { if (!box.contains(e.target) && e.target !== input) box.style.display = 'none'; });

    // ── Cliente ──
    window.vmQuitarCliente = function () {
        $('vm-cliente-id').value = ''; $('vm-cliente-etiqueta').value = '';
        $('vm-cliente-elegido').style.display = 'none'; $('vm-cliente-buscar').style.display = '';
    };
    function elegirCliente(id, etiqueta) {
        $('vm-cliente-id').value = id; $('vm-cliente-etiqueta').value = etiqueta;
        $('vm-cliente-texto').textContent = etiqueta;
        $('vm-cliente-elegido').style.display = 'flex'; $('vm-cliente-buscar').style.display = 'none';
        $('vm-clientes').style.display = 'none'; $('vm-cliente-q').value = '';
        vmNuevoCliente(false);
    }
    window.vmNuevoCliente = function (on) {
        $('vm-cliente-nuevo').style.display = on ? '' : 'none';
        $('vm-c-doc').required = on; $('vm-c-nombre').required = on;
        if (on) { vmQuitarCliente(); $('vm-clientes').style.display = 'none'; $('vm-c-doc').focus(); }
    };
    $('vm-cliente-q').addEventListener('input', debounce(async e => {
        const q = e.target.value.trim(), box = $('vm-clientes');
        if (q.length < 2) { box.style.display = 'none'; return; }
        const r = await fetch(@json(route('ventas_mayor.clientes.buscar')) + '?q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}});
        const {clientes} = await r.json();
        box.innerHTML = clientes.length
            ? clientes.map(c => `<button type="button" class="vm-op" data-id="${c.id}" data-et="${esc(c.nombre + ' · ' + c.documento)}"><span><strong>${esc(c.nombre)}</strong> · ${esc(c.documento)}<br><small class="muted">${esc([c.telefono, c.email].filter(Boolean).join(' · '))}</small></span><span class="vm-agregar">Elegir</span></button>`).join('')
            : `<div style="padding:12px;">No está registrado. <a href="#" id="vm-registrar">Regístralo aquí</a>.</div>`;
        box.querySelectorAll('[data-id]').forEach(b => b.addEventListener('click', () => elegirCliente(b.dataset.id, b.dataset.et)));
        box.querySelector('#vm-registrar')?.addEventListener('click', ev => { ev.preventDefault(); vmNuevoCliente(true); $('vm-c-nombre').value = /\d/.test(q) ? '' : q; if (/\d/.test(q)) $('vm-c-doc').value = q; });
        box.style.display = 'block';
    }));
    cerrarAlClicFuera($('vm-cliente-q'), $('vm-clientes'));
    if ($('vm-cliente-id').value) elegirCliente($('vm-cliente-id').value, $('vm-cliente-etiqueta').value);
    else if ($('vm-c-doc').value || $('vm-c-nombre').value) vmNuevoCliente(true);

    // ── Pago ──
    const condicion = () => document.querySelector('input[name="condicion"]:checked')?.value || 'contado';
    function pintarCondicion() {
        const credito = condicion() === 'credito';
        $('vm-credito').style.display = credito ? '' : 'none';
        $('vm-vence').required = credito;
        totales();
    }
    document.querySelectorAll('input[name="condicion"]').forEach(r => r.addEventListener('change', pintarCondicion));
    document.querySelectorAll('[data-dias]').forEach(b => b.addEventListener('click', () => {
        const d = new Date(($('vm-fecha').value || new Date().toISOString().slice(0, 10)) + 'T12:00:00');
        d.setDate(d.getDate() + parseInt(b.dataset.dias, 10));
        $('vm-vence').value = d.toISOString().slice(0, 10);
    }));
    $('vm-abono').addEventListener('input', totales);
    if (condicion() === 'credito' && $('vm-pago-monto').value) $('vm-abono').value = $('vm-pago-monto').value;

    // ── Productos ──
    let fila = 0;
    function totales() {
        let total = 0, unidades = 0, n = 0;
        document.querySelectorAll('#vm-items tr[data-item]').forEach(tr => {
            const cant = parseInt(tr.querySelector('.vm-cant').value, 10) || 0;
            const precio = parseFloat(tr.querySelector('.vm-precio').value) || 0;
            const disp = parseInt(tr.dataset.disponible, 10) || 0;
            const t = Math.round(cant * precio * 100) / 100;
            total += t; unidades += cant * (parseInt(tr.dataset.unidadesPor, 10) || 1); n++;
            tr.querySelector('.vm-tot').textContent = usd(t);
            const pasa = cant > disp;
            tr.querySelector('.vm-cant').style.borderColor = pasa ? '#dc2626' : '';
            tr.querySelector('.vm-aviso').textContent = pasa ? `Solo hay ${cantidadTexto(disp, tr.dataset.presentacion)} en JRZ` : '';
        });
        total = Math.round(total * 100) / 100;
        const tasa = parseFloat($('vm-tasa').value) || 0;
        const credito = condicion() === 'credito';
        const paga = credito ? Math.min(total, Math.max(0, parseFloat($('vm-abono').value) || 0)) : total;
        $('vm-pago-monto').value = credito ? ($('vm-abono').value === '' ? '0' : paga.toFixed(2)) : '';
        $('vm-n-items').textContent = `${n} · ${unidades} u.`;
        $('vm-total-usd').textContent = usd(total);
        $('vm-total-bs').textContent = bs(Math.round(total * tasa * 100) / 100);
        $('vm-paga').textContent = usd(paga);
        $('vm-saldo').textContent = usd(Math.round((total - paga) * 100) / 100);
        $('vm-sin-items').style.display = n ? 'none' : '';
    }
    function cantidadTexto(n, presentacion) {
        if (presentacion === 'caja') return `${n} ${n === 1 ? 'caja' : 'cajas'}`;
        if (presentacion === 'paquete') return `${n} ${n === 1 ? 'paquete' : 'paquetes'}`;
        return `${n}`;
    }
    function agregar(p, cantidad = 1, precio = null) {
        const existente = document.querySelector(`#vm-items tr[data-producto="${p.id}"]`);
        if (existente) { const c = existente.querySelector('.vm-cant'); c.value = (parseInt(c.value, 10) || 0) + 1; c.focus(); c.select(); totales(); return; }
        const i = fila++;
        const pres = p.presentacion || 'unidad';
        const por = parseInt(p.unidades_por, 10) || 1;
        const disp = p.disponible_pres ?? p.disponible_jrz;
        const tr = document.createElement('tr');
        tr.dataset.item = '1'; tr.dataset.producto = p.id; tr.dataset.disponible = disp;
        tr.dataset.presentacion = pres; tr.dataset.unidadesPor = por;
        const etiqueta = pres !== 'unidad' ? `<span class="vm-chip" style="background:#ede9fe; color:#6d28d9;">${esc(p.presentacion_texto || pres)}</span> ` : '';
        tr.innerHTML = `
            <td><strong>${esc(p.nombre)}</strong><div class="muted" style="font-size:.72rem;">${etiqueta}${esc(p.codigo)} <span class="vm-aviso" style="color:#dc2626; font-weight:700;"></span></div>
                <input type="hidden" name="items[${i}][producto_id]" value="${p.id}">
                <input type="hidden" name="items[${i}][codigo]" value="${esc(p.codigo)}">
                <input type="hidden" name="items[${i}][nombre]" value="${esc(p.nombre)}">
                <input type="hidden" name="items[${i}][disponible_jrz]" value="${p.disponible_jrz}">
                <input type="hidden" name="items[${i}][disponible_pres]" value="${disp}">
                <input type="hidden" name="items[${i}][presentacion]" value="${esc(pres)}">
                <input type="hidden" name="items[${i}][presentacion_texto]" value="${esc(p.presentacion_texto || '')}">
                <input type="hidden" name="items[${i}][unidades_por]" value="${por}"></td>
            <td class="vm-num" style="font-weight:700; color:#15803d;">${cantidadTexto(disp, pres)}${por > 1 ? `<div class="muted" style="font-size:.7rem; font-weight:400;">${p.disponible_jrz} und.</div>` : ''}</td>
            <td class="vm-num"><input type="number" class="vm-cant" name="items[${i}][cantidad]" min="1" max="${disp}" step="1" value="${cantidad}" required style="width:84px;"></td>
            <td class="vm-num"><input type="hidden" class="vm-precio" name="items[${i}][precio_usd]" value="${p.precio_mayor || 0}">${p.precio_mayor > 0
                ? `<strong>${usd(p.precio_mayor)}</strong>`
                : `<span style="color:#dc2626; font-weight:700;" title="Hay que ponerle precio en su ficha">Sin precio</span>`}</td>
            <td class="vm-num vm-tot" style="font-weight:700;">$0.00</td>
            <td><button type="button" style="padding:4px 9px; background:#fee2e2; color:#b91c1c; border:0; border-radius:6px; cursor:pointer; font-weight:700;" title="Quitar">&times;</button></td>`;
        tr.querySelector('button').addEventListener('click', () => { tr.remove(); totales(); });
        tr.querySelectorAll('input').forEach(inp => inp.addEventListener('input', totales));
        $('vm-items').appendChild(tr);
        totales();
        tr.querySelector('.vm-cant').select();
    }
    let resultados = [];
    const cajaProd = $('vm-productos');
    function cerrarProductos() { cajaProd.style.display = 'none'; $('vm-prod-q').value = ''; resultados = []; }
    $('vm-prod-q').addEventListener('input', debounce(async e => {
        const q = e.target.value.trim();
        if (q.length < 2) { cajaProd.style.display = 'none'; resultados = []; return; }
        cajaProd.innerHTML = '<div style="padding:12px;" class="muted">Buscando…</div>';
        cajaProd.style.display = 'block';
        try {
            const r = await fetch(@json(route('ventas_mayor.productos.buscar')) + '?q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}});
            if (!r.ok) throw new Error(r.status);
            resultados = (await r.json()).productos;
        } catch (err) {
            cajaProd.innerHTML = '<div style="padding:12px; color:#dc2626;">No se pudo buscar. Recarga la página e intenta de nuevo.</div>';
            return;
        }
        if (q !== $('vm-prod-q').value.trim()) return;
        const orden = [...resultados.keys()].sort((a, b) => (resultados[b].disponible_jrz > 0) - (resultados[a].disponible_jrz > 0));
        cajaProd.innerHTML = resultados.length ? orden.map(k => {
            const p = resultados[k];
            const otras = Object.entries(p.otras_sedes || {}).map(([s, n]) => `${s} ${n}`).join(' · ');
            const hay = p.disponible_pres > 0;
            const porPres = p.presentacion && p.presentacion !== 'unidad' ? ` por ${esc(p.presentacion_texto).toLowerCase()}` : '';
            return `<button type="button" class="vm-op" data-k="${k}" ${hay ? '' : 'disabled'}>
                <span><strong>${esc(p.nombre)}</strong><br>
                    <small class="muted">${esc(p.codigo)} · ${usd(p.precio_mayor)} al mayor${porPres}${otras ? ' · otras sedes: ' + esc(otras) : ''}</small></span>
                <span style="display:flex; align-items:center; gap:8px;">
                    <span class="vm-chip" style="background:${hay ? '#dcfce7; color:#166534' : '#fee2e2; color:#991b1b'};">JRZ ${cantidadTexto(p.disponible_pres, p.presentacion)}</span>
                    <span class="vm-agregar">${hay ? '+ Agregar' : 'Sin stock JRZ'}</span>
                </span></button>`;
        }).join('') : '<div style="padding:12px;" class="muted">Sin resultados.</div>';
        cajaProd.querySelectorAll('[data-k]').forEach(b => b.addEventListener('click', () => { agregar(resultados[b.dataset.k]); cerrarProductos(); $('vm-prod-q').focus(); }));
    }));
    $('vm-prod-q').addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const p = resultados.find(x => x.disponible_pres > 0);
        if (p) { agregar(p); cerrarProductos(); }
    });
    cerrarAlClicFuera($('vm-prod-q'), cajaProd);
    $('vm-tasa').addEventListener('input', totales);

    // Si la validación devolvió el formulario, se recuperan los productos.
    (@json(array_values(old('items', [])))).forEach(it => agregar({
        id: it.producto_id, codigo: it.codigo, nombre: it.nombre, disponible_jrz: parseInt(it.disponible_jrz, 10) || 0, precio_mayor: it.precio_usd,
        disponible_pres: parseInt(it.disponible_pres ?? it.disponible_jrz, 10) || 0, presentacion: it.presentacion || 'unidad',
        presentacion_texto: it.presentacion_texto || '', unidades_por: parseInt(it.unidades_por, 10) || 1,
    }, it.cantidad, it.precio_usd));
    if (!document.querySelector('#vm-items tr[data-item]')) (@json($iniciales ?? [])).forEach(p => agregar(p));
    pintarCondicion();

    $('vm-form').addEventListener('submit', function (e) {
        const fallar = m => { e.preventDefault(); alert(m); };
        if (!$('vm-cliente-id').value && $('vm-cliente-nuevo').style.display === 'none') return fallar('Elige el cliente o regístralo.');
        if (!document.querySelector('#vm-items tr[data-item]')) return fallar('Agrega al menos un producto.');
        const pasados = [...document.querySelectorAll('#vm-items tr[data-item]')].filter(tr => (parseInt(tr.querySelector('.vm-cant').value, 10) || 0) > (parseInt(tr.dataset.disponible, 10) || 0));
        if (pasados.length) return fallar('Hay cantidades mayores a lo disponible en JRZ (marcadas en rojo).');
        const sinPrecio = [...document.querySelectorAll('#vm-items .vm-precio')].some(inp => !(parseFloat(inp.value) > 0));
        if (sinPrecio) return fallar('Hay productos sin precio al mayor: hay que ponerle precio en su ficha.');
        totales();
    });
})();
</script>
@endsection
