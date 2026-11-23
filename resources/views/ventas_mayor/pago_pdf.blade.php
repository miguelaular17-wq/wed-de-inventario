<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Factura de pago {{ $pago->id }} · {{ $factura->numeroFormateado() }}</title>
<style>
    @page { margin: 18px 22px 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #0f172a; }
    .muted { color: #64748b; }
    .cab { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .cab td { vertical-align: middle; padding: 0; }
    .cab img { height: 52px; }
    .empresa { font-size: 15px; font-weight: bold; color: #1e3a8a; }
    .doc { border: 2px solid #1e3a8a; border-radius: 8px; padding: 7px 10px; text-align: right; }
    .doc .t { font-size: 9px; font-weight: bold; letter-spacing: 1px; color: #1e3a8a; text-transform: uppercase; }
    .doc .n { font-size: 16px; font-weight: bold; }
    .franja { height: 4px; background: #1e3a8a; margin: 8px 0 10px; }
    .caja { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; }
    .caja .t { font-size: 8.5px; font-weight: bold; color: #64748b; letter-spacing: .5px; text-transform: uppercase; margin-bottom: 3px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.items th { background: #1e3a8a; color: #fff; padding: 5px 6px; font-size: 8.5px; text-align: left; text-transform: uppercase; }
    table.items td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9.5px; }
    table.items tr:nth-child(even) td { background: #f8fafc; }
    .num { text-align: right; white-space: nowrap; }
    .resumen { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .resumen td { padding: 4px 8px; border-bottom: 1px solid #f1f5f9; }
    .resumen td.l { color: #64748b; width: 55%; }
    .resumen .abonado td { font-size: 13px; font-weight: bold; color: #166534; background: #f0fdf4; }
    .resumen .saldo td { font-size: 12px; font-weight: bold; color: #b45309; background: #fffbeb; }
    .resumen .total td { font-weight: bold; background: #eff6ff; color: #1e3a8a; }
    .pie { margin-top: 12px; font-size: 8.5px; color: #94a3b8; border-top: 1px dashed #cbd5e1; padding-top: 6px; text-align: center; }
</style>
</head>
<body>
@php
    $usd = fn ($n) => '$'.number_format((float) $n, 2, ',', '.');
    $abonado = (float) ($pago->monto_usd ?? 0);
    $saldo = (float) $saldoRestante;
    $pagadoAntes = (float) ($pagadoAntes ?? max(0, $pagadoHasta - $abonado));
    $items = $factura->items ?? collect();
@endphp

<table class="cab">
    <tr>
        <td style="width:70px;">@if($logo)<img src="{{ $logo }}" alt="Logo">@endif</td>
        <td style="padding-left:8px;">
            <div class="empresa">{{ $empresa['razon_social'] ?? 'Palacio de los Detalles' }}</div>
            @if(! empty($empresa['rif']))<div><strong>RIF:</strong> {{ $empresa['rif'] }}</div>@endif
            <div class="muted">Ventas al mayor · Comprobante de pago / abono</div>
        </td>
        <td style="width:210px;">
            <div class="doc">
                <div class="t">Factura de pago</div>
                <div class="n">N.º {{ $pago->id }}</div>
                <div>Abono a <strong>{{ $factura->numeroFormateado() }}</strong></div>
                <div>{{ $pago->fecha?->format('d/m/Y') }} · {{ now()->format('H:i') }}</div>
            </div>
        </td>
    </tr>
</table>
<div class="franja"></div>

<table style="width:100%; border-collapse:separate; border-spacing:0; margin-bottom:8px;">
    <tr>
        <td style="width:55%; padding-right:8px; vertical-align:top;">
            <div class="caja">
                <div class="t">Cliente</div>
                <div style="font-size:12px; font-weight:bold;">{{ $factura->cliente?->nombre }}</div>
                <div>RIF / C.I.: {{ $factura->cliente?->documento }}</div>
                @if($factura->cliente?->telefono)<div>Tel.: {{ $factura->cliente->telefono }}</div>@endif
                @if($factura->cliente?->direccion)<div class="muted">{{ $factura->cliente->direccion }}</div>@endif
            </div>
        </td>
        <td style="width:45%; vertical-align:top;">
            <div class="caja">
                <div class="t">Datos del pago</div>
                <div><strong>Método:</strong> {{ $pago->forma_pago ?: '—' }}</div>
                @if($pago->referencia)<div><strong>Referencia:</strong> {{ $pago->referencia }}</div>@endif
                <div><strong>Registró:</strong> {{ $pago->usuario?->name ?? '—' }}</div>
                <div><strong>Vendedora:</strong> {{ $factura->vendedor_nombre ?: '—' }}</div>
            </div>
        </td>
    </tr>
</table>

<div style="font-size:10px; font-weight:bold; color:#1e3a8a; text-transform:uppercase; margin-top:4px;">
    Productos de la factura {{ $factura->numeroFormateado() }}
</div>
<table class="items">
    <thead>
        <tr>
            <th style="width:4%;">#</th>
            <th style="width:16%;">Código</th>
            <th>Producto</th>
            <th class="num" style="width:14%;">Cantidad</th>
            <th class="num" style="width:12%;">Precio $</th>
            <th class="num" style="width:13%;">Total $</th>
        </tr>
    </thead>
    <tbody>
        @forelse($items as $n => $i)
            <tr>
                <td class="muted">{{ $n + 1 }}</td>
                <td>{{ $i->codigo }}</td>
                <td>{{ $i->descripcion }}</td>
                <td class="num">{{ $i->cantidadTexto() }}</td>
                <td class="num">{{ number_format((float) $i->precio_usd, 2, ',', '.') }}</td>
                <td class="num"><strong>{{ number_format((float) $i->total_usd, 2, ',', '.') }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted" style="text-align:center; padding:12px;">Sin productos cargados en la factura.</td></tr>
        @endforelse
    </tbody>
</table>

<table class="resumen">
    <tr class="total">
        <td class="l">Total factura {{ $factura->numeroFormateado() }}</td>
        <td class="num">{{ $usd($factura->total_usd) }}</td>
    </tr>
    @if($pagadoAntes > 0.009)
        <tr>
            <td class="l">Abonos anteriores</td>
            <td class="num">− {{ $usd($pagadoAntes) }}</td>
        </tr>
    @endif
    <tr class="abonado">
        <td class="l">Monto abonado en este pago</td>
        <td class="num">{{ $usd($abonado) }}</td>
    </tr>
    <tr class="saldo">
        <td class="l">Saldo por cobrar</td>
        <td class="num">{{ $usd($saldo) }}</td>
    </tr>
    @if($factura->tasa_bcv > 0)
        <tr>
            <td class="l">Equiv. Bs (tasa {{ number_format($factura->tasa_bcv, 4, ',', '.') }})</td>
            <td class="num">Bs {{ number_format($abonado * $factura->tasa_bcv, 2, ',', '.') }}</td>
        </tr>
    @endif
</table>

<p class="pie">
    Documento de pago / abono de Ventas al mayor.
    @if($empresa)
        {{ $empresa['razon_social'] ?? '' }} {{ isset($empresa['rif']) ? '· '.$empresa['rif'] : '' }}
    @endif
    · Incluye el detalle de productos de la factura asociada.
</p>
</body>
</html>
