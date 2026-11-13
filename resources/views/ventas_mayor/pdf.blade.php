@php
    $pagado = $factura->totalPagadoUsd();
    $saldo = $factura->saldoUsd();
    $unidades = $factura->items->sum(fn ($i) => $i->unidadesTotales());
    $pagos = $factura->pagos->filter(fn ($p) => $p->monto_usd > 0)->values();
    $usd = fn ($v) => '$'.number_format((float) $v, 2);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Factura {{ $factura->numeroFormateado() }}</title>
<style>
    @page { margin: 26px 30px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #0f172a; }
    .muted { color: #64748b; }
    .cab { width: 100%; border-collapse: collapse; }
    .cab td { vertical-align: top; }
    .logo { width: 78px; height: 78px; }
    .empresa { font-size: 17px; font-weight: bold; color: #1e3a8a; margin-top: 4px; }
    .doc { border: 2px solid #1e3a8a; border-radius: 8px; padding: 8px 12px; text-align: right; }
    .doc .t { font-size: 10px; font-weight: bold; letter-spacing: 1px; color: #1e3a8a; }
    .doc .n { font-size: 18px; font-weight: bold; }
    .franja { height: 4px; background: #1e3a8a; margin: 10px 0 12px; }
    .caja { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; }
    .caja .t { font-size: 8.5px; font-weight: bold; color: #64748b; letter-spacing: .5px; text-transform: uppercase; margin-bottom: 3px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
    table.items th { background: #1e3a8a; color: #fff; padding: 6px; font-size: 9px; text-align: left; text-transform: uppercase; }
    table.items td { padding: 6px; border-bottom: 1px solid #e2e8f0; }
    table.items tr:nth-child(even) td { background: #f8fafc; }
    .num { text-align: right; white-space: nowrap; }
    .totales { width: 100%; border-collapse: collapse; }
    .totales td { padding: 5px 8px; }
    .totales .grande td { font-size: 13px; font-weight: bold; background: #1e3a8a; color: #fff; }
    table.pagos { width: 100%; border-collapse: collapse; }
    table.pagos th { text-align: left; font-size: 8.5px; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #cbd5e1; padding: 4px 6px; }
    table.pagos td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; }
    .sello { position: fixed; top: 330px; left: 110px; font-size: 80px; font-weight: bold; transform: rotate(-25deg); }
    .sello.anulada { color: rgba(220, 38, 38, .16); }
    .sello.pagada { color: rgba(22, 163, 74, .13); }
    .firmas { width: 100%; margin-top: 46px; }
    .firmas td { width: 50%; text-align: center; padding: 0 30px; }
    .firmas .linea { border-top: 1px solid #334155; padding-top: 4px; font-size: 9px; color: #475569; }
    .pie { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 8px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
@if($factura->estaAnulada())
    <div class="sello anulada">ANULADA</div>
@elseif($saldo < 0.01)
    <div class="sello pagada">PAGADA</div>
@endif

<table class="cab">
    <tr>
        <td style="width:90px;">
            @if($logo)<img src="{{ $logo }}" class="logo" alt="">@endif
        </td>
        <td style="padding-left:6px;">
            <div class="empresa">{{ $empresa['razon_social'] ?? 'Palacio de los Detalles' }}</div>
            @if(! empty($empresa['rif']))<div><strong>RIF:</strong> {{ $empresa['rif'] }}</div>@endif
            @foreach(($empresa['direccion'] ?? []) as $linea)<div class="muted">{{ $linea }}</div>@endforeach
            <div class="muted">Ventas al mayor · Despacho desde JRZ</div>
        </td>
        <td style="width:210px;">
            <div class="doc">
                <div class="t">FACTURA AL MAYOR</div>
                <div class="n">{{ $factura->numeroFormateado() }}</div>
                <div>Fecha: <strong>{{ $factura->fecha->format('d/m/Y') }}</strong></div>
                <div>{{ $factura->esCredito() ? 'Crédito' : 'Contado' }}@if($factura->esCredito() && $factura->fecha_vencimiento) · vence {{ $factura->fecha_vencimiento->format('d/m/Y') }}@endif</div>
            </div>
        </td>
    </tr>
</table>
<div class="franja"></div>

<table style="width:100%; border-collapse:separate; border-spacing:0;">
    <tr>
        <td style="width:58%; padding-right:8px; vertical-align:top;">
            <div class="caja">
                <div class="t">Cliente</div>
                <div style="font-size:12px; font-weight:bold;">{{ $factura->cliente?->nombre }}</div>
                <div>RIF / C.I.: {{ $factura->cliente?->documento }}</div>
                @if($factura->cliente?->telefono)<div>Tel.: {{ $factura->cliente->telefono }}</div>@endif
                @if($factura->cliente?->email)<div>{{ $factura->cliente->email }}</div>@endif
                @if($factura->cliente?->direccion)<div class="muted">{{ $factura->cliente->direccion }}</div>@endif
            </div>
        </td>
        <td style="width:42%; vertical-align:top;">
            <div class="caja">
                <div class="t">Datos de la venta</div>
                <div><strong>Vendedora:</strong> {{ $factura->vendedor_nombre }}</div>
                <div><strong>Tasa BCV:</strong> Bs {{ number_format($factura->tasa_bcv, 4, ',', '.') }}</div>
                <div><strong>Emitida por:</strong> {{ $factura->usuario?->name ?? '—' }}</div>
            </div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width:4%;">#</th>
            <th style="width:17%;">Código</th>
            <th>Descripción</th>
            <th class="num" style="width:15%;">Cantidad</th>
            <th class="num" style="width:12%;">Precio $</th>
            <th class="num" style="width:13%;">Total $</th>
        </tr>
    </thead>
    <tbody>
        @foreach($factura->items as $n => $i)
            <tr>
                <td class="muted">{{ $n + 1 }}</td>
                <td>{{ $i->codigo }}</td>
                <td>{{ $i->descripcion }}</td>
                <td class="num">{{ $i->cantidadTexto() }}</td>
                <td class="num">{{ number_format($i->precio_usd, 2) }}</td>
                <td class="num"><strong>{{ number_format($i->total_usd, 2) }}</strong></td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="width:100%; margin-top:12px;">
    <tr>
        <td style="width:55%; vertical-align:top; padding-right:12px;">
            @if($pagos->isNotEmpty())
                <div class="caja">
                    <div class="t">Pagos registrados</div>
                    <table class="pagos">
                        <thead><tr><th>Fecha</th><th>Forma</th><th>Ref.</th><th class="num">Monto</th></tr></thead>
                        <tbody>
                            @foreach($pagos as $p)
                                <tr>
                                    <td>{{ $p->fecha?->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ $p->forma_pago ?: '—' }}</td>
                                    <td>{{ $p->referencia ?: '—' }}</td>
                                    <td class="num">{{ $usd($p->monto_usd) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if($factura->observaciones)
                <p style="margin:8px 0 0;"><strong>Observaciones:</strong> {{ $factura->observaciones }}</p>
            @endif
            @if($factura->estaAnulada())
                <p style="margin:8px 0 0; color:#dc2626;"><strong>Anulada:</strong> {{ $factura->anulada_motivo }}</p>
            @endif
        </td>
        <td style="width:45%; vertical-align:top;">
            <table class="totales caja" style="padding:0;">
                <tr><td>Unidades despachadas</td><td class="num">{{ number_format($unidades) }}</td></tr>
                <tr class="grande"><td>TOTAL $</td><td class="num">{{ $usd($factura->total_usd) }}</td></tr>
                <tr><td>Total en Bs (tasa BCV)</td><td class="num">Bs {{ number_format($factura->total_bs, 2, ',', '.') }}</td></tr>
                <tr><td>Pagado</td><td class="num">{{ $usd($pagado) }}</td></tr>
                <tr><td><strong>Saldo por pagar</strong></td><td class="num" style="color:{{ $saldo >= 0.01 ? '#b45309' : '#15803d' }};"><strong>{{ $usd($saldo) }}</strong></td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="firmas">
    <tr>
        <td><div class="linea">Entregado por</div></td>
        <td><div class="linea">Recibido conforme (cliente)</div></td>
    </tr>
</table>

<div class="pie">{{ $empresa['razon_social'] ?? 'Palacio de los Detalles' }} · Factura {{ $factura->numeroFormateado() }} · Generada el {{ now()->format('d/m/Y h:i a') }}</div>
</body>
</html>
