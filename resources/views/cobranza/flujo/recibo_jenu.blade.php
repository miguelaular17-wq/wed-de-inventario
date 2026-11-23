<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo {{ $r['numero'] }}</title>
<style>
    @page { margin: 18mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #000; }
    table { width: 100%; border-collapse: collapse; }
    .header td { vertical-align: top; border: none; padding: 0; }
    .logo { height: 72px; }
    .empresa { text-align: right; font-size: 9px; line-height: 1.55; }
    .empresa .nombre { font-size: 10.5px; font-weight: bold; text-decoration: underline; }
    .titulo-row td { vertical-align: middle; border: none; padding: 0; }
    .fecha-tbl { border-collapse: collapse; font-size: 10px; }
    .fecha-tbl th, .fecha-tbl td { border: 1.5px solid #000; padding: 4px 12px; text-align: center; font-weight: bold; }
    .ctrl { color: #cc0000; font-weight: bold; font-size: 12px; text-align: center; }
    .titulo { font-size: 20px; font-weight: bold; letter-spacing: 2px; text-align: center; }
    .monto-box { border: 2.5px solid #000; padding: 6px 14px; text-align: center; min-width: 90px; }
    .monto-box .lbl { font-size: 8px; font-weight: bold; }
    .monto-box .val { font-size: 18px; font-weight: bold; }
    hr { border: 0; border-top: 1.5px solid #000; margin: 10px 0; }
    .campo { margin: 10px 0; }
    .campo .lbl { font-weight: bold; white-space: nowrap; }
    .campo .val { border-bottom: 1.2px solid #000; padding: 0 0 2px 6px; }
    .italic { font-style: italic; }
    .right { text-align: right; font-size: 9px; font-weight: bold; margin: 2px 0 8px; }
    .checks td { border: none; padding: 3px 8px 3px 0; font-size: 10px; vertical-align: middle; }
    .box { display: inline-block; border: 1.4px solid #000; width: 12px; height: 12px; text-align: center; font-size: 9px; font-weight: bold; line-height: 12px; }
    .firmas td { border: none; text-align: center; vertical-align: bottom; padding-top: 36px; width: 45%; }
    .firma-img { height: 48px; margin-bottom: 4px; }
    .firma-linea { border-top: 1.4px solid #000; padding-top: 6px; font-weight: bold; font-size: 10px; }
</style>
</head>
<body>
@php
    $e = $r['empresa'] ?? [];
    $f = $r['formas'] ?? [];
    $chk = fn ($k) => ! empty($f[$k]) ? 'X' : '';
@endphp

<table class="header">
    <tr>
        <td style="width:38%;">
            @if(! empty($r['logo']))
                <img class="logo" src="{{ $r['logo'] }}" alt="Logo">
            @endif
        </td>
        <td class="empresa">
            <div class="nombre">Grupo Inmobiliario y de Transporte</div>
            <div class="nombre">Je Nu &amp; Asociados, C.A.</div>
            <div>Rif.: {{ $e['rif'] ?? 'J-50255135-2' }}</div>
            <div>Calle Girardot, con Av. Santa Irene, Punto Fijo - Edo.</div>
            <div>Falcon, Zona Postal 4102. <strong>Teléfono:</strong> 0412-6937658</div>
        </td>
    </tr>
</table>

<table class="titulo-row" style="margin-top:14px;">
    <tr>
        <td style="width:28%;">
            <table class="fecha-tbl">
                <tr><th>DIA</th><th>MES</th><th>AÑO</th></tr>
                <tr>
                    <td>{{ $r['dia'] }}</td>
                    <td>{{ $r['mes'] }}</td>
                    <td>{{ $r['anio'] }}</td>
                </tr>
            </table>
        </td>
        <td style="width:44%; text-align:center;">
            <div class="ctrl">{{ $r['control'] }}</div>
            <div class="titulo">RECIBO</div>
        </td>
        <td style="width:28%; text-align:right;">
            <div class="monto-box" style="display:inline-block;">
                <div class="lbl">BS/$.</div>
                <div class="val">{{ $r['monto_caja'] }}</div>
            </div>
        </td>
    </tr>
</table>

<hr>

<table class="campo">
    <tr>
        <td class="lbl" style="width:90px;">Recibí de:</td>
        <td class="val">{{ $r['cliente'] }}</td>
    </tr>
</table>
<table class="campo">
    <tr>
        <td class="lbl" style="width:90px;">La Suma de:</td>
        <td class="val italic">{{ $r['suma_letras'] }}</td>
    </tr>
</table>
<div class="right">Bolivares / Dólares</div>

<hr>

<table class="campo">
    <tr>
        <td class="lbl" style="width:110px;">Por Concepto de</td>
        <td class="val">{{ $r['concepto'] }}</td>
    </tr>
</table>

<hr>

<table style="margin:14px 0 8px;">
    <tr>
        <td style="width:70px; font-weight:bold; text-decoration:underline; vertical-align:top; border:none;">Forma de<br>Pago:</td>
        <td style="border:none;">
            <table class="checks">
                <tr>
                    <td>Efectivo <span class="box">{{ $chk('efectivo') }}</span></td>
                    <td>Transferencia Bs <span class="box">{{ $chk('transf_bs') }}</span></td>
                    <td>Transferencia en divisa <span class="box">{{ $chk('transf_divisa') }}</span></td>
                    <td>Pago Movil <span class="box">{{ $chk('pago_movil') }}</span></td>
                </tr>
                <tr>
                    <td>Zelle <span class="box">{{ $chk('zelle') }}</span></td>
                    <td>Binance <span class="box">{{ $chk('binance') }}</span></td>
                    <td>Otro <span class="box">{{ $chk('otro') }}</span></td>
                    <td>Titular: <span style="display:inline-block; border-bottom:1.2px solid #000; min-width:120px; padding:0 4px;">{{ $r['titular'] ?: ' ' }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="firmas">
    <tr>
        <td>
            @if(! empty($r['firma']))
                <img class="firma-img" src="{{ $r['firma'] }}" alt="Firma">
            @else
                <div style="height:48px;"></div>
            @endif
            <div class="firma-linea">Recibi Conforme</div>
        </td>
        <td>
            <div style="height:48px;"></div>
            <div class="firma-linea">Entregue Conforme</div>
        </td>
    </tr>
</table>
</body>
</html>
