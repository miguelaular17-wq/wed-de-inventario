@extends('layouts.app')

@section('title', 'Cuadre de caja · '.($cuadre->usuario_nombre ?: $cuadre->usuario))

@php
    $dinero = fn ($n) => $n === null ? '—' : (round((float) $n, 2) < 0 ? '-' : '').'$'.number_format(abs((float) $n), 2, ',', '.');
    $monto = fn ($n, $usd) => $n === null ? '' : ($usd ? '$' : 'Bs ').number_format((float) $n, 2, ',', '.');
    $valor = fn ($n) => $n === null ? '' : number_format((float) $n, 2, '.', '');
    $colorDif = fn ($d) => $d === null ? 'inherit' : ($d < -0.004 ? '#b91c1c' : ($d > 0.004 ? '#15803d' : '#334155'));
    $volverUrl = $volver && str_starts_with($volver, url('/')) ? $volver : route('cuadre_caja.index', ['desde' => $cuadre->fecha_cierre->toDateString(), 'hasta' => $cuadre->fecha_cierre->toDateString()]);
    $estadoCaja = $cuadre->estadoTesoreria();
    $editable = $puedeConciliar && $cuadre->pendiente();
    $puedeCerrar = $puedeConciliar && ! $cuadre->cerrado();
    $badges = [
        'pendiente' => ['Pendiente', 'cc-badge--pendiente'],
        'procesado' => ['Procesado', 'cc-badge--procesado'],
        'cerrado' => ['Cerrado', 'cc-badge--cerrado'],
    ];
    $accionPrevia = old('accion');
    $tipoDif = fn ($d) => $d === null ? 'vacio' : (round((float) $d, 2) < 0 ? 'faltante' : (round((float) $d, 2) > 0 ? 'sobrante' : 'cuadra'));
    $filaDif = fn ($d) => $d === null ? '' : 'is-'.$tipoDif($d);
    $pildoraDif = function ($d) use ($tipoDif) {
        $tipo = $tipoDif($d);
        $texto = match ($tipo) {
            'vacio' => '—',
            'cuadra' => '✓ Cuadra',
            default => ($tipo === 'sobrante' ? '+' : '-').'$'.number_format(abs(round((float) $d, 2)), 2, ',', '.'),
        };

        return '<span class="cc-dif-pill is-'.$tipo.'">'.e($texto).'</span>';
    };
@endphp

