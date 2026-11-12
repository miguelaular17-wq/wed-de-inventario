<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Factura {{ $factura->numeroFormateado() }}</title>
<style>
    @page { margin: 28px 32px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
    .cab { width: 100%; border-bottom: 3px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
    .cab td { vertical-align: top; }
    .empresa { font-size: 18px; font-weight: bold; color: #1e3a8a; }
    .titulo { font-size: 16px; font-weight: bold; text-align: right; }
    .muted { color: #64748b; }
    .caja { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
    table.items th { background: #1e3a8a; color: #fff; padding: 6px; font-size: 10px; text-align: left; }
    table.items td { padding: 6px; border-bottom: 1px solid #e2e8f0; }
    .num { text-align: right; }
    .totales { width: 45%; margin-left: 55%; margin-top: 10px; border-collapse: collapse; }
    .totales td { padding: 5px 6px; }
    .totales .grande { font-size: 14px; font-weight: bold; background: #1e3a8a; color: #fff; }
    .anulada { position: fixed; top: 300px; left: 80px; font-size: 90px; color: rgba(220, 38, 38, .18); transform: rotate(-30deg); font-weight: bold; }
</style>
</head>
<body>
@if($factura->estaAnulada())
    <div class="anulada">ANULADA</div>
@endif
<table class="cab">
    <tr>
        <td>
            <div class="empresa">{{ config('app.name', 'Nexo PD') }}</div>
            <div class="muted">Ventas al mayor · despacho desde JRZ</div>
        </td>
        <td class="titulo">
            FACTURA DE COMPRA<br>
            <span style="font-size:13px;">{{ $factura->numeroFormateado() }}</span><br>
            <span class="muted" style="font-size:11px; font-weight:normal;">Fecha: {{ $factura->fecha->format('d/m/Y') }}</span>
        </td>
    </tr>
</table>

<table style="width:100%;">
    <tr>
        <td style="width:60%; padding-right:8px;">
            <div class="caja">
                <strong>Cliente</strong><br>
                {{ $factura->cliente?->nombre }}<br>
                RIF / C.I.: {{ $factura->cliente?->documento }}<br>
                @if($factura->cliente?->telefono) Tel.: {{ $factura->cliente->telefono }}<br>@endif
                @if($factura->cliente?->direccion) {{ $factura->cliente->direccion }}@endif
            </div>
        </td>
        <td style="width:40%;">
            <div class="caja">
                <strong>Condición:</strong> {{ $factura->esCredito() ? 'Crédito' : 'Contado' }}@if($factura->esCredito() && $factura->fecha_vencimiento) · vence {{ $factura->fecha_vencimiento->format('d/m/Y') }}@endif<br>
                <strong>Vendedora:</strong> {{ $factura->vendedor_nombre }}<br>
                <strong>Tasa BCV:</strong> Bs {{ number_format($factura->tasa_bcv, 4, ',', '.') }}<br>
                <strong>Emitida por:</strong> {{ $factura->usuario?->name ?? '—' }}
            </div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr><th style="width:14%;">Código</th><th>Descripción</th><th class="num" style="width:10%;">Cant.</th><th class="num" style="width:14%;">Precio $</th><th class="num" style="width:15%;">Total $</th></tr>
    </thead>
    <tbody>
        @foreach($factura->items as $i)
            <tr>
                <td>{{ $i->codigo }}</td>
                <td>{{ $i->descripcion }}</td>
                <td class="num">{{ $i->cantidad }}</td>
                <td class="num">{{ number_format($i->precio_usd, 2) }}</td>
                <td class="num">{{ number_format($i->total_usd, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totales">
    <tr><td>Unidades</td><td class="num">{{ $factura->items->sum('cantidad') }}</td></tr>
    <tr class="grande"><td>TOTAL $</td><td class="num">{{ number_format($factura->total_usd, 2) }}</td></tr>
    <tr><td>Total en Bs (tasa BCV)</td><td class="num">Bs {{ number_format($factura->total_bs, 2, ',', '.') }}</td></tr>
    <tr><td>Pagado $</td><td class="num">{{ number_format($factura->totalPagadoUsd(), 2) }}</td></tr>
    <tr><td><strong>Saldo por pagar $</strong></td><td class="num"><strong>{{ number_format($factura->saldoUsd(), 2) }}</strong></td></tr>
</table>

@php $conMonto = $factura->pagos->filter(fn ($p) => $p->monto_usd > 0); @endphp
@if($conMonto->isNotEmpty())
    <p style="margin-top:14px;"><strong>Pagos registrados:</strong>
        {{ $conMonto->map(fn ($p) => collect([$p->fecha?->format('d/m/Y'), $p->forma_pago, $p->referencia ? 'Ref '.$p->referencia : null, '$'.number_format($p->monto_usd, 2)])->filter()->implode(' '))->implode(' · ') }}
    </p>
@endif
@if($factura->observaciones)
    <p><strong>Observaciones:</strong> {{ $factura->observaciones }}</p>
@endif
@if($factura->estaAnulada())
    <p style="color:#dc2626;"><strong>Anulada:</strong> {{ $factura->anulada_motivo }}</p>
@endif
</body>
</html>
