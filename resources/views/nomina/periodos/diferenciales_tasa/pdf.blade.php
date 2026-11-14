<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diferencial de nómina por tasa {{ $periodo->etiqueta }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 14px; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 120px; }
        .header-logo img { height: 52px; width: auto; }
        .header-titles { display: table-cell; vertical-align: middle; }
        h1 { font-size: 16px; color: #1e3a8a; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 4px; }
        .resumen { margin-bottom: 10px; }
        .resumen span { display: inline-block; margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #dbeafe; color: #1e3a8a; font-size: 8px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #bfdbfe; text-align: center; }
        td { padding: 4px 5px; border: 1px solid #e2e8f0; font-size: 9px; }
        .num { text-align: right; white-space: nowrap; }
        .pos { color: #166534; font-weight: bold; }
        .nopag { color: #b91c1c; font-weight: bold; }
        .tot td { font-weight: bold; background: #f1f5f9; }
        .note { margin-top: 10px; color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
    @php
        $tasaPagada = (float) $diferencial->tasa_pagada;
        $tasaNueva = (float) $diferencial->tasa_nueva;
        $fmtTasa = fn ($v) => number_format((float) $v, 4, ',', '.');
    @endphp
    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="header-titles">
            <h1>Diferencial de nómina por tasa</h1>
            <div class="sub" style="font-weight:bold;color:#1e3a8a;">{{ $diferencial->motivo }}</div>
            <div class="sub">
                Quincena {{ $periodo->etiqueta }}
                · Pago {{ $diferencial->fecha_pago?->format('d/m/Y') }}
                · Tasa pagada {{ $fmtTasa($tasaPagada) }} → tasa nueva {{ $fmtTasa($tasaNueva) }}
                · Creado por {{ $diferencial->creador?->name ?? '—' }}
                · {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <div class="resumen">
        <span>Personas: <strong>{{ count($filas) }}</strong></span>
        <span>Ya se les pagó la diferencia: <strong>{{ collect($filas)->where('situacion', 'Ya se le pagó la diferencia')->count() }}</strong></span>
        <span>Nómina USD: <strong>{{ number_format($totales['total_usd'], 2) }}</strong></span>
        <span>Diferencia de tasa: <strong>{{ $fmtTasa($tasaNueva - $tasaPagada) }}</strong></span>
        <span>A pagar Bs: <strong>{{ number_format($totales['a_pagar_bs'], 2) }}</strong></span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Cédula</th>
                <th>Empleado</th>
                <th>Empresa</th>
                <th>Sede / área</th>
                <th>Situación</th>
                <th>Nómina USD</th>
                <th>Pagado Bs<br>(tasa {{ $fmtTasa($tasaPagada) }})</th>
                <th>Correspondía Bs<br>(tasa {{ $fmtTasa($tasaNueva) }})</th>
                <th>Diferencia Bs</th>
                <th>A pagar Bs</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $f)
                <tr>
                    <td>{{ $f['cedula'] }}</td>
                    <td>{{ $f['nombre'] }}</td>
                    <td>{{ $f['empresa'] }}</td>
                    <td>{{ $f['sede'] }}</td>
                    <td class="{{ $f['a_pagar_bs'] > 0 ? 'nopag' : '' }}">{{ $f['situacion'] }}</td>
                    <td class="num">{{ number_format($f['total_usd'], 2) }}</td>
                    <td class="num">{{ number_format($f['bs_pagado'], 2) }}</td>
                    <td class="num">{{ number_format($f['bs_nuevo'], 2) }}</td>
                    <td class="num">{{ number_format($f['diferencia_bs'], 2) }}</td>
                    <td class="num pos">{{ number_format($f['a_pagar_bs'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="tot">
                <td colspan="5">Totales ({{ count($filas) }} empleados)</td>
                <td class="num">{{ number_format($totales['total_usd'], 2) }}</td>
                <td class="num">{{ number_format($totales['bs_pagado'], 2) }}</td>
                <td class="num">{{ number_format($totales['bs_nuevo'], 2) }}</td>
                <td class="num">{{ number_format($totales['diferencia_bs'], 2) }}</td>
                <td class="num">{{ number_format($totales['a_pagar_bs'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="note">Diferencia Bs = nómina USD × (tasa nueva − tasa con que se pagó). "A pagar" incluye solo a quienes no se les canceló la diferencia; es lo que va en el TXT. Los montos en USD son los de la quincena calculada; no se recalculan.</p>
</body>
</html>
