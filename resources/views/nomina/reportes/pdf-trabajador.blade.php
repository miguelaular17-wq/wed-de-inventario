<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte {{ $r['empleado']['nombre'] }} {{ $periodo->etiqueta }}</title>
    @php
        $usd = fn ($n) => '$'.number_format((float) $n, 2, ',', '.');
        $pct = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',').'%';
        $parte = fn ($n) => $r['venta_total'] != 0 ? $pct($n / $r['venta_total'] * 100) : '—';
        $comisionBruta = round($r['comision']['total'] + $r['bonos'], 2);
    @endphp
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 14px; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 120px; }
        .header-logo img { height: 52px; width: auto; }
        .header-titles { display: table-cell; vertical-align: middle; }
        h1 { font-size: 16px; color: #1e3a8a; text-transform: uppercase; }
        h2 { font-size: 11px; color: #1e3a8a; margin: 16px 0 6px; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #dbeafe; color: #1e3a8a; font-size: 8px; text-transform: uppercase; padding: 5px 6px; border: 1px solid #bfdbfe; text-align: left; }
        td { padding: 5px 6px; border: 1px solid #e2e8f0; font-size: 10px; }
        .num { text-align: right; white-space: nowrap; }
        .lbl { color: #64748b; width: 32%; }
        .tot td { font-weight: bold; background: #f1f5f9; }
        .neg { color: #b91c1c; }
        .kpis { display: table; width: 100%; border-spacing: 6px 0; margin: 0 -6px; }
        .kpi { display: table-cell; width: 25%; border: 1px solid #bfdbfe; border-radius: 6px; padding: 8px 10px; background: #f8fafc; }
        .kpi-l { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .kpi-v { font-size: 14px; font-weight: bold; color: #1e3a8a; margin-top: 3px; }
        .kpi-s { color: #64748b; font-size: 8px; margin-top: 2px; }
        .pagar td { font-size: 12px; font-weight: bold; background: #1e3a8a; color: #fff; border-color: #1e3a8a; }
        .note { margin-top: 12px; color: #64748b; font-size: 8px; line-height: 1.4; }
        .empty { color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="header-titles">
            <h1>Reporte de comisión por trabajador</h1>
            <div class="sub">
                Quincena {{ $periodo->etiqueta }}
                · Pago {{ $periodo->fecha_pago_comision?->format('d/m/Y') ?: '—' }}
                · Tasa BCV {{ number_format($r['tasa'], 2, ',', '.') }}
                · Generado {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <h2>Trabajador</h2>
    <table>
        <tr><td class="lbl">Nombre</td><td><strong>{{ $r['empleado']['nombre'] }}</strong></td></tr>
        <tr><td class="lbl">Cédula</td><td>{{ $r['empleado']['cedula'] }}</td></tr>
        <tr><td class="lbl">Cargo</td><td>{{ $r['empleado']['cargo'] }}</td></tr>
        <tr><td class="lbl">Sede</td><td>{{ $r['empleado']['sede'] }}</td></tr>
        <tr><td class="lbl">Empresa</td><td>{{ $r['empleado']['empresa'] }}</td></tr>
        <tr><td class="lbl">Tipo de comisión</td><td>{{ $r['modo'] }}</td></tr>
        <tr><td class="lbl">Base de la comisión</td><td>{{ $r['alcance'] }}</td></tr>
    </table>

    <h2>Ventas de la quincena</h2>
    <div class="kpis">
        <div class="kpi">
            <div class="kpi-l">Cantidad de facturas</div>
            <div class="kpi-v">{{ number_format($r['facturas'], 0, ',', '.') }}</div>
            <div class="kpi-s">
                {{ $r['devoluciones'] > 0 ? number_format($r['devoluciones'], 0, ',', '.').' devoluciones' : 'Sin devoluciones' }}
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-l">Monto total de venta</div>
            <div class="kpi-v">{{ $usd($r['venta_total']) }}</div>
            <div class="kpi-s">{{ $r['st'] ? 'Incluye '.$usd($r['st']['ventas']).' de servicio técnico' : 'Venta neta' }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-l">Telefonía</div>
            <div class="kpi-v">{{ $usd($r['telefonia']) }}</div>
            <div class="kpi-s">{{ $parte($r['telefonia']) }} de la venta</div>
        </div>
        <div class="kpi">
            <div class="kpi-l">Otros</div>
            <div class="kpi-v">{{ $usd($r['otros']) }}</div>
            <div class="kpi-s">{{ $parte($r['otros']) }} de la venta</div>
        </div>
    </div>

    @if($r['st'])
        <h2>Servicio técnico</h2>
        <table>
            <tr><td class="lbl">Facturas con servicio técnico</td><td class="num">{{ number_format($r['facturas_st'], 0, ',', '.') }}</td></tr>
            <tr><td class="lbl">Ventas de servicio técnico</td><td class="num">{{ $usd($r['st']['ventas']) }}</td></tr>
            <tr><td class="lbl">Egresos 058 (garantías)</td><td class="num neg">-{{ $usd($r['st']['egresos']) }}</td></tr>
            <tr class="tot"><td>Base servicio técnico</td><td class="num">{{ $usd($r['st']['base']) }}</td></tr>
        </table>
    @endif

    <h2>Comisión</h2>
    <table>
        <thead>
            <tr><th>Concepto</th><th class="num">Monto</th></tr>
        </thead>
        <tbody>
            @if($r['st'])
                <tr><td>Comisión servicio técnico</td><td class="num">{{ $usd($r['st']['comision']) }}</td></tr>
            @endif
            @if($r['comision']['telefonia'] != 0 || $r['comision']['otros'] != 0)
                <tr><td>Comisión telefonía ({{ $pct($r['comision']['pct_telefonia']) }})</td><td class="num">{{ $usd($r['comision']['telefonia']) }}</td></tr>
                <tr><td>Comisión otros ({{ $pct($r['comision']['pct_otros']) }})</td><td class="num">{{ $usd($r['comision']['otros']) }}</td></tr>
            @elseif(! $r['st'])
                <tr><td>Comisión sobre la venta</td><td class="num">{{ $usd($r['comision']['total']) }}</td></tr>
            @endif
            <tr><td>Bonos</td><td class="num">{{ $usd($r['bonos']) }}</td></tr>
            <tr class="tot"><td>Comisión bruta</td><td class="num">{{ $usd($comisionBruta) }}</td></tr>
        </tbody>
    </table>

    <h2>Retenciones</h2>
    <table>
        <tr>
            <td class="lbl">Retención de comisión</td>
            <td>
                @if($r['exento_retencion'])
                    Exento de retención
                @else
                    {{ $pct($r['retencion_pct']) }}
                @endif
            </td>
            <td class="num neg">{{ $r['retencion'] > 0 ? '-'.$usd($r['retencion']) : $usd(0) }}</td>
        </tr>
    </table>

    <h2>Deducciones</h2>
    <table>
        <thead>
            <tr><th>Tipo</th><th>Motivo</th><th class="num">Monto</th></tr>
        </thead>
        <tbody>
            @forelse($r['deducciones'] as $d)
                <tr>
                    <td>{{ $d['tipo'] }}</td>
                    <td>{{ $d['comentario'] }}</td>
                    <td class="num neg">-{{ $usd($d['monto']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty">Sin deducciones en esta quincena.</td></tr>
            @endforelse
            <tr class="tot"><td colspan="2">Total deducciones</td><td class="num">{{ $r['total_deducciones'] > 0 ? '-' : '' }}{{ $usd($r['total_deducciones']) }}</td></tr>
        </tbody>
    </table>

    <h2>Resumen</h2>
    <table>
        <tr><td class="lbl">Comisión bruta</td><td class="num">{{ $usd($comisionBruta) }}</td></tr>
        <tr><td class="lbl">Retención</td><td class="num neg">{{ $r['retencion'] > 0 ? '-' : '' }}{{ $usd($r['retencion']) }}</td></tr>
        <tr><td class="lbl">Deducciones</td><td class="num neg">{{ $r['total_deducciones'] > 0 ? '-' : '' }}{{ $usd($r['total_deducciones']) }}</td></tr>
        <tr class="pagar"><td>Total a pagar USD</td><td class="num">{{ $usd($r['total_pagar']) }}</td></tr>
        <tr class="tot"><td>Total a pagar Bs (tasa BCV)</td><td class="num">Bs {{ number_format($r['total_pagar_bs'], 2, ',', '.') }}</td></tr>
    </table>

    <p class="note">
        Montos en dólares sobre venta neta. El sueldo de nómina se paga aparte y no aparece en este documento.
        @if($r['reparto_estimado'])
            Telefonía y otros se reparten según la categoría de los productos vendidos en {{ mb_strtolower($r['alcance'], 'UTF-8') }}.
        @endif
    </p>
</body>
</html>
