<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diferencial de comisiones {{ $periodo->etiqueta }}</title>
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
        .pcts { margin-bottom: 10px; font-size: 8px; color: #475569; }
        .pcts span { display: inline-block; border: 1px solid #e2e8f0; padding: 2px 5px; margin: 0 3px 3px 0; }
        .pcts span.cambiado { border-color: #f59e0b; background: #fffbeb; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #dbeafe; color: #1e3a8a; font-size: 8px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #bfdbfe; text-align: center; }
        td { padding: 4px 5px; border: 1px solid #e2e8f0; font-size: 9px; }
        .num { text-align: right; white-space: nowrap; }
        .neg { color: #b91c1c; }
        .pos { color: #166534; font-weight: bold; }
        .nopag { color: #b91c1c; font-weight: bold; }
        .tot td { font-weight: bold; background: #f1f5f9; }
        .note { margin-top: 10px; color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
    @php
        $fmtPct = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    @endphp
    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="header-titles">
            <h1>Diferencial de comisiones</h1>
            <div class="sub" style="font-weight:bold;color:#1e3a8a;">{{ $diferencial->motivo }}</div>
            <div class="sub">
                Quincena {{ $periodo->etiqueta }}
                · Pago {{ $diferencial->fecha_pago?->format('d/m/Y') }}
                · Tasa BCV {{ number_format($tasa, 2) }}
                · Creado por {{ $diferencial->creador?->name ?? '—' }}
                · {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <div class="resumen">
        <span>Personas: <strong>{{ count($filas) }}</strong></span>
        <span>No se les pagó: <strong>{{ collect($filas)->where('situacion', 'No se le pagó')->count() }}</strong></span>
        <span>A pagar USD: <strong>{{ number_format($totales['a_pagar'], 2) }}</strong></span>
        <span>A pagar Bs: <strong>{{ number_format($totales['diferencia_bs'], 2) }}</strong></span>
    </div>

    <div class="pcts">
        @foreach(\App\Services\Nomina\CommissionDifferentialService::PORCENTAJES as $clave => [$etiqueta, $default])
            @php
                $usado = (float) ($diferencial->config[$clave] ?? $configActual[$clave]);
                $cambiado = abs($usado - (float) $configActual[$clave]) > 0.00001;
            @endphp
            <span class="{{ $cambiado ? 'cambiado' : '' }}">{{ $etiqueta }}: <strong>{{ $fmtPct($usado) }}</strong></span>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th>Cédula</th>
                <th>Empleado</th>
                <th>Empresa</th>
                <th>Situación</th>
                <th>Comisión quincena</th>
                <th>Comisión ajustada</th>
                <th>Bonos</th>
                <th>Retención</th>
                <th>Desc. / prést.</th>
                <th>Total ajustado</th>
                <th>Ya pagado</th>
                <th>Diferencia USD</th>
                <th>Diferencia Bs</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $f)
                <tr>
                    <td>{{ $f['cedula'] }}</td>
                    <td>{{ $f['nombre'] }}</td>
                    <td>{{ $f['empresa'] }}</td>
                    <td class="{{ $f['situacion'] === 'No se le pagó' ? 'nopag' : '' }}">{{ $f['situacion'] }}</td>
                    <td class="num">{{ number_format($f['comision_original'], 2) }}</td>
                    <td class="num">{{ number_format($f['comision_ajustada'], 2) }}</td>
                    <td class="num">{{ number_format($f['abonos'], 2) }}</td>
                    <td class="num">{{ number_format($f['retencion_ajustada'], 2) }}</td>
                    <td class="num">{{ number_format($f['descuentos'], 2) }}</td>
                    <td class="num">{{ number_format($f['total_ajustado'], 2) }}</td>
                    <td class="num">{{ number_format($f['pagado'], 2) }}</td>
                    <td class="num {{ $f['diferencia'] > 0 ? 'pos' : ($f['diferencia'] < 0 ? 'neg' : '') }}">{{ number_format($f['diferencia'], 2) }}</td>
                    <td class="num">{{ number_format($f['diferencia_bs'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="tot">
                <td colspan="4">Totales ({{ count($filas) }} empleados)</td>
                <td class="num">{{ number_format($totales['comision_original'], 2) }}</td>
                <td class="num">{{ number_format($totales['comision_ajustada'], 2) }}</td>
                <td class="num">{{ number_format($totales['abonos'], 2) }}</td>
                <td class="num">{{ number_format($totales['retencion_ajustada'], 2) }}</td>
                <td class="num">{{ number_format($totales['descuentos'], 2) }}</td>
                <td class="num">{{ number_format($totales['total_ajustado'], 2) }}</td>
                <td class="num">{{ number_format($totales['pagado'], 2) }}</td>
                <td class="num">{{ number_format($totales['diferencia'], 2) }}</td>
                <td class="num">{{ number_format($totales['diferencia_bs'], 2) }}</td>
            </tr>
            <tr class="tot">
                <td colspan="11">A pagar en TXT (solo diferencias positivas)</td>
                <td class="num">{{ number_format($totales['a_pagar'], 2) }}</td>
                <td class="num">{{ number_format($totales['diferencia_bs'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="note">Diferencia = total ajustado − ya pagado. Las diferencias negativas (pagado de más) no se incluyen en el TXT del banco. Los porcentajes resaltados difieren de la configuración actual.</p>
</body>
</html>
