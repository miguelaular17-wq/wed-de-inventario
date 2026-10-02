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
    background:#fff; border-radius:16px; width:min(520px, 100%);
    padding:22px 24px 18px; box-shadow:0 24px 60px rgba(0,0,0,.35);
}
.vd-modal h3 { margin:0 0 4px; font-size:1.15rem; }
.vd-modal .sub { margin:0 0 14px; color:#64748b; font-size:.85rem; }
.vd-modal table { width:100%; border-collapse:collapse; font-size:.92rem; }
.vd-modal td { padding:7px 4px; border-bottom:1px solid #f1f5f9; }
.vd-modal .lab { font-weight:650; }
.vd-modal .usd { color:#059669; font-weight:700; text-align:right; white-space:nowrap; }
.vd-modal .bs { text-align:right; color:#334155; font-weight:600; white-space:nowrap; }
.vd-modal .tot td { font-weight:800; background:#f8fafc; }
.vd-modal tr.vd-venta td { background:#d1fae5; border-top:2px solid #059669; border-bottom:2px solid #059669; font-size:1.02rem; }
.vd-modal tr.vd-venta .lab { color:#064e3b; }
.vd-modal tr.vd-venta .usd { font-size:1.12rem; }
.vd-pct.neg { color:#dc2626; }
.vd-pct.pos { color:#16a34a; }
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
            <div hidden>
                <input type="hidden" name="divisas_efectivo" class="vd-from-cajas" data-caja="efectivo_usd" value="0">
                <input type="hidden" name="efectivo_bs" class="vd-from-cajas" data-caja="efectivo_bs" value="0">
                <input type="hidden" name="punto_venta_bs" class="vd-from-cajas" data-caja="punto_venta" value="0">
                <input type="hidden" name="transf_pm_bs" class="vd-from-cajas" data-caja="transf_pm" value="0">
                <input type="hidden" name="zelle_binance" class="vd-from-cajas" data-caja="zelle_binance" value="0">
                <input type="hidden" name="cashea" class="vd-from-cajas" data-caja="cashea" value="0">
                <input type="hidden" name="abonos" class="vd-from-cajas" data-caja="abonos" value="0">
                <input type="hidden" name="iphone" class="vd-from-cajas" data-caja="iphone" value="0">
                <input type="hidden" name="gift_card" class="vd-from-cajas" data-caja="gift_card" value="0">
                <input type="hidden" name="total_creditos" class="vd-from-cajas" data-caja="fact_credito" value="0">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;">
                <h3 style="margin:0;">CAJAS</h3>
                <button type="button" class="btn" id="vd-add-caja">+ Caja</button>
            </div>
                <div class="vd-cajas-wrap">
                    <table class="vd-cajas" id="vd-cajas-table">
                        <thead>
                            <tr>
                                <th>CAJA</th>
                                <th>EFECTIVO $</th>
                                <th>EFECTIVO BS</th>
                                <th>PUNTO DE VENTA</th>
                                <th>TRANS/PAGO MOVIL</th>
                                <th>ZELLE / BINANCE</th>
                                <th>CASHEA</th>
                                <th>FACT A CREDITO</th>
                                <th>ABONOS</th>
                                <th>IPHONE</th>
                                <th>GIFT CARD</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cajas as $i => $c)
                                <tr>
                                    <td><input name="cajas[{{ $i }}][nombre]" value="{{ old('cajas.'.$i.'.nombre', $c->nombre ?? '') }}" placeholder="Nombre"></td>
                                    <td><input type="number" step="0.01" name="cajas[{{ $i }}][efectivo_usd]" value="{{ old('cajas.'.$i.'.efectivo_usd', $c->efectivo_usd ?? 0) }}"></td>
                                    <td><input type="number" step="0.01" name="cajas[{{ $i }}][efectivo_bs]" value="{{ old('cajas.'.$i.'.efectivo_bs', $c->efectivo_bs ?? 0) }}"></td>
                                    <td><input type="number" step="0.01" name="cajas[{{ $i }}][punto_venta]" value="{{ old('cajas.'.$i.'.punto_venta', $c->punto_venta ?? 0) }}"></td>
                                    <td><input type="number" step="0.01" name="cajas[{{ $i }}][transf_pm]" value="{{ old('cajas.'.$i.'.transf_pm', $c->transf_pm ?? 0) }}"></td>
                                    <td><input type="number" step="0.01" name="cajas[{{ $i }}][zelle_binance]" value="{{ old('cajas.'.$i.'.zelle_binance', $c->zelle_binance ?? 0) }}"></td>
                                    <td><input type="number" step="0.01" name="cajas[{{ $i }}][cashea]" value="{{ old('cajas.'.$i.'.cashea', $c->cashea ?? 0) }}"></td>
                                    <td><input type="number" step="0.01" name="cajas[{{ $i }}][fact_credito]" value="{{ old('cajas.'.$i.'.fact_credito', $c->fact_credito ?? 0) }}"></td>
                                    <td><input type="number" step="0.01" name="cajas[{{ $i }}][abonos]" value="{{ old('cajas.'.$i.'.abonos', $c->abonos ?? 0) }}"></td>
                                    <td><input type="number" step="0.01" name="cajas[{{ $i }}][iphone]" value="{{ old('cajas.'.$i.'.iphone', $c->iphone ?? 0) }}"></td>
                                    <td><input type="number" step="0.01" name="cajas[{{ $i }}][gift_card]" value="{{ old('cajas.'.$i.'.gift_card', $c->gift_card ?? 0) }}"></td>
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
                <label>Z fiscal (Bs)</label>
                <input type="number" step="any" name="z_fiscal_bs" value="{{ old('z_fiscal_bs', $reporte->z_fiscal_bs ?? 0) }}">
            </div>
            <div class="field">
                <label>Deliverys pendientes ($)</label>
                <input type="number" step="any" name="deliverys_pendientes" value="{{ old('deliverys_pendientes', $reporte->deliverys_pendientes ?? 0) }}">
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
        <h3>DESGLOSE</h3>
        <p class="sub" id="vd-modal-sub"></p>
        <table>
            <tr><td class="lab">Divisas en efectivo</td><td class="bs" id="md-divisas-bs"></td><td class="usd" id="md-divisas"></td></tr>
            <tr><td class="lab">Efectivo Bs</td><td class="bs" id="md-ebs"></td><td class="usd" id="md-ebs-usd"></td></tr>
            <tr><td class="lab">Punto de venta</td><td class="bs" id="md-punto"></td><td class="usd" id="md-punto-usd"></td></tr>
            <tr><td class="lab">Transf/P.M.</td><td class="bs" id="md-transf"></td><td class="usd" id="md-transf-usd"></td></tr>
            <tr><td class="lab">Zelle y binance</td><td class="bs" id="md-zelle-bs"></td><td class="usd" id="md-zelle"></td></tr>
            <tr><td class="lab">Cashea financiado</td><td class="bs" id="md-cashea-bs"></td><td class="usd" id="md-cashea"></td></tr>
            <tr><td class="lab">Abono de apartado / Deuda</td><td class="bs" id="md-abonos-bs"></td><td class="usd" id="md-abonos"></td></tr>
            <tr><td class="lab">iPhone</td><td class="bs" id="md-iphone-bs"></td><td class="usd" id="md-iphone"></td></tr>
            <tr><td class="lab">Gift card</td><td class="bs" id="md-gift-bs"></td><td class="usd" id="md-gift"></td></tr>
            <tr class="tot"><td class="lab">Total cobros del día</td><td class="bs" id="md-cobros-bs"></td><td class="usd" id="md-cobros"></td></tr>
            <tr><td class="lab">Total créditos del día</td><td class="bs" id="md-creditos-bs"></td><td class="usd" id="md-creditos"></td></tr>
            <tr class="tot vd-venta"><td class="lab">Total de ventas del día</td><td class="bs" id="md-ventas-bs"></td><td class="usd" id="md-ventas"></td><td id="md-pct-venta"></td></tr>
            <tr><td class="lab">Productos vendidos</td><td class="bs" id="md-prod"></td><td></td><td id="md-pct-prod"></td></tr>
            <tr><td class="lab">Z fiscal (Bs)</td><td class="bs" id="md-zfiscal"></td><td class="usd" id="md-zfiscal-usd"></td></tr>
            <tr><td class="lab">Facturación fiscal</td><td class="bs" id="md-fiscal-bs"></td><td class="usd" id="md-fiscal"></td><td id="md-pct-fiscal"></td></tr>
            <tr><td class="lab">Deliverys pendientes</td><td class="bs" id="md-deliverys-bs"></td><td class="usd" id="md-deliverys"></td></tr>
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
    const money = (n) => '$' + (Number(n) || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const pctHtml = (v) => {
        if (v === null || Number.isNaN(v)) return '—';
        const cls = v >= 0 ? 'pos' : 'neg';
        const sign = v >= 0 ? '+' : '';
        return '<span class="vd-pct ' + cls + '">' + sign + (v * 100).toFixed(1) + '%</span>';
    };
    const num = (sel) => parseFloat(document.querySelector(sel)?.value || '0') || 0;

    function sumCajas(field) {
        let total = 0;
        document.querySelectorAll('#vd-cajas-table tbody tr').forEach((tr) => {
            const input = tr.querySelector(`input[name*="[${field}]"]`);
            total += parseFloat(input?.value || '0') || 0;
        });
        return Math.round(total * 100) / 100;
    }

    function syncDesgloseFromCajas() {
        document.querySelectorAll('.vd-from-cajas').forEach((el) => {
            const field = el.dataset.caja;
            if (!field) return;
            el.value = sumCajas(field).toFixed(2);
        });
    }

    function recalc() {
        syncDesgloseFromCajas();

        const tasa = num('#vd-tasa') || 1;
        const divisas = num('[name=divisas_efectivo]');
        const ebs = num('[name=efectivo_bs]');
        const pbs = num('[name=punto_venta_bs]');
        const tbs = num('[name=transf_pm_bs]');
        const zelle = num('[name=zelle_binance]');
        const cashea = num('[name=cashea]');
        const abonos = num('[name=abonos]');
        const iphone = num('[name=iphone]');
        const giftCard = num('[name=gift_card]');
        const creditos = num('[name=total_creditos]');
        const zfiscal = num('[name=z_fiscal_bs]');
        const productos = num('[name=productos_vendidos]');

        const eUsd = ebs / tasa;
        const pUsd = pbs / tasa;
        const tUsd = tbs / tasa;
        const cobros = divisas + eUsd + pUsd + tUsd + zelle + cashea + abonos + iphone + giftCard;
        const ventas = cobros + creditos;
        const fiscal = zfiscal / tasa;
        const bs = (n) => 'Bs ' + (Number(n) || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const enBs = (usd) => bs((Number(usd) || 0) * tasa);
        const set = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
        const setHtml = (id, html) => { const el = document.getElementById(id); if (el) el.innerHTML = html; };

        set('md-divisas-bs', enBs(divisas));
        set('md-divisas', money(divisas));
        set('md-ebs', bs(ebs));
        set('md-ebs-usd', money(eUsd));
        set('md-punto', bs(pbs));
        set('md-punto-usd', money(pUsd));
        set('md-transf', bs(tbs));
        set('md-transf-usd', money(tUsd));
        set('md-zelle-bs', enBs(zelle));
        set('md-zelle', money(zelle));
        set('md-cashea-bs', enBs(cashea));
        set('md-cashea', money(cashea));
        set('md-abonos-bs', enBs(abonos));
        set('md-abonos', money(abonos));
        set('md-iphone-bs', enBs(iphone));
        set('md-iphone', money(iphone));
        set('md-gift-bs', enBs(giftCard));
        set('md-gift', money(giftCard));
        set('md-cobros-bs', enBs(cobros));
        set('md-cobros', money(cobros));
        set('md-creditos-bs', enBs(creditos));
        set('md-creditos', money(creditos));
        set('md-ventas-bs', enBs(ventas));
        set('md-ventas', money(ventas));
        setHtml('md-pct-venta', pctHtml(metaVenta > 0 ? (ventas / metaVenta) - 1 : null));
        set('md-prod', bs(productos));
        setHtml('md-pct-prod', pctHtml(metaProd > 0 ? (productos / metaProd) - 1 : null));
        set('md-zfiscal', bs(zfiscal));
        set('md-zfiscal-usd', money(fiscal));
        set('md-fiscal-bs', bs(zfiscal));
        set('md-fiscal', money(fiscal));
        setHtml('md-pct-fiscal', pctHtml(ventas > 0 ? fiscal / ventas : null));
        set('md-deliverys-bs', enBs(num('[name=deliverys_pendientes]')));
        set('md-deliverys', money(num('[name=deliverys_pendientes]')));
    }

    document.querySelectorAll('#vd-tasa, [name=z_fiscal_bs], [name=productos_vendidos], [name=deliverys_pendientes]').forEach(
        (el) => el.addEventListener('input', recalc)
    );
    document.getElementById('vd-cajas-table')?.addEventListener('input', recalc);

    let cajaIdx = {{ count($cajas) }};
    document.getElementById('vd-add-caja')?.addEventListener('click', () => {
        const tb = document.querySelector('#vd-cajas-table tbody');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input name="cajas[${cajaIdx}][nombre]" placeholder="Nombre"></td>
            <td><input type="number" step="0.01" name="cajas[${cajaIdx}][efectivo_usd]" value="0"></td>
            <td><input type="number" step="0.01" name="cajas[${cajaIdx}][efectivo_bs]" value="0"></td>
            <td><input type="number" step="0.01" name="cajas[${cajaIdx}][punto_venta]" value="0"></td>
            <td><input type="number" step="0.01" name="cajas[${cajaIdx}][transf_pm]" value="0"></td>
            <td><input type="number" step="0.01" name="cajas[${cajaIdx}][zelle_binance]" value="0"></td>
            <td><input type="number" step="0.01" name="cajas[${cajaIdx}][cashea]" value="0"></td>
            <td><input type="number" step="0.01" name="cajas[${cajaIdx}][fact_credito]" value="0"></td>
            <td><input type="number" step="0.01" name="cajas[${cajaIdx}][abonos]" value="0"></td>
            <td><input type="number" step="0.01" name="cajas[${cajaIdx}][iphone]" value="0"></td>
            <td><input type="number" step="0.01" name="cajas[${cajaIdx}][gift_card]" value="0"></td>
            <td><button type="button" class="btn vd-del-caja">×</button></td>`;
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
            const sede = form.querySelector('[name=sede]');
            const fecha = form.querySelector('[name=fecha]');
            document.getElementById('vd-modal-sub').textContent =
                (sede?.selectedOptions?.[0]?.textContent || sede?.value || '') + ' · ' + (fecha?.value || '');
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
