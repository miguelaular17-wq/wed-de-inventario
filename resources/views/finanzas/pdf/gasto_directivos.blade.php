<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Gasto Directivos</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 18px; }
        .header { width: 100%; margin-bottom: 16px; border-bottom: 2px solid #1a4273; padding-bottom: 10px; }
        .header td { vertical-align: middle; border: none; padding: 0; }
        .header img { height: 64px; width: auto; }
        .header h2 { margin: 0; color: #1a4273; font-size: 16px; }
        .header p { margin: 3px 0 0; color: #64748b; font-size: 10px; }
        .kpis { width: 100%; margin: 12px 0 16px; border-collapse: collapse; }
        .kpis td { width: 25%; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px; text-align: center; }
        .kpis span { display: block; font-size: 8px; text-transform: uppercase; color: #64748b; letter-spacing: .04em; }
        .kpis strong { display: block; margin-top: 4px; font-size: 13px; color: #1a4273; }
        .section-title { font-size: 11px; font-weight: bold; color: #fff; background: #1a4273; margin: 14px 0 0; padding: 6px 8px; }
        table.mov { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.mov th, table.mov td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; }
        table.mov th { background: #e2e8f0; font-size: 8px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .total-row { background: #f1f5f9; font-weight: bold; }
        .muted { color: #64748b; }
        .footer { margin-top: 18px; font-size: 8px; color: #94a3b8; text-align: right; }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td style="width: 90px;">
            @if(!empty($logo))
                <img src="{{ $logo }}" alt="Logo">
            @elseif(is_file(public_path('logo.png')))
                <img src="{{ public_path('logo.png') }}" alt="Logo">
            @endif
        </td>
        <td>
            <h2>Palacio de los Detalles — Gasto Directivos</h2>
            <p>Desde {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} hasta {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p>
            <p>José Leonardo Jerez (V24703210) · María Núñez (V24525502)</p>
        </td>
        <td style="width: 130px; text-align: right; color: #64748b; font-size: 9px;">
            Generado<br>{{ $generado ?? now()->format('d/m/Y H:i') }}
        </td>
    </tr>
</table>

<table class="kpis">
    <tr>
        <td><span>Egresos USD</span><strong>${{ number_format($totales['egresos_usd'], 2) }}</strong></td>
        <td><span>Egresos Bs</span><strong>Bs {{ number_format($totales['egresos_bs'], 2) }}</strong></td>
        <td><span>Movimientos</span><strong>{{ number_format($totales['egresos_count']) }}</strong></td>
        <td><span>Saldo cobranza</span><strong>${{ number_format($totales['cobranza_saldo'], 2) }}</strong></td>
    </tr>
</table>

<div class="section-title">Cobranza — Directivos</div>
<table class="mov">
    <thead>
        <tr>
            <th style="width:18%">Código</th>
            <th>Nombre</th>
            <th style="width:14%" class="text-right">Documentos</th>
            <th style="width:18%" class="text-right">Saldo USD</th>
        </tr>
    </thead>
    <tbody>
        @foreach($personas as $p)
            <tr>
                <td>{{ $p['codigo'] }}</td>
                <td>{{ $p['nombre'] }}</td>
                <td class="text-right">{{ $p['documentos'] }}</td>
                <td class="text-right">${{ number_format($p['saldo'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="3">Total cobranza</td>
            <td class="text-right">${{ number_format($totales['cobranza_saldo'], 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="section-title">Egresos por concepto</div>
<table class="mov">
    <thead>
        <tr>
            <th>Concepto</th>
            <th style="width:14%" class="text-right">Mov.</th>
            <th style="width:18%" class="text-right">USD</th>
            <th style="width:20%" class="text-right">Bs</th>
        </tr>
    </thead>
    <tbody>
        @foreach($egresos_por_concepto as $c)
            @if($c['count'] > 0)
                <tr>
                    <td>{{ $c['codigo'] }} — {{ $c['label'] }}</td>
                    <td class="text-right">{{ $c['count'] }}</td>
                    <td class="text-right">${{ number_format($c['usd'], 2) }}</td>
                    <td class="text-right">Bs {{ number_format($c['bs'], 2) }}</td>
                </tr>
            @endif
        @endforeach
        <tr class="total-row">
            <td>Total egresos</td>
            <td class="text-right">{{ number_format($totales['egresos_count']) }}</td>
            <td class="text-right">${{ number_format($totales['egresos_usd'], 2) }}</td>
            <td class="text-right">Bs {{ number_format($totales['egresos_bs'], 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="section-title">Detalle de egresos</div>
@if($egresos->isEmpty())
    <p class="muted" style="padding:12px; border:1px solid #cbd5e1;">Sin egresos en el rango.</p>
@else
    <table class="mov">
        <thead>
            <tr>
                <th style="width:12%">Fecha</th>
                <th style="width:28%">Concepto</th>
                <th>Motivo</th>
                <th style="width:14%" class="text-right">USD</th>
                <th style="width:16%" class="text-right">Bs</th>
            </tr>
        </thead>
        <tbody>
            @foreach($egresos as $mov)
                <tr>
                    <td>{{ $mov->fecha ? date('d/m/Y', strtotime((string) $mov->fecha)) : '—' }}</td>
                    <td>{{ $mov->tipo_gasto ?: '—' }}</td>
                    <td>{{ $mov->motivo ?: '—' }}</td>
                    <td class="text-right">${{ number_format((float) $mov->monto_usd, 2) }}</td>
                    <td class="text-right">Bs {{ number_format((float) $mov->monto_bs, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">Documento generado desde Nexo PD · Gasto Directivos</div>
</body>
</html>
