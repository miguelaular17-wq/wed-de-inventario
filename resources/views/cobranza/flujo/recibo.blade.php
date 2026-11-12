<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo {{ $r['numero'] }}</title>
<style>
    @page { margin: 10px 10px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #000; }
    .centro { text-align: center; }
    .cab { line-height: 1.35; margin-bottom: 6px; }
    .cab .nombre { font-weight: bold; font-size: 9px; }
    table { width: 100%; border-collapse: collapse; }
    .datos td { padding: 1px 0; vertical-align: top; }
    .datos td.l { width: 52px; }
    .caja { border: 1px solid #000; padding: 4px 5px; margin-top: 6px; }
    .caja .titulo { text-align: center; font-weight: bold; margin-bottom: 4px; }
    .caja td { padding: 2px 0; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .grande { font-size: 11px; font-weight: bold; }
    .total td { padding-top: 6px; font-size: 11px; font-weight: bold; }
    .linea { border-top: 1px dashed #000; margin: 6px 0; }
    .pie { font-size: 7.5px; color: #333; }
</style>
</head>
<body>
@php
    $bs = fn ($v) => number_format((float) $v, 2, ',', '.');
    $e = $r['empresa'];
@endphp
<div class="linea"></div>
<div class="cab centro">
    @if($e)
        <div>{{ $e['rif'] }}</div>
        <div class="nombre">{{ $e['razon_social'] }}</div>
        @foreach($e['direccion'] ?? [] as $d)<div>{{ $d }}</div>@endforeach
        @if(! empty($e['leyenda']))<div>{{ $e['leyenda'] }}</div>@endif
    @else
        <div class="nombre">{{ $r['sede'] ?: config('app.name') }}</div>
    @endif
</div>

<table class="datos">
    <tr><td class="l">Cliente:</td><td>{{ $r['cliente'] }}</td></tr>
    @if($r['documento'])<tr><td class="l">C.I./R.I.F.:</td><td>{{ $r['documento'] }}</td></tr>@endif
    @if($r['telefono'])<tr><td class="l">Teléfono:</td><td>{{ $r['telefono'] }}</td></tr>@endif
    @if($r['atendio'])<tr><td class="l">Atendió:</td><td>{{ mb_strtoupper($r['atendio'], 'UTF-8') }}</td></tr>@endif
    <tr><td class="l">FECHA</td><td>{{ $r['fecha'] }}</td></tr>
    <tr><td class="l">N.º</td><td>{{ $r['numero'] }}</td></tr>
</table>

<div class="caja">
    <div class="titulo">RECIBO DE PAGO</div>
    <table>
        <tr><td>{{ $r['detalle'] ? 'FACTURA AFECTADA:' : 'PAGO DE:' }}</td><td class="num">{{ $r['afecta'] }}</td></tr>
        <tr><td>METODO DE PAGO:</td><td class="num">{{ $r['metodo'] }}</td></tr>
        @if($r['referencia'])<tr><td>REFERENCIA:</td><td class="num">{{ $r['referencia'] }}</td></tr>@endif
        <tr><td>MONTO ABONADO:</td><td class="num grande">{{ $r['abonado_bs'] !== null ? $bs($r['abonado_bs']) : '$'.$bs($r['abonado_usd']) }}</td></tr>
        @if($r['por_pagar_usd'] !== null)
            <tr><td>MONTO POR PAGAR:</td><td class="num grande">{{ $r['por_pagar_bs'] !== null ? $bs($r['por_pagar_bs']) : '$'.$bs($r['por_pagar_usd']) }}</td></tr>
        @endif
    </table>
</div>

<table class="total">
    <tr><td>TOTAL ABONADO</td><td class="num">{{ $r['abonado_bs'] !== null ? $bs($r['abonado_bs']) : '$'.$bs($r['abonado_usd']) }}</td></tr>
</table>
<table>
    <tr><td>REF.</td><td class="num">{{ $bs($r['abonado_usd']) }}</td></tr>
    @if($r['por_pagar_usd'] !== null && $r['por_pagar_bs'] !== null)
        <tr><td>REF. POR PAGAR</td><td class="num">{{ $bs($r['por_pagar_usd']) }}</td></tr>
    @endif
    @if($r['tasa'])
        <tr class="pie"><td>Tasa</td><td class="num">{{ $bs($r['tasa']) }}</td></tr>
    @endif
</table>
<div class="linea"></div>
</body>
</html>
