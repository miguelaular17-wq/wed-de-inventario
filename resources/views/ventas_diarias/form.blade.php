@extends('layouts.app')

@section('title', $reporte ? 'Editar ventas diarias' : 'Nuevo ventas diarias')

@push('head')
<style>
.vd-wrap { max-width: 1200px; }
.vd-title { font-size: 1.25rem; font-weight: 700; margin: 0 0 12px; color: #1e293b; }
.vd-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; }
.vd-card h3 { margin:0 0 10px; font-size:.95rem; color:#334155; }
.vd-meta-table, .vd-cajas { width:100%; border-collapse:collapse; font-size:.85rem; }
.vd-cajas th, .vd-cajas td { padding:6px 8px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.vd-cajas input, .vd-head input, .vd-head select, .vd-extra input {
    width:100%; min-width:0; padding:6px 8px; border:1px solid #cbd5e1; border-radius:6px; font-weight:600;
}
.vd-head { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:14px; align-items:flex-end; }
.vd-head .field, .vd-extra .field { display:flex; flex-direction:column; gap:4px; min-width:140px; flex:1; }
.vd-head label, .vd-extra label { font-size:.7rem; font-weight:700; color:#64748b; text-transform:uppercase; }
.vd-extra { display:flex; flex-wrap:wrap; gap:12px; margin-top:16px; }
.vd-cajas-wrap { overflow-x:auto; }
.vd-cajas th { font-size:.7rem; text-transform:uppercase; color:#64748b; white-space:nowrap; }
.vd-actions { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; }
.vd-modal-back {
    display:none; position:fixed; inset:0; z-index:2000;
    background: rgba(15, 23, 42, .96);
    align-items:center; justify-content:center; padding:24px;
}
.vd-modal-back.open { display:flex; }
.vd-modal {
    background:#fff; border-radius:16px; width:min(720px, 100%);
    padding:22px 24px 18px; box-shadow:0 24px 60px rgba(0,0,0,.35);
}
.vd-modal h3 { margin:0 0 4px; font-size:1.15rem; text-align:center; }
.vd-modal .sub { margin:0 0 14px; color:#64748b; font-size:.85rem; text-align:center; }
.vd-modal table { width:100%; border-collapse:collapse; font-size:.92rem; }
.vd-modal td { padding:7px 4px; border-bottom:1px solid #f1f5f9; }
.vd-modal th, .vd-modal .lab { font-weight:650; white-space:nowrap; text-transform:uppercase; }
.vd-modal .usd { color:#059669; font-weight:700; text-align:right; white-space:nowrap; }
.vd-modal .bs { text-align:right; color:#334155; font-weight:600; white-space:nowrap; }
.vd-modal .tot td { font-weight:800; background:#f8fafc; }
.vd-modal tr.vd-venta td { background:#d1fae5; border-top:2px solid #059669; border-bottom:2px solid #059669; font-size:1.02rem; }
.vd-modal tr.vd-venta .lab { color:#064e3b; }
.vd-modal tr.vd-unidades .usd { text-align:center; color:#059669; font-weight:700; font-size:1.12rem; }
.vd-modal tr.vd-venta .usd { font-size:1.12rem; }
.vd-modal tr.vd-credito .lab { color:#dc2626; }
</style>
@endpush

@section('content')
<div class="vd-wrap">
    <form method="POST" action="{{ $reporte ? route('ventas_diarias.update', $reporte) : route('ventas_diarias.store') }}" id="vd-form">
        @csrf
        @if($reporte) @method('PUT') @endif

        <div class="vd-title">
            Reporte cierre de venta diaria:
            @if($verTodas && ! $reporte)
                <select name="sede" style="font-size:1rem;font-weight:700;padding:4px 8px;">
                    @foreach($sedes as $s)
                        <option value="{{ $s }}" @selected($sede === $s)>{{ config('inventario.display.'.$s, $s) }}</option>
                    @endforeach
                </select>
            @else
                <strong>{{ config('inventario.display.'.$sede, $sede) }}</strong>
                <input type="hidden" name="sede" value="{{ $sede }}">
            @endif
        </div>

        <div class="vd-head vd-card">
            <div class="field">
                <label>Fecha</label>
                @if($reporte)
                    <input type="date" value="{{ $fecha }}" disabled>
                    <input type="hidden" name="fecha" value="{{ $fecha }}">
                @else
                    <input type="date" name="fecha" value="{{ $fecha }}" required>
                @endif
            </div>
            <div class="field">
                <label>Tasa</label>
                <input type="number" step="any" min="0.0001" name="tasa" id="vd-tasa"
                       value="{{ old('tasa', $reporte->tasa ?? '') }}" required>
            </div>
            <div class="field">
                <label>Meta venta (día)</label>
                <input type="text" value="{{ number_format($metaCtx['meta_venta'], 2) }}" disabled>
            </div>
            <div class="field">
                <label>Meta productos</label>
                <input type="text" value="{{ number_format($metaCtx['meta_productos'], 2) }}" disabled>
            </div>
            <div class="field">
                <label>Tipo día</label>
                <input type="text" value="{{ $metaCtx['es_domingo'] ? 'Domingo' : 'Lunes a sábado' }}" disabled>
            </div>
        </div>

        @if($errors->any())
            <div class="alert error" style="margin-bottom:12px;">{{ $errors->first() }}</div>
        @endif

        <div class="vd-card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;">
                <h3 style="margin:0;">CAJAS</h3>
                <button type="button" class="btn" id="vd-add-caja">+ Caja</button>
            </div>
                <div class="vd-cajas-wrap">
                    <table class="vd-cajas" id="vd-cajas-table">
                        <thead>
                            <tr>
                                <th>CAJA</th>
                                @foreach($columnasCaja as $etiqueta)
                                    <th>{{ $etiqueta }}</th>
                                @endforeach
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cajas as $i => $c)
                                <tr>
                                    <td><input name="cajas[{{ $i }}][nombre]" value="{{ old('cajas.'.$i.'.nombre', $c->nombre ?? '') }}" placeholder="Nombre"></td>
                                    @foreach($columnasCaja as $campo => $etiqueta)
                                        <td><input type="number" step="0.01" name="cajas[{{ $i }}][{{ $campo }}]" value="{{ old('cajas.'.$i.'.'.$campo, $c->{$campo} ?? 0) }}"></td>
                                    @endforeach
                                    <td><button type="button" class="btn vd-del-caja" title="Quitar">×</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="muted" style="font-size:12px;margin:10px 0 0;">
                    Metas L–Sáb venta {{ number_format($metaCtx['meta_venta_lv_sab'], 2) }} /
                    Dom {{ number_format($metaCtx['meta_venta_domingo'], 2) }} ·
                    Prod L–Sáb {{ number_format($metaCtx['meta_prod_lv_sab'], 2) }} /
                    Dom {{ number_format($metaCtx['meta_prod_domingo'], 2) }}
                </p>
        </div>

        <div class="vd-card vd-extra">
            <div class="field">
                <label>Productos vendidos</label>
                <input type="number" step="any" name="productos_vendidos" value="{{ old('productos_vendidos', $reporte->productos_vendidos ?? 0) }}">
            </div>
            <div class="field">
                <label>Facturación fiscal (Bs)</label>
                <input type="number" step="any" name="z_fiscal_bs" value="{{ old('z_fiscal_bs', $reporte->z_fiscal_bs ?? 0) }}">
            </div>
            <div class="field">
                <label>Deliverys pendientes ($)</label>
                <input type="number" step="any" name="deliverys_pendientes" value="{{ old('deliverys_pendientes', $reporte->deliverys_pendientes ?? 0) }}">
            </div>
            <div class="field">
                <label>Disponible fondo Bs</label>
                <input type="number" step="any" name="fondo_bs" value="{{ old('fondo_bs', $reporte->fondo_bs ?? 0) }}">
            </div>
            <div class="field">
                <label>Disponible fondo divisas ($)</label>
                <input type="number" step="any" name="fondo_divisas" value="{{ old('fondo_divisas', $reporte->fondo_divisas ?? 0) }}">
            </div>
            <div class="field" style="flex-basis:100%;">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;">{{ old('observaciones', $reporte->observaciones ?? '') }}</textarea>
            </div>
        </div>

        <div class="vd-actions">
            <button class="btn primary" type="button" id="vd-guardar">Guardar reporte</button>
            <a class="btn" href="{{ route('ventas_diarias.index') }}">Volver</a>
        </div>
        <div id="vd-form-error" class="alert error" style="display:none;margin-top:12px;"></div>
    </form>
</div>

<div class="vd-modal-back" id="vd-modal" role="dialog" aria-modal="true">
    <div class="vd-modal">
        <h3>DESGLOSE VENTAS DEL DIA</h3>
        <p class="sub" id="vd-modal-sub"></p>
        <table>
            <tr><th>Forma de pago</th><th class="bs">Bolívares</th><th class="usd">Divisas</th><th>% meta</th></tr>
            <tr data-linea data-kind="usd" data-field="efectivo_usd"><td class="lab">Efectivo divisas</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="usd" data-field="zelle"><td class="lab">Zelle</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="usd" data-field="binance"><td class="lab">Binance</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="usd" data-field="mercantil_panama"><td class="lab">Mercantil Panamá</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="usd" data-field="iphone"><td class="lab">iPhone</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="usd" data-field="preventa"><td class="lab">Preventa</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="usd" data-field="abonos"><td class="lab">Abono deuda/apartado</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="bs" data-field="efectivo_bs"><td class="lab">Efectivo Bs</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="bs" data-field="punto_venta"><td class="lab">Punto de venta</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="bs" data-field="pago_movil"><td class="lab">Pago móvil</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="bs" data-field="transferencias"><td class="lab">Transferencias</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="usdbs" data-field="cashea"><td class="lab">Cashea financiamiento</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="usdbs" data-field="flaexpay"><td class="lab">Flexpay financiamiento</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="usdbs" data-field="krece"><td class="lab">Krece financiamiento</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="usdbs" data-field="gift_card"><td class="lab">Gift card</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr data-linea data-kind="credito" data-field="fact_credito" class="vd-credito"><td class="lab">Facturas a crédito</td><td class="bs" data-col="bs"></td><td class="usd" data-col="usd"></td><td></td></tr>
            <tr class="tot vd-venta"><td class="lab">Total de ventas del día</td><td class="bs" id="md-ventas-bs"></td><td class="usd" id="md-ventas"></td><td id="md-pct-venta"></td></tr>
            <tr class="vd-venta vd-unidades"><td class="lab">Unidades vendidas</td><td class="usd" id="md-prod" colspan="2"></td><td id="md-pct-prod"></td></tr>
            <tr><td class="lab">Facturación fiscal</td><td class="bs" id="md-fiscal-bs"></td><td class="usd" id="md-fiscal"></td><td id="md-pct-fiscal"></td></tr>
            <tr><td class="lab">Disponible fondo Bs</td><td class="bs" id="md-fondo-bs"></td><td class="usd" id="md-fondo-bs-usd"></td><td></td></tr>
            <tr><td class="lab">Disponible fondo divisas</td><td class="bs"></td><td class="usd" id="md-fondo-divisas"></td><td></td></tr>
        </table>
        <div class="vd-actions">
            <a class="btn primary" id="vd-modal-cerrar" href="#">Cerrar</a>
        </div>
    </div>
</div>

<script>
(function () {
    const metaVenta = {{ json_encode((float) $metaCtx['meta_venta']) }};
    const metaProd = {{ json_encode((float) $metaCtx['meta_productos']) }};
    const columnas = @json(array_keys($columnasCaja));
    const grupo = (n, dec) => (Number(n) || 0).toLocaleString('es-VE', {minimumFractionDigits: dec, maximumFractionDigits: dec});
    const money = (n) => '$' + grupo(n, 2);
    const pctTxt = (v) => (v === null || Number.isNaN(v)) ? '—' : grupo(v * 100, 1) + '%';
    const num = (sel) => parseFloat(document.querySelector(sel)?.value || '0') || 0;
    const bs = (n) => 'Bs ' + grupo(n, 2);

    function sumCajas(field) {
        let total = 0;
        document.querySelectorAll('#vd-cajas-table tbody tr').forEach((tr) => {
            const input = tr.querySelector(`input[name*="[${field}]"]`);
            total += parseFloat(input?.value || '0') || 0;
        });
        return Math.round(total * 100) / 100;
    }

    function recalc() {
        const tasa = num('#vd-tasa') || 1;
        let cobros = 0;
        let totalBs = 0;
        let creditos = 0;
        document.querySelectorAll('#vd-modal [data-linea]').forEach((tr) => {
            const monto = sumCajas(tr.dataset.field);
            const kind = tr.dataset.kind;
            let usd = monto;
            let bolivares = null;
            if (kind === 'bs') {
                bolivares = monto;
                usd = monto / tasa;
                cobros += usd;
                totalBs += bolivares;
            } else if (kind === 'usdbs') {
                bolivares = monto * tasa;
                cobros += usd;
                totalBs += bolivares;
            } else if (kind === 'credito') {
                bolivares = monto * tasa;
                creditos += usd;
                totalBs += bolivares;
            } else {
                cobros += usd;
            }
            tr.querySelector('[data-col="bs"]').textContent = bolivares === null ? '' : bs(bolivares);
            tr.querySelector('[data-col="usd"]').textContent = money(usd);
        });

        const ventas = cobros + creditos;
        const zfiscal = num('[name=z_fiscal_bs]');
        const fiscal = zfiscal / tasa;
        const productos = num('[name=productos_vendidos]');
        const fondoBs = num('[name=fondo_bs]');
        const set = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };

        set('md-ventas-bs', bs(totalBs));
        set('md-ventas', money(ventas));
        set('md-pct-venta', pctTxt(metaVenta > 0 ? ventas / metaVenta : null));
        set('md-prod', grupo(productos, 0));
        set('md-pct-prod', pctTxt(metaProd > 0 ? productos / metaProd : null));
        set('md-fiscal-bs', bs(zfiscal));
        set('md-fiscal', money(fiscal));
        set('md-pct-fiscal', pctTxt(ventas > 0 ? fiscal / ventas : null));
        set('md-fondo-bs', bs(fondoBs));
        set('md-fondo-bs-usd', money(fondoBs / tasa));
        set('md-fondo-divisas', money(num('[name=fondo_divisas]')));
    }

    document.querySelectorAll('#vd-tasa, [name=z_fiscal_bs], [name=productos_vendidos], [name=deliverys_pendientes], [name=fondo_bs], [name=fondo_divisas]').forEach(
        (el) => el.addEventListener('input', recalc)
    );
    document.getElementById('vd-cajas-table')?.addEventListener('input', recalc);

    let cajaIdx = {{ count($cajas) }};
    document.getElementById('vd-add-caja')?.addEventListener('click', () => {
        const tb = document.querySelector('#vd-cajas-table tbody');
        const tr = document.createElement('tr');
        const celdas = columnas.map((campo) =>
            `<td><input type="number" step="0.01" name="cajas[${cajaIdx}][${campo}]" value="0"></td>`
        ).join('');
        tr.innerHTML = `<td><input name="cajas[${cajaIdx}][nombre]" placeholder="Nombre"></td>${celdas}<td><button type="button" class="btn vd-del-caja">×</button></td>`;
        tb.appendChild(tr);
        cajaIdx++;
        recalc();
    });
    document.getElementById('vd-cajas-table')?.addEventListener('click', (e) => {
        if (e.target.classList.contains('vd-del-caja')) {
            e.target.closest('tr')?.remove();
            recalc();
        }
    });
    recalc();

    const form = document.getElementById('vd-form');
    const btn = document.getElementById('vd-guardar');
    const errBox = document.getElementById('vd-form-error');
    btn?.addEventListener('click', async () => {
        recalc();
        if (!form.reportValidity()) {
            errBox.textContent = 'Revisa la tasa y los montos. Usa punto para los decimales.';
            errBox.style.display = 'block';
            return;
        }
        errBox.style.display = 'none';
        btn.disabled = true;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data.message
                    || (data.errors && Object.values(data.errors).flat()[0])
                    || 'No se pudo guardar.';
                errBox.textContent = msg;
                errBox.style.display = 'block';
                btn.disabled = false;
                return;
            }
            const fecha = form.querySelector('[name=fecha]')?.value || '';
            const partes = /^(\d{4})-(\d{2})-(\d{2})$/.exec(fecha);
            const fechaTxt = partes ? partes[3] + '/' + partes[2] + '/' + partes[1] : fecha;
            const tasaTxt = (num('#vd-tasa') || 0).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('vd-modal-sub').textContent = fechaTxt + '  ·  Tasa ' + tasaTxt;
            document.getElementById('vd-modal-cerrar').href = data.url || '#';
            document.getElementById('vd-modal').classList.add('open');
        } catch (e) {
            errBox.textContent = 'No se pudo guardar.';
            errBox.style.display = 'block';
            btn.disabled = false;
        }
    });
})();
</script>
@endsection
