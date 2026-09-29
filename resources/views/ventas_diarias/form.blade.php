@extends('layouts.app')

@section('title', $reporte ? 'Editar ventas diarias' : 'Nuevo ventas diarias')

@push('head')
<style>
.vd-wrap { max-width: 1200px; }
.vd-title { font-size: 1.25rem; font-weight: 700; margin: 0 0 12px; color: #1e293b; }
.vd-grid { display: grid; grid-template-columns: minmax(280px, 1fr) minmax(320px, 1.4fr); gap: 16px; }
@media (max-width: 960px) { .vd-grid { grid-template-columns: 1fr; } }
.vd-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; }
.vd-card h3 { margin:0 0 10px; font-size:.95rem; color:#334155; }
.vd-meta-table, .vd-desglose, .vd-cajas { width:100%; border-collapse:collapse; font-size:.85rem; }
.vd-meta-table th, .vd-meta-table td,
.vd-desglose th, .vd-desglose td,
.vd-cajas th, .vd-cajas td { padding:6px 8px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.vd-desglose .lab { font-weight:600; color:#1e293b; white-space:nowrap; }
.vd-desglose input, .vd-cajas input, .vd-head input, .vd-head select {
    width:100%; min-width:0; padding:6px 8px; border:1px solid #cbd5e1; border-radius:6px; font-weight:600;
}
.vd-usd { color:#059669; font-weight:600; white-space:nowrap; }
.vd-pct { font-size:.8rem; color:#64748b; }
.vd-pct.neg { color:#dc2626; }
.vd-pct.pos { color:#16a34a; }
.vd-head { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:14px; align-items:flex-end; }
.vd-head .field { display:flex; flex-direction:column; gap:4px; min-width:140px; }
.vd-head label { font-size:.7rem; font-weight:700; color:#64748b; text-transform:uppercase; }
.vd-cajas-wrap { overflow-x:auto; }
.vd-cajas th { font-size:.7rem; text-transform:uppercase; color:#64748b; white-space:nowrap; }
.vd-actions { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; }
.vd-desglose input.vd-from-cajas {
    background: #f1f5f9;
    color: #0f172a;
    cursor: default;
}
</style>
@endpush

@section('content')
@php
    $fmtPct = function (?float $v) {
        if ($v === null) return '—';
        $cls = $v >= 0 ? 'pos' : 'neg';
        return '<span class="vd-pct '.$cls.'">'.($v >= 0 ? '+' : '').number_format($v * 100, 1).'%</span>';
    };
@endphp
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
                <input type="number" step="0.0001" min="0.0001" name="tasa" id="vd-tasa"
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

        <div class="vd-grid">
            <div class="vd-card">
                <h3>DESGLOSE</h3>
                <p class="muted" style="margin:0 0 8px;font-size:12px;">Se calcula solo con la suma de las cajas.</p>
                <table class="vd-desglose">
                    <tr>
                        <td class="lab"><span class="vd-check">✅</span> Divisas en efectivo:</td>
                        <td><input type="number" step="0.01" name="divisas_efectivo" class="vd-in vd-from-cajas" data-caja="efectivo_usd" readonly
                                   value="{{ old('divisas_efectivo', $reporte->divisas_efectivo ?? 0) }}"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="lab"><span class="vd-check">✅</span> Efectivo Bs:</td>
                        <td><input type="number" step="0.01" name="efectivo_bs" class="vd-in vd-from-cajas" data-caja="efectivo_bs" readonly
                                   value="{{ old('efectivo_bs', $reporte->efectivo_bs ?? 0) }}"></td>
                        <td class="vd-usd" id="vd-efectivo-usd">${{ number_format($totales['efectivo_bs_usd'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="lab"><span class="vd-check">✅</span> Punto de venta:</td>
                        <td><input type="number" step="0.01" name="punto_venta_bs" class="vd-in vd-from-cajas" data-caja="punto_venta" readonly
                                   value="{{ old('punto_venta_bs', $reporte->punto_venta_bs ?? 0) }}"></td>
                        <td class="vd-usd" id="vd-punto-usd">${{ number_format($totales['punto_venta_usd'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="lab"><span class="vd-check">✅</span> Transf/P.M.</td>
                        <td><input type="number" step="0.01" name="transf_pm_bs" class="vd-in vd-from-cajas" data-caja="transf_pm" readonly
                                   value="{{ old('transf_pm_bs', $reporte->transf_pm_bs ?? 0) }}"></td>
                        <td class="vd-usd" id="vd-transf-usd">${{ number_format($totales['transf_pm_usd'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="lab"><span class="vd-check">✅</span> Zelle y binance:</td>
                        <td><input type="number" step="0.01" name="zelle_binance" class="vd-in vd-from-cajas" data-caja="zelle_binance" readonly
                                   value="{{ old('zelle_binance', $reporte->zelle_binance ?? 0) }}"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="lab"><span class="vd-check">✅</span> Cashea financiado:</td>
                        <td><input type="number" step="0.01" name="cashea" class="vd-in vd-from-cajas" data-caja="cashea" readonly
                                   value="{{ old('cashea', $reporte->cashea ?? 0) }}"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="lab"><span class="vd-check">✅</span> Abono de apartado / Deuda</td>
                        <td><input type="number" step="0.01" name="abonos" class="vd-in vd-from-cajas" data-caja="abonos" readonly
                                   value="{{ old('abonos', $reporte->abonos ?? 0) }}"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="lab">Total cobros del dia</td>
                        <td class="vd-usd" id="vd-total-cobros">${{ number_format($totales['total_cobros'], 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="lab">Total creditos del dia</td>
                        <td><input type="number" step="0.01" name="total_creditos" class="vd-in vd-from-cajas" data-caja="fact_credito" readonly
                                   value="{{ old('total_creditos', $reporte->total_creditos ?? 0) }}"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="lab">Total de ventas del dia</td>
                        <td class="vd-usd" id="vd-total-ventas">${{ number_format($totales['total_ventas'], 2) }}</td>
                        <td id="vd-pct-venta">{!! $fmtPct($totales['pct_vs_meta_venta']) !!}</td>
                    </tr>
                    <tr>
                        <td class="lab">Total de productos vendidos</td>
                        <td><input type="number" step="0.01" name="productos_vendidos" class="vd-in" data-key="productos"
                                   value="{{ old('productos_vendidos', $reporte->productos_vendidos ?? 0) }}"></td>
                        <td id="vd-pct-prod">{!! $fmtPct($totales['pct_vs_meta_prod']) !!}</td>
                    </tr>
                    <tr>
                        <td class="lab">Z FISCAL (Monto en Bs)</td>
                        <td><input type="number" step="0.01" name="z_fiscal_bs" class="vd-in" data-key="z_fiscal"
                                   value="{{ old('z_fiscal_bs', $reporte->z_fiscal_bs ?? 0) }}"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="lab">Facturacion Fiscal</td>
                        <td class="vd-usd" id="vd-fiscal">${{ number_format($totales['facturacion_fiscal_usd'], 2) }}</td>
                        <td id="vd-pct-fiscal">{!! $fmtPct($totales['pct_fiscal']) !!}</td>
                    </tr>
                </table>

                <div style="margin-top:12px;">
                    <label class="muted" style="font-size:.75rem;font-weight:700;text-transform:uppercase;">Deliverys pendientes ($)</label>
                    <input type="number" step="0.01" name="deliverys_pendientes" class="vd-in"
                           style="width:100%;margin-top:4px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;font-weight:600;"
                           value="{{ old('deliverys_pendientes', $reporte->deliverys_pendientes ?? 0) }}">
                </div>
                <div style="margin-top:8px;">
                    <label class="muted" style="font-size:.75rem;font-weight:700;text-transform:uppercase;">Observaciones</label>
                    <textarea name="observaciones" rows="2" style="width:100%;margin-top:4px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;">{{ old('observaciones', $reporte->observaciones ?? '') }}</textarea>
                </div>
            </div>

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
                                <th>EFECTIVO $</th>
                                <th>EFECTIVO BS</th>
                                <th>PUNTO DE VENTA</th>
                                <th>TRANS/PAGO MOVIL</th>
                                <th>ZELLE / BINANCE</th>
                                <th>CASHEA</th>
                                <th>FACT A CREDITO</th>
                                <th>ABONOS</th>
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
        </div>

        <div class="vd-actions">
            <button class="btn primary" type="submit">Guardar reporte</button>
            <a class="btn" href="{{ route('ventas_diarias.index') }}">Volver</a>
        </div>
    </form>
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
        const creditos = num('[name=total_creditos]');
        const zfiscal = num('[name=z_fiscal_bs]');
        const productos = num('[name=productos_vendidos]');

        const eUsd = ebs / tasa;
        const pUsd = pbs / tasa;
        const tUsd = tbs / tasa;
        const cobros = divisas + eUsd + pUsd + tUsd + zelle + cashea + abonos;
        const ventas = cobros + creditos;
        const fiscal = zfiscal / tasa;

        document.getElementById('vd-efectivo-usd').textContent = money(eUsd);
        document.getElementById('vd-punto-usd').textContent = money(pUsd);
        document.getElementById('vd-transf-usd').textContent = money(tUsd);
        document.getElementById('vd-total-cobros').textContent = money(cobros);
        document.getElementById('vd-total-ventas').textContent = money(ventas);
        document.getElementById('vd-fiscal').textContent = money(fiscal);
        document.getElementById('vd-pct-venta').innerHTML = pctHtml(metaVenta > 0 ? (ventas / metaVenta) - 1 : null);
        document.getElementById('vd-pct-prod').innerHTML = pctHtml(metaProd > 0 ? (productos / metaProd) - 1 : null);
        document.getElementById('vd-pct-fiscal').innerHTML = pctHtml(ventas > 0 ? fiscal / ventas : null);
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
})();
</script>
@endsection
