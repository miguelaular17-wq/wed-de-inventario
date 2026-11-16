<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Factura de pago {{ $pago->id }} · {{ $factura->numeroFormateado() }}</title>
<style>
    @page { margin: 18px 20px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    .cab { width: 100%; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
    .cab td { border: none; vertical-align: middle; padding: 0; }
    .cab img { height: 48px; }
    h1 { margin: 0; font-size: 16px; color: #1e3a8a; text-transform: uppercase; }
    .sub { color: #64748b; margin-top: 3px; font-size: 10px; }
    .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 12px; margin-bottom: 10px; }
    .box h2 { margin: 0 0 8px; font-size: 12px; color: #1e3a8a; text-transform: uppercase; }
    table.datos { width: 100%; border-collapse: collapse; }
    table.datos td { padding: 3px 0; vertical-align: top; }
    table.datos td.l { width: 140px; color: #64748b; }
    .num { text-align: right; white-space: nowrap; font-weight: bold; }
    .grande { font-size: 18px; color: #166534; }
    .saldo { font-size: 14px; color: #b45309; }
    .pie { margin-top: 14px; font-size: 9px; color: #94a3b8; border-top: 1px dashed #cbd5e1; padding-top: 6px; }
</style>
</head>
<body>
@php
    $usd = fn ($n) => '$'.number_format((float) $n, 2, ',', '.');
    $abonado = (float) ($pago->monto_usd ?? 0);
    $saldo = (float) $saldoRestante;
@endphp
<table class="cab">
    <tr>
        <td style="width:70px;">@if($logo)<img src="{{ $logo }}" alt="Logo">@endif</td>
        <td>
            <h1>Factura de pago</h1>
            <div class="sub">Abono a factura {{ $factura->numeroFormateado() }} · {{ $pago->fecha?->format('d/m/Y') }}</div>
        </td>
        <td style="width:160px; text-align:right; color:#64748b;">
            N.º pago <strong style="color:#1e293b;">{{ $pago->id }}</strong><br>
            {{ now()->format('d/m/Y H:i') }}
        </td>
    </tr>
</table>

<div class="box">
    <h2>Cliente</h2>
    <table class="datos">
        <tr><td class="l">Nombre</td><td><strong>{{ $factura->cliente?->nombre }}</strong></td></tr>
        <tr><td class="l">RIF / C.I.</td><td>{{ $factura->cliente?->documento }}</td></tr>
        @if($factura->cliente?->telefono)
            <tr><td class="l">Teléfono</td><td>{{ $factura->cliente->telefono }}</td></tr>
        @endif
    </table>
</div>

<div class="box">
    <h2>Detalle del abono</h2>
    <table class="datos">
        <tr><td class="l">Factura afectada</td><td class="num">{{ $factura->numeroFormateado() }}</td></tr>
        <tr><td class="l">Total factura</td><td class="num">{{ $usd($factura->total_usd) }}</td></tr>
        <tr><td class="l">Método de pago</td><td class="num">{{ $pago->forma_pago ?: '—' }}</td></tr>
        @if($pago->referencia)
            <tr><td class="l">Referencia</td><td class="num">{{ $pago->referencia }}</td></tr>
        @endif
        <tr><td class="l">Monto abonado</td><td class="num grande">{{ $usd($abonado) }}</td></tr>
        <tr><td class="l">Saldo por cobrar</td><td class="num saldo">{{ $usd($saldo) }}</td></tr>
        @if($factura->tasa_bcv > 0)
            <tr><td class="l">Equiv. Bs (tasa {{ number_format($factura->tasa_bcv, 4, ',', '.') }})</td><td class="num">Bs {{ number_format($abonado * $factura->tasa_bcv, 2, ',', '.') }}</td></tr>
        @endif
        <tr><td class="l">Registró</td><td>{{ $pago->usuario?->name ?? '—' }}</td></tr>
    </table>
</div>

<p class="pie">
    Documento de pago / abono de Ventas al mayor.
    @if($empresa)
        {{ $empresa['razon_social'] ?? '' }} {{ isset($empresa['rif']) ? '· '.$empresa['rif'] : '' }}
    @endif
</p>
</body>
</html>