@push('head')
<style>
.cc-titulo { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.cc-titulo h1 { margin:0; }
.cc-badge { font-size:.72rem; font-weight:700; padding:3px 10px; border-radius:999px; letter-spacing:.02em; text-transform:uppercase; }
.cc-badge--pendiente { background:#fef3c7; color:#92400e; }
.cc-badge--procesado { background:#dbeafe; color:#1e40af; }
.cc-badge--cerrado { background:#1e293b; color:#fff; }

.cc-estado { display:flex; gap:14px; align-items:flex-start; border-radius:12px; padding:14px 16px; margin-bottom:14px; border:1px solid; }
.cc-estado--procesado { background:#eff6ff; border-color:#bfdbfe; }
.cc-estado--cerrado { background:#f8fafc; border-color:#cbd5e1; border-left:4px solid #1e293b; }
.cc-estado--reabierto { background:#fffbeb; border-color:#fde68a; padding:10px 14px; font-size:.85rem; color:#92400e; }
.cc-estado-icono { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
.cc-estado--procesado .cc-estado-icono { background:#2563eb; color:#fff; }
.cc-estado--cerrado .cc-estado-icono { background:#1e293b; color:#fff; }
.cc-estado-cuerpo { flex:1; min-width:0; }
.cc-estado-cuerpo h3 { margin:0 0 2px; font-size:1rem; color:#0f172a; }
.cc-estado-cuerpo .quien { margin:0 0 10px; font-size:.8rem; color:#64748b; }
.cc-estado-datos { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px; }
.cc-dato { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:8px 10px; }
.cc-dato span { display:block; font-size:.68rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:2px; }
.cc-dato div { font-size:.9rem; color:#0f172a; white-space:pre-line; }
.cc-estado-botones { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }

.cc-fp-cab { display:flex; justify-content:space-between; align-items:flex-end; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
.cc-fp-cab h3 { margin:0; }
.cc-fp-cab p { margin:2px 0 0; font-size:.8rem; color:#64748b; }
.btn.cc-btn-chico { padding:6px 12px; font-size:.8rem; }

.cc-tabla-wrap { border:1px solid #e2e8f0; border-radius:12px; overflow-x:auto; }
.cc-tabla { width:100%; border-collapse:separate; border-spacing:0; font-size:.88rem; }
.cc-tabla th { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; background:#f8fafc; padding:8px 14px; border-bottom:1px solid #e2e8f0; white-space:nowrap; text-align:left; }
.cc-tabla tr.cc-grupos th { padding-top:10px; padding-bottom:4px; border-bottom:0; }
.cc-tabla tr.cc-grupos th[rowspan] { border-bottom:1px solid #e2e8f0; vertical-align:bottom; padding-bottom:8px; }
.cc-tabla th.cc-grupo { text-align:center; color:#334155; }
.cc-tabla th.is-tes { background:#eef4fc; color:#1a4480; }
.cc-tabla td { padding:11px 14px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.cc-tabla td.is-tes { background:#f7faff; }
.cc-tabla tbody tr.cc-linea:hover td { background:#f8fafc; }
.cc-tabla tbody tr.cc-linea:hover td.is-tes { background:#eef4fc; }
.cc-tabla tr.cc-seccion td { background:#fff; padding:14px 14px 6px; font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#1a4480; border-bottom:1px solid #e2e8f0; }
.cc-tabla tr.cc-seccion td span { margin-left:6px; font-weight:500; text-transform:none; letter-spacing:0; color:#64748b; font-size:.75rem; }
.cc-tabla tr.cc-pos.is-abierto td { border-bottom:0; }
.cc-lotes-toggle { display:inline-flex; align-items:center; gap:4px; margin-top:3px; padding:2px 9px; border:1px solid #bfdbfe; border-radius:999px; background:#eff6ff; color:#1d4ed8; font-size:.72rem; font-weight:700; cursor:pointer; }
.cc-lotes-toggle:hover { background:#dbeafe; }
.cc-lotes-toggle .cc-pos-n { color:inherit; font-size:inherit; }
.cc-lotes-toggle .cc-flecha { transition:transform .15s; font-size:.7rem; }
.cc-lotes-toggle[aria-expanded="true"] .cc-flecha { transform:rotate(180deg); }
.cc-lote-banco select { border:0; outline:none; padding:7px 8px; font-size:.82rem; font-weight:700; color:#0f172a; background:transparent; cursor:pointer; }
.cc-lote-banco span { background:#f8fafc; color:#475569; }
.cc-tabla tr.cc-subtotal td { background:#f1f5f9; font-weight:800; color:#0f172a; border-top:1px solid #e2e8f0; border-bottom:2px solid #cbd5e1; }
.cc-tabla tr.cc-subtotal td.is-tes { background:#e5eefb; }
.cc-tabla tr.cc-lotes-fila td { padding:0 14px 12px; border-bottom:1px solid #f1f5f9; background:#fff; }
.cc-lotes { margin-left:6px; padding:8px 0 2px 14px; border-left:2px solid #bfdbfe; }
.cc-lotes-cab { display:flex; gap:10px; align-items:baseline; flex-wrap:wrap; margin-bottom:8px; font-size:.78rem; color:#64748b; }
.cc-lotes-cab strong { color:#1a4480; font-size:.8rem; }
.cc-lotes-lista { display:flex; flex-wrap:wrap; gap:8px 14px; }
.cc-lote { display:inline-flex; align-items:center; gap:6px; }
.cc-lote .cc-input input { width:120px; }
.cc-lote .cc-lote-num input { width:110px; text-align:left; }
.cc-lote-num span { background:#f8fafc; color:#475569; }
.cc-lote-quitar { width:26px; height:26px; border-radius:50%; border:0; background:transparent; color:#94a3b8; cursor:pointer; font-size:.85rem; }
.cc-lote-quitar:hover { background:#fee2e2; color:#b91c1c; }
.cc-lotes-pie { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-top:8px; }
.cc-lote-agregar { border:1px dashed #93c5fd; background:#eff6ff; color:#1d4ed8; font-weight:700; font-size:.78rem; border-radius:8px; padding:6px 12px; cursor:pointer; }
.cc-lote-agregar:hover { background:#dbeafe; }
.cc-lotes-falta { font-size:.78rem; font-weight:700; }
.cc-lotes-chips { display:flex; flex-wrap:wrap; gap:6px; }
.cc-lote-chip { display:inline-flex; align-items:center; gap:6px; font-size:.78rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:999px; padding:3px 10px; color:#334155; }
.cc-pos-suma { display:inline-flex; flex-direction:column; align-items:flex-end; }
.cc-pos-suma strong { font-size:.95rem; color:#0f172a; }
.cc-pos-n { font-size:.7rem; color:#64748b; font-weight:600; }
.cc-tabla tbody tr td:first-child { box-shadow:inset 3px 0 0 transparent; }
.cc-tabla tbody tr.is-faltante td:first-child { box-shadow:inset 3px 0 0 #dc2626; }
.cc-tabla tbody tr.is-sobrante td:first-child { box-shadow:inset 3px 0 0 #16a34a; }
.cc-tabla tbody tr.is-cuadra td:first-child { box-shadow:inset 3px 0 0 #94a3b8; }
.cc-tabla .cc-num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
.cc-tabla th.cc-num { text-align:right; }
.cc-tabla .cc-suite { color:#334155; }
.cc-tabla .cc-suite-usd, .cc-tabla .cc-tes-usd { color:#64748b; font-size:.84rem; }
.cc-tabla tfoot td { background:#f8fafc; border-top:2px solid #e2e8f0; border-bottom:0; font-weight:800; color:#0f172a; padding:12px 14px; }
.cc-tabla tfoot td.is-tes { background:#eef4fc; }
.cc-tabla td.cc-col-forma { min-width:200px; }
.cc-forma { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.cc-forma strong { color:#0f172a; }
.cc-cuenta { font-size:.75rem; color:#64748b; margin-top:2px; }
.cc-moneda { font-size:.6rem; font-weight:800; padding:2px 6px; border-radius:4px; letter-spacing:.03em; }
.cc-moneda.is-bs { background:#dbeafe; color:#1e40af; }
.cc-moneda.is-usd { background:#dcfce7; color:#166534; }
.cc-moneda.is-extra { background:#fef3c7; color:#92400e; text-transform:uppercase; }

.cc-input-fila { display:inline-flex; align-items:center; gap:6px; }
.cc-input { display:inline-flex; align-items:stretch; border:1px solid #cbd5e1; border-radius:8px; background:#fff; overflow:hidden; transition:border-color .15s, box-shadow .15s; }
.cc-input span { display:flex; align-items:center; padding:0 9px; font-size:.75rem; font-weight:800; border-right:1px solid #e2e8f0; }
.cc-input.is-bs span { background:#eff6ff; color:#1e40af; }
.cc-input.is-usd span { background:#f0fdf4; color:#166534; }
.cc-input input { border:0; outline:none; width:130px; padding:8px 10px; text-align:right; font-size:.92rem; font-weight:700; font-variant-numeric:tabular-nums; background:transparent; color:#0f172a; -moz-appearance:textfield; appearance:textfield; }
.cc-input input::-webkit-outer-spin-button, .cc-input input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.cc-input input::placeholder { color:#cbd5e1; font-weight:500; }
.cc-input:focus-within { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37, 99, 235, .15); }
.cc-input:has(input:disabled) { background:#f1f5f9; }
.cc-igual { width:30px; height:30px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; color:#64748b; font-weight:800; cursor:pointer; }
.cc-igual:hover { background:#1a4480; border-color:#1a4480; color:#fff; }

.cc-dif-pill { display:inline-block; min-width:86px; text-align:center; padding:4px 10px; border-radius:999px; font-weight:800; font-size:.8rem; font-variant-numeric:tabular-nums; }
.cc-dif-pill.is-vacio { color:#cbd5e1; }
.cc-dif-pill.is-cuadra { background:#f1f5f9; color:#334155; }
.cc-dif-pill.is-faltante { background:#fee2e2; color:#b91c1c; }
.cc-dif-pill.is-sobrante { background:#dcfce7; color:#15803d; }

.cc-nota { font-size:.78rem; color:#64748b; margin:10px 0 0; }

.cc-acciones { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:12px; margin-top:16px; }
.cc-accion { border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; display:flex; flex-direction:column; gap:6px; background:#fff; }
.cc-accion h4 { margin:0; font-size:.95rem; display:flex; align-items:center; gap:8px; }
.cc-accion p { margin:0; font-size:.8rem; color:#64748b; flex:1; }
.cc-accion .btn { align-self:flex-start; margin-top:6px; }
.cc-accion--procesar { border-top:3px solid #2563eb; }
.cc-accion--cerrar { border-top:3px solid #b91c1c; }
.btn.cc-btn-cerrar { background:#b91c1c; }
.btn.cc-btn-cerrar:hover { background:#991b1b; }

.cc-modal-back { display:none; position:fixed; inset:0; z-index:2000; background:rgba(15, 23, 42, .6); align-items:center; justify-content:center; padding:20px; }
.cc-modal-back.open { display:flex; }
.cc-modal { background:#fff; border-radius:16px; width:min(560px, 100%); max-height:calc(100vh - 40px); display:flex; flex-direction:column; box-shadow:0 24px 60px rgba(0,0,0,.3); overflow:hidden; }
.cc-modal-cab { padding:18px 22px 14px; border-bottom:1px solid #f1f5f9; }
.cc-modal-cab h3 { margin:0; font-size:1.15rem; display:flex; align-items:center; gap:8px; }
.cc-modal-cab p { margin:4px 0 0; font-size:.82rem; color:#64748b; }
.cc-modal-resumen { display:grid; grid-template-columns:repeat(3, 1fr); background:#f8fafc; border-bottom:1px solid #f1f5f9; }
.cc-modal-resumen div { padding:10px 14px; text-align:center; }
.cc-modal-resumen span { display:block; font-size:.66rem; font-weight:700; color:#64748b; text-transform:uppercase; }
.cc-modal-resumen strong { font-size:1rem; }
.cc-modal-cuerpo { padding:16px 22px 4px; overflow-y:auto; flex:1; min-height:0; }
.cc-paso { margin-bottom:14px; }
.cc-paso label { display:flex; align-items:center; gap:8px; font-weight:700; font-size:.85rem; color:#0f172a; margin-bottom:6px; }
.cc-paso .num { width:22px; height:22px; border-radius:50%; background:#1a4480; color:#fff; font-size:.75rem; display:inline-flex; align-items:center; justify-content:center; }
.cc-paso select, .cc-paso textarea { width:100%; padding:9px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:.9rem; font-family:inherit; }
.cc-paso textarea { min-height:90px; resize:vertical; }
.cc-paso textarea:disabled { background:#f1f5f9; cursor:not-allowed; }
.cc-paso .ayuda { font-size:.75rem; color:#64748b; margin-top:4px; }
.cc-aviso { border-radius:8px; padding:9px 12px; font-size:.8rem; margin-bottom:12px; }
.cc-aviso--peligro { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.cc-aviso--info { background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }
.cc-modal-pie { display:flex; justify-content:flex-end; gap:8px; padding:12px 22px 18px; border-top:1px solid #f1f5f9; }
</style>
@endpush

@section('content')
<div class="page-header">
    <a href="{{ $volverUrl }}" class="btn secondary" style="margin-bottom:10px;">← Cuadre de caja</a>
    <div class="cc-titulo">
        <h1>{{ $cuadre->usuario_nombre ?: $cuadre->usuario }}</h1>
        <span class="cc-badge {{ $badges[$estadoCaja][1] }}">{{ $badges[$estadoCaja][0] }}</span>
    </div>
    <p class="lead">
        {{ $etiquetas[$cuadre->sede] ?? $cuadre->sede }} · {{ $cuadre->descripcion_caja ?: $cuadre->codigo_caja }}
        · {{ $cuadre->fecha_cierre->format('d/m/Y') }}
        · tasa {{ number_format($cuadre->factor_cambio, 2, ',', '.') }}
        · {{ $cuadre->id_cierre ? 'cierre N.º '.$cuadre->id_cierre.' en Suite' : 'sin cierre en Suite todavía' }}
    </p>
</div>

@if($cuadre->cerrado())
    <div class="cc-estado cc-estado--cerrado">
        <div class="cc-estado-icono">🔒</div>
        <div class="cc-estado-cuerpo">
            <h3>Caja cerrada</h3>
            <p class="quien">
                Por {{ $cuadre->conciliador?->name ?? '—' }} el {{ $cuadre->conciliado_at?->format('d/m/Y H:i') }}.
                El cierre es definitivo: no se puede reabrir.
            </p>
            <div class="cc-estado-datos">
                <div class="cc-dato"><span>Motivo</span><div>{{ $cuadre->motivoCierreTexto() ?? '—' }}</div></div>
                <div class="cc-dato"><span>Observación</span><div>{{ $cuadre->observacion_tesoreria ?: '—' }}</div></div>
            </div>
        </div>
    </div>
@elseif($cuadre->procesado())
    <div class="cc-estado cc-estado--procesado">
        <div class="cc-estado-icono">✓</div>
        <div class="cc-estado-cuerpo">
            <h3>Caja procesada</h3>
            <p class="quien">Por {{ $cuadre->conciliador?->name ?? '—' }} el {{ $cuadre->conciliado_at?->format('d/m/Y H:i') }}.</p>
            <div class="cc-estado-datos">
                <div class="cc-dato"><span>Observación</span><div>{{ $cuadre->observacion_tesoreria ?: '—' }}</div></div>
            </div>
            @if($puedeConciliar)
                <div class="cc-estado-botones">
                    <form method="POST" action="{{ route('cuadre_caja.reabrir', $cuadre) }}"
                          onsubmit="return confirm('¿Reabrir la caja para corregir los montos?');">
                        @csrf
                        <input type="hidden" name="volver" value="{{ $volver }}">
                        <button type="submit" class="btn secondary">↺ Reabrir caja</button>
                    </form>
                    <button type="button" class="btn cc-btn-cerrar" data-cc-accion="cerrar">🔒 Cerrar caja</button>
                </div>
            @endif
        </div>
    </div>
@elseif($cuadre->reabierto_at)
    <div class="cc-estado cc-estado--reabierto">
        Reabierta por {{ $cuadre->reabridor?->name ?? '—' }} el {{ $cuadre->reabierto_at->format('d/m/Y H:i') }}.
        Corrige los montos y vuelve a procesarla o ciérrala.
    </div>
@endif

<div class="nomina-kpis" style="margin-bottom:14px;">
    <div class="nomina-kpi"><span>Ingresos en Suite</span><strong id="cc-total-suite">{{ $dinero($ingresos_usd) }}</strong></div>
    <div class="nomina-kpi"><span>Contado por Tesorería</span><strong id="cc-total-tes">{{ $dinero($tesoreria_usd) }}</strong></div>
    <div class="nomina-kpi"><span>Diferencia</span><strong id="cc-total-dif" style="color:{{ $colorDif($diferencia_usd) }};">{{ $dinero($diferencia_usd) }}</strong></div>
</div>

<form method="POST" action="{{ route('cuadre_caja.conciliar', $cuadre) }}" class="panel" id="cc-form">
    @csrf
    <input type="hidden" name="volver" value="{{ $volver }}">
    <div class="cc-fp-cab">
        <div>
            <h3>Formas de pago</h3>
            <p>{{ $editable ? 'Anota lo que contó Tesorería de cada forma de pago, en su moneda.' : 'Lo que declaró la cajera en Suite contra lo que contó Tesorería.' }}</p>
        </div>
        @if($editable)
            <button type="button" class="btn secondary cc-btn-chico" id="cc-copiar"
                    title="Pone en cada casilla vacía lo que declaró la cajera en Suite. Los puntos de venta van por lotes.">⧉ Copiar lo de Suite</button>
        @endif
    </div>
    <div class="cc-tabla-wrap">
        <table class="cc-tabla" id="cc-tabla">
            <thead>
                <tr class="cc-grupos">
                    <th rowspan="2" class="cc-col-forma">Forma de pago</th>
                    <th colspan="2" class="cc-grupo">Declarado en Suite</th>
                    <th colspan="2" class="cc-grupo is-tes">Contado por Tesorería</th>
                    <th rowspan="2" class="cc-num">Diferencia</th>
                </tr>
                <tr>
                    <th class="cc-num">Monto</th>
                    <th class="cc-num">En USD</th>
                    <th class="cc-num is-tes">Monto</th>
                    <th class="cc-num is-tes">En USD</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $fila = compact('editable', 'cuadre', 'monto', 'dinero', 'valor', 'pildoraDif', 'filaDif', 'bancosPos', 'titularesPos');
                    $posLineas = array_values(array_filter($lineas, fn ($l) => $l['punto_venta']));
                    $otrasLineas = array_values(array_filter($lineas, fn ($l) => ! $l['punto_venta']));
                    $primerPos = collect($lineas)->search(fn ($l) => $l['punto_venta']);
                    $antesLineas = $primerPos === false ? [] : array_values(array_filter(array_slice($lineas, 0, $primerPos), fn ($l) => ! $l['punto_venta']));
                    $otrasLineas = array_values(array_udiff($otrasLineas, $antesLineas, fn ($a, $b) => strcmp($a['clave'], $b['clave'])));
                    $posSuiteBs = array_sum(array_map(fn ($l) => $l['en_dolares'] ? 0 : $l['suite'], $posLineas));
                    $posSuiteUsd = round(array_sum(array_column($posLineas, 'suite_usd')), 2);
                    $posContados = array_filter($posLineas, fn ($l) => $l['tesoreria_usd'] !== null);
                    $posTesBs = $posContados ? array_sum(array_map(fn ($l) => $l['en_dolares'] ? 0 : (float) $l['tesoreria'], $posContados)) : null;
                    $posTesUsd = $posContados ? round(array_sum(array_column($posContados, 'tesoreria_usd')), 2) : null;
                    $posDif = $posTesUsd !== null ? round($posTesUsd - $posSuiteUsd, 2) : null;
                @endphp

                @foreach($antesLineas as $l)
                    @include('cuadre_caja._linea', $fila + ['l' => $l])
                @endforeach

                @if($posLineas)
                    <tr class="cc-seccion"><td colspan="6">💳 Puntos de venta <span>Todos los bancos en una sola línea: el monto es la suma de los lotes. Toca «Lotes» para verlos o anotarlos con su banco.</span></td></tr>
                    @foreach($posLineas as $l)
                        @include('cuadre_caja._linea', $fila + ['l' => $l])
                    @endforeach
                    <tr class="cc-subtotal" id="cc-pos-subtotal" @if(count($posLineas) < 2) style="display:none;" @endif>
                        <td class="cc-col-forma">Total puntos de venta</td>
                        <td class="cc-num">{{ $posSuiteBs > 0 ? 'Bs '.number_format($posSuiteBs, 2, ',', '.') : '—' }}</td>
                        <td class="cc-num">{{ $dinero($posSuiteUsd) }}</td>
                        <td class="cc-num is-tes" id="cc-pos-tes">{{ $posTesBs !== null ? 'Bs '.number_format($posTesBs, 2, ',', '.') : '—' }}</td>
                        <td class="cc-num is-tes" id="cc-pos-tes-usd">{{ $dinero($posTesUsd) }}</td>
                        <td class="cc-num" id="cc-pos-dif">{!! $pildoraDif($posDif) !!}</td>
                    </tr>
                    @if($otrasLineas)
                        <tr class="cc-seccion"><td colspan="6">Otras formas de pago</td></tr>
                    @endif
                @endif

                @foreach($otrasLineas as $l)
                    @include('cuadre_caja._linea', $fila + ['l' => $l])
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td class="cc-col-forma">Total</td>
                    <td></td>
                    <td class="cc-num">{{ $dinero($ingresos_usd) }}</td>
                    <td class="is-tes"></td>
                    <td class="cc-num is-tes" id="cc-foot-tes">{{ $dinero($tesoreria_usd) }}</td>
                    <td class="cc-num" id="cc-foot-dif">{!! $pildoraDif($diferencia_usd) !!}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if($editable)
        <p class="cc-nota">
            ⓘ Los montos en Bs se pasan a USD con la tasa del día ({{ number_format($cuadre->factor_cambio, 2, ',', '.') }}).
            Al procesar o cerrar, una forma de pago vacía cuenta como $0 recibido.
        </p>

        <div class="cc-acciones">
            <div class="cc-accion cc-accion--procesar">
                <h4>✓ Procesar</h4>
                <p>Guarda el cuadre con una observación. Se puede reabrir después si hay que corregirlo.</p>
                <button type="button" class="btn primary" data-cc-accion="procesar">Procesar caja</button>
            </div>
            <div class="cc-accion cc-accion--cerrar">
                <h4>🔒 Cerrar caja</h4>
                <p>Cierre definitivo: primero eliges el motivo y luego escribes la observación. No se puede reabrir.</p>
                <button type="button" class="btn cc-btn-cerrar" data-cc-accion="cerrar">Cerrar caja</button>
            </div>
        </div>
    @endif

    @if($puedeCerrar)
        <div class="cc-modal-back" id="cc-modal" role="dialog" aria-modal="true" aria-labelledby="cc-modal-titulo"
             data-titulo-cerrar="🔒 Cerrar caja" data-titulo-procesar="✓ Procesar caja"
             data-boton-cerrar="Cerrar definitivamente" data-boton-procesar="Procesar caja">
            <div class="cc-modal">
                <div class="cc-modal-cab">
                    <h3 id="cc-modal-titulo">Procesar caja</h3>
                    <p>{{ $cuadre->usuario_nombre ?: $cuadre->usuario }} · {{ $cuadre->descripcion_caja ?: $cuadre->codigo_caja }} · {{ $cuadre->fecha_cierre->format('d/m/Y') }}</p>
                </div>
                <div class="cc-modal-resumen">
                    <div><span>Suite</span><strong id="cc-m-suite">—</strong></div>
                    <div><span>Tesorería</span><strong id="cc-m-tes">—</strong></div>
                    <div><span>Diferencia</span><strong id="cc-m-dif">—</strong></div>
                </div>
                <div class="cc-modal-cuerpo">
                    <input type="hidden" name="accion" id="cc-accion" value="{{ $accionPrevia ?: 'procesar' }}">

                    <div class="cc-paso" id="cc-paso-motivo">
                        <label for="cc-motivo"><span class="num">1</span> Motivo del cierre</label>
                        <select name="motivo" id="cc-motivo">
                            <option value="">Elige un motivo…</option>
                            @foreach($motivos as $clave => $texto)
                                <option value="{{ $clave }}" @selected(old('motivo') === $clave)>{{ $texto }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cc-paso">
                        <label for="cc-obs"><span class="num" id="cc-num-obs">1</span> Observación</label>
                        <textarea id="cc-obs" name="observacion" maxlength="2000" required
                                  placeholder="Ej.: faltante de $5 en efectivo, la cajera lo repone mañana">{{ old('observacion', $cuadre->observacion_tesoreria) }}</textarea>
                        <div class="ayuda" id="cc-obs-ayuda">Explica cómo quedó la caja: diferencias, quién responde, qué falta por confirmar.</div>
                    </div>

                    <div class="cc-aviso cc-aviso--peligro" id="cc-aviso-cerrar">
                        <strong>El cierre es definitivo.</strong> Después no se podrá reabrir la caja ni cambiar los montos.
                    </div>
                    <div class="cc-aviso cc-aviso--info" id="cc-aviso-procesar">
                        La caja queda procesada. Si hay que corregirla, se puede reabrir.
                    </div>
                </div>
                <div class="cc-modal-pie">
                    <button type="button" class="btn secondary" id="cc-cancelar">Cancelar</button>
                    <button type="submit" class="btn primary" id="cc-confirmar">Procesar caja</button>
                </div>
            </div>
        </div>
    @endif
</form>

<script>
(function () {
    const factor = {{ $cuadre->factor_cambio > 0 ? $cuadre->factor_cambio : 0 }};
    const fmt = (n) => {
        const r = Math.round(n * 100) / 100;
        return (r < 0 ? '-' : '') + '$' + Math.abs(r).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    };
    const color = (d) => d < -0.004 ? '#b91c1c' : (d > 0.004 ? '#15803d' : '#334155');
    const editable = {{ $editable ? 'true' : 'false' }};
    const bs = (n) => 'Bs ' + (Math.round(n * 100) / 100).toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const tipo = (d) => d === null ? 'vacio' : (Math.round(d * 100) < 0 ? 'faltante' : (Math.round(d * 100) > 0 ? 'sobrante' : 'cuadra'));
    const pildora = function (d) {
        const t = tipo(d);
        const texto = t === 'vacio' ? '—' : (t === 'cuadra' ? '✓ Cuadra' : (t === 'sobrante' ? '+' : '-') + fmt(Math.abs(d)).replace('-', ''));
        return '<span class="cc-dif-pill is-' + t + '">' + texto + '</span>';
    };
    const filaDe = (clave) => [...document.querySelectorAll('#cc-tabla .cc-linea')].find(f => f.dataset.clave === clave);
    const lotesDe = (clave) => [...document.querySelectorAll('#cc-tabla .cc-lotes-fila')].find(f => f.dataset.para === clave);

    function recalcular() {
        let totalTes = 0, totalSuite = 0, posTes = 0, posTesBs = 0, posSuite = 0, posContado = false;
        document.querySelectorAll('#cc-tabla .cc-linea').forEach(function (fila) {
            if (fila.style.display === 'none') return;
            const input = fila.querySelector('.cc-monto');
            const suiteUsd = parseFloat(fila.dataset.suiteUsd || '0');
            const valor = input && input.value !== '' ? parseFloat(input.value) : null;
            const usd = valor === null ? 0 : (fila.dataset.usd === '1' ? valor : (factor ? valor / factor : 0));
            const dif = valor === null ? null : usd - suiteUsd;
            totalTes += usd;
            totalSuite += suiteUsd;
            if (fila.dataset.pos === '1') {
                posSuite += suiteUsd;
                posTes += usd;
                posTesBs += valor !== null && fila.dataset.usd !== '1' ? valor : 0;
                posContado = posContado || valor !== null;
            }
            fila.querySelector('.cc-tes-usd').textContent = valor === null ? '—' : fmt(usd);
            fila.querySelector('.cc-dif').innerHTML = pildora(dif);
            fila.classList.remove('is-faltante', 'is-sobrante', 'is-cuadra');
            if (dif !== null) fila.classList.add('is-' + tipo(dif));
        });

        const posTesEl = document.getElementById('cc-pos-tes');
        if (posTesEl) {
            posTesEl.textContent = posContado ? bs(posTesBs) : '—';
            document.getElementById('cc-pos-tes-usd').textContent = posContado ? fmt(posTes) : '—';
            document.getElementById('cc-pos-dif').innerHTML = pildora(posContado ? posTes - posSuite : null);
        }

        const dif = totalTes - totalSuite;
        ['cc-total-tes', 'cc-foot-tes'].forEach(id => { const el = document.getElementById(id); if (el) el.textContent = fmt(totalTes); });
        const kpi = document.getElementById('cc-total-dif');
        if (kpi) { kpi.textContent = fmt(dif); kpi.style.color = color(dif); }
        const pie = document.getElementById('cc-foot-dif');
        if (pie) pie.innerHTML = pildora(dif);
    }

    function sumarLotes(clave) {
        const fila = filaDe(clave), panel = lotesDe(clave);
        if (!fila || !panel) return;
        const anotados = [...panel.querySelectorAll('.cc-lote')].filter(function (l) {
            const num = l.querySelector('input[type=text]'), m = l.querySelector('.cc-lote-monto');
            return !m.disabled && (num.value.trim() !== '' || m.value !== '');
        });
        const suma = anotados.reduce((s, l) => s + (parseFloat(l.querySelector('.cc-lote-monto').value) || 0), 0);
        const hayLotes = anotados.length > 0;
        fila.querySelector('.cc-monto').value = hayLotes ? suma.toFixed(2) : '';
        fila.querySelector('.cc-pos-suma strong').textContent = hayLotes ? bs(suma) : '—';
        fila.querySelector('.cc-pos-n').textContent = anotados.length === 0 ? 'Anotar lotes' : (anotados.length === 1 ? '1 lote' : anotados.length + ' lotes');

        const falta = panel.querySelector('.cc-lotes-falta');
        const suite = parseFloat(fila.dataset.suite || '0');
        const resta = Math.round((suite - suma) * 100) / 100;
        if (!hayLotes || fila.dataset.usd === '1') {
            falta.textContent = '';
        } else if (resta > 0) {
            falta.textContent = 'Faltan ' + bs(resta) + ' para llegar a lo de Suite';
            falta.style.color = '#b91c1c';
        } else if (resta < 0) {
            falta.textContent = 'Sobran ' + bs(-resta) + ' sobre lo de Suite';
            falta.style.color = '#15803d';
        } else {
            falta.textContent = '✓ Los lotes suman lo mismo que Suite';
            falta.style.color = '#334155';
        }
        recalcular();
    }

    function agregarLote(panel, enfocar) {
        const lista = panel.querySelector('.cc-lotes-lista');
        const i = parseInt(lista.dataset.siguiente || '0', 10);
        lista.dataset.siguiente = String(i + 1);
        const plantilla = panel.querySelector('.cc-lote-plantilla').innerHTML.replaceAll('__I__', String(i));
        lista.insertAdjacentHTML('beforeend', plantilla);
        if (enfocar) lista.lastElementChild.querySelector('input').focus();
        sumarLotes(panel.dataset.para);
    }

    function mostrarLotes(clave, abrir) {
        const fila = filaDe(clave), panel = lotesDe(clave);
        if (!fila || !panel) return;
        panel.style.display = abrir ? '' : 'none';
        fila.classList.toggle('is-abierto', abrir);
        fila.querySelector('.cc-lotes-toggle')?.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        if (abrir && editable && !panel.querySelector('.cc-lote')) agregarLote(panel, true);
    }

    document.getElementById('cc-tabla')?.addEventListener('click', function (e) {
        const toggle = e.target.closest('.cc-lotes-toggle');
        if (!toggle) return;
        const fila = toggle.closest('.cc-linea');
        mostrarLotes(fila.dataset.clave, toggle.getAttribute('aria-expanded') !== 'true');
    });

    if (editable) {
        document.querySelectorAll('#cc-tabla .cc-monto').forEach(i => i.addEventListener('input', recalcular));
        if ([...document.querySelectorAll('#cc-tabla .cc-monto')].some(i => !i.disabled && i.value !== '')) {
            recalcular();
        }
        document.querySelectorAll('#cc-tabla .cc-lotes-fila').forEach(function (panel) {
            if (panel.querySelector('.cc-lote')) sumarLotes(panel.dataset.para);
        });

        document.getElementById('cc-tabla').addEventListener('click', function (e) {
            const igual = e.target.closest('.cc-igual');
            if (igual) {
                const fila = igual.closest('.cc-linea');
                fila.querySelector('.cc-monto').value = parseFloat(fila.dataset.suite || '0').toFixed(2);
                recalcular();
                return;
            }
            const agregar = e.target.closest('.cc-lote-agregar');
            if (agregar) {
                agregarLote(agregar.closest('.cc-lotes-fila'), true);
                return;
            }
            const quitar = e.target.closest('.cc-lote-quitar');
            if (quitar) {
                const panel = quitar.closest('.cc-lotes-fila');
                quitar.closest('.cc-lote').remove();
                sumarLotes(panel.dataset.para);
            }
        });
        document.getElementById('cc-tabla').addEventListener('input', function (e) {
            if (e.target.classList.contains('cc-lote-monto')) sumarLotes(e.target.closest('.cc-lotes-fila').dataset.para);
        });
        document.getElementById('cc-tabla').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.closest('.cc-lote')) {
                e.preventDefault();
                agregarLote(e.target.closest('.cc-lotes-fila'), true);
            }
        });

        document.getElementById('cc-copiar')?.addEventListener('click', function () {
            document.querySelectorAll('#cc-tabla .cc-linea').forEach(function (fila) {
                const input = fila.querySelector('.cc-monto');
                if (fila.dataset.pos !== '1' && input && !input.disabled && input.value === '') {
                    input.value = parseFloat(fila.dataset.suite || '0').toFixed(2);
                }
            });
            recalcular();
        });
    }

    const modal = document.getElementById('cc-modal');
    if (!modal) return;
    const motivo = document.getElementById('cc-motivo');
    const obs = document.getElementById('cc-obs');
    const confirmar = document.getElementById('cc-confirmar');

    function pasoObservacion() {
        const esCerrar = document.getElementById('cc-accion').value === 'cerrar';
        obs.disabled = esCerrar && motivo.value === '';
        obs.placeholder = obs.disabled ? 'Primero elige el motivo del cierre' : 'Ej.: faltante de $5 en efectivo, la cajera lo repone mañana';
    }

    function abrir(accion) {
        const esCerrar = accion === 'cerrar';
        document.getElementById('cc-accion').value = accion;
        document.getElementById('cc-modal-titulo').textContent = esCerrar ? modal.dataset.tituloCerrar : modal.dataset.tituloProcesar;
        document.getElementById('cc-paso-motivo').style.display = esCerrar ? '' : 'none';
        motivo.required = esCerrar;
        motivo.disabled = !esCerrar;
        document.getElementById('cc-num-obs').textContent = esCerrar ? '2' : '1';
        document.getElementById('cc-aviso-cerrar').style.display = esCerrar ? '' : 'none';
        document.getElementById('cc-aviso-procesar').style.display = esCerrar ? 'none' : '';
        confirmar.textContent = esCerrar ? modal.dataset.botonCerrar : modal.dataset.botonProcesar;
        confirmar.className = esCerrar ? 'btn cc-btn-cerrar' : 'btn primary';

        ['suite', 'tes', 'dif'].forEach(function (k) {
            const origen = document.getElementById(k === 'suite' ? 'cc-total-suite' : 'cc-total-' + k);
            const destino = document.getElementById('cc-m-' + k);
            destino.textContent = origen ? origen.textContent : '—';
            destino.style.color = origen ? origen.style.color : '';
        });

        pasoObservacion();
        modal.classList.add('open');
        (esCerrar && motivo.value === '' ? motivo : obs).focus();
    }

    function cerrarModal() { modal.classList.remove('open'); }

    document.querySelectorAll('[data-cc-accion]').forEach(b => b.addEventListener('click', () => abrir(b.dataset.ccAccion)));
    document.getElementById('cc-cancelar').addEventListener('click', cerrarModal);
    modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('open')) cerrarModal(); });
    motivo.addEventListener('change', function () {
        pasoObservacion();
        if (!obs.disabled) obs.focus();
    });

    @if($accionPrevia)
        abrir(@json($accionPrevia));
    @endif
})();
</script>
@endsection
