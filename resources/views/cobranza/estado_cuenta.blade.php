@extends('layouts.app')
@section('title', 'Estado de cuenta · '.($cliente ?: $codigo_cliente))
@section('content')
@php
    $m = fn ($n) => $n === null || $n === '' ? '—' : '$'.number_format((float) $n, 2, ',', '.');
    $empresa = $empresa ?? ['razon_social' => config('app.name'), 'rif' => '', 'direccion' => []];
    $movimientos = $movimientos ?? [];
    $estadoFacturas = $estado_facturas ?? [];
    $resumen = $resumen ?? ['total_facturado' => 0, 'total_abonado' => 0, 'saldo_pendiente' => 0];
    $periodoTexto = $periodo['texto'] ?? ($fecha_sync ?? '—');
    $totales = $totales ?? [];
@endphp
<style>
    .edo-page { max-width: 980px; margin: 0 auto; padding: 20px; }
    .edo-acciones { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; margin-bottom:14px; }
    .edo-btn { padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; color:#334155; font-weight:600; cursor:pointer; text-decoration:none; font-size:.88rem; }
    .edo-btn.primario { background:#1e3a8a; color:#fff; border-color:#1e3a8a; }
    .edo-btn.ok { background:#166534; color:#fff; border-color:#166534; }
    .edo-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px 20px; margin-bottom:14px; }
    .edo-empresa { text-align:center; border-bottom:2px solid #1e3a8a; padding-bottom:12px; margin-bottom:14px; }
    .edo-empresa h1 { margin:0; font-size:1.15rem; font-weight:800; color:#1e3a8a; }
    .edo-empresa .rif { color:#64748b; font-size:.85rem; margin-top:2px; }
    .edo-empresa .dir { color:#94a3b8; font-size:.75rem; margin-top:2px; }
    .edo-titulo { text-align:center; font-size:.95rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; margin-bottom:14px; }
    .edo-meta { display:grid; grid-template-columns:1fr 1fr; gap:8px 24px; font-size:.92rem; }
    .edo-meta .lbl { color:#64748b; }
    .edo-sec { margin:0 0 6px; font-size:.78rem; font-weight:800; color:#1e3a8a; text-transform:uppercase; letter-spacing:.04em; }
    .edo-tabla { width:100%; border-collapse:collapse; font-size:.84rem; background:#fff; }
    .edo-tabla th { background:#1e3a8a; color:#fff; padding:9px 10px; text-align:left; font-size:.72rem; text-transform:uppercase; }
    .edo-tabla th.r, .edo-tabla td.r { text-align:right; }
    .edo-tabla td { padding:8px 10px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
    .edo-tabla tr.abono td { background:#f8fafc; }
    .edo-tabla tr.tot td { background:#f1f5f9; font-weight:800; border-top:2px solid #1e3a8a; }
    .edo-wrap { overflow-x:auto; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:14px; }
    .edo-resumen { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:14px; }
    .edo-resumen .box { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; }
    .edo-resumen .k { font-size:.72rem; color:#64748b; text-transform:uppercase; font-weight:700; }
    .edo-resumen .v { font-size:1.2rem; font-weight:800; margin-top:4px; }
    .edo-rel { margin-top:10px; padding:8px 10px; background:#f5f3ff; border:1px solid #ddd6fe; border-radius:8px; color:#5b21b6; font-size:.82rem; font-weight:600; }
    .edo-nota { margin-top:10px; font-size:.78rem; color:#64748b; }
    @media (max-width:720px) {
        .edo-meta, .edo-resumen { grid-template-columns:1fr; }
        .edo-meta > div:nth-child(even) { text-align:left !important; }
    }
    @media print {
        .edo-acciones { display:none !important; }
        .edo-page { padding:0; max-width:none; }
        .edo-card, .edo-wrap, .edo-resumen .box { border-color:#ccc; }
    }
</style>

<div class="edo-page">
    <div class="edo-acciones">
        <a href="{{ route('cobranza.index') }}" class="edo-btn">← Cobranza</a>
        <a href="{{ route('cobranza.estado_cuenta.cliente.pdf', $codigo_cliente) }}" class="edo-btn primario">⬇ PDF</a>
        <a href="{{ route('cobranza.estado_cuenta.cliente.pdf', $codigo_cliente) }}?detalle=1" class="edo-btn">⬇ PDF + detalle</a>
        <a href="{{ route('cobranza.estado_cuenta.cliente.excel', $codigo_cliente) }}" class="edo-btn ok">⬇ Excel</a>
        <button type="button" class="edo-btn" onclick="window.print()">Imprimir</button>
    </div>

    @if(($ok ?? true) === false)
        <div class="edo-card" style="color:#b91c1c;">{{ $message ?? 'No se pudo armar el estado de cuenta.' }}</div>
    @else
        <div class="edo-card">
            <div class="edo-empresa">
                <h1>{{ $empresa['razon_social'] ?? 'Empresa' }}</h1>
                @if(! empty($empresa['rif']))
                    <div class="rif">RIF {{ $empresa['rif'] }}</div>
                @endif
                @if(! empty($empresa['direccion']))
                    <div class="dir">{{ implode(' · ', $empresa['direccion']) }}</div>
                @endif
            </div>
            <div class="edo-titulo">Estado de cuenta</div>
            <div class="edo-meta">
                <div><span class="lbl">Cliente:</span> <strong>{{ $cliente ?: '—' }}</strong></div>
                <div style="text-align:right;"><span class="lbl">Período:</span> <strong>{{ $periodoTexto }}</strong></div>
                <div><span class="lbl">RIF / CI:</span> <strong>{{ $rif ?? $codigo_cliente }}</strong></div>
                <div style="text-align:right;"><span class="lbl">Código:</span> <strong>{{ $codigo_cliente }}</strong></div>
            </div>
            @if(! empty($intermedia_de))
                <div class="edo-rel">Intermediario de: {{ implode(', ', $intermedia_de) }}</div>
            @endif
            @if(! empty($intermediado_por))
                <div class="edo-rel">Facturas llevadas por: {{ implode(', ', $intermediado_por) }}</div>
            @endif
        </div>

        <div class="edo-sec">Detalle de facturas y abonos</div>
        <div class="edo-wrap">
            @php
                $fmtCant = fn ($n) => $n === null || $n === '' ? '—' : (fmod((float) $n, 1.0) < 0.001 ? (string) (int) $n : number_format((float) $n, 2, ',', '.'));
                $fmtNetoDesc = function ($mov) use ($m) {
                    if (! isset($mov['descuento']) || $mov['descuento'] === null || (float) $mov['descuento'] < 0.01) {
                        return '';
                    }

                    return isset($mov['total_factura']) && $mov['total_factura'] !== null
                        ? $m($mov['total_factura'])
                        : '';
                };
            @endphp
            <table class="edo-tabla">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Doc.</th>
                        <th>N.º documento</th>
                        <th>Descripción</th>
                        <th class="r">Cant.</th>
                        <th class="r">Precio regular</th>
                        <th class="r">Total</th>
                        <th class="r">Total factura</th>
                        <th class="r">Descuento</th>
                        <th class="r">Pagado</th>
                        <th class="r">Saldo acumulado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movimientos as $mov)
                        @php $esProd = ($mov['tipo'] ?? '') === 'producto'; $esAbono = ($mov['tipo'] ?? '') === 'abono'; @endphp
                        <tr class="{{ $esAbono ? 'abono' : ($esProd ? 'producto' : '') }}" @if($esProd) style="background:#f8fafc;" @endif>
                            <td style="white-space:nowrap;{{ $esProd ? 'color:#94a3b8;font-size:.8rem;' : '' }}">{{ $esProd ? '' : ($mov['fecha'] ?? '') }}</td>
                            <td>
                                @if($esAbono)
                                    <span style="display:inline-block;padding:1px 7px;border-radius:4px;font-size:.72rem;font-weight:800;color:#15803d;background:#dcfce7;">PAG</span>
                                @elseif(! $esProd)
                                    <span style="display:inline-block;padding:1px 7px;border-radius:4px;font-size:.72rem;font-weight:800;color:#b45309;background:#ffedd5;">{{ $mov['doc'] ?? 'FAC' }}</span>
                                @endif
                            </td>
                            <td style="font-weight:700;">{{ $esProd ? '' : ($mov['numero'] ?? '') }}</td>
                            <td style="{{ $esProd ? 'padding-left:18px;color:#475569;' : '' }}">
                                {{ $mov['descripcion'] ?? '' }}
                                @if(! $esProd && ! empty($mov['sede']))
                                    <span style="color:#94a3b8;"> · {{ $mov['sede'] }}</span>
                                @endif
                            </td>
                            <td class="r" style="white-space:nowrap;{{ $esProd ? 'color:#334155;' : 'color:#94a3b8;' }}">{{ $esProd ? $fmtCant($mov['cantidad'] ?? null) : '—' }}</td>
                            <td class="r" style="white-space:nowrap;{{ $esProd ? 'color:#334155;' : 'color:#94a3b8;' }}">{{ $esProd && isset($mov['precio']) ? $m($mov['precio']) : '—' }}</td>
                            <td class="r" style="white-space:nowrap;font-weight:700;color:#64748b;">{{ $esProd && isset($mov['total']) ? $m($mov['total']) : '—' }}</td>
                            <td class="r" style="white-space:nowrap;color:#b45309;font-weight:700;">
                                @if($esProd || $esAbono)
                                    —
                                @elseif(isset($mov['total']) && $mov['total'] !== null)
                                    {{ $m($mov['total']) }}
                                @elseif(isset($mov['total_factura']) && $mov['total_factura'] !== null)
                                    {{ $m($mov['total_factura']) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="r" style="white-space:nowrap;color:#7c3aed;font-weight:700;">{{ $esProd || $esAbono ? '' : $fmtNetoDesc($mov) }}</td>
                            <td class="r" style="color:#15803d;font-weight:700;white-space:nowrap;">{{ isset($mov['pagado']) && $mov['pagado'] !== null ? $m($mov['pagado']) : '—' }}</td>
                            <td class="r" style="color:#b91c1c;font-weight:800;white-space:nowrap;">{{ $esProd ? '' : $m($mov['saldo_acumulado'] ?? null) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" style="padding:20px;text-align:center;color:#94a3b8;">Sin movimientos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="edo-sec">Resumen de cuenta</div>
        <div class="edo-resumen">
            <div class="box">
                <div class="k">Total facturado</div>
                <div class="v" style="color:#b45309;">{{ $m($resumen['total_facturado'] ?? 0) }}</div>
            </div>
            <div class="box">
                <div class="k">Total abonado</div>
                <div class="v" style="color:#15803d;">{{ $m($resumen['total_abonado'] ?? 0) }}</div>
            </div>
            <div class="box" style="border-color:#fecaca;">
                <div class="k">Saldo pendiente por cobrar</div>
                <div class="v" style="color:#b91c1c;font-size:1.35rem;">{{ $m($resumen['saldo_pendiente'] ?? 0) }}</div>
            </div>
        </div>

        <div class="edo-sec">Estado de las facturas</div>
        <div class="edo-wrap">
            <table class="edo-tabla">
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th class="r">Total</th>
                        <th class="r">Abonado</th>
                        <th class="r">Pendiente</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($estadoFacturas as $f)
                        <tr>
                            <td>
                                <strong>{{ $f['factura'] ?? '' }}</strong>
                                @if(! empty($f['sede']))
                                    <span style="color:#94a3b8;"> · {{ $f['sede'] }}</span>
                                @endif
                            </td>
                            <td class="r">{{ $m($f['total'] ?? 0) }}</td>
                            <td class="r" style="color:#15803d;">{{ $m($f['abonado'] ?? 0) }}</td>
                            <td class="r" style="color:#b91c1c;font-weight:800;">{{ $m($f['pendiente'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="padding:16px;text-align:center;color:#94a3b8;">Sin facturas abiertas.</td></tr>
                    @endforelse
                    @if($estadoFacturas)
                        <tr class="tot">
                            <td>Total</td>
                            <td class="r">{{ $m($resumen['total_facturado'] ?? 0) }}</td>
                            <td class="r" style="color:#15803d;">{{ $m($resumen['total_abonado'] ?? 0) }}</td>
                            <td class="r" style="color:#b91c1c;">{{ $m($resumen['saldo_pendiente'] ?? 0) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if(($totales['tasa_bcv'] ?? 0) > 1)
            <div class="edo-nota">Referencia BCV {{ number_format($totales['tasa_bcv'], 4, ',', '.') }} · Saldo aproximado Bs {{ number_format($totales['saldo_bs'], 2, ',', '.') }}</div>
        @endif
        @if(($totales['saldo_intermediado'] ?? 0) > 0)
            <div class="edo-nota">Incluye propio {{ $m($totales['saldo_propio']) }} y como intermediario {{ $m($totales['saldo_intermediado']) }}.</div>
        @endif
    @endif
</div>
@endsection
