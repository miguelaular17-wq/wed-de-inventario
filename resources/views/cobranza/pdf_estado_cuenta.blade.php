<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Estado de cuenta</title>
    <style>
        @page { margin: 28px 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; margin: 0; }
        .empresa { text-align: center; margin-bottom: 14px; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        .empresa img { height: 42px; margin-bottom: 4px; }
        .empresa h1 { margin: 0; font-size: 16px; color: #1e3a8a; letter-spacing: .02em; }
        .empresa .rif { margin: 2px 0 0; color: #64748b; font-size: 9px; }
        .empresa .dir { margin: 2px 0 0; color: #94a3b8; font-size: 8px; }
        .titulo { text-align: center; margin: 10px 0 12px; font-size: 13px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: .06em; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta .lbl { color: #64748b; width: 70px; }
        .meta .val { font-weight: bold; color: #0f172a; }
        .sec { margin: 16px 0 6px; font-size: 11px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        table.lista { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.lista th { background: #1e3a8a; color: #fff; font-size: 8px; text-transform: uppercase; padding: 6px 5px; text-align: left; }
        table.lista th.r, table.lista td.r { text-align: right; }
        table.lista td { border-bottom: 1px solid #e5e7eb; padding: 5px; vertical-align: top; }
        tr.abono td { background: #f8fafc; }
        tr.tot td { font-weight: bold; background: #f1f5f9; border-top: 2px solid #1e3a8a; }
        .resumen { width: 100%; border-collapse: collapse; margin: 8px 0 14px; }
        .resumen td { padding: 8px 10px; border: 1px solid #e2e8f0; }
        .resumen .k { color: #64748b; font-size: 9px; text-transform: uppercase; }
        .resumen .v { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .resumen .saldo .v { color: #b91c1c; font-size: 15px; }
        .nota { color: #64748b; font-size: 8px; margin-top: 8px; }
        .footer { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 8px; color: #94a3b8; }
        .footer .pagina:after { content: "Página " counter(page); }
        .rel { margin: 0 0 10px; padding: 6px 8px; background: #f5f3ff; border: 1px solid #ddd6fe; color: #5b21b6; font-size: 9px; }
    </style>
</head>
<body>
@php
    $m = fn ($n) => $n === null || $n === '' ? '—' : '$'.number_format((float) $n, 2, ',', '.');
    $empresa = $empresa ?? ['razon_social' => config('app.name'), 'rif' => '', 'direccion' => []];
    $movimientos = $movimientos ?? [];
    $estadoFacturas = $estado_facturas ?? [];
    $resumen = $resumen ?? [
        'total_facturado' => $totales['cargos'] ?? 0,
        'total_abonado' => $totales['abonos'] ?? 0,
        'saldo_pendiente' => $totales['saldo'] ?? 0,
    ];
    $periodoTexto = $periodo['texto'] ?? ($fecha_sync ?? '—');
    $detallado = ! empty($detallado);
@endphp

<div class="footer">
    <span>{{ $empresa['razon_social'] }} · Estado de cuenta · {{ now()->format('d/m/Y H:i') }}</span>
    <span class="pagina" style="float:right;"></span>
</div>

<div class="empresa">
    @if(! empty($logo))
        <div><img src="{{ $logo }}" alt="Logo"></div>
    @endif
    <h1>{{ $empresa['razon_social'] }}</h1>
    @if(! empty($empresa['rif']))
        <div class="rif">RIF {{ $empresa['rif'] }}</div>
    @endif
    @if(! empty($empresa['direccion']))
        <div class="dir">{{ implode(' · ', $empresa['direccion']) }}</div>
    @endif
</div>

<div class="titulo">Estado de cuenta</div>

<table class="meta">
    <tr>
        <td class="lbl">Cliente:</td>
        <td class="val">{{ $cliente ?: '—' }}</td>
        <td class="lbl" style="text-align:right; padding-right:6px;">Período:</td>
        <td class="val" style="text-align:right; width:160px;">{{ $periodoTexto }}</td>
    </tr>
    <tr>
        <td class="lbl">RIF / CI:</td>
        <td class="val">{{ $rif ?? $codigo_cliente }}</td>
        <td class="lbl" style="text-align:right; padding-right:6px;">Código:</td>
        <td class="val" style="text-align:right;">{{ $codigo_cliente }}</td>
    </tr>
</table>

@if(! empty($intermedia_de))
    <div class="rel">Intermediario de: {{ implode(', ', $intermedia_de) }}</div>
@endif
@if(! empty($intermediado_por))
    <div class="rel">Facturas llevadas por: {{ implode(', ', $intermediado_por) }}</div>
@endif

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
<div class="sec">Detalle de facturas y abonos</div>
<table class="lista">
    <thead>
        <tr>
            <th style="width:48px;">Fecha</th>
            <th style="width:28px;">Doc.</th>
            <th style="width:58px;">N.º documento</th>
            <th>Descripción</th>
            <th class="r" style="width:32px;">Cant.</th>
            <th class="r" style="width:58px;">Precio regular</th>
            <th class="r" style="width:58px;">Total</th>
            <th class="r" style="width:62px;">Total factura</th>
            <th class="r" style="width:58px;">Descuento</th>
            <th class="r" style="width:54px;">Pagado</th>
            <th class="r" style="width:62px;">Saldo acumulado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($movimientos as $mov)
            @php $esProd = ($mov['tipo'] ?? '') === 'producto'; $esAbono = ($mov['tipo'] ?? '') === 'abono'; @endphp
            <tr class="{{ $esAbono ? 'abono' : ($esProd ? 'detalle' : '') }}">
                <td>{{ $esProd ? '' : ($mov['fecha'] ?? '') }}</td>
                <td>
                    @if(! $esProd)
                        <strong style="color:{{ $esAbono ? '#15803d' : '#b45309' }};">{{ $mov['doc'] ?? '' }}</strong>
                    @endif
                </td>
                <td>{{ $esProd ? '' : ($mov['numero'] ?? '') }}</td>
                <td style="{{ $esProd ? 'padding-left:10px;color:#475569;' : '' }}">
                    {{ $mov['descripcion'] ?? '' }}
                    @if(! $esProd && ! empty($mov['sede']))
                        <span style="color:#94a3b8;"> · {{ $mov['sede'] }}</span>
                    @endif
                </td>
                <td class="r">{{ $esProd ? $fmtCant($mov['cantidad'] ?? null) : '—' }}</td>
                <td class="r">{{ $esProd && isset($mov['precio']) ? $m($mov['precio']) : '—' }}</td>
                <td class="r" style="color:#64748b; font-weight:bold;">{{ $esProd && isset($mov['total']) ? $m($mov['total']) : '—' }}</td>
                <td class="r" style="color:#b45309; font-weight:bold;">
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
                <td class="r" style="color:#7c3aed; font-weight:bold;">{{ $esProd || $esAbono ? '' : $fmtNetoDesc($mov) }}</td>
                <td class="r" style="color:#15803d; font-weight:bold;">{{ isset($mov['pagado']) && $mov['pagado'] !== null ? $m($mov['pagado']) : '—' }}</td>
                <td class="r" style="color:#b91c1c; font-weight:bold;">{{ $esProd ? '' : $m($mov['saldo_acumulado'] ?? null) }}</td>
            </tr>
        @empty
            <tr><td colspan="11" style="text-align:center; padding:16px; color:#94a3b8;">Sin movimientos en el período.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="sec">Resumen de cuenta</div>
<table class="resumen">
    <tr>
        <td style="width:33%;">
            <div class="k">Total facturado</div>
            <div class="v" style="color:#b45309;">{{ $m($resumen['total_facturado'] ?? 0) }}</div>
        </td>
        <td style="width:33%;">
            <div class="k">Total abonado</div>
            <div class="v" style="color:#15803d;">{{ $m($resumen['total_abonado'] ?? 0) }}</div>
        </td>
        <td class="saldo" style="width:34%;">
            <div class="k">Saldo pendiente por cobrar</div>
            <div class="v">{{ $m($resumen['saldo_pendiente'] ?? 0) }}</div>
        </td>
    </tr>
</table>

<div class="sec">Estado de las facturas</div>
<table class="lista">
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
                <td class="r" style="color:#b91c1c; font-weight:bold;">{{ $m($f['pendiente'] ?? 0) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align:center; padding:12px; color:#94a3b8;">Sin facturas abiertas.</td></tr>
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

@if(($totales['tasa_bcv'] ?? 0) > 1)
    <div class="nota">Referencia BCV {{ number_format($totales['tasa_bcv'], 4, ',', '.') }} · Saldo aproximado Bs {{ number_format($totales['saldo_bs'], 2, ',', '.') }}</div>
@endif
@if(($totales['saldo_intermediado'] ?? 0) > 0)
    <div class="nota">Incluye saldo propio {{ $m($totales['saldo_propio']) }} y como intermediario {{ $m($totales['saldo_intermediado']) }}.</div>
@endif

@if($detallado && ! empty($lineas))
    <div class="sec" style="margin-top:18px;">Anexo · detalle de artículos</div>
    <table class="lista">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Factura</th>
                <th>Artículo</th>
                <th class="r">Cant.</th>
                <th class="r">Precio</th>
                <th class="r">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lineas as $l)
                @continue(($l['tipo'] ?? '') === 'abono')
                <tr>
                    <td>{{ $l['fecha'] ?? '' }}</td>
                    <td>{{ $l['factura_padre'] ?? $l['numero_documento'] ?? '' }}</td>
                    <td>{{ $l['descripcion'] ?? '' }}</td>
                    <td class="r">{{ isset($l['cantidad']) ? number_format((float) $l['cantidad'], 2, ',', '.') : '' }}</td>
                    <td class="r">{{ isset($l['precio']) ? $m($l['precio']) : '' }}</td>
                    <td class="r">{{ isset($l['total']) ? $m($l['total']) : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
